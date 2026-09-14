<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Exceptions\Tiendanube\TiendanubeOperacionConflictException;
use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionEvento;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteAprobacionService;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TiendanubePrecioEjecucionAdmisionService
{
    public function __construct(
        private readonly TiendanubePrecioLoteAprobacionService $aprobacion,
        private readonly TiendanubeApiClient $api,
        private readonly TiendanubeOperacionTiendaService $operaciones,
        private readonly TiendanubePrecioVarianteWriteService $escritor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function admitir(
        string $loteId,
        int $storeId,
        int $userId,
        bool $puedeAplicar,
        string $checksum,
    ): array {
        if (! config('tiendanube.precios_aplicacion_habilitada', true)) {
            throw new TiendanubePrecioEjecucionException('La aplicación por API no está habilitada.', 'deshabilitado', 403);
        }
        if (! $puedeAplicar) {
            throw new TiendanubePrecioEjecucionException('No tiene permiso para aplicar precios de forma masiva.', 'sin_permiso', 403);
        }

        $this->assertVersionApi();
        $config = TiendanubeConfiguracion::obtener();
        try {
            $revision = $this->aprobacion->obtenerRevisionAprobada($loteId, $storeId, $userId);
        } catch (TiendanubePrecioLoteException $e) {
            throw new TiendanubePrecioEjecucionException($e->getMessage(), $e->codigo, $e->httpStatus, $e->errores, $e);
        }

        if (! hash_equals((string) $revision['checksum'], $checksum)) {
            throw new TiendanubePrecioEjecucionException(
                'La revisión cambió. Recargue e intente de nuevo.',
                'conflicto_checksum',
                409
            );
        }

        if ((int) $revision['config_generation'] !== (int) ($config->config_generation ?: 1)) {
            throw new TiendanubePrecioEjecucionException(
                'La generación de la tienda cambió. No se enviará esta revisión.',
                'generacion_cambiada',
                409
            );
        }

        $existente = TiendanubePrecioEjecucion::query()
            ->where('revision_id', $revision['revision_id'])
            ->where('canal', TiendanubePrecioEjecucion::CANAL_API)
            ->first();
        if ($existente) {
            return app(TiendanubePrecioEjecucionService::class)->payload($existente);
        }

        $filas = $this->materializarItems($revision);
        if ($filas === []) {
            throw new TiendanubePrecioEjecucionException(
                'La revisión no tiene variantes publicables para aplicar.',
                'sin_items'
            );
        }

        try {
            $ejecucion = DB::transaction(function () use ($revision, $storeId, $userId, $config, $filas) {
                $token = $this->operaciones->adquirirExclusiva(
                    $storeId,
                    TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION,
                    $userId,
                    (int) ($config->config_generation ?: 1)
                );

                $conteoCampos = ['normal' => 0, 'promocional' => 0, 'costo_remoto' => 0];
                foreach ($filas as $fila) {
                    foreach (array_keys($conteoCampos) as $destino) {
                        $intencion = $fila['campos_objetivo'][$destino]['intencion'] ?? 'conservar';
                        if ($intencion !== TiendanubePrecioIntencion::Conservar->value) {
                            $conteoCampos[$destino]++;
                        }
                    }
                }

                $ejecucion = TiendanubePrecioEjecucion::query()->create([
                    'id' => (string) Str::uuid(),
                    'lote_id' => $revision['lote_id'],
                    'revision_id' => $revision['revision_id'],
                    'store_id' => $storeId,
                    'user_id' => $userId,
                    'config_generation' => (int) $revision['config_generation'],
                    'api_version' => (string) $revision['api_version'],
                    'checksum_revision' => $revision['checksum'],
                    'canal' => TiendanubePrecioEjecucion::CANAL_API,
                    'estado' => TiendanubePrecioEjecucion::ESTADO_PENDIENTE,
                    'total' => count($filas),
                    'pendientes' => count($filas),
                    'resumen_campos' => $conteoCampos,
                    'lease_token' => $token,
                    'lease_expires_at' => now()->addSeconds(max(30, (int) config('tiendanube.sync_lease_seconds', 120))),
                    'started_at' => now(),
                ]);

                foreach ($filas as $fila) {
                    $ejecucion->items()->create($fila);
                }

                $ejecucion->eventos()->create([
                    'tipo' => TiendanubePrecioEjecucionEvento::TIPO_INICIADA,
                    'actor_id' => $userId,
                    'payload' => [
                        'canal' => TiendanubePrecioEjecucion::CANAL_API,
                        'total' => count($filas),
                        'campos' => $conteoCampos,
                    ],
                    'created_at' => now(),
                ]);

                return $ejecucion;
            });
        } catch (TiendanubeOperacionConflictException $e) {
            throw new TiendanubePrecioEjecucionException(
                $e->getMessage(),
                'operacion_tienda',
                409,
                [],
                $e
            );
        }

        ProcesarPrecioEjecucionJob::dispatch($ejecucion->id);

        return app(TiendanubePrecioEjecucionService::class)->payload($ejecucion->fresh());
    }

    public function assertVersionApi(): void
    {
        $requerida = (string) config('tiendanube.api_version_precios', '2025-03');
        $efectiva = $this->api->configuredVersion();
        if ($efectiva !== $requerida) {
            $base = trim((string) config('tiendanube.api_base', ''));
            throw new TiendanubePrecioEjecucionException(
                $base !== ''
                    ? 'TIENDANUBE_API_BASE apunta a la versión '.$efectiva.'; se requiere '.$requerida.' sin alterar la configuración.'
                    : 'La versión efectiva de la API es '.$efectiva.'; se requiere '.$requerida.'.',
                'api_version_invalida',
                409
            );
        }
    }

    /**
     * @param  array<string, mixed>  $revision
     * @return list<array<string, mixed>>
     */
    private function materializarItems(array $revision): array
    {
        $filas = [];
        foreach ($revision['items'] as $item) {
            if (! ($item['publicable'] ?? false) || ($item['excluido'] ?? false)) {
                continue;
            }

            $campos = $item['campos'] ?? [];
            try {
                $this->escritor->construirPayload($campos);
            } catch (TiendanubePrecioEjecucionException $e) {
                if ($e->codigo === 'sin_cambios') {
                    continue;
                }
                throw $e;
            }

            $objetivo = [];
            $aprobado = [];
            foreach (TiendanubePrecioDestino::cases() as $destino) {
                $clave = $destino->value;
                $campo = $campos[$clave] ?? [];
                $objetivo[$clave] = [
                    'intencion' => $campo['intencion'] ?? TiendanubePrecioIntencion::Conservar->value,
                    'valor_final' => $campo['valor_final'] ?? null,
                ];
                $aprobado[$clave] = $campo['valor_final'] ?? null;
            }

            $filas[] = [
                'producto_id' => (int) $item['producto_id'],
                'variante_id' => (int) $item['variante_id'],
                'producto_nombre' => $item['nombre'] ?? null,
                'variante_sku' => $item['sku'] ?? null,
                'variante_atributos' => $item['atributos'] ?? [],
                'imagen_url' => $item['imagen_url'] ?? null,
                'campos_objetivo' => $objetivo,
                'valor_aprobado' => $aprobado,
                'valor_anterior' => $item['valores_anteriores'] ?? [],
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
            ];
        }

        return $filas;
    }
}
