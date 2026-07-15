<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\ClienteDireccion;
use Illuminate\Support\Facades\DB;

class VersionadorDireccion
{
    public function __construct(
        private NormalizadorDireccion $normalizador,
        private ServicioAuditoriaDireccion $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null}  $contexto
     */
    public function ejecutar(ClienteDireccion $direccionActual, array $datos, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($direccionActual, $datos, $contexto) {
            $direccionActual = ClienteDireccion::query()->lockForUpdate()->findOrFail($direccionActual->id);
            $datos = $this->normalizador->ejecutar($datos);
            $anteriores = $direccionActual->only($direccionActual->getFillable());

            $nueva = ClienteDireccion::query()->create([
                'cliente_id' => $direccionActual->cliente_id,
                'numero_direccion' => $direccionActual->numero_direccion,
                'etiqueta' => $datos['etiqueta'] ?? $direccionActual->etiqueta,
                'tipo_direccion' => $datos['tipo_direccion'] ?? $direccionActual->tipo_direccion,
                'nombre_destinatario' => $datos['nombre_destinatario'] ?? $direccionActual->nombre_destinatario,
                'telefono_destinatario' => $datos['telefono_destinatario'] ?? $direccionActual->telefono_destinatario,
                'calle' => $datos['calle'] ?? $direccionActual->calle,
                'numero_exterior' => $datos['numero_exterior'] ?? $direccionActual->numero_exterior,
                'numero_interior' => $datos['numero_interior'] ?? $direccionActual->numero_interior,
                'colonia' => $datos['colonia'] ?? $direccionActual->colonia,
                'codigo_postal' => $datos['codigo_postal'] ?? $direccionActual->codigo_postal,
                'municipio' => $datos['municipio'] ?? $direccionActual->municipio,
                'ciudad' => $datos['ciudad'] ?? $direccionActual->ciudad,
                'estado' => $datos['estado'] ?? $direccionActual->estado,
                'pais' => $datos['pais'] ?? $direccionActual->pais,
                'referencias' => $datos['referencias'] ?? $direccionActual->referencias,
                'indicaciones_entrega' => $datos['indicaciones_entrega'] ?? $direccionActual->indicaciones_entrega,
                'es_principal' => $direccionActual->es_principal,
                'esta_activa' => true,
                'estado_verificacion' => $datos['estado_verificacion'] ?? ClienteDireccion::ESTADO_PENDING,
                'origen' => $contexto['origen'] ?? ClienteDireccion::ORIGEN_MANUAL,
                'version' => $direccionActual->version + 1,
                'direccion_anterior_id' => $direccionActual->id,
                'creada_por' => $contexto['usuario_id'] ?? null,
                'actualizada_por' => $contexto['usuario_id'] ?? null,
            ]);

            $direccionActual->update([
                'esta_activa' => false,
                'es_principal' => false,
                'actualizada_por' => $contexto['usuario_id'] ?? null,
            ]);

            $this->auditoria->ejecutar(
                $direccionActual->cliente_id,
                'crear_nueva_version',
                $contexto['usuario_id'] ?? null,
                $nueva->id,
                $contexto['solicitud_id'] ?? null,
                $anteriores,
                $nueva->only($nueva->getFillable()),
                $contexto['origen'] ?? null,
            );

            return $nueva->fresh();
        });
    }
}
