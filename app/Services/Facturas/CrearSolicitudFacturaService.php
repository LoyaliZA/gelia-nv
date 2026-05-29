<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\User;
use App\Notifications\AlertaFactura;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CrearSolicitudFacturaService
{
    public function __construct(
        private ImportarDatosFiscalesService $importarDatosFiscales
    ) {}

    public function ejecutar(array $datos, int $vendedorId): SolicitudFactura
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();

            $vendedor = User::with('departamentos')->findOrFail($vendedorId);
            $departamentoId = $vendedor->departamentos->first()?->id;

            $clienteId = null;
            $datosFiscales = null;

            if (!empty($datos['numero_cliente'])) {
                $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                if ($cliente) {
                    $clienteId = $cliente->id;
                    $datosFiscales = $this->importarDatosFiscales->datosFiscalesDesdeCliente($cliente);
                }
            }

            $archivoFiscalPath = null;
            if (isset($datos['archivo_fiscal']) && $datos['archivo_fiscal'] instanceof UploadedFile && $datos['archivo_fiscal']->isValid()) {
                $datosFiscales = $this->importarDatosFiscales->extraer($datos['archivo_fiscal']);
                $archivoFiscalPath = $datos['archivo_fiscal']->store('facturas/fiscales', 'public');
            }

            if (!empty($datos['datos_fiscales']) && is_array($datos['datos_fiscales'])) {
                $datosFiscales = $datos['datos_fiscales'];
            }

            $solicitud = SolicitudFactura::create([
                'folio' => SolicitudFactura::generarFolio(),
                'vendedor_id' => $vendedorId,
                'departamento_id' => $departamentoId,
                'cliente_id' => $clienteId,
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'razon_social' => $datos['razon_social'],
                'datos_fiscales' => $datosFiscales,
                'archivo_fiscal_path' => $archivoFiscalPath,
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
            ]);

            $orden = 1;
            foreach ($datos['vouchers'] ?? [] as $voucher) {
                if (!$voucher instanceof UploadedFile || !$voucher->isValid()) {
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
                'estado_nuevo_id' => $estadoPendiente->id,
                'motivo_reporte' => 'Creación de solicitud de factura.',
                'datos_snapshot' => [
                    'razon_social' => $solicitud->razon_social,
                    'vouchers_count' => $orden - 1,
                    'tiene_archivo_fiscal' => (bool) $archivoFiscalPath,
                ],
            ]);

            $encargados = User::permission(['facturas.responder', 'facturas.verificar'])
                ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $departamentoId))
                ->get();

            if ($encargados->isNotEmpty()) {
                Notification::send($encargados, new AlertaFactura(
                    $solicitud,
                    'nueva',
                    "Nueva solicitud de factura de: {$vendedor->name}"
                ));
            }

            return $solicitud->load(['vendedor', 'estado', 'vouchers', 'cliente']);
        });
    }
}
