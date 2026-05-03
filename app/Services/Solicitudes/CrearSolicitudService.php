<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use Illuminate\Support\Facades\DB;

class CrearSolicitudService
{
    /**
     * Ejecuta la lógica para almacenar una nueva solicitud.
     *
     * @param array $datos Validados por el FormRequest
     * @param int $vendedorId ID del usuario autenticado
     * @return SolicitudTag
     */
    public function ejecutar(array $datos, int $vendedorId): SolicitudTag
    {
        // DB::transaction asegura que si hay un error en alguna línea, 
        // no se guarde información a medias en la base de datos.
        return DB::transaction(function () use ($datos, $vendedorId) {
            
            // 1. Resolver el Cliente (Existente o Nuevo Prospecto)
            $clienteId = $this->resolverClienteId($datos, $vendedorId);

            // 2. Obtener el estado inicial por defecto ("Pendiente")
            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();

            // 3. Crear la Solicitud
            $solicitud = SolicitudTag::create([
                'cliente_id' => $clienteId,
                'vendedor_id' => $vendedorId,
                'catalogo_proceso_id' => $datos['catalogo_proceso_id'],
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'monto_cotizado' => $datos['monto_cotizado'],
                'pago_confirmado' => false, // Regla de negocio: inicia sin confirmar
                'observaciones_vendedor' => $datos['observaciones_vendedor'] ?? null,
            ]);

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