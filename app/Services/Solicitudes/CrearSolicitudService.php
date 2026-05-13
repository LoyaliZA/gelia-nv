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

            // 1. Procesamiento de la Evidencia (NUEVO)
            // Guardamos el archivo antes de crear la solicitud para tener el path listo.
            $evidenciaPath = null;
            if (isset($datos['evidencia']) && $datos['evidencia'] instanceof UploadedFile && $datos['evidencia']->isValid()) {
                // store() lo guarda en storage/app/public/evidencias_solicitudes y devuelve el path relativo
                $evidenciaPath = $datos['evidencia']->store('evidencias_solicitudes', 'public');
            }

            // 2. Creación de la Solicitud (Versión 1)
            $solicitud = SolicitudTag::create([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId, 
                'catalogo_proceso_id' => $datos['catalogo_proceso_id'],
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'monto_cotizado' => $datos['monto_cotizado'],
                'pago_confirmado' => false,
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
                'catalogo_tipo_cliente_id' => $datos['catalogo_tipo_cliente_id'] ?? null,
                'catalogo_lista_descuento_id' => $datos['catalogo_lista_descuento_id'] ?? null,
                
                // Aquí asignamos el path generado arriba
                'evidencia_path' => $evidenciaPath, 
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
                    'evidencia_path' => $solicitud->evidencia_path, // El snapshot captura la ruta correcta
                    'lista_descuento_id' => $solicitud->catalogo_lista_descuento_id
                ]
            ]);

            // 4. Despliegue de Notificaciones
            $colaborador = User::find($vendedorId);
            $nombreColaborador = $colaborador ? $colaborador->name : 'Sistema';
            $encargados = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();

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
}