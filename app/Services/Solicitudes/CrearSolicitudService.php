<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
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

            // 3. Creación de la Solicitud
            $solicitud = SolicitudTag::create([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'departamento_id' => $departamentoOrigenId,
                'catalogo_proceso_id' => $datos['catalogo_proceso_id'],
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'monto_cotizado' => $datos['monto_cotizado'],
                'pago_confirmado' => filter_var($datos['pago_confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
                'evidencia_path' => $evidenciaPath,
                'catalogo_tipo_cliente_id' => $datos['catalogo_tipo_cliente_id'] ?? null,
                'catalogo_lista_descuento_id' => $datos['catalogo_lista_descuento_id'] ?? null,
                'confirmo_informacion_escalonamiento' => filter_var($datos['confirmo_informacion_escalonamiento'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'monto_final_tentativo' => $montoFinalTentativo,
                'total_proyectado_neto' => $totalProyectadoNeto,
            ]);

            // 3. Registro del Snapshot Inicial (Auditoría V1)
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => $vendedorId,
                'estado_anterior_id' => null, // Es nuevo, no hay estado anterior
                'estado_nuevo_id' => $estadoPendiente->id,
                'motivo_reporte' => 'Creación original de la solicitud.',
                'datos_snapshot' => [
                    'monto_cotizado' => $solicitud->monto_cotizado,
                    'proceso_id' => $solicitud->catalogo_proceso_id,
                    'evidencia_path' => $solicitud->evidencia_path,
                    'lista_descuento_id' => $solicitud->catalogo_lista_descuento_id,
                    'monto_final_tentativo' => $solicitud->monto_final_tentativo,
                    'total_proyectado_neto' => $solicitud->total_proyectado_neto,
                    'confirmo_informacion_escalonamiento' => $solicitud->confirmo_informacion_escalonamiento,
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
