<?php

namespace App\Services\Tiendanube\Gobernador;

use Redis;

/**
 * Presupuesto compartido por tienda. gobernador_activo queda en falso: este servicio
 * no enruta colas. La demora durable pertenece a TiendanubeReprogramador (E2b).
 *
 * ponytail: una reserva pendiente sobrevive si el proceso muere entre reservar y
 * finalizar. E2c puede vencerla con el lease; mientras tanto el reintento reutiliza
 * esa reserva y no cobra un segundo turno de clase.
 */
class TiendanubeGobernador
{
    private ?TiendanubeAlmacenTurnosRedis $almacen;

    private ?int $ahoraFijo = null;

    public function __construct(?TiendanubeAlmacenTurnosRedis $almacen = null)
    {
        $this->almacen = $almacen;
    }

    public static function conectarPrueba(string $prefijo): self
    {
        $redis = new Redis;
        $host = (string) config('database.redis.default.host', '127.0.0.1');
        $port = (int) config('database.redis.default.port', 6379);
        $redis->connect($host, $port, 2.0);
        $password = config('database.redis.default.password');
        if (is_string($password) && $password !== '') {
            $redis->auth($password);
        }
        $redis->select(15);

        return new self(new TiendanubeAlmacenTurnosRedis($redis, $prefijo));
    }

    public function fijarAhora(int $milisegundos): void
    {
        $this->ahoraFijo = $milisegundos;
    }

    public function marcaTemporal(): int
    {
        return $this->ahora(null);
    }

    public function anunciarDemanda(int $storeId, string $clase, string $operacionId, ?int $venceEn = null, ?int $ahoraMs = null): void
    {
        $ahora = $this->ahora($ahoraMs);
        $vence = $venceEn ?? ($ahora + $this->ttlDemanda());
        $this->almacen()->evaluar(
            $this->clave($storeId),
            'anunciar',
            $ahora,
            0,
            $clase,
            '',
            $operacionId,
            '',
            (string) $vence,
        );
    }

    public function retirarDemanda(int $storeId, string $clase, string $operacionId, ?int $ahoraMs = null): void
    {
        $this->almacen()->evaluar(
            $this->clave($storeId),
            'retirar',
            $this->ahora($ahoraMs),
            0,
            $clase,
            '',
            $operacionId,
            '',
        );
    }

    /**
     * @return list<string>
     */
    public function clasesConDemanda(int $storeId, ?int $ahoraMs = null): array
    {
        $lista = $this->almacen()->evaluar(
            $this->clave($storeId),
            'clases',
            $this->ahora($ahoraMs),
            0,
            '',
            '',
            '',
            '',
        );

        return array_values(array_map('strval', $lista));
    }

    /**
     * @return array{pendiente: bool, vigencias: int}
     */
    public function estadoReserva(int $storeId, string $operacionId, ?int $ahoraMs = null): array
    {
        $fila = $this->almacen()->evaluar(
            $this->clave($storeId),
            'estado',
            $this->ahora($ahoraMs),
            0,
            '',
            '',
            $operacionId,
            '',
        );

        return [
            'pendiente' => (string) ($fila[0] ?? '0') === '1',
            'vigencias' => (int) ($fila[1] ?? 0),
        ];
    }

    public function reservarEscritura(int $storeId, string $clase, string $operacionId, string $fase, ?int $intervaloMs = null, ?int $ahoraMs = null): TiendanubeTurnoResultado
    {
        return $this->resultado('reservar', $storeId, $clase, 'intento', $operacionId, $fase, $intervaloMs, $ahoraMs);
    }

    public function adquirir(int $storeId, string $clase, string $proposito, string $operacionId, string $fase, ?int $intervaloMs = null, ?int $ahoraMs = null): TiendanubeTurnoResultado
    {
        return $this->resultado('adquirir', $storeId, $clase, $proposito, $operacionId, $fase, $intervaloMs, $ahoraMs);
    }

    public function registrarLimiteRemoto(int $storeId, int $resetMs, string $operacionId, string $fase, ?int $ahoraMs = null): TiendanubeTurnoResultado
    {
        $ahora = $this->ahora($ahoraMs);
        $fila = $this->almacen()->evaluar(
            $this->clave($storeId),
            'limite',
            $ahora,
            0,
            '',
            '',
            $operacionId,
            $fase,
            (string) max(0, $resetMs),
        );

        return $this->interpretar($fila, $operacionId, '');
    }

