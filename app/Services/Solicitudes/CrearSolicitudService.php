<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\AuditoriaSolicitud;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile; // Importación necesaria para manejar el archivo

class CrearSolicitudService
{
    public function ejecutar(array $datos, int $vendedorId): SolicitudTag
    {
        return DB::transaction(function () use ($datos, $vendedorId) {

            $clienteId = $this->resolverClienteId($datos, $vendedorId);
            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();

            // 1. Obtención del Departamento de Origen
            $vendedor = User::with('departamentos')->find($vendedorId);
            $departamentoOrigenId = $vendedor && $vendedor->departamentos->isNotEmpty()
                ? $vendedor->departamentos->first()->id
                : null;

            // 2. Procesamiento de la Evidencia
            $evidenciaPath = null;
            if (isset($datos['evidencia']) && $datos['evidencia'] instanceof UploadedFile && $datos['evidencia']->isValid()) {
                $evidenciaPath = $datos['evidencia']->store('evidencias_solicitudes', 'public');
            }

            $montoFinalTentativo = isset($datos['monto_final_tentativo']) ? (float) $datos['monto_final_tentativo'] : null;
            $totalProyectadoNeto = isset($datos['total_proyectado_neto']) ? (float) $datos['total_proyectado_neto'] : null;

            $proceso = \App\Models\CatalogoProceso::find($datos['catalogo_proceso_id']);
            $esOperativo = $proceso?->esOperativo() ?? false;
            $compraEnTienda = $this->aplicaCompraEnTienda($proceso, $datos);

            $listaDescuentoId = $esOperativo ? null : ($datos['catalogo_lista_descuento_id'] ?? null);
            $montoCotizado = $esOperativo ? 0 : (float) ($datos['monto_cotizado'] ?? 0);

            if ($compraEnTienda) {
                $listaBronce = $this->resolverListaBronce();
                $listaDescuentoId = $listaBronce?->id;
                $montoCotizado = 0;
            }

            // 3. Creación de la Solicitud
            $solicitud = SolicitudTag::create([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'departamento_id' => $departamentoOrigenId,
                'catalogo_proceso_id' => $datos['catalogo_proceso_id'],
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'monto_cotizado' => $montoCotizado,
                'pago_confirmado' => filter_var($datos['pago_confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
                'evidencia_path' => $evidenciaPath,
                'catalogo_tipo_cliente_id' => $esOperativo ? null : ($datos['catalogo_tipo_cliente_id'] ?? null),
                'catalogo_lista_descuento_id' => $esOperativo ? null : $listaDescuentoId,
                'compra_en_tienda' => $compraEnTienda,
                'confirmo_informacion_escalonamiento' => $esOperativo ? false : filter_var($datos['confirmo_informacion_escalonamiento'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'monto_final_tentativo' => $esOperativo ? null : $montoFinalTentativo,
                'total_proyectado_neto' => $esOperativo ? null : $totalProyectadoNeto,
                'numero_remision' => $datos['numero_remision'] ?? null,
                'numero_pedido' => $datos['numero_pedido'] ?? null,
                'fecha_operacion' => $datos['fecha_operacion'] ?? null,
                'motivo_operacion' => $datos['motivo_operacion'] ?? null,
                'catalogo_banco_id' => $datos['catalogo_banco_id'] ?? null,
                'solicitar_cotizacion' => filter_var($datos['solicitar_cotizacion'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

            // 3. Registro del Snapshot Inicial (Auditoría V1)
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => $vendedorId,
                'estado_anterior_id' => null, // Es nuevo, no hay estado anterior
                'estado_nuevo_id' => $estadoPendiente->id,
                'motivo_reporte' => $compraEnTienda
                    ? 'Creación de solicitud. Compra en tienda: lista Bronce asignada automáticamente. Cotización no requerida.'
                    : 'Creación original de la solicitud.',
                'datos_snapshot' => [
                    'monto_cotizado' => $solicitud->monto_cotizado,
                    'proceso_id' => $solicitud->catalogo_proceso_id,
                    'compra_en_tienda' => $compraEnTienda,
                    'lista_bronce_autoasignada' => $compraEnTienda,
                    'lista_descuento_nombre' => $compraEnTienda
                        ? CatalogoListaDescuento::find($listaDescuentoId)?->nombre
                        : null,
                    'evidencia_path' => $solicitud->evidencia_path,
                    'lista_descuento_id' => $solicitud->catalogo_lista_descuento_id,
                    'monto_final_tentativo' => $solicitud->monto_final_tentativo,
                    'total_proyectado_neto' => $solicitud->total_proyectado_neto,
                    'confirmo_informacion_escalonamiento' => $solicitud->confirmo_informacion_escalonamiento,
                    'numero_remision' => $solicitud->numero_remision,
                    'numero_pedido' => $solicitud->numero_pedido,
                    'fecha_operacion' => $solicitud->fecha_operacion?->format('Y-m-d'),
                    'motivo_operacion' => $solicitud->motivo_operacion,
                    'catalogo_banco_id' => $solicitud->catalogo_banco_id,
                    'solicitar_cotizacion' => $solicitud->solicitar_cotizacion,
                    'antes' => $this->snapshotClienteAlCrear($clienteId),
                ]
            ]);

            // 4. Despliegue de Notificaciones
            $colaborador = User::find($vendedorId);
            $nombreColaborador = $colaborador ? $colaborador->name : 'Sistema';

            $encargados = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])
                ->whereHas('departamentos', function ($query) use ($departamentoOrigenId) {
                    $query->where('departamentos.id', $departamentoOrigenId);
                })
                ->get();

            if ($encargados->isNotEmpty()) {
                Notification::send($encargados, new AlertaSolicitud(
                    $solicitud,
                    'nueva',
                    "Nueva solicitud recibida de: {$nombreColaborador}"
                ));
            }

            return $solicitud;
        });
    }

    private function aplicaCompraEnTienda(?\App\Models\CatalogoProceso $proceso, array $datos): bool
    {
        if (!$proceso || $proceso->esOperativo()) {
            return false;
        }

        $esClienteNuevo = str_contains(strtoupper($proceso->nombre), 'ASIGNAR CLIENTE NUEVO');

        return $esClienteNuevo && filter_var($datos['compra_en_tienda'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function resolverListaBronce(): ?CatalogoListaDescuento
    {
        return CatalogoListaDescuento::query()
            ->where('activo', true)
            ->where(function ($q) {
                $q->where('nombre', 'MAYOREO BRONCE')
                    ->orWhere('nombre', 'like', '%BRONCE%');
            })
            ->orderByRaw("CASE WHEN nombre = 'MAYOREO BRONCE' THEN 0 ELSE 1 END")
            ->orderBy('monto_requerido')
            ->first();
    }

    private function resolverClienteId(array $datos, int $vendedorId): ?int
    {
        if (empty($datos['numero_cliente'])) return null;

        $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();

        return $cliente ? $cliente->id : null;
    }

    private function snapshotClienteAlCrear(?int $clienteId): array
    {
        if (!$clienteId) {
            return [];
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($clienteId);
        if (!$cliente) {
            return [];
        }

        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }
}
