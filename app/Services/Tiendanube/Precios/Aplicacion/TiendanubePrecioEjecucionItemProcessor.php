<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeApiNotFoundException;
use App\Exceptions\Tiendanube\TiendanubeApiRouteException;
use App\Exceptions\Tiendanube\TiendanubeApiValidationException;
use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Exceptions\Tiendanube\TiendanubePresupuestoLocalException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionEvento;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Services\Tiendanube\Gobernador\TiendanubeContextoHttpActual;
use App\Services\Tiendanube\Gobernador\TiendanubeEmisorHttp;
use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use App\Services\Tiendanube\Gobernador\TiendanubeReprogramador;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Services\Tiendanube\TiendanubeProductoRecursoLockService;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use App\Support\Tiendanube\TiendanubePropositoHttp;
use Illuminate\Support\Str;
use Throwable;

class TiendanubePrecioEjecucionItemProcessor
{
    public function __construct(
        private readonly TiendanubeApiClient $api,
        private readonly TiendanubePrecioVarianteWriteService $escritor,
        private readonly TiendanubePrecioEjecucionControlRemotoService $control,
        private readonly TiendanubeProductoRecursoLockService $recursoLocks,
        private readonly TiendanubeOperacionTiendaService $operaciones,
        private readonly TiendanubePrecioEjecucionAdmisionService $admision,
        private readonly TiendanubeGobernador $gobernador,
        private readonly TiendanubeReprogramador $reprogramador,
        private readonly TiendanubeContextoHttpActual $contextoHttp,
        private readonly TiendanubeEmisorHttp $emisor,
    ) {}

    private ?int $reservaStoreId = null;

    private ?string $reservaOperacionId = null;