    /**
     * @param  array<string, mixed>  $evidencia
     */
    public function guardarEvidencia(int $storeId, string $operacionId, string $fase, array $evidencia): void
    {
        $this->almacen()->evaluar(
            $this->clave($storeId),
            'evidencia',
            $this->ahora(null),
            0,
            '',
            '',
            $operacionId,
            $fase,
            json_encode($evidencia, JSON_THROW_ON_ERROR),
        );
    }

    public function liberarReserva(int $storeId, string $operacionId): void
    {
        $this->almacen()->evaluar($this->clave($storeId), 'liberar', $this->ahora(null), 0, '', '', $operacionId, '');
    }

    /**
     * @return list<string>
     */
    public function ventana(int $storeId): array
    {
        $lista = $this->almacen()->evaluar($this->clave($storeId), 'ventana', $this->ahora(null), 0, '', '', '', '');

        return array_values(array_map(function (mixed $item): string {
            $texto = (string) $item;
            $corte = strpos($texto, '|');

            return $corte === false ? $texto : substr($texto, 0, $corte);
        }, $lista));
    }

    /**
     * @return array{fase: string, motivo: string, reanudar_despues_at: int, evidencia: array<string, mixed>}
     */
    public function espera(int $storeId, string $operacionId): array
    {
        $datos = $this->almacen()->espera($this->clave($storeId), $operacionId);
        $evidencia = [];
        if (isset($datos['evidencia']) && $datos['evidencia'] !== '') {
            $decodificada = json_decode($datos['evidencia'], true);
            $evidencia = is_array($decodificada) ? $decodificada : [];
        }

        return [
            'fase' => (string) ($datos['fase'] ?? ''),
            'motivo' => (string) ($datos['motivo'] ?? ''),
            'reanudar_despues_at' => (int) ($datos['reanudar_despues_at'] ?? 0),
            'evidencia' => $evidencia,
        ];
    }

    public function revertirRecorridoADefault(): void
    {
        config(['tiendanube.colas_dedicadas' => false]);
    }

    public function olvidar(): void
    {
        $this->almacen()->olvidar();
    }

    private function almacen(): TiendanubeAlmacenTurnosRedis
    {
        if ($this->almacen instanceof TiendanubeAlmacenTurnosRedis) {
            return $this->almacen;
        }

        $conexion = \Illuminate\Support\Facades\Redis::connection()->client();
        $this->almacen = new TiendanubeAlmacenTurnosRedis($conexion, 'tn-gov');

        return $this->almacen;
    }

    private function resultado(string $accion, int $storeId, string $clase, string $proposito, string $operacionId, string $fase, ?int $intervaloMs, ?int $ahoraMs): TiendanubeTurnoResultado
    {
        $fila = $this->almacen()->evaluar(
            $this->clave($storeId),
            $accion,
            $this->ahora($ahoraMs),
            $this->intervalo($intervaloMs),
            $clase,
            $proposito,
            $operacionId,
            $fase,
        );

        return $this->interpretar($fila, $operacionId, $clase);
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function interpretar(array $fila, string $operacionId, string $clase): TiendanubeTurnoResultado
    {
        return new TiendanubeTurnoResultado(
            concedido: (int) ($fila[0] ?? 0) === 1,
            motivo: (string) ($fila[1] ?? ''),
            reanudarDespuesAt: (int) ($fila[2] ?? 0),
            fase: (string) ($fila[3] ?? ''),
            operacionId: $operacionId,
            clase: $clase,
        );
    }

    private function clave(int $storeId): string
    {
        return 'tienda:'.$storeId;
    }

    private function ahora(?int $ahoraMs): int
    {
        if ($ahoraMs !== null) {
            return $ahoraMs;
        }

        return $this->ahoraFijo ?? (int) floor(microtime(true) * 1000);
    }

    private function ttlDemanda(): int
    {
        return max(1000, (int) config('tiendanube.gobernador_demanda_ttl_ms', 120_000));
    }

    private function intervalo(?int $intervaloMs): int
    {
        if ($intervaloMs !== null) {
            return max(0, $intervaloMs);
        }

        return max(0, (int) config('tiendanube.gobernador_intervalo_ms', 1000));
    }
}
