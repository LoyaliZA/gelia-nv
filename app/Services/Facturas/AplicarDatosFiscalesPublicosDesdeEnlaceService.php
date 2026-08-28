<?php

namespace App\Services\Facturas;

use App\Events\SolicitudFacturaActualizada;
use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use App\Models\Cliente;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Notifications\AlertaFactura;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AplicarDatosFiscalesPublicosDesdeEnlaceService
{
    public function __construct(
        private ValidarEnlaceDatosFiscalesService $validador,
        private GestionarDatosFiscalesClienteService $gestionarDatosFiscales,
        private GestionarReceptorFiscalService $gestionarReceptor,
    ) {}

    /**
     * @param  array<string, mixed>  $datosEntrada
     */
    public function ejecutar(string $token, array $datosEntrada): SolicitudFactura
    {
        return DB::transaction(function () use ($token, $datosEntrada) {
            $enlace = $this->reclamarEnlace($token);
            $solicitud = SolicitudFactura::query()
                ->whereKey($enlace->solicitud_factura_id)
                ->lockForUpdate()
                ->firstOrFail();

            $idBorrador = CatalogoEstadoSolicitud::idDe('Borrador');
            $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
            $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
            $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
            $estadoId = (int) $solicitud->catalogo_estado_solicitud_id;
            $esBorrador = $idBorrador !== null && $estadoId === (int) $idBorrador;
            $esRespondida = $idRespondida !== null && $estadoId === (int) $idRespondida;
            $esIncorrecta = $idIncorrecta !== null && $estadoId === (int) $idIncorrecta;
            $esPendiente = $idPendiente !== null && $estadoId === (int) $idPendiente;

            if (! $esBorrador && ! $esRespondida && ! $esIncorrecta && ! $esPendiente) {
                throw new \InvalidArgumentException('La solicitud ya no acepta respuesta del formulario.');
            }

            $campos = is_array($enlace->campos_permitidos) && $enlace->campos_permitidos !== []
                ? $enlace->campos_permitidos
                : EnlaceDatosFiscales::CAMPOS;

            $datos = $this->filtrarYValidar($datosEntrada, $campos);

            $snapshot = is_array($solicitud->datos_fiscales) ? $solicitud->datos_fiscales : [];
            foreach ($datos as $clave => $valor) {
                $snapshot[$clave] = $valor;
            }

            $razonSocial = $solicitud->razon_social;
            if (! empty($datos['nombre_razon_social'])) {
                $razonSocial = $datos['nombre_razon_social'];
            }

            $estadoAnterior = $solicitud->catalogo_estado_solicitud_id;

            // Borrador: pendiente de voucher. Respondida: corrige datos sin cambiar estado ni PDF/XML.
            $solicitud->update([
                'datos_fiscales' => $snapshot,
                'razon_social' => $razonSocial,
                'formulario_respondido_at' => now(),
            ]);

            if ($enlace->destinatario_tipo === SolicitudFactura::DESTINATARIO_CLIENTE && $solicitud->cliente_id) {
                $cliente = Cliente::query()->whereKey($solicitud->cliente_id)->lockForUpdate()->first();
                if ($cliente) {
                    $merge = [
                        'rfc' => $cliente->rfc,
                        'codigo_postal' => $cliente->codigo_postal,
                        'regimen_fiscal' => $cliente->regimen_fiscal,
                        'correo_electronico' => $cliente->correo_electronico,
                        'uso_factura' => $cliente->uso_factura,
                        'nombre_razon_social' => $cliente->nombre_razon_social,
                        'telefono' => $cliente->telefono,
                    ];
                    foreach ($datos as $clave => $valor) {
                        $merge[$clave] = $valor;
                    }
                    $this->gestionarDatosFiscales->actualizar($cliente, $merge);
                }
            } elseif ($enlace->destinatario_tipo === SolicitudFactura::DESTINATARIO_TERCERO) {
                $receptor = $this->gestionarReceptor->upsertDesdeFormulario(
                    $solicitud->receptor_fiscal_id ? (int) $solicitud->receptor_fiscal_id : null,
                    $snapshot,
                );
                $solicitud->update(['receptor_fiscal_id' => $receptor->id]);
            }

            $motivoAuditoria = match (true) {
                $esRespondida => 'Formulario fiscal corregido sobre solicitud respondida. Datos fiscales actualizados.',
                $esIncorrecta => 'Formulario fiscal respondido en solicitud incorrecta. Vendedora puede completar la reparación.',
                $esPendiente => 'Formulario fiscal respondido en solicitud pendiente.',
                default => 'Formulario público de datos fiscales respondido. Pendiente de voucher y envío a encargada.',
            };

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $enlace->creado_por,
                'estado_anterior_id' => $estadoAnterior,
                'estado_nuevo_id' => $estadoAnterior,
                'motivo_reporte' => $motivoAuditoria,
                'datos_snapshot' => [
                    'accion' => $esRespondida ? 'formulario_corregido' : 'formulario_respondido',
                    'campos' => array_keys($datos),
                    'destinatario_tipo' => $enlace->destinatario_tipo,
                    'enlace_id' => $enlace->id,
                ],
            ]);

            $solicitud = $solicitud->fresh(['vendedor', 'estado', 'cliente', 'vouchers']);

            if ($esRespondida) {
                app(NotificarEncargadosFacturaService::class)->formularioCorregido($solicitud);
            } elseif ($solicitud->vendedor) {
                $mensaje = $esIncorrecta
                    ? 'El cliente corrigió datos fiscales. Complete la reparación de la solicitud.'
                    : 'El cliente respondió el formulario. Adjunte el voucher y envíe a encargada.';
                $solicitud->vendedor->notify(new AlertaFactura(
                    $solicitud,
                    'formulario_respondido',
                    $mensaje
                ));
            }

            event(new SolicitudFacturaActualizada(
                solicitudId: $solicitud->id,
                accion: $esRespondida ? 'formulario_corregido' : 'formulario_respondido',
                porUsuarioId: null,
                vendedorId: $solicitud->vendedor_id,
                departamentoId: $solicitud->departamento_id,
            ));

            return $solicitud;
        });
    }

    private function reclamarEnlace(string $token): EnlaceDatosFiscales
    {
        $enlace = $this->validador->porToken($token);

        if (! $enlace) {
            throw new \InvalidArgumentException('Enlace no válido.');
        }

        $enlace = EnlaceDatosFiscales::query()
            ->whereKey($enlace->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($enlace->fueUsado()) {
            throw new \InvalidArgumentException('Este enlace ya fue utilizado.');
        }

        if ($enlace->revocado_en !== null || ($enlace->expira_en !== null && $enlace->expira_en->isPast())) {
            throw new \InvalidArgumentException('El enlace expiró o fue revocado.');
        }

        $enlace->update(['usado_en' => now()]);

        return $enlace->fresh();
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<string>  $campos
     * @return array<string, string>
     */
    private function filtrarYValidar(array $entrada, array $campos): array
    {
        $datos = [];
        $errores = [];

        foreach ($campos as $campo) {
            $valor = trim((string) ($entrada[$campo] ?? ''));
            if ($valor === '') {
                $errores[$campo] = 'Este campo es obligatorio.';
                continue;
            }
            $datos[$campo] = $valor;
        }

        if (isset($datos['rfc'])) {
            $rfc = ReglasCatalogosFiscales::normalizarRfc($datos['rfc']);
            $errorRfc = ReglasCatalogosFiscales::errorRfc($rfc);
            if ($errorRfc !== null) {
                $errores['rfc'] = $errorRfc;
            } else {
                $datos['rfc'] = $rfc;
            }
        }

        if (isset($datos['codigo_postal']) && ! preg_match('/^\d{5}$/', $datos['codigo_postal'])) {
            $errores['codigo_postal'] = 'El código postal debe tener 5 dígitos.';
        }

        if (isset($datos['correo_electronico'])) {
            $datos['correo_electronico'] = mb_strtolower(trim($datos['correo_electronico']));
            if (! filter_var($datos['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
                $errores['correo_electronico'] = 'El correo electrónico no es válido.';
            }
        }

        if (isset($datos['telefono'])) {
            $datos['telefono'] = preg_replace('/\D+/', '', $datos['telefono']) ?? '';
            if ($datos['telefono'] === '' || ! preg_match('/^\d{1,10}$/', $datos['telefono'])) {
                $errores['telefono'] = 'El número telefónico solo admite dígitos (máximo 10).';
            }
        }

        if (isset($datos['nombre_razon_social']) && mb_strlen($datos['nombre_razon_social']) < 3) {
            $errores['nombre_razon_social'] = 'La razón social debe tener al menos 3 caracteres.';
        }

        $datos = ReglasCatalogosFiscales::aplicarForzados($datos);

        if (isset($datos['regimen_fiscal'])
            && ! CatalogoRegimenFiscal::query()->activos()->where('codigo', $datos['regimen_fiscal'])->exists()) {
            $errores['regimen_fiscal'] = 'El régimen fiscal no es válido.';
        }

        if (isset($datos['uso_factura'])
            && ! CatalogoUsoCfdi::query()->activos()->where('codigo', $datos['uso_factura'])->exists()) {
            $errores['uso_factura'] = 'El uso de CFDI no es válido.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }
}
