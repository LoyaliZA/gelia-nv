<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\User;
use App\Notifications\AlertaFactura;
use App\Support\Facturas\CamposIncorrectosFactura;
use App\Support\Facturas\FacturaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RepararSolicitudFacturaService
{
    public function __construct(
        private ImportarDatosFiscalesService $importarDatosFiscales,
        private ActualizarDatosFiscalesSolicitudService $actualizarFiscales,
        private GenerarEnlaceDatosFiscalesService $generarEnlace,
    ) {}

    public function ejecutar(SolicitudFactura $solicitud, array $datos, User $usuario): SolicitudFactura
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
            $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');

            if ($idIncorrecta === null || $idPendiente === null) {
                abort(422, 'Estados de solicitud no configurados.');
            }

            if ((int) $solicitud->catalogo_estado_solicitud_id !== $idIncorrecta) {
                abort(422, 'Solo se pueden reparar solicitudes marcadas como incorrectas.');
            }

            if ((int) $solicitud->vendedor_id !== (int) $usuario->id) {
                abort(403, 'Solo quien creó la solicitud puede repararla.');
            }

            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $corregidos = [];

            $updates = [
                'razon_social' => $datos['razon_social'],
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
                'motivo_respuesta' => null,
                'motivo_incorrecta' => null,
                'respondida_por_id' => null,
                'respondida_at' => null,
            ];
            $corregidos[] = CamposIncorrectosFactura::RAZON_SOCIAL;
            if (array_key_exists('observaciones_vendedor', $datos)) {
                $corregidos[] = CamposIncorrectosFactura::OBSERVACIONES_VENDEDOR;
            }

            if ($solicitud->evidencia_error_path) {
                FacturaStorage::delete($solicitud->evidencia_error_path);
                $updates['evidencia_error_path'] = null;
            }

            $solicitud->load('vouchers');

            if (isset($datos['archivo_fiscal']) && $datos['archivo_fiscal'] instanceof UploadedFile && $datos['archivo_fiscal']->isValid()) {
                FacturaStorage::delete($solicitud->archivo_fiscal_path);
                $updates['datos_fiscales'] = $this->importarDatosFiscales->extraer($datos['archivo_fiscal']);
                $updates['archivo_fiscal_path'] = $datos['archivo_fiscal']->store('facturas/fiscales', FacturaStorage::storeDisk());
                $corregidos[] = CamposIncorrectosFactura::ARCHIVO_FISCAL;
            } elseif (! empty($datos['eliminar_archivo_fiscal']) && $solicitud->archivo_fiscal_path) {
                FacturaStorage::delete($solicitud->archivo_fiscal_path);
                $updates['archivo_fiscal_path'] = null;
            }

            if (! empty($datos['numero_cliente'])) {
                $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                if ($cliente) {
                    $updates['cliente_id'] = $cliente->id;
                    if (! isset($updates['datos_fiscales'])) {
                        $updates['datos_fiscales'] = $this->importarDatosFiscales->datosFiscalesDesdeCliente($cliente);
                    }
                }
            }

            $solicitud->update($updates);

            if (! empty($datos['datos_fiscales']) && is_array($datos['datos_fiscales'])) {
                $fiscalesActualizados = $this->actualizarFiscales->mergeEnSolicitud($solicitud, $datos['datos_fiscales']);
                $corregidos = array_merge($corregidos, $fiscalesActualizados);
            } elseif ($solicitud->formulario_respondido_at) {
                $solicitados = is_array($solicitud->campos_fiscales_solicitados)
                    ? $solicitud->campos_fiscales_solicitados
                    : [];
                foreach ($solicitados as $campo) {
                    if (in_array($campo, $solicitud->campos_incorrectos ?? [], true)) {
                        $corregidos[] = $campo;
                    }
                }
            }

            $conservarIds = collect($datos['vouchers_conservar'] ?? $solicitud->vouchers->pluck('id')->all())
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $vouchersEliminados = 0;
            $vouchersAgregados = 0;

            foreach ($solicitud->vouchers as $voucher) {
                if (! in_array($voucher->id, $conservarIds, true)) {
                    FacturaStorage::delete($voucher->path);
                    $voucher->delete();
                    $vouchersEliminados++;
                }
            }

            $orden = (int) SolicitudFacturaVoucher::where('solicitud_factura_id', $solicitud->id)->max('orden');
            foreach ($datos['vouchers'] ?? [] as $voucher) {
                if (! $voucher instanceof UploadedFile || ! $voucher->isValid()) {
                    continue;
                }
                SolicitudFacturaVoucher::create([
                    'solicitud_factura_id' => $solicitud->id,
                    'path' => $voucher->store("facturas/vouchers/{$solicitud->id}", FacturaStorage::storeDisk()),
                    'nombre_original' => $voucher->getClientOriginalName(),
                    'mime' => $voucher->getMimeType(),
                    'orden' => ++$orden,
                ]);
                $vouchersAgregados++;
            }

            $totalFinal = SolicitudFacturaVoucher::where('solicitud_factura_id', $solicitud->id)->count();
            if ($totalFinal < 1) {
                abort(422, 'Debe conservar o adjuntar al menos un comprobante de pago (voucher).');
            }
            $corregidos[] = CamposIncorrectosFactura::VOUCHERS;

            $enlaceUrl = null;
            if (! empty($datos['generar_enlace_fiscal']) && $solicitud->formulario_respondido_at === null) {
                $camposEnlace = is_array($datos['campos_fiscales'] ?? null) && $datos['campos_fiscales'] !== []
                    ? $datos['campos_fiscales']
                    : CamposIncorrectosFactura::soloFiscales($solicitud->campos_incorrectos ?? []);
                if ($camposEnlace === []) {
                    $camposEnlace = EnlaceDatosFiscales::CAMPOS;
                }
                $resultado = $this->generarEnlace->ejecutar($solicitud, [
                    'accion' => EnlaceDatosFiscales::ACCION_ACTUALIZAR,
                    'campos' => $camposEnlace,
                    'usuario_id' => $usuario->id,
                ]);
                $enlaceUrl = $resultado['url'];
            }

            $corregidos = array_values(array_unique($corregidos));
            $restantes = CamposIncorrectosFactura::quitarResueltos($solicitud->campos_incorrectos, $corregidos);

            if ($restantes === [] && empty($datos['generar_enlace_fiscal'])) {
                $updatesEstado = [
                    'catalogo_estado_solicitud_id' => $idPendiente,
                    'campos_incorrectos' => null,
                ];
            } else {
                $updatesEstado = [
                    'catalogo_estado_solicitud_id' => $idIncorrecta,
                    'campos_incorrectos' => $restantes === [] ? null : $restantes,
                ];
            }

            $solicitud->update($updatesEstado);

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => 'El colaborador corrigió la solicitud de factura.',
                'datos_snapshot' => [
                    'razon_social' => $solicitud->razon_social,
                    'vouchers_conservados' => count($conservarIds) - $vouchersEliminados,
                    'vouchers_eliminados' => $vouchersEliminados,
                    'vouchers_agregados' => $vouchersAgregados,
                    'archivo_fiscal_actualizado' => isset($updates['archivo_fiscal_path']) && ! empty($datos['archivo_fiscal']),
                    'archivo_fiscal_eliminado' => ! empty($datos['eliminar_archivo_fiscal']),
                    'campos_corregidos' => $corregidos,
                    'campos_incorrectos_restantes' => $restantes,
                    'enlace_fiscal_url' => $enlaceUrl,
                ],
            ]);

            $solicitud->load(['vendedor', 'estado', 'vouchers', 'cliente']);

            if ($solicitud->departamento_id && (int) $solicitud->catalogo_estado_solicitud_id === $idPendiente) {
                $encargadosPorDepto = User::permission(['facturas.responder', 'facturas.verificar'])
                    ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $solicitud->departamento_id))
                    ->get();
            } else {
                $encargadosPorDepto = collect();
            }

            $adminsGlobales = User::role(['Super Admin', 'Administrador'])->get();

            $encargados = $encargadosPorDepto->merge($adminsGlobales)
                ->unique('id')
                ->reject(fn ($u) => $u->id === $usuario->id);

            if ($encargados->isNotEmpty() && (int) $solicitud->catalogo_estado_solicitud_id === $idPendiente) {
                Notification::send($encargados, new AlertaFactura(
                    $solicitud,
                    'reparada',
                    "El colaborador {$usuario->name} reparó la solicitud {$solicitud->folio}."
                ));
            }

            return $solicitud;
        });
    }
}