    public function procesar(TiendanubePrecioEjecucionItem $item): void
    {
        if ($this->emisor->pendiente(TiendanubeClaseTurno::Publicacion)) {
            $this->emisor->bajo(
                TiendanubeClaseTurno::Publicacion,
                'precio:'.$item->id,
                'sin_escritura',
                fn () => $this->procesar($item),
            );

            return;
        }

        $ejecucion = $item->ejecucion;
        $ejecucion->refresh();

        if (in_array($ejecucion->estado, [
            TiendanubePrecioEjecucion::ESTADO_CANCELADA,
            TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA,
        ], true)) {
            return;
        }

        if (! $this->reclamar($item)) {
            return;
        }

        $item->refresh();

        try {
            $this->admision->assertVersionApi();
            $config = TiendanubeConfiguracion::obtener();
            if ((int) $ejecucion->config_generation !== (int) ($config->config_generation ?: 1)
                || (int) $ejecucion->store_id !== (int) $config->store_id) {
                $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'generacion_cambiada', 'La generación o tienda cambió. No se escribió.');

                return;
            }

            $this->operaciones->renovar((int) $ejecucion->store_id, (string) $ejecucion->lease_token);

            $lock = $this->recursoLocks->intentarProducto((int) $ejecucion->store_id, (int) $item->producto_id, 90);
            if ($lock === null) {
                $this->liberarAReintento($item, 'recurso_ocupado', 'El producto está ocupado por otra operación.');

                return;
            }

            try {
                $this->ejecutarTrasLock($item, $ejecucion);
            } finally {
                $lock->release();
            }
        } catch (TiendanubePresupuestoLocalException $e) {
            $this->registrarEsperaLocal($item, $e);
        } catch (TiendanubePrecioEjecucionException $e) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $e->codigo, $e->getMessage());
        } catch (Throwable $e) {
            $this->mapearFallo($item, $e);
        }
    }

    private function ejecutarTrasLock(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): void
    {
        if ($this->debeReconciliarSinPut($item, $ejecucion)) {
            $this->reconciliarIncierta($item, 'La escritura ya salió. Se reanuda la comprobación sin otro PUT.');

            return;
        }

        try {
            $producto = $this->leerProducto($item, $ejecucion);
        } catch (TiendanubePresupuestoLocalException $e) {
            $this->registrarEsperaLocal($item, $e);

            return;
        } catch (TiendanubeApiNotFoundException $e) {
            $this->finalizar(
                $item,
                TiendanubePrecioEjecucionItem::ESTADO_FALLIDO,
                'variante_inexistente',
                'El producto ya no existe en TiendaNube. El espejo local no se borró.',
                null,
                ['canal' => 'api', 'escritura' => false, 'fase' => 'sin_escritura']
            );

            return;
        } catch (TiendanubeApiException $e) {
            if (in_array($e->statusCode, [401, 402, 403, 422, 429], true)) {
                throw $e;
            }
            $this->registrarLecturaSinEscritura($item, 'lectura_previa', $e->getMessage());

            return;
        } catch (Throwable $e) {
            $this->registrarLecturaSinEscritura($item, 'lectura_previa', $e->getMessage());

            return;
        }

        $variante = $this->control->varianteDeProducto($producto, (int) $item->variante_id);
        if ($variante === null) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'variante_inexistente', 'La variante ya no existe en TiendaNube. El espejo local no se borró.');

            return;
        }

        $remoto = $this->control->preciosDeVariante($variante);
        $item->update(['valor_remoto_previo' => $remoto]);
        $comparacion = $this->control->comparar(
            $item->campos_objetivo ?? [],
            $remoto,
            $item->valor_anterior ?? []
        );

        if ($comparacion['resultado'] === 'objetivo') {
            $this->finalizar(
                $item,
                TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
                'coincidencia_previa',
                $comparacion['explicacion'],
                $remoto,
                ['canal' => 'api', 'escritura' => false]
            );

            return;
        }

        if ($comparacion['resultado'] === 'conflicto') {
            $this->finalizar(
                $item,
                TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO,
                'conflicto_remoto',
                $comparacion['explicacion'],
                $remoto
            );

            return;
        }

        $payload = $this->escritor->construirPayload($item->campos_objetivo ?? []);
        $resultado = $this->escribirConTurno($item, $ejecucion, $payload);

        if ($resultado->escrituraIncierta) {
            $this->reconciliarIncierta($item, $resultado->detalleRefresco);

            return;
        }

        $productoFinal = $resultado->productoCompleto ?? [];
        $varianteFinal = $this->control->varianteDeProducto($productoFinal, (int) $item->variante_id);
        $remotoFinal = $varianteFinal ? $this->control->preciosDeVariante($varianteFinal) : $remoto;
        $post = $this->control->comparar($item->campos_objetivo ?? [], $remotoFinal, $item->valor_anterior ?? []);

        if ($post['resultado'] === 'objetivo') {
            $this->finalizar(
                $item,
                TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO,
                null,
                'Escritura confirmada por relectura.',
                $remotoFinal,
                ['canal' => 'api', 'escritura' => true, 'payload' => $payload]
            );

            return;
        }

        $this->finalizar(
            $item,
            TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO,
            'conflicto_post_escritura',
            'Tras escribir, el valor remoto no coincide con el aprobado.',
            $remotoFinal,
            ['canal' => 'api', 'escritura' => true, 'payload' => $payload]
        );
    }

    public function verificar(TiendanubePrecioEjecucionItem $item): void
    {
        if ($this->emisor->pendiente(TiendanubeClaseTurno::Recuperacion)) {
            $this->emisor->bajo(
                TiendanubeClaseTurno::Recuperacion,
                'precio:'.$item->id.':verificar',
                'lectura_hecha',
                fn () => $this->verificar($item),
            );

            return;
        }

        $ejecucion = $item->ejecucion;
        try {
            $producto = $this->api->getProduct((int) $item->producto_id);
        } catch (Throwable $e) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO, 'lectura_fallida', $e->getMessage());

            return;
        }

        $variante = $this->control->varianteDeProducto($producto, (int) $item->variante_id);
        if ($variante === null) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'variante_inexistente', 'La variante ya no existe en TiendaNube. El espejo local no se borró.');

            return;
        }

        $remoto = $this->control->preciosDeVariante($variante);
        $comparacion = $this->control->comparar($item->campos_objetivo ?? [], $remoto, $item->valor_anterior ?? []);

        if ($comparacion['resultado'] === 'objetivo') {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, 'verificado', $comparacion['explicacion'], $remoto);

            return;
        }
        if ($comparacion['resultado'] === 'origen') {
            $this->liberarAReintento($item, 'origen_tras_incertidumbre', $comparacion['explicacion']);

            return;
        }

        $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, 'conflicto_remoto', $comparacion['explicacion'], $remoto);
    }

    private function reconciliarIncierta(TiendanubePrecioEjecucionItem $item, ?string $detalle): void
    {
        try {
            $producto = $this->emisor->bajo(
                TiendanubeClaseTurno::Recuperacion,
                'precio:'.$item->id.':reconciliar',
                'efecto_remoto_posible',
                fn () => $this->api->getProduct((int) $item->producto_id),
            );
        } catch (TiendanubePresupuestoLocalException $e) {
            $this->registrarEsperaLocal($item, $e);

            return;
        } catch (Throwable) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO, 'resultado_incierto', $detalle ?? 'Timeout tras el envío. Queda pendiente de verificar.');

            return;
        }

        $variante = $this->control->varianteDeProducto($producto, (int) $item->variante_id);
        if ($variante === null) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO, 'resultado_incierto', 'No se pudo confirmar la variante tras un envío ambiguo.');

            return;
        }

        $remoto = $this->control->preciosDeVariante($variante);
        $comparacion = $this->control->comparar($item->campos_objetivo ?? [], $remoto, $item->valor_anterior ?? []);
        if ($comparacion['resultado'] === 'objetivo') {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO, 'reconciliado', 'Timeout con resultado ya aplicado, reconocido por relectura.', $remoto);

            return;
        }
        if ($comparacion['resultado'] === 'origen') {
            $this->liberarAReintento($item, 'reintento_post_timeout', 'El valor remoto sigue en el origen; se reintentará el envío.');

            return;
        }

        $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, 'conflicto_remoto', $comparacion['explicacion'], $remoto);
    }

    private function reclamar(TiendanubePrecioEjecucionItem $item): bool
    {
        $token = (string) Str::uuid();
        $now = now();
        $lease = $now->copy()->addSeconds(max(30, (int) config('tiendanube.precio_ejecucion_lease_seconds', 90)));

        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ])
            ->where(function ($q) use ($now) {
                $q->whereNull('siguiente_intento_at')->orWhere('siguiente_intento_at', '<=', $now);
            })
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
                'lease_token' => $token,
                'lease_expires_at' => $lease,
                'intentos' => $item->intentos + 1,
                'updated_at' => $now,
            ]);

        return $affected === 1;
    }

    /**
     * @param  array<string, mixed>|null  $confirmado
     * @param  array<string, mixed>  $evidencia
     */
    private function leerProducto(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): array
    {
        if (! config('tiendanube.gobernador_activo', false)) {
            return $this->api->getProduct((int) $item->producto_id);
        }

        $operacion = 'precio:'.$item->id;
        $storeId = (int) $ejecucion->store_id;
        $this->reservaStoreId = $storeId;
        $this->reservaOperacionId = $operacion;

        $estado = $this->gobernador->estadoReserva($storeId, $operacion);
        $guardada = $this->lecturaVigente($item);
        if ($estado['pendiente'] && $estado['vigencias'] >= 1 && $guardada !== null) {
            return $guardada;
        }

        $turno = $this->gobernador->reservarEscritura(
            $storeId,
            TiendanubeClaseTurno::Publicacion->value,
            $operacion,
            'sin_escritura',
        );
        if (! $turno->concedido) {
            $this->reprogramador->reprogramar($turno);

            throw new TiendanubePresupuestoLocalException($turno);
        }

        $producto = $this->contextoHttp->usar(
            $operacion,
            TiendanubeClaseTurno::Publicacion->value,
            TiendanubePropositoHttp::ComprobacionVigencia->value,
            'lectura_hecha',
            fn () => $this->api->getProduct((int) $item->producto_id),
        );
        $this->guardarLecturaVigente($item, $storeId, $operacion, $producto);

        return $producto;
    }

    private function debeReconciliarSinPut(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): bool
    {
        if (! config('tiendanube.gobernador_activo', false)) {
            return false;
        }
        $evidencia = is_array($item->evidencia) ? $item->evidencia : [];
        if (($evidencia['motivo'] ?? '') !== 'sin_presupuesto_local') {
            return false;
        }
        if (! in_array($evidencia['fase'] ?? '', ['escritura_enviada', 'efecto_remoto_posible'], true)) {
            return false;
        }
        $estado = $this->gobernador->estadoReserva((int) $ejecucion->store_id, 'precio:'.$item->id);

        return ! $estado['pendiente'] && $estado['vigencias'] >= 1 && $this->lecturaVigente($item) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lecturaVigente(TiendanubePrecioEjecucionItem $item): ?array
    {
        $evidencia = is_array($item->evidencia) ? $item->evidencia : [];
        $lectura = $evidencia['lectura_vigente'] ?? null;
        if (! is_array($lectura) || ! is_array($lectura['producto'] ?? null)) {
            return null;
        }
        if (($lectura['proposito'] ?? '') !== TiendanubePropositoHttp::ComprobacionVigencia->value) {
            return null;
        }

        return $lectura['producto'];
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    private function guardarLecturaVigente(TiendanubePrecioEjecucionItem $item, int $storeId, string $operacion, array $producto): void
    {
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $tomadaEn = $this->gobernador->marcaTemporal();
        $item->update([
            'evidencia' => array_merge($previa, [
                'lectura_vigente' => [
                    'producto' => $producto,
                    'proposito' => TiendanubePropositoHttp::ComprobacionVigencia->value,
                    'fase' => 'lectura_hecha',
                    'tomada_en' => $tomadaEn,
                ],
            ]),
        ]);
        $item->refresh();
        $this->gobernador->guardarEvidencia($storeId, $operacion, 'lectura_hecha', [
            'proposito' => TiendanubePropositoHttp::ComprobacionVigencia->value,
            'tomada_en' => $tomadaEn,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function escribirConTurno(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion, array $payload): TiendanubePrecioVarianteWriteResult
    {
        $escribir = fn () => $this->escritor->escribir(
            (int) $ejecucion->store_id,
            (int) $item->producto_id,
            (int) $item->variante_id,
            $payload,
        );

        if (! config('tiendanube.gobernador_activo', false) || $this->reservaOperacionId === null) {
            return $escribir();
        }

        return $this->contextoHttp->usar(
            $this->reservaOperacionId,
            TiendanubeClaseTurno::Publicacion->value,
            TiendanubePropositoHttp::Intento->value,
            'escritura_enviada',
            $escribir,
        );
    }

    private function registrarEsperaLocal(TiendanubePrecioEjecucionItem $item, TiendanubePresupuestoLocalException $e): void
    {
        $this->reservaStoreId = null;
        $this->reservaOperacionId = null;
        $this->liberarAReintento($item, 'sin_presupuesto_local', $e->getMessage());
        $item->refresh();
        if ($item->estado !== TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE) {
            return;
        }
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $item->update([
            'evidencia' => array_merge($previa, [
                'canal' => 'gobernador',
                'escritura' => false,
                'fase' => $e->turno->fase !== '' ? $e->turno->fase : 'sin_escritura',
                'motivo' => 'sin_presupuesto_local',
            ]),
        ]);
    }

    private function soltarReserva(): void
    {
        if ($this->reservaStoreId === null || $this->reservaOperacionId === null) {
            return;
        }
        if (config('tiendanube.gobernador_activo', false)) {
            $this->gobernador->liberarReserva($this->reservaStoreId, $this->reservaOperacionId);
        }
        $this->reservaStoreId = null;
        $this->reservaOperacionId = null;
    }

    private function finalizar(
        TiendanubePrecioEjecucionItem $item,
        string $estado,
        ?string $codigo,
        string $mensaje,
        ?array $confirmado = null,
        array $evidencia = [],
    ): void {
        $this->soltarReserva();
        $tipoEvento = match ($estado) {
            TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO => $codigo === 'coincidencia_previa'
                ? TiendanubePrecioEjecucionEvento::TIPO_ITEM_COINCIDENCIA
                : TiendanubePrecioEjecucionEvento::TIPO_ITEM_CONFIRMADO,
            TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO => TiendanubePrecioEjecucionEvento::TIPO_ITEM_CONFLICTO,
            TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO => TiendanubePrecioEjecucionEvento::TIPO_ITEM_INCIERTO,
            default => TiendanubePrecioEjecucionEvento::TIPO_ITEM_FALLIDO,
        };

        $item->update([
            'estado' => $estado,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'valor_confirmado' => $confirmado,
            'evidencia' => $evidencia + ['mensaje' => $mensaje],
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);

        $item->ejecucion?->eventos()->create([
            'item_id' => $item->id,
            'tipo' => $tipoEvento,
            'actor_id' => $item->ejecucion?->user_id,
            'payload' => [
                'canal' => 'api',
                'variante_id' => $item->variante_id,
                'estado' => $estado,
                'codigo' => $codigo,
            ],
            'created_at' => now(),
        ]);
    }

    private function registrarLecturaSinEscritura(TiendanubePrecioEjecucionItem $item, string $codigo, string $mensaje): void
    {
        $this->soltarReserva();
        $this->liberarAReintento($item, $codigo, $mensaje);
        $item->refresh();
        if ($item->estado !== TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE) {
            return;
        }
        $item->update([
            'evidencia' => [
                'canal' => 'api',
                'escritura' => false,
                'fase' => 'sin_escritura',
                'mensaje' => $mensaje,
            ],
        ]);
    }

    private function liberarAReintento(TiendanubePrecioEjecucionItem $item, string $codigo, string $mensaje): void
    {
        $max = max(1, (int) config('tiendanube.precio_ejecucion_item_max_intentos', 5));
        if ($item->intentos >= $max) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'max_intentos', $mensaje);

            return;
        }

        $item->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'siguiente_intento_at' => now()->addSeconds(5),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
    }

    private function mapearFallo(TiendanubePrecioEjecucionItem $item, Throwable $e): void
    {
        if ($e instanceof TiendanubeApiValidationException) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'validacion_remota', $e->getMessage());

            return;
        }

        if ($e instanceof TiendanubeApiNotFoundException) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'no_encontrado', $e->getMessage());

            return;
        }

        if ($e instanceof TiendanubeApiRouteException) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'ruta_no_encontrada', $e->getMessage());

            return;
        }

        if ($e instanceof TiendanubeApiException && in_array($e->statusCode, [401, 403], true)) {
            $item->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                'error_codigo' => 'credenciales',
                'error_mensaje' => $e->getMessage(),
                'lease_token' => null,
                'lease_expires_at' => null,
            ]);
            $item->ejecucion?->update([
                'estado' => TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA,
                'error_codigo' => 'credenciales',
                'error_mensaje' => 'Las credenciales fueron rechazadas. Se detuvieron los envíos nuevos.',
            ]);
            $item->ejecucion?->eventos()->create([
                'tipo' => TiendanubePrecioEjecucionEvento::TIPO_SUSPENDIDA,
                'actor_id' => $item->ejecucion?->user_id,
                'payload' => ['canal' => 'api', 'codigo' => 'credenciales'],
                'created_at' => now(),
            ]);

            return;
        }

        if ($e instanceof TiendanubeApiException && $e->statusCode === 429) {
            $this->liberarAReintento($item, 'rate_limit', $e->getMessage());

            return;
        }

        if ($e instanceof TiendanubePrecioEjecucionException) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $e->codigo, $e->getMessage());

            return;
        }

        $this->liberarAReintento($item, 'error_transitorio', $e->getMessage());
    }
}
