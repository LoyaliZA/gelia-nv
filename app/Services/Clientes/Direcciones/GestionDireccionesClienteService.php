<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Support\Clientes\FormatearDireccionEstructurada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GestionDireccionesClienteService
{
    public function __construct(
        private NormalizadorDireccion $normalizador,
        private VersionadorDireccion $versionador,
        private DetectorDireccionDuplicada $detectorDuplicados,
        private ServicioAuditoriaDireccion $auditoria,
        private SincronizarDireccionPrincipalConContactoService $sincronizarContacto,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listarActivasVerificadasPorCliente(int $clienteId): Collection
    {
        return ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->activas()
            ->verificadas()
            ->orderByDesc('es_principal')
            ->orderBy('numero_direccion')
            ->get()
            ->map(fn (ClienteDireccion $d) => [
                'id' => $d->id,
                'numero_direccion' => $d->numero_direccion,
                'etiqueta' => $d->etiqueta,
                'tipo_direccion' => $d->tipo_direccion,
                'nombre_destinatario' => $d->nombre_destinatario,
                'telefono_destinatario' => $d->telefono_destinatario,
                'calle' => $d->calle,
                'numero_exterior' => $d->numero_exterior,
                'numero_interior' => $d->numero_interior,
                'colonia' => $d->colonia,
                'codigo_postal' => $d->codigo_postal,
                'municipio' => $d->municipio,
                'ciudad' => $d->ciudad,
                'estado' => $d->estado,
                'pais' => $d->pais,
                'referencias' => $d->referencias,
                'indicaciones_entrega' => $d->indicaciones_entrega,
                'anexa_remision' => (bool) $d->anexa_remision,
                'direccion_resumida' => FormatearDireccionEstructurada::resumida($d),
                'es_principal' => $d->es_principal,
                'estado_verificacion' => $d->estado_verificacion,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerParaSnapshot(int $clienteId, int $direccionId): array
    {
        $direccion = ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->whereKey($direccionId)
            ->firstOrFail();

        if (! $direccion->esSnapshotable()) {
            throw new \InvalidArgumentException('La dirección no está activa, verificada o vigente para snapshot.');
        }

        return [
            'cliente_direccion_id' => $direccion->id,
            'cliente_id' => $direccion->cliente_id,
            'numero_direccion' => $direccion->numero_direccion,
            'etiqueta' => $direccion->etiqueta,
            'tipo_direccion' => $direccion->tipo_direccion,
            'nombre_destinatario' => $direccion->nombre_destinatario,
            'telefono_destinatario' => $direccion->telefono_destinatario,
            'calle' => $direccion->calle,
            'numero_exterior' => $direccion->numero_exterior,
            'numero_interior' => $direccion->numero_interior,
            'colonia' => $direccion->colonia,
            'codigo_postal' => $direccion->codigo_postal,
            'municipio' => $direccion->municipio,
            'ciudad' => $direccion->ciudad,
            'estado' => $direccion->estado,
            'pais' => $direccion->pais,
            'referencias' => $direccion->referencias,
            'indicaciones_entrega' => $direccion->indicaciones_entrega,
            'version' => $direccion->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null, verificar?: bool}  $contexto
     */
    public function crearPrimeraDireccion(int $clienteId, array $datos, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($clienteId, $datos, $contexto) {
            $existentes = ClienteDireccion::query()
                ->where('cliente_id', $clienteId)
                ->activas()
                ->count();

            if ($existentes > 0) {
                throw new \RuntimeException('El cliente ya tiene direcciones activas; use el alta adicional.');
            }

            return $this->crearDireccion($clienteId, $datos, array_merge($contexto, [
                'es_principal' => true,
                'numero_direccion' => 1,
            ]));
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null, verificar?: bool, es_principal?: bool}  $contexto
     */
    public function crearDireccionAdicional(int $clienteId, array $datos, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($clienteId, $datos, $contexto) {
            $siguiente = (int) ClienteDireccion::query()
                ->where('cliente_id', $clienteId)
                ->max('numero_direccion') + 1;

            if ($siguiente < 1) {
                $siguiente = 1;
            }

            return $this->crearDireccion($clienteId, $datos, array_merge($contexto, [
                'es_principal' => (bool) ($contexto['es_principal'] ?? false),
                'numero_direccion' => $siguiente,
            ]));
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null}  $contexto
     */
    public function crearNuevaVersion(int $direccionId, array $datos, array $contexto = []): ClienteDireccion
    {
        $actual = ClienteDireccion::query()->findOrFail($direccionId);

        return $this->versionador->ejecutar($actual, $datos, $contexto);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, verificar?: bool}  $contexto
     */
    public function actualizarDireccionInPlace(int $direccionId, array $datos, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($direccionId, $datos, $contexto) {
            $direccion = ClienteDireccion::query()->lockForUpdate()->findOrFail($direccionId);

            if (! $direccion->esta_activa) {
                throw new \InvalidArgumentException('La dirección no está activa.');
            }

            $datos = $this->normalizador->ejecutar($datos);
            $verificar = (bool) ($contexto['verificar'] ?? false);

            $attrs = [
                'etiqueta' => $datos['etiqueta'] ?? $direccion->etiqueta,
                'tipo_direccion' => $datos['tipo_direccion'] ?? $direccion->tipo_direccion,
                'nombre_destinatario' => $datos['nombre_destinatario'] ?? $direccion->nombre_destinatario,
                'nombres_destinatario' => $datos['nombres_destinatario'] ?? $direccion->nombres_destinatario,
                'apellidos_destinatario' => $datos['apellidos_destinatario'] ?? $direccion->apellidos_destinatario,
                'telefono_destinatario' => $datos['telefono_destinatario'] ?? $direccion->telefono_destinatario,
                'calle' => $datos['calle'] ?? $direccion->calle,
                'numero_exterior' => $datos['numero_exterior'] ?? $direccion->numero_exterior,
                'numero_interior' => $datos['numero_interior'] ?? $direccion->numero_interior,
                'colonia' => $datos['colonia'] ?? $direccion->colonia,
                'codigo_postal' => $datos['codigo_postal'] ?? $direccion->codigo_postal,
                'municipio' => $datos['municipio'] ?? $direccion->municipio,
                'ciudad' => $datos['ciudad'] ?? $direccion->ciudad,
                'estado' => $datos['estado'] ?? $direccion->estado,
                'pais' => $datos['pais'] ?? $direccion->pais,
                'referencias' => $datos['referencias'] ?? $direccion->referencias,
                'indicaciones_entrega' => $datos['indicaciones_entrega'] ?? $direccion->indicaciones_entrega,
                'anexa_remision' => array_key_exists('anexa_remision', $datos)
                    ? (bool) $datos['anexa_remision']
                    : $direccion->anexa_remision,
                'actualizada_por' => $contexto['usuario_id'] ?? null,
            ];

            if ($verificar) {
                $attrs['estado_verificacion'] = ClienteDireccion::ESTADO_VERIFIED;
                $attrs['verificada_en'] = now();
                $attrs['verificada_por'] = $contexto['usuario_id'] ?? null;
            }

            $direccion->update($attrs);

            $this->auditoria->ejecutar(
                $direccion->cliente_id,
                'actualizar_direccion',
                $contexto['usuario_id'] ?? null,
                $direccion->id,
                $contexto['solicitud_id'] ?? null,
                null,
                $direccion->fresh()->only($direccion->getFillable()),
                $contexto['origen'] ?? null,
            );

            if ($direccion->es_principal && $verificar) {
                $this->sincronizarContacto->ejecutar($direccion->cliente_id, $contexto);
            }

            return $direccion->fresh();
        });
    }

    /**
     * @param  array{usuario_id?: int|null, origen?: string|null}  $contexto
     */
    public function marcarComoPrincipal(int $direccionId, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($direccionId, $contexto) {
            $direccion = ClienteDireccion::query()->lockForUpdate()->findOrFail($direccionId);

            if (! $direccion->esta_activa) {
                throw new \InvalidArgumentException('Solo direcciones activas pueden ser principales.');
            }

            ClienteDireccion::query()
                ->where('cliente_id', $direccion->cliente_id)
                ->where('es_principal', true)
                ->where('id', '!=', $direccion->id)
                ->update(['es_principal' => false]);

            $direccion->update([
                'es_principal' => true,
                'actualizada_por' => $contexto['usuario_id'] ?? null,
            ]);

            $this->auditoria->ejecutar(
                $direccion->cliente_id,
                'marcar_principal',
                $contexto['usuario_id'] ?? null,
                $direccion->id,
                null,
                null,
                ['es_principal' => true],
                $contexto['origen'] ?? null,
            );

            $this->sincronizarContacto->ejecutar($direccion->cliente_id, $contexto);

            return $direccion->fresh();
        });
    }

    /**
     * @param  array{usuario_id?: int|null, origen?: string|null}  $contexto
     */
    public function sincronizarDireccionPrincipalConContacto(int $clienteId, array $contexto = []): Cliente
    {
        return $this->sincronizarContacto->ejecutar($clienteId, $contexto);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function detectarPosiblesDuplicados(int $clienteId, array $datos, ?int $excluirId = null): Collection
    {
        return $this->detectorDuplicados->ejecutar($clienteId, $datos, $excluirId);
    }

    public function formatearDireccionEstructurada(array|ClienteDireccion $direccion): ?string
    {
        return FormatearDireccionEstructurada::ejecutar($direccion);
    }

    /**
     * @param  array{usuario_id?: int|null, origen?: string|null}  $contexto
     */
    public function desactivar(int $direccionId, array $contexto = []): ClienteDireccion
    {
        return DB::transaction(function () use ($direccionId, $contexto) {
            $direccion = ClienteDireccion::query()->lockForUpdate()->findOrFail($direccionId);
            $direccion->update([
                'esta_activa' => false,
                'es_principal' => false,
                'actualizada_por' => $contexto['usuario_id'] ?? null,
            ]);

            $this->auditoria->ejecutar(
                $direccion->cliente_id,
                'desactivar',
                $contexto['usuario_id'] ?? null,
                $direccion->id,
                null,
                null,
                ['esta_activa' => false],
                $contexto['origen'] ?? null,
            );

            return $direccion->fresh();
        });
    }

    /**
     * @param  array{usuario_id?: int|null, origen?: string|null}  $contexto
     */
    public function verificar(int $direccionId, array $contexto = []): ClienteDireccion
    {
        $direccion = ClienteDireccion::query()->findOrFail($direccionId);
        $direccion->update([
            'estado_verificacion' => ClienteDireccion::ESTADO_VERIFIED,
            'verificada_en' => now(),
            'verificada_por' => $contexto['usuario_id'] ?? null,
            'actualizada_por' => $contexto['usuario_id'] ?? null,
        ]);

        $this->auditoria->ejecutar(
            $direccion->cliente_id,
            'verificar',
            $contexto['usuario_id'] ?? null,
            $direccion->id,
            null,
            null,
            ['estado_verificacion' => ClienteDireccion::ESTADO_VERIFIED],
            $contexto['origen'] ?? null,
        );

        if ($direccion->es_principal) {
            $this->sincronizarContacto->ejecutar($direccion->cliente_id, $contexto);
        }

        return $direccion->fresh();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null, verificar?: bool, es_principal?: bool, numero_direccion?: int}  $contexto
     */
    private function crearDireccion(int $clienteId, array $datos, array $contexto): ClienteDireccion
    {
        $datos = $this->normalizador->ejecutar($datos);
        $verificar = (bool) ($contexto['verificar'] ?? false);
        $esPrincipal = (bool) ($contexto['es_principal'] ?? false);

        if ($esPrincipal) {
            ClienteDireccion::query()
                ->where('cliente_id', $clienteId)
                ->where('es_principal', true)
                ->update(['es_principal' => false]);
        }

        $direccion = ClienteDireccion::query()->create([
            'cliente_id' => $clienteId,
            'numero_direccion' => (int) ($contexto['numero_direccion'] ?? 1),
            'etiqueta' => $datos['etiqueta'] ?? null,
            'tipo_direccion' => $datos['tipo_direccion'] ?? ClienteDireccion::TIPO_ENVIO,
            'nombre_destinatario' => $datos['nombre_destinatario'] ?? '',
            'nombres_destinatario' => $datos['nombres_destinatario'] ?? null,
            'apellidos_destinatario' => $datos['apellidos_destinatario'] ?? null,
            'telefono_destinatario' => $datos['telefono_destinatario'] ?? null,
            'calle' => $datos['calle'] ?? null,
            'numero_exterior' => $datos['numero_exterior'] ?? null,
            'numero_interior' => $datos['numero_interior'] ?? null,
            'colonia' => $datos['colonia'] ?? null,
            'codigo_postal' => $datos['codigo_postal'] ?? null,
            'municipio' => $datos['municipio'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'estado' => $datos['estado'] ?? null,
            'pais' => $datos['pais'] ?? null,
            'referencias' => $datos['referencias'] ?? null,
            'indicaciones_entrega' => $datos['indicaciones_entrega'] ?? null,
            'anexa_remision' => (bool) ($datos['anexa_remision'] ?? false),
            'es_principal' => $esPrincipal,
            'esta_activa' => true,
            'estado_verificacion' => $verificar
                ? ClienteDireccion::ESTADO_VERIFIED
                : ($datos['estado_verificacion'] ?? ClienteDireccion::ESTADO_PENDING),
            'origen' => $contexto['origen'] ?? ClienteDireccion::ORIGEN_MANUAL,
            'version' => 1,
            'direccion_anterior_id' => null,
            'verificada_en' => $verificar ? now() : null,
            'verificada_por' => $verificar ? ($contexto['usuario_id'] ?? null) : null,
            'creada_por' => $contexto['usuario_id'] ?? null,
            'actualizada_por' => $contexto['usuario_id'] ?? null,
        ]);

        $this->auditoria->ejecutar(
            $clienteId,
            'crear_direccion',
            $contexto['usuario_id'] ?? null,
            $direccion->id,
            $contexto['solicitud_id'] ?? null,
            null,
            $direccion->only($direccion->getFillable()),
            $contexto['origen'] ?? null,
        );

        if ($esPrincipal && $verificar) {
            $this->sincronizarContacto->ejecutar($clienteId, $contexto);
        }

        return $direccion->fresh();
    }
}
