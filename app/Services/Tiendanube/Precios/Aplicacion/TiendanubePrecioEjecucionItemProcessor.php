<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Exceptions\Tiendanube\TiendanubeApiException;
use App\Exceptions\Tiendanube\TiendanubeApiNotFoundException;
use App\Exceptions\Tiendanube\TiendanubeApiPaymentRequiredException;
use App\Exceptions\Tiendanube\TiendanubeApiRateLimitException;
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
use App\Services\Tiendanube\Gobernador\TiendanubeTurnoResultado;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;
use App\Services\Tiendanube\TiendanubeProductoRecursoLockService;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use App\Support\Tiendanube\TiendanubePropositoHttp;
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
        private readonly TiendanubePrecioLeaseGuard $leases,
    ) {}

    private ?int $reservaStoreId = null;

    private ?string $reservaOperacionId = null;

    private ?string $leaseToken = null;

    private bool $envioIniciado = false;

    public function procesar(TiendanubePrecioEjecucionItem $item): void
    {
        if ($this->emisor->pendiente(TiendanubeClaseTurno::Publicacion)) {
            try {
                $this->emisor->bajo(
                    TiendanubeClaseTurno::Publicacion,
                    'precio:'.$item->id,
                    'sin_escritura',
                    fn () => $this->procesar($item),
                );
            } catch (TiendanubePresupuestoLocalException $e) {
                $this->registrarEsperaLocal($item, $e);
            } catch (TiendanubeApiRateLimitException $e) {
                $this->registrarEsperaRemota($item, $e);
            }

            return;
        }

        $this->leaseToken = null;
        $this->envioIniciado = false;
        $ejecucion = $item->ejecucion;
        $ejecucion->refresh();

        if (in_array($ejecucion->estado, [
            TiendanubePrecioEjecucion::ESTADO_CANCELADA,
            TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA,
        ], true)) {
            return;
        }

        $this->leaseToken = $this->reclamar($item);
        if ($this->leaseToken === null) {
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

            if (! $this->prepararExclusividad($ejecucion)) {
                $this->parkearSinEnvio($item, 'exclusividad_perdida', 'La exclusividad de la tienda no está vigente. No hay envíos nuevos.');

                return;
            }

            $lock = $this->recursoLocks->intentarProducto(
                (int) $ejecucion->store_id,
                (int) $item->producto_id,
                max(1, (int) config('tiendanube.precio_recurso_lock_seconds', 90)),
            );
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
            if ($this->contextoHttp->clase === TiendanubeClaseTurno::Publicacion->value) {
                throw $e;
            }
            $this->registrarEsperaLocal($item, $e);
        } catch (TiendanubeApiRateLimitException $e) {
            if ($this->contextoHttp->clase === TiendanubeClaseTurno::Publicacion->value) {
                throw $e;
            }
            $this->registrarEsperaRemota($item, $e);
        } catch (TiendanubePrecioEjecucionException $e) {
            if ($e->codigo === 'sin_propiedad') {
                return;
            }
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
            if ($producto === null) {
                return;
            }
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
        } catch (TiendanubePresupuestoLocalException|TiendanubeApiRateLimitException|TiendanubePrecioEjecucionException $e) {
            throw $e;
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
        if (! $this->propio($item, ['valor_remoto_previo' => $remoto])) {
            $this->exigirConciliacionSiHizoFalta($item, 'Se perdió la propiedad antes de escribir.');

            return;
        }
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
        if ($resultado === null) {
            return;
        }

        if ($resultado->escrituraIncierta) {
            $this->marcarEfectoPosible($item, $payload);
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
            try {
                $this->emisor->bajo(
                    TiendanubeClaseTurno::Recuperacion,
                    'precio:'.$item->id.':verificar',
                    'escritura_enviada',
                    fn () => $this->verificar($item),
                );
            } catch (TiendanubePresupuestoLocalException $e) {
                $this->registrarEsperaLocal($item, $e);
            } catch (TiendanubeApiRateLimitException $e) {
                $this->registrarEsperaRemota($item, $e);
            }

            return;
        }

        $this->leaseToken = $this->leases->reclamarParaVerificar($item);
        if ($this->leaseToken === null) {
            return;
        }
        $this->envioIniciado = false;

        $ejecucion = $item->ejecucion;
        if (! $this->puedeEnviar($item, $ejecucion)) {
            return;
        }
        try {
            $producto = $this->apiPrecio()->getProduct((int) $item->producto_id);
        } catch (TiendanubePresupuestoLocalException $e) {
            if ($this->contextoHttp->clase === TiendanubeClaseTurno::Recuperacion->value) {
                throw $e;
            }
            $this->registrarEsperaLocal($item, $e);

            return;
        } catch (TiendanubeApiRateLimitException $e) {
            if ($this->contextoHttp->clase === TiendanubeClaseTurno::Recuperacion->value) {
                throw $e;
            }
            $this->registrarEsperaRemota($item, $e);

            return;
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
            $this->cerrarVerificacion($item, 'origen');
            $this->liberarAReintento($item, 'origen_tras_incertidumbre', $comparacion['explicacion'], false);

            return;
        }

        $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, 'conflicto_remoto', $comparacion['explicacion'], $remoto);
    }

    private function reconciliarIncierta(TiendanubePrecioEjecucionItem $item, ?string $detalle): void
    {
        $ejecucion = $item->ejecucion;
        if ($ejecucion === null || ! $this->puedeEnviar($item, $ejecucion)) {
            return;
        }
        try {
            $producto = $this->emisor->bajo(
                TiendanubeClaseTurno::Recuperacion,
                'precio:'.$item->id.':reconciliar',
                'efecto_remoto_posible',
                fn () => $this->apiPrecio()->getProduct((int) $item->producto_id),
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
            $this->cerrarVerificacion($item, 'origen');
            $this->liberarAReintento($item, 'reintento_post_timeout', 'El valor remoto sigue en el origen; se reintentará el envío.', false);

            return;
        }

        $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO, 'conflicto_remoto', $comparacion['explicacion'], $remoto);
    }

    private function reclamar(TiendanubePrecioEjecucionItem $item): ?string
    {
        return $this->leases->reclamar($item);
    }

    /**
     * @param  array<string, mixed>|null  $confirmado
     * @param  array<string, mixed>  $evidencia
     */
    /**
     * @return array<string, mixed>|null
     */
    private function leerProducto(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): ?array
    {
        if (! $this->puedeEnviar($item, $ejecucion)) {
            return null;
        }

        if (! config('tiendanube.gobernador_activo', false)) {
            return $this->apiPrecio()->getProduct((int) $item->producto_id);
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
        if ($estado['pendiente'] && $estado['vigencias'] >= 1) {
            $renovacion = $this->gobernador->renovarVigencia($storeId, $operacion, 'lectura_hecha');
            if (! $renovacion->concedido) {
                $this->reprogramador->reprogramar($renovacion);

                throw new TiendanubePresupuestoLocalException($renovacion);
            }
        }

        $turno = $this->gobernador->reservarEscritura(
            $storeId,
            TiendanubeClaseTurno::Publicacion->value,
            $operacion,
            'sin_escritura',
            null,
            null,
            $this->leaseToken,
            $this->leases->segundos() * 1000,
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
            fn () => $this->apiPrecio()->getProduct((int) $item->producto_id),
        );
        if (! $this->guardarLecturaVigente($item, $storeId, $operacion, $producto)) {
            return null;
        }

        return $producto;
    }

    private function debeReconciliarSinPut(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): bool
    {
        $evidencia = is_array($item->evidencia) ? $item->evidencia : [];
        if ((bool) ($evidencia['verificacion_pendiente'] ?? false)) {
            return true;
        }

        return $this->leases->hayConciliacionAbierta($item);
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
        $tomadaEn = (int) ($lectura['tomada_en'] ?? 0);
        $maxima = max(1, (int) config('tiendanube.gobernador_lectura_max_ms', 30_000));
        if ($tomadaEn <= 0 || ($this->gobernador->marcaTemporal() - $tomadaEn) > $maxima) {
            return null;
        }
        $ejecucion = $item->ejecucion;
        if ($ejecucion === null) {
            return null;
        }
        $generacion = (int) ($lectura['config_generation'] ?? -1);
        if ($generacion !== (int) $ejecucion->config_generation) {
            return null;
        }
        if ($generacion !== (int) (TiendanubeConfiguracion::obtener()->config_generation ?: 1)) {
            return null;
        }
        if ((string) ($lectura['checksum_revision'] ?? '') !== (string) $ejecucion->checksum_revision) {
            return null;
        }

        return $lectura['producto'];
    }

    /**
     * @param  array<string, mixed>  $producto
     */
    private function guardarLecturaVigente(TiendanubePrecioEjecucionItem $item, int $storeId, string $operacion, array $producto): bool
    {
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $tomadaEn = $this->gobernador->marcaTemporal();
        $ejecucion = $item->ejecucion;
        $contadores = $this->contadores($previa);
        $contadores['lecturas']++;
        if (! $this->propio($item, [
            'evidencia' => array_merge($previa, [
                'contadores' => $contadores,
                'lectura_vigente' => [
                    'producto' => $producto,
                    'proposito' => TiendanubePropositoHttp::ComprobacionVigencia->value,
                    'fase' => 'lectura_hecha',
                    'tomada_en' => $tomadaEn,
                    'config_generation' => (int) ($ejecucion->config_generation ?? 0),
                    'checksum_revision' => (string) ($ejecucion->checksum_revision ?? ''),
                ],
            ]),
        ])) {
            return false;
        }
        $this->gobernador->guardarEvidencia($storeId, $operacion, 'lectura_hecha', [
            'proposito' => TiendanubePropositoHttp::ComprobacionVigencia->value,
            'tomada_en' => $tomadaEn,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function escribirConTurno(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion, array $payload): ?TiendanubePrecioVarianteWriteResult
    {
        if (! $this->puedeEnviar($item, $ejecucion)) {
            return null;
        }

        $escribir = fn () => $this->escritor->escribir(
            (int) $ejecucion->store_id,
            (int) $item->producto_id,
            (int) $item->variante_id,
            $payload,
            function () {
                $this->envioIniciado = true;
            },
        );

        if (! config('tiendanube.gobernador_activo', false) || $this->reservaOperacionId === null) {
            $resultado = $escribir();
            $this->contarEnvio($item);

            return $resultado;
        }

        try {
            $resultado = $this->contextoHttp->usar(
                $this->reservaOperacionId,
                TiendanubeClaseTurno::Publicacion->value,
                TiendanubePropositoHttp::Intento->value,
                'escritura_enviada',
                $escribir,
            );
        } catch (TiendanubePresupuestoLocalException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->contarEnvio($item);

            throw $e;
        }
        $this->contarEnvio($item);

        return $resultado;
    }

    private function registrarEsperaLocal(TiendanubePrecioEjecucionItem $item, TiendanubePresupuestoLocalException $e): void
    {
        $this->cederDuenoReserva();
        $this->reservaStoreId = null;
        $this->reservaOperacionId = null;
        $this->anotarEspera($item, $e->turno, 'sin_presupuesto_local');
    }

    private function registrarEsperaRemota(TiendanubePrecioEjecucionItem $item, TiendanubeApiRateLimitException $e): void
    {
        $this->cederDuenoReserva();
        $this->reservaStoreId = null;
        $this->reservaOperacionId = null;
        $item->refresh();
        $operacion = 'precio:'.$item->id;
        $storeId = (int) ($item->ejecucion->store_id ?? 0);
        $espera = $storeId > 0 ? $this->gobernador->espera($storeId, $operacion) : ['reanudar_despues_at' => 0, 'fase' => ''];
        $reanudar = (int) ($espera['reanudar_despues_at'] ?? 0);
        if ($reanudar <= $this->gobernador->marcaTemporal()) {
            $reanudar = $this->gobernador->marcaTemporal() + max(1, (int) config('tiendanube.gobernador_reset_respaldo_ms', 2_000));
        }
        $turno = new TiendanubeTurnoResultado(
            false,
            'rate_limit',
            $reanudar,
            (string) (($espera['fase'] ?? '') !== '' ? $espera['fase'] : 'sin_escritura'),
            $operacion,
            TiendanubeClaseTurno::Publicacion->value,
        );
        $this->anotarEspera($item, $turno, 'rate_limit');
    }

    private function anotarEspera(TiendanubePrecioEjecucionItem $item, TiendanubeTurnoResultado $turno, string $categoria): void
    {
        $item->refresh();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $contadores = $this->contadores($previa);
        $contadores['esperas']++;
        $pendiente = (bool) ($previa['verificacion_pendiente'] ?? false);
        $fase = $turno->fase !== '' ? $turno->fase : 'sin_escritura';
        if (! $pendiente && in_array($fase, ['escritura_enviada', 'efecto_remoto_posible'], true)) {
            $fase = isset($previa['lectura_vigente']) ? 'lectura_hecha' : 'sin_escritura';
        }
        if (! $this->propio($item, [
            'error_codigo' => $categoria,
            'error_mensaje' => 'Sin presupuesto local para la fase '.$turno->fase.'.',
            'evidencia' => array_merge($previa, [
                'contadores' => $contadores,
                'canal' => $categoria === 'rate_limit' ? 'api' : 'gobernador',
                'escritura' => $pendiente,
                'fase' => $fase,
                'motivo' => $categoria,
                'categoria' => $categoria,
            ]),
        ])) {
            $this->exigirConciliacionSiHizoFalta($item, 'La espera no pudo guardarse con el lease vigente.');

            return;
        }
        $this->reprogramador->reprogramar($turno, $this->leaseToken);
        $this->leaseToken = null;
    }

    private function soltarReserva(): void
    {
        if ($this->reservaStoreId === null || $this->reservaOperacionId === null) {
            return;
        }
        if (config('tiendanube.gobernador_activo', false)) {
            $this->gobernador->liberarReserva($this->reservaStoreId, $this->reservaOperacionId, $this->leaseToken);
        }
        $this->reservaStoreId = null;
        $this->reservaOperacionId = null;
    }

    private function cederDuenoReserva(): void
    {
        if (! config('tiendanube.gobernador_activo', false)
            || $this->reservaStoreId === null
            || $this->reservaOperacionId === null
            || $this->leaseToken === null
            || $this->leaseToken === '') {
            return;
        }

        $this->gobernador->cederDuenoReserva($this->reservaStoreId, $this->reservaOperacionId, $this->leaseToken);
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

        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $base = $estado === TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO ? $previa : [];
        $token = (string) $this->leaseToken;
        $ok = $this->propio($item, [
            'estado' => $estado,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'valor_confirmado' => $confirmado,
            'evidencia' => array_merge($base, $evidencia, ['mensaje' => $mensaje]),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        if (! $ok) {
            if ($this->envioIniciado) {
                $this->leases->registrarEfectoTrasPerderVigencia(
                    $item,
                    $token,
                    $evidencia,
                    'La vigencia se perdió después del envío. Eso no cancela una solicitud que Tiendanube ya pudo recibir. Hace falta conciliación.',
                );
            }

            return;
        }
        $this->leaseToken = null;

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
        $item->refresh();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $contadores = $this->contadores($previa);
        $contadores['lecturas']++;
        $max = max(1, (int) config('tiendanube.precio_ejecucion_item_max_intentos', 5));
        if ($contadores['lecturas'] >= $max) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'max_lecturas', $mensaje, null, [
                'canal' => 'api',
                'escritura' => false,
                'fase' => 'sin_escritura',
                'categoria' => 'error_transitorio',
                'contadores' => $contadores,
            ]);

            return;
        }
        $espera = max(1, (int) config('tiendanube.gobernador_reset_respaldo_ms', 2_000));
        if (! $this->propio($item, [
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'evidencia' => array_merge($previa, [
                'contadores' => $contadores,
                'canal' => 'api',
                'escritura' => false,
                'fase' => 'sin_escritura',
                'categoria' => 'error_transitorio',
                'mensaje' => $mensaje,
            ]),
        ])) {
            return;
        }
        $turno = new TiendanubeTurnoResultado(
            false,
            'error_transitorio',
            $this->gobernador->marcaTemporal() + $espera,
            'sin_escritura',
            'precio:'.$item->id,
            TiendanubeClaseTurno::Publicacion->value,
        );
        $this->reprogramador->reprogramar($turno, $this->leaseToken);
        $this->leaseToken = null;
    }

    private function liberarAReintento(TiendanubePrecioEjecucionItem $item, string $codigo, string $mensaje, bool $agotaEnvios = true): void
    {
        $item->refresh();
        $max = max(1, (int) config('tiendanube.precio_ejecucion_item_max_intentos', 5));
        if ($agotaEnvios && $item->intentos >= $max) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'max_intentos', $mensaje);

            return;
        }

        $espera = max(1, (int) config('tiendanube.gobernador_reset_respaldo_ms', 2_000));
        if ($this->propio($item, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'siguiente_intento_at' => now()->addMilliseconds($espera),
            'lease_token' => null,
            'lease_expires_at' => null,
        ])) {
            $this->leaseToken = null;
        }
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

        if ($e instanceof TiendanubeApiPaymentRequiredException || ($e instanceof TiendanubeApiException && $e->statusCode === 402)) {
            $this->suspender($item, 'cuenta', 'La cuenta de la tienda requiere atención. Se detuvieron los envíos nuevos.');

            return;
        }

        if ($e instanceof TiendanubeApiException && in_array($e->statusCode, [401, 403], true)) {
            $this->suspender($item, 'credenciales', 'Las credenciales fueron rechazadas. Se detuvieron los envíos nuevos.');

            return;
        }

        if ($e instanceof TiendanubeApiException && $e->statusCode === 429) {
            $this->registrarEsperaRemota($item, $e instanceof TiendanubeApiRateLimitException
                ? $e
                : new TiendanubeApiRateLimitException($e->getMessage(), 429, '', '', 'rate_limit'));

            return;
        }

        if ($e instanceof TiendanubePrecioEjecucionException) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, $e->codigo, $e->getMessage());

            return;
        }

        $item->refresh();
        $espera = max(1, (int) config('tiendanube.gobernador_reset_respaldo_ms', 2_000));
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $max = max(1, (int) config('tiendanube.precio_ejecucion_item_max_intentos', 5));
        if ($item->intentos >= $max) {
            $this->finalizar($item, TiendanubePrecioEjecucionItem::ESTADO_FALLIDO, 'max_intentos', $e->getMessage());

            return;
        }
        if (! $this->propio($item, [
            'error_codigo' => 'error_transitorio',
            'error_mensaje' => $e->getMessage(),
            'evidencia' => array_merge($previa, [
                'canal' => 'api',
                'escritura' => (bool) ($previa['verificacion_pendiente'] ?? false),
                'fase' => (bool) ($previa['verificacion_pendiente'] ?? false) ? ($previa['fase'] ?? 'efecto_remoto_posible') : 'sin_escritura',
                'categoria' => 'error_transitorio',
                'mensaje' => $e->getMessage(),
            ]),
        ])) {
            $this->exigirConciliacionSiHizoFalta($item, $e->getMessage());

            return;
        }
        $this->reprogramador->reprogramar(new TiendanubeTurnoResultado(
            false,
            'error_transitorio',
            $this->gobernador->marcaTemporal() + $espera,
            'sin_escritura',
            'precio:'.$item->id,
            TiendanubeClaseTurno::Publicacion->value,
        ), $this->leaseToken);
        $this->leaseToken = null;
    }

    /**
     * @param  array<string, mixed>  $evidencia
     * @return array{esperas: int, lecturas: int, envios: int}
     */
    private function contadores(array $evidencia): array
    {
        $actual = is_array($evidencia['contadores'] ?? null) ? $evidencia['contadores'] : [];

        return [
            'esperas' => (int) ($actual['esperas'] ?? 0),
            'lecturas' => (int) ($actual['lecturas'] ?? 0),
            'envios' => (int) ($actual['envios'] ?? 0),
        ];
    }

    private function contarEnvio(TiendanubePrecioEjecucionItem $item): void
    {
        $item->refresh();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $contadores = $this->contadores($previa);
        $contadores['envios']++;
        if (! $this->propio($item, [
            'intentos' => $item->intentos + 1,
            'evidencia' => array_merge($previa, ['contadores' => $contadores]),
        ])) {
            $this->exigirConciliacionSiHizoFalta($item, 'El envío quedó sin poder anotarse en el ítem.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marcarEfectoPosible(TiendanubePrecioEjecucionItem $item, array $payload): void
    {
        $item->refresh();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        if (! $this->propio($item, [
            'evidencia' => array_merge($previa, [
                'verificacion_pendiente' => true,
                'payload' => $payload,
                'escritura' => true,
                'fase' => 'escritura_enviada',
                'efecto' => 'posible',
            ]),
        ])) {
            $this->exigirConciliacionSiHizoFalta($item, 'El envío pudo salir y la evidencia no quedó en el ítem.');
        }
    }

    private function cerrarVerificacion(TiendanubePrecioEjecucionItem $item, string $resultado): void
    {
        $item->refresh();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $this->propio($item, [
            'evidencia' => array_merge($previa, [
                'verificacion_pendiente' => false,
                'verificacion' => $resultado,
            ]),
        ]);
    }

    private function suspender(TiendanubePrecioEjecucionItem $item, string $codigo, string $mensaje): void
    {
        $this->soltarReserva();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        if (! $this->propio($item, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'lease_token' => null,
            'lease_expires_at' => null,
            'evidencia' => array_merge($previa, [
                'categoria' => $codigo,
                'escritura' => $this->envioIniciado,
                'verificacion_pendiente' => $this->envioIniciado,
                'fase' => $this->envioIniciado ? 'efecto_remoto_posible' : 'sin_escritura',
                'conciliacion' => $this->envioIniciado ? 'exigida' : null,
            ]),
        ])) {
            $this->exigirConciliacionSiHizoFalta($item, $mensaje);

            return;
        }
        $tokenSuspendido = (string) $this->leaseToken;
        $this->leaseToken = null;
        if ($this->envioIniciado) {
            $this->leases->anotarConciliacion($item, $tokenSuspendido, $mensaje);
        }
        $item->ejecucion?->update([
            'estado' => TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
        ]);
        $item->ejecucion?->eventos()->create([
            'tipo' => TiendanubePrecioEjecucionEvento::TIPO_SUSPENDIDA,
            'actor_id' => $item->ejecucion?->user_id,
            'payload' => ['canal' => 'api', 'codigo' => $codigo],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function propio(TiendanubePrecioEjecucionItem $item, array $cambios): bool
    {
        if ($this->leaseToken === null || $this->leaseToken === '') {
            return false;
        }

        return $this->leases->actualizarVigente($item, $this->leaseToken, $cambios, [
            TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
        ]);
    }

    private function puedeEnviar(TiendanubePrecioEjecucionItem $item, TiendanubePrecioEjecucion $ejecucion): bool
    {
        if ($this->leaseToken === null || ! $this->leases->renovar($item, $this->leaseToken)) {
            $this->parkearSinEnvio($item, 'lease_item', 'El lease del ítem no sigue vigente. No hay envío nuevo.');

            return false;
        }
        $ejecucion->refresh();
        $tokenTienda = (string) $ejecucion->lease_token;
        if (! $this->operaciones->renovar((int) $ejecucion->store_id, $tokenTienda)) {
            $this->parkearSinEnvio($item, 'exclusividad_no_renovada', 'La exclusividad de la tienda no se renovó. Se detienen los envíos nuevos.');

            return false;
        }

        return true;
    }

    private function prepararExclusividad(TiendanubePrecioEjecucion $ejecucion): bool
    {
        $storeId = (int) $ejecucion->store_id;
        $token = (string) $ejecucion->lease_token;
        if ($this->operaciones->sigueVigente($storeId, $token)) {
            return $this->operaciones->renovar($storeId, $token);
        }

        $nuevo = $this->operaciones->recuperarPropiedad(
            $storeId,
            $token,
            TiendanubeOperacionTiendaService::TIPO_PRECIO_APLICACION,
            (int) $ejecucion->user_id,
            (int) $ejecucion->config_generation,
        );
        if ($nuevo === null) {
            return false;
        }

        $ahora = now();
        $segundos = max(30, (int) config('tiendanube.sync_lease_seconds', 120));
        $asignado = TiendanubePrecioEjecucion::query()
            ->where('id', $ejecucion->id)
            ->where(function ($q) use ($token) {
                if ($token === '') {
                    $q->whereNull('lease_token');
                } else {
                    $q->where('lease_token', $token);
                }
            })
            ->where(function ($q) use ($ahora) {
                $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<', $ahora);
            })
            ->update([
                'lease_token' => $nuevo,
                'lease_expires_at' => $ahora->copy()->addSeconds($segundos),
                'updated_at' => $ahora,
            ]);
        if ($asignado !== 1) {
            $this->operaciones->liberar($storeId, $nuevo);

            return false;
        }
        $ejecucion->refresh();

        return true;
    }

    private function parkearSinEnvio(TiendanubePrecioEjecucionItem $item, string $codigo, string $mensaje): void
    {
        $this->soltarReserva();
        if ($this->envioIniciado) {
            $this->exigirConciliacionSiHizoFalta($item, $mensaje);

            return;
        }
        $this->propio($item, [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'error_codigo' => $codigo,
            'error_mensaje' => $mensaje,
            'siguiente_intento_at' => now()->addMilliseconds(max(1, (int) config('tiendanube.gobernador_reset_respaldo_ms', 2_000))),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        $this->leaseToken = null;
    }

    private function exigirConciliacionSiHizoFalta(TiendanubePrecioEjecucionItem $item, string $mensaje): void
    {
        if (! $this->envioIniciado || $this->leaseToken === null) {
            return;
        }
        $this->leases->registrarEfectoTrasPerderVigencia(
            $item,
            $this->leaseToken,
            [],
            'La vigencia se perdió después del envío. Eso no cancela una solicitud que Tiendanube ya pudo recibir. '.$mensaje,
        );
        $this->leaseToken = null;
    }

    private function apiPrecio(): TiendanubeApiClient
    {
        return $this->api->conTimeout(max(1, (int) config('tiendanube.precio_http_timeout_seconds', 30)));
    }
}
