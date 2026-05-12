<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\User; // <-- Importar User
use App\Notifications\AlertaSolicitud; // <-- Importar tu Notificación
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification; // <-- Importar el facade

class CrearSolicitudService
{
    /**
     * Ejecuta la lógica para almacenar una nueva solicitud.
     *
     * @param array $datos Validados por el FormRequest
     * @param int $vendedorId ID del usuario autenticado (Colaborador)
     * @return SolicitudTag
     */
    public function ejecutar(array $datos, int $vendedorId): SolicitudTag
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            
            // SECCIÓN: Inicialización y Creación de la Solicitud
            $clienteId = $this->resolverClienteId($datos, $vendedorId);
            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();

            $solicitud = SolicitudTag::create([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId, // Conservamos el nombre de la columna por integridad de la BD
                'catalogo_proceso_id' => $datos['catalogo_proceso_id'],
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'monto_cotizado' => $datos['monto_cotizado'],
                'pago_confirmado' => false,
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
            ]);

            // SECCIÓN: Identificación del Colaborador
            // Buscamos al usuario mediante el ID inyectado para aislar el servicio de la sesión HTTP
            $colaborador = User::find($vendedorId);
            $nombreColaborador = $colaborador ? $colaborador->name : 'Sistema';

            // SECCIÓN: Despliegue de Notificaciones
            // Solicitamos al sistema los roles superiores (Gerentes y Administradores)
            $encargados = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();

            // Validación de seguridad: Solo enviamos a la cola si existen encargados registrados
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

    /**
     * Función auxiliar para buscar al cliente o registrar uno básico si no existe.
     */
    private function resolverClienteId(array $datos, int $vendedorId): ?int
    {
        if (empty($datos['numero_cliente'])) {
            return null; // Es un prospecto sin número de cliente aún
        }

        $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();

        // Si el cliente no existe en nuestra BD pero la vendedora puso los datos manuales
        if (!$cliente && !empty($datos['nombre_cliente'])) {
            // Nota: Aquí se asume que tienes un registro en CatalogoListaDescuento por defecto, 
            // ej: "Sin Asignar" o "Bronce". Por ahora, omitimos la creación estricta 
            // o se debe definir una lista por defecto.
        }

        return $cliente ? $cliente->id : null;
    }
}