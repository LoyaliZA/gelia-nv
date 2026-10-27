<?php

namespace Tests\Feature\Tiendanube;

use Illuminate\Support\Str;
use Tests\TestCase;

class TiendanubePrecioLeaseConcurrenciaTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();
        $this->script = base_path('tests/Support/tiendanube_lease_competidor.php');
    }

    public function test_dos_procesos_compiten_por_el_mismo_item(): void
    {
        [$sqlite, $store, $ejecucion] = $this->archivo();
        try {
            $preparado = $this->correr($sqlite, 'preparar', [$ejecucion, (string) $store]);
            $item = (string) $preparado['item_id'];
            $salidas = $this->enParalelo($sqlite, [
                ['reclamar', [$item]],
                ['reclamar', [$item]],
            ]);
            $tokens = array_values(array_filter([
                $salidas[0]['token'] ?? null,
                $salidas[1]['token'] ?? null,
            ]));
            $this->assertCount(1, $tokens);
            $itemFinal = $this->correr($sqlite, 'leer-item', [$item]);
            $this->assertSame('procesando', $itemFinal['estado']);
            $this->assertSame($tokens[0], $itemFinal['lease_token']);
        } finally {
            @unlink($sqlite);
        }
    }

    public function test_el_worker_anterior_no_pisa_al_sucesor_ni_libera_su_tienda(): void
    {
        [$sqlite, $store, $ejecucion] = $this->archivo();
        $viejo = (string) Str::uuid();
        $tienda = (string) Str::uuid();
        try {
            $preparado = $this->correr($sqlite, 'preparar', [$ejecucion, (string) $store]);
            $item = (string) $preparado['item_id'];
            $this->correr($sqlite, 'sembrar', [$item, $viejo, 'vigente']);
            $reemplazo = $this->correr($sqlite, 'reemplazar', [$item, $viejo]);
            $final = $this->correr($sqlite, 'finalizar', [$item, $viejo]);
            $leido = $this->correr($sqlite, 'leer-item', [$item]);

            $this->assertSame(1, $reemplazo['filas']);
            $this->assertFalse($final['ok']);
            $this->assertSame('confirmado', $leido['estado']);
            $this->assertSame($reemplazo['token'], $leido['lease_token']);

            $this->correr($sqlite, 'sembrar-tienda', [(string) $store, $tienda]);
            $this->correr($sqlite, 'liberar-tienda', [(string) $store, $viejo]);
            $operacion = $this->correr($sqlite, 'leer-tienda', [(string) $store]);
            $this->assertSame($tienda, $operacion['lease_token']);
            $this->assertSame('activa', $operacion['estado']);
        } finally {
            @unlink($sqlite);
        }
    }

    public function test_cancelar_y_recuperar_no_dejan_que_el_worker_anterior_sobrescriba(): void
    {
        [$sqlite, $store, $ejecucion] = $this->archivo();
        $viejo = (string) Str::uuid();
        try {
            $preparado = $this->correr($sqlite, 'preparar', [$ejecucion, (string) $store]);
            $item = (string) $preparado['item_id'];
            $this->correr($sqlite, 'sembrar', [$item, $viejo, 'vigente']);
            $this->correr($sqlite, 'detener', [$ejecucion]);
            $final = $this->correr($sqlite, 'finalizar', [$item, $viejo]);
            $leido = $this->correr($sqlite, 'leer-item', [$item]);
            $this->assertFalse($final['ok']);
            $this->assertSame('cancelado', $leido['estado']);
            $this->assertSame('detenido', $leido['error_codigo']);
            $this->assertNull($leido['lease_token']);

            $this->correr($sqlite, 'sembrar', [$item, $viejo, 'vencido']);
            $sucesor = $this->correr($sqlite, 'sucesor', [$item]);
            $this->correr($sqlite, 'recuperar');
            $tardio = $this->correr($sqlite, 'finalizar', [$item, $viejo]);
            $despues = $this->correr($sqlite, 'leer-item', [$item]);
            $this->assertTrue($sucesor['ok']);
            $this->assertNotSame($viejo, $sucesor['token']);
            $this->assertFalse($tardio['ok']);
            $this->assertSame('procesando', $despues['estado']);
            $this->assertSame($sucesor['token'], $despues['lease_token']);
        } finally {
            @unlink($sqlite);
        }
    }

    public function test_la_cancelacion_concurrente_deja_un_estado_coherente(): void
    {
        [$sqlite, $store, $ejecucion] = $this->archivo();
        $viejo = (string) Str::uuid();
        try {
            $preparado = $this->correr($sqlite, 'preparar', [$ejecucion, (string) $store]);
            $item = (string) $preparado['item_id'];
            $this->correr($sqlite, 'sembrar', [$item, $viejo, 'vigente']);
            $this->enParalelo($sqlite, [
                ['detener', [$ejecucion]],
                ['finalizar', [$item, $viejo]],
            ]);
            $leido = $this->correr($sqlite, 'leer-item', [$item]);
            $this->assertContains($leido['estado'], ['cancelado', 'fallido']);
            if ($leido['estado'] === 'cancelado') {
                $this->assertSame('detenido', $leido['error_codigo']);
            } else {
                $this->assertSame('tarde', $leido['error_codigo']);
            }
            $this->assertNull($leido['lease_token']);
        } finally {
            @unlink($sqlite);
        }
    }

    public function test_un_worker_antiguo_no_libera_la_reserva_ni_el_lock_del_sucesor(): void
    {
        $sqlite = $this->sqliteVacio();
        $prefijo = 'tn-lease-'.bin2hex(random_bytes(4));
        $store = random_int(800_000_000, 899_999_999);
        $operacion = 'precio:e2c';
        $dueno = (string) Str::uuid();
        $ajeno = (string) Str::uuid();
        try {
            $tomada = $this->correr($sqlite, 'reserva-tomar', [$prefijo, (string) $store, $operacion, $dueno]);
            $this->assertTrue($tomada['concedido'], (string) ($tomada['motivo'] ?? ''));
            $this->correr($sqlite, 'reserva-liberar', [$prefijo, (string) $store, $operacion, $ajeno]);
            $estado = $this->correr($sqlite, 'reserva-estado', [$prefijo, (string) $store, $operacion]);
            $this->assertTrue($estado['pendiente']);

            $dir = sys_get_temp_dir().'/tn-lease-lock-'.bin2hex(random_bytes(4));
            mkdir($dir);
            $cache = 'tn-lock-'.bin2hex(random_bytes(4));
            $clave = 'tiendanube:recurso:'.$store.':product:1';
            $toma = $this->abrir($sqlite, 'lock-tomar', [$cache, $clave, $dir]);
            $this->esperarArchivo($dir.'/held');
            $liberacion = $this->correr($sqlite, 'lock-liberar', [$cache, $clave, $dir]);
            $intento = $this->correr($sqlite, 'lock-intentar', [$cache, $clave, $dir]);
            $this->assertFalse($liberacion['libero']);
            $this->assertFalse($intento['obtuvo']);
            file_put_contents($dir.'/soltar', '1');
            $this->cerrar($toma);
            $this->esperarArchivo($dir.'/released');
            $libre = $this->correr($sqlite, 'lock-intentar', [$cache, $clave, $dir]);
            $this->assertTrue($libre['obtuvo']);
            @unlink($dir.'/held');
            @unlink($dir.'/soltar');
            @unlink($dir.'/released');
            @rmdir($dir);
        } finally {
            $this->correr($sqlite, 'reserva-olvidar', [$prefijo]);
            @unlink($sqlite);
        }
    }

    /**
     * @return array{0: string, 1: int, 2: string}
     */
    private function archivo(): array
    {
        return [$this->sqliteVacio(), random_int(800_000_000, 899_999_999), (string) Str::uuid()];
    }

    private function sqliteVacio(): string
    {
        $ruta = sys_get_temp_dir().'/tn-lease-'.bin2hex(random_bytes(6)).'.sqlite';
        touch($ruta);

        return $ruta;
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function correr(string $sqlite, string $accion, array $args = []): array
    {
        $abierto = $this->abrir($sqlite, $accion, $args);
        $json = $this->cerrar($abierto);
        $this->assertIsArray($json);

        return $json;
    }

    /**
     * @param  list<string>  $args
     * @return array{proceso: resource, tubos: array<int, resource>}
     */
    private function abrir(string $sqlite, string $accion, array $args): array
    {
        $pipes = [];
        $proceso = proc_open(
            [PHP_BINARY, $this->script, $accion, $sqlite, ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertIsResource($proceso);

        return ['proceso' => $proceso, 'tubos' => $pipes];
    }

    /**
     * @param  array{proceso: resource, tubos: array<int, resource>}  $abierto
     * @return array<string, mixed>
     */
    private function cerrar(array $abierto): array
    {
        $salida = stream_get_contents($abierto['tubos'][1]);
        $error = stream_get_contents($abierto['tubos'][2]);
        fclose($abierto['tubos'][1]);
        fclose($abierto['tubos'][2]);
        $codigo = proc_close($abierto['proceso']);
        $this->assertSame(0, $codigo, $error.$salida);
        $lineas = array_values(array_filter(array_map('trim', explode("\n", (string) $salida))));
        $json = json_decode((string) ($lineas === [] ? '' : $lineas[array_key_last($lineas)]), true);
        $this->assertIsArray($json, $error.$salida);

        return $json;
    }

    /**
     * @param  list<array{0: string, 1: list<string>}>  $tareas
     * @return list<array<string, mixed>>
     */
    private function enParalelo(string $sqlite, array $tareas): array
    {
        $abiertos = [];
        foreach ($tareas as $tarea) {
            $abiertos[] = $this->abrir($sqlite, $tarea[0], $tarea[1]);
        }
        $salidas = [];
        foreach ($abiertos as $abierto) {
            $salidas[] = $this->cerrar($abierto);
        }

        return $salidas;
    }

    private function esperarArchivo(string $ruta): void
    {
        $limite = microtime(true) + 10;
        while (! is_file($ruta) && microtime(true) < $limite) {
            usleep(50_000);
        }
        $this->assertFileExists($ruta);
    }
}
