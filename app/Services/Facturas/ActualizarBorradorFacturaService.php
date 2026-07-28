<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\EnlaceDatosFiscales;
use App\Models\ReceptorFiscal;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\User;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ActualizarBorradorFacturaService
{
    public function __construct(
        private ImportarDatosFiscalesService $importarDatosFiscales,
        private GenerarEnlaceDatosFiscalesService $generarEnlace,
        private NotificarEncargadosFacturaService $notificarEncargados,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array{solicitud: SolicitudFactura, enlace_url: string|null}
     */
    public function ejecutar(SolicitudFactura $solicitud, array $datos, User $usuario): array
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $idBorrador = CatalogoEstadoSolicitud::idDe('Borrador');
            if ($idBorrador === null || (int) $solicitud->catalogo_estado_solicitud_id !== $idBorrador) {
                throw new \InvalidArgumentException('Solo se pueden actualizar solicitudes en borrador.');
            }

            if ((int) $solicitud->vendedor_id !== (int) $usuario->id
                && ! $usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
                throw new \InvalidArgumentException('No puede editar este borrador.');
            }

            $destinatarioTipo = ($datos['destinatario_tipo'] ?? $solicitud->destinatario_tipo) === SolicitudFactura::DESTINATARIO_TERCERO
                ? SolicitudFactura::DESTINATARIO_TERCERO
                : SolicitudFactura::DESTINATARIO_CLIENTE;

            $razonSocial = $datos['razon_social'] ?? $solicitud->razon_social;
            if (is_string($razonSocial) && $razonSocial !== '' && $razonSocial !== 'Pendiente de formulario') {
                $razonSocial = ReglasCatalogosFiscales::normalizarRazonSocial($razonSocial);
            }

            $updates = [
                'destinatario_tipo' => $destinatarioTipo,
                'razon_social' => $razonSocial,
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? $solicitud->observaciones_vendedor,
            ];

            $cliente = null;
            if (array_key_exists('numero_cliente', $datos)) {
                $clienteId = null;
                $datosFiscales = $solicitud->datos_fiscales;
                if (! empty($datos['numero_cliente'])) {
                    $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                    if ($cliente) {
                        $clienteId = $cliente->id;
                        if ($destinatarioTipo === SolicitudFactura::DESTINATARIO_CLIENTE && empty($solicitud->datos_fiscales)) {
                            $datosFiscales = $this->importarDatosFiscales->datosFiscalesDesdeCliente($cliente);
                        }
                    }
                }
                $updates['cliente_id'] = $clienteId;
                $updates['datos_fiscales'] = $datosFiscales;
            } else {
                $cliente = $solicitud->cliente;
            }

            $receptorId = $solicitud->receptor_fiscal_id;
            if ($destinatarioTipo === SolicitudFactura::DESTINATARIO_CLIENTE) {
                $updates['receptor_fiscal_id'] = null;
                $receptorId = null;
            } elseif (array_key_exists('receptor_fiscal_id', $datos)) {
                $receptorId = null;
                if (! empty($datos['receptor_fiscal_id'])) {
                    $receptor = ReceptorFiscal::query()
                        ->whereKey((int) $datos['receptor_fiscal_id'])
                        ->where('activo', true)
                        ->first();
                    if ($receptor) {
                        $receptorId = $receptor->id;
                        if (empty($updates['datos_fiscales'])) {
                            $updates['datos_fiscales'] = $receptor->aDatosFiscales();
                        }
                        if (
                            empty($datos['razon_social'])
                            || trim((string) ($datos['razon_social'] ?? '')) === ''
                            || trim((string) ($datos['razon_social'] ?? '')) === 'Pendiente de formulario'
                        ) {
                            $updates['razon_social'] = $receptor->nombre_razon_social ?: 'Pendiente de formulario';
                        }
                    }
                }
                $updates['receptor_fiscal_id'] = $receptorId;
            }

            if (
                $destinatarioTipo === SolicitudFactura::DESTINATARIO_TERCERO
                && $cliente
                && $receptorId
                && ! empty($datos['vincular_receptor_cliente'])
            ) {
                $cliente->receptoresFiscales()->syncWithoutDetaching([$receptorId]);
            }

            if (! empty($datos['eliminar_archivo_fiscal'])) {
                if ($solicitud->archivo_fiscal_path) {
                    Storage::disk('public')->delete($solicitud->archivo_fiscal_path);
                }
                $updates['archivo_fiscal_path'] = null;
            }

            if (isset($datos['archivo_fiscal']) && $datos['archivo_fiscal'] instanceof UploadedFile && $datos['archivo_fiscal']->isValid()) {
                if ($solicitud->archivo_fiscal_path) {
                    Storage::disk('public')->delete($solicitud->archivo_fiscal_path);
                }
                $updates['datos_fiscales'] = $this->importarDatosFiscales->extraer($datos['archivo_fiscal']);
                $updates['archivo_fiscal_path'] = $datos['archivo_fiscal']->store('facturas/fiscales', 'public');
            }

            $conservar = collect($datos['vouchers_conservar'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            if (array_key_exists('vouchers_conservar', $datos) || ! empty($datos['vouchers'])) {
                $existentes = $solicitud->vouchers()->get();
                foreach ($existentes as $voucher) {
                    if (! in_array((int) $voucher->id, $conservar, true)) {
                        Storage::disk('public')->delete($voucher->path);
                        $voucher->delete();
                    }
                }
            }

            $orden = (int) ($solicitud->vouchers()->max('orden') ?? 0) + 1;
            foreach ($datos['vouchers'] ?? [] as $voucher) {
                if (! $voucher instanceof UploadedFile || ! $voucher->isValid()) {
                    continue;
                }
                SolicitudFacturaVoucher::create([
                    'solicitud_factura_id' => $solicitud->id,
                    'path' => $voucher->store("facturas/vouchers/{$solicitud->id}", 'public'),
                    'nombre_original' => $voucher->getClientOriginalName(),
                    'mime' => $voucher->getMimeType(),
                    'orden' => $orden++,
                ]);
            }

            $solicitud->update($updates);

            $enlaceUrl = null;
            $enviarAhora = ! empty($datos['enviar_ahora']);
            $pedirFormulario = ! empty($datos['pedir_formulario']);

            if ($pedirFormulario) {
                $campos = is_array($datos['campos_fiscales'] ?? null) ? $datos['campos_fiscales'] : EnlaceDatosFiscales::CAMPOS;
                $accion = ! empty($datos['accion_formulario'])
                    ? (string) $datos['accion_formulario']
                    : EnlaceDatosFiscales::ACCION_PRIMERA;
                $resultado = $this->generarEnlace->ejecutar($solicitud->fresh(), [
                    'accion' => $accion,
                    'campos' => $campos,
                    'usuario_id' => $usuario->id,
                ]);
                $enlaceUrl = $resultado['url'];
            }

            if ($enviarAhora) {
                $solicitud = $solicitud->fresh();
                if ($solicitud->formulario_enviado_at && ! $solicitud->formulario_respondido_at) {
                    throw ValidationException::withMessages([
                        'enviar_ahora' => 'Espere a que respondan el formulario fiscal antes de enviar a encargada.',
                    ]);
                }

                $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
                if ($idPendiente === null) {
                    throw new \RuntimeException('Estado Pendiente no configurado.');
                }
                if ($solicitud->vouchers()->count() < 1) {
                    throw new \InvalidArgumentException('Debe adjuntar al menos un voucher antes de enviar.');
                }

                $estadoAnterior = $solicitud->catalogo_estado_solicitud_id;
                $solicitud->update(['catalogo_estado_solicitud_id' => $idPendiente]);

                AuditoriaSolicitudFactura::create([
                    'solicitud_factura_id' => $solicitud->id,
                    'usuario_id' => $usuario->id,
                    'estado_anterior_id' => $estadoAnterior,
                    'estado_nuevo_id' => $idPendiente,
                    'motivo_reporte' => 'Borrador enviado a encargada.',
                ]);

                $this->notificarEncargados->nueva($solicitud->fresh(['vendedor']));
            } else {
                AuditoriaSolicitudFactura::create([
                    'solicitud_factura_id' => $solicitud->id,
                    'usuario_id' => $usuario->id,
                    'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                    'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                    'motivo_reporte' => 'Borrador de factura actualizado.',
                ]);
            }

            return [
                'solicitud' => $solicitud->fresh(['vendedor', 'estado', 'vouchers', 'cliente', 'enlacesFiscales']),
                'enlace_url' => $enlaceUrl,
            ];
        });
    }
}
