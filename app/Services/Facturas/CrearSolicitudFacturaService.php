<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CrearSolicitudFacturaService
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
    public function ejecutar(array $datos, int $vendedorId): array
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            $modo = ($datos['modo'] ?? 'pendiente') === 'borrador' ? 'borrador' : 'pendiente';
            $estadoNombre = $modo === 'borrador' ? 'Borrador' : 'Pendiente';
            $estado = CatalogoEstadoSolicitud::where('nombre', $estadoNombre)->firstOrFail();

            $vendedor = User::with(['departamentos', 'area.departamento'])->findOrFail($vendedorId);
            $departamentoId = $vendedor->departamentos->first()?->id
                ?? $vendedor->area?->departamento_id;

            $destinatarioTipo = ($datos['destinatario_tipo'] ?? SolicitudFactura::DESTINATARIO_CLIENTE) === SolicitudFactura::DESTINATARIO_TERCERO
                ? SolicitudFactura::DESTINATARIO_TERCERO
                : SolicitudFactura::DESTINATARIO_CLIENTE;

            $clienteId = null;
            $datosFiscales = null;

            if (! empty($datos['numero_cliente'])) {
                $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                if ($cliente) {
                    $clienteId = $cliente->id;
                    if ($destinatarioTipo === SolicitudFactura::DESTINATARIO_CLIENTE) {
                        $datosFiscales = $this->importarDatosFiscales->datosFiscalesDesdeCliente($cliente);
                    }
                }
            }

            $archivoFiscalPath = null;
            if (isset($datos['archivo_fiscal']) && $datos['archivo_fiscal'] instanceof UploadedFile && $datos['archivo_fiscal']->isValid()) {
                $datosFiscales = $this->importarDatosFiscales->extraer($datos['archivo_fiscal']);
                $archivoFiscalPath = $datos['archivo_fiscal']->store('facturas/fiscales', 'public');
            }

            if (! empty($datos['datos_fiscales']) && is_array($datos['datos_fiscales'])) {
                $datosFiscales = $datos['datos_fiscales'];
            }

            $razonSocial = trim((string) ($datos['razon_social'] ?? ''));
            if ($razonSocial === '' && $destinatarioTipo === SolicitudFactura::DESTINATARIO_TERCERO) {
                $razonSocial = 'Pendiente de formulario';
            }

            $solicitud = SolicitudFactura::create([
                'folio' => SolicitudFactura::generarFolio(),
                'vendedor_id' => $vendedorId,
                'departamento_id' => $departamentoId,
                'cliente_id' => $clienteId,
                'destinatario_tipo' => $destinatarioTipo,
                'catalogo_estado_solicitud_id' => $estado->id,
                'razon_social' => $razonSocial,
                'datos_fiscales' => $datosFiscales,
                'archivo_fiscal_path' => $archivoFiscalPath,
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
                'campos_fiscales_solicitados' => $datos['campos_fiscales'] ?? null,
            ]);

            $orden = 1;
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

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $vendedorId,
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $estado->id,
                'motivo_reporte' => $modo === 'borrador'
                    ? 'Borrador de solicitud de factura creado.'
                    : 'Creación de solicitud de factura.',
                'datos_snapshot' => [
                    'razon_social' => $solicitud->razon_social,
                    'destinatario_tipo' => $destinatarioTipo,
                    'vouchers_count' => $orden - 1,
                    'tiene_archivo_fiscal' => (bool) $archivoFiscalPath,
                    'modo' => $modo,
                ],
            ]);

            $enlaceUrl = null;
            if ($modo === 'borrador' && ! empty($datos['pedir_formulario'])) {
                $campos = is_array($datos['campos_fiscales'] ?? null) ? $datos['campos_fiscales'] : EnlaceDatosFiscales::CAMPOS;
                $accion = ! empty($datos['accion_formulario'])
                    ? (string) $datos['accion_formulario']
                    : EnlaceDatosFiscales::ACCION_PRIMERA;

                $resultado = $this->generarEnlace->ejecutar($solicitud, [
                    'accion' => $accion,
                    'campos' => $campos,
                    'usuario_id' => $vendedorId,
                ]);
                $enlaceUrl = $resultado['url'];
                $solicitud = $solicitud->fresh();
            }

            if ($modo === 'pendiente') {
                $this->notificarEncargados->nueva($solicitud->loadMissing('vendedor'));
            }

            return [
                'solicitud' => $solicitud->load(['vendedor', 'estado', 'vouchers', 'cliente', 'enlacesFiscales']),
                'enlace_url' => $enlaceUrl,
            ];
        });
    }
}
