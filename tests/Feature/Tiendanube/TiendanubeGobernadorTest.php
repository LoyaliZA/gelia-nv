<?php

namespace Tests\Feature\Tiendanube;

use App\Services\Tiendanube\Gobernador\TiendanubeEnrutadorColas;
use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use App\Support\Tiendanube\TiendanubeClaseTurno;
use Tests\TestCase;

class TiendanubeGobernadorTest extends TestCase
{
    public function test_una_clase_sigue_despues_de_la_ventana_si_paso_el_intervalo(): void
    {
        [$gobernador, $store] = $this->gobernador();
        try {
            $gobernador->anunciarDemanda($store, 'publicacion', 'lote-publicacion');
            $inicio = 1_700_000_000_000;
            $intervalo = 1000;
            for ($n = 0; $n < 10; $n++) {
                $turno = $gobernador->adquirir(
                    $store,
                    'publicacion',
                    'intento',
                    'ventana-'.$n,
                    'sin_escritura',
                    $intervalo,
                    $inicio + ($n * $intervalo),
                );
                $this->assertTrue($turno->concedido, 'turno '.$n);
            }

            $bloqueado = $gobernador->adquirir(
                $store,
                'publicacion',
                'intento',
                'ventana-temprano',
                'sin_escritura',
                $intervalo,
                $inicio + (10 * $intervalo) - 1,
            );
            $this->assertFalse($bloqueado->concedido);

            for ($n = 10; $n < 25; $n++) {
                $turno = $gobernador->adquirir(
                    $store,
                    'publicacion',
                    'intento',
                    'ventana-'.$n,
                    'sin_escritura',
                    $intervalo,
                    $inicio + ($n * $intervalo),
                );
                $this->assertTrue($turno->concedido, 'turno '.$n);
            }
            $this->assertCount(10, $gobernador->ventana($store));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_una_sola_clase_usa_la_ventana_completa(): void
    {
        [$gobernador, $store] = $this->gobernador();
        try {
            $gobernador->anunciarDemanda($store, 'publicacion', 'lote-publicacion');
            for ($n = 0; $n < 10; $n++) {
                $turno = $gobernador->adquirir($store, 'publicacion', 'intento', 'solo-'.$n, 'sin_escritura', 0);
                $this->assertTrue($turno->concedido);
            }
            $this->assertCount(10, $gobernador->ventana($store));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_interactivo_sostenido_deja_cupo_a_publicacion(): void
    {
        [$gobernador, $store] = $this->gobernador();
        try {
            $gobernador->anunciarDemanda($store, 'interactivo', 'ui-interactiva');
            $gobernador->anunciarDemanda($store, 'publicacion', 'lote-publicacion');

            $interactivo = 0;
            while ($interactivo <= 10) {
                $turno = $gobernador->adquirir($store, 'interactivo', 'intento', 'mono-'.$interactivo, 'sin_escritura', 0);
                if (! $turno->concedido) {
                    break;
                }
                $interactivo++;
            }

            $this->assertSame(3, $interactivo);
            $this->assertSame('sin_presupuesto_local', $gobernador->espera($store, 'mono-'.$interactivo)['motivo']);

            $guardia = 0;
            while (count($gobernador->ventana($store)) < 10 && $guardia < 40) {
                $guardia++;
                $conteo = array_count_values($gobernador->ventana($store));
                $primero = ($conteo['interactivo'] ?? 0) <= ($conteo['publicacion'] ?? 0) ? 'interactivo' : 'publicacion';
                $segundo = $primero === 'interactivo' ? 'publicacion' : 'interactivo';
                $turno = $gobernador->adquirir($store, $primero, 'intento', 'reparto-'.$guardia, 'sin_escritura', 0);
                if (! $turno->concedido) {
                    $turno = $gobernador->adquirir($store, $segundo, 'intento', 'reparto-b-'.$guardia, 'sin_escritura', 0);
                }
                $this->assertTrue($turno->concedido);
            }

            $conteo = array_count_values($gobernador->ventana($store));
            $this->assertCount(10, $gobernador->ventana($store));
            $this->assertGreaterThanOrEqual(3, $conteo['interactivo']);
            $this->assertGreaterThanOrEqual(3, $conteo['publicacion']);
            $this->assertLessThanOrEqual(6, $conteo['interactivo']);
            $this->assertLessThanOrEqual(6, $conteo['publicacion']);
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_la_comprobacion_de_vigencia_no_agota_el_turno_del_put(): void
    {
        [$gobernador, $store] = $this->gobernador();
        try {
            $gobernador->anunciarDemanda($store, 'publicacion', 'lote-publicacion');
            $this->assertTrue($gobernador->reservarEscritura($store, 'publicacion', 'precio:1', 'sin_escritura', 0)->concedido);
            $this->assertTrue($gobernador->adquirir($store, 'publicacion', 'comprobacion_vigencia', 'precio:1', 'lectura_hecha', 0)->concedido);

            for ($n = 0; $n < 4; $n++) {
                $extra = $gobernador->adquirir($store, 'publicacion', 'comprobacion_vigencia', 'precio:1', 'lectura_hecha', 0);
                $this->assertFalse($extra->concedido);
                $this->assertSame('sin_presupuesto_local', $extra->motivo);
                $this->assertSame('lectura_hecha', $extra->fase);
            }

            $put = $gobernador->adquirir($store, 'publicacion', 'intento', 'precio:1', 'escritura_enviada', 0);
            $this->assertTrue($put->concedido);
            $this->assertSame('escritura_enviada', $put->fase);
            $this->assertSame(['publicacion'], $gobernador->ventana($store));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_la_espera_conserva_la_fase_y_distingue_429_de_presupuesto_local(): void
    {
        [$gobernador, $store] = $this->gobernador();
        try {
            $ahora = 1_700_000_000_000;
            $this->assertTrue($gobernador->adquirir($store, 'catalogo', 'intento', 'op-tiempo', 'sin_escritura', 60_000, $ahora)->concedido);
            $espera = $gobernador->adquirir($store, 'catalogo', 'intento', 'op-tiempo', 'lectura_hecha', 60_000, $ahora + 10);
            $this->assertFalse($espera->concedido);
            $this->assertSame('sin_presupuesto_local', $espera->motivo);
            $this->assertSame('lectura_hecha', $espera->fase);
            $this->assertSame('lectura_hecha', $gobernador->espera($store, 'op-tiempo')['fase']);

            $remoto = $gobernador->registrarLimiteRemoto($store, 25_000, 'op-429', 'escritura_enviada', $ahora);
            $this->assertSame('rate_limit', $remoto->motivo);
            $this->assertSame($ahora + 25_000, $gobernador->espera($store, 'op-429')['reanudar_despues_at']);
            $this->assertSame('escritura_enviada', $gobernador->espera($store, 'op-429')['fase']);
            $durante = $gobernador->adquirir($store, 'catalogo', 'intento', 'op-429', 'escritura_enviada', 0, $ahora + 10);
            $this->assertFalse($durante->concedido);
            $this->assertSame('rate_limit', $durante->motivo);
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_revertir_a_default_cambia_el_enrutado_y_conserva_evidencia(): void
    {
        [$gobernador, $store] = $this->gobernador();
        $enrutador = app(TiendanubeEnrutadorColas::class);
        config(['tiendanube.colas_dedicadas' => true]);
        try {
            $gobernador->guardarEvidencia($store, 'op-rev', 'escritura_enviada', ['reconciliacion' => true]);
            $this->assertSame('tiendanube', $enrutador->conexion());
            $this->assertSame('tiendanube_publicacion', $enrutador->cola(TiendanubeClaseTurno::Publicacion));
            $gobernador->revertirRecorridoADefault();
            $this->assertFalse((bool) config('tiendanube.colas_dedicadas'));
            $this->assertSame('default', $enrutador->cola(TiendanubeClaseTurno::Publicacion));
            $this->assertNotSame('tiendanube', $enrutador->conexion());
            $espera = $gobernador->espera($store, 'op-rev');
            $this->assertSame('escritura_enviada', $espera['fase']);
            $this->assertTrue($espera['evidencia']['reconciliacion']);
            $this->assertTrue($gobernador->adquirir($store, 'publicacion', 'intento', 'op-rev-sigue', 'escritura_enviada', 0)->concedido);
        } finally {
            config(['tiendanube.colas_dedicadas' => false]);
            $gobernador->olvidar();
        }
    }

    public function test_la_demanda_aparece_desaparece_y_la_vencida_no_reserva_cupo(): void
    {
        [$gobernador, $store] = $this->gobernador();
        $ahora = 1_700_000_000_000;
        try {
            $gobernador->anunciarDemanda($store, 'interactivo', 'ui', $ahora + 50_000, $ahora);
            $gobernador->anunciarDemanda($store, 'publicacion', 'lote', $ahora + 50_000, $ahora);
            $this->assertEqualsCanonicalizing(
                ['interactivo', 'publicacion'],
                $gobernador->clasesConDemanda($store, $ahora),
            );

            $interactivo = 0;
            while ($interactivo <= 10) {
                $turno = $gobernador->adquirir($store, 'interactivo', 'intento', 'd-'.$interactivo, 'sin_escritura', 0, $ahora);
                if (! $turno->concedido) {
                    break;
                }
                $interactivo++;
            }
            $this->assertSame(3, $interactivo);

            $gobernador->retirarDemanda($store, 'interactivo', 'ui', $ahora);
            $this->assertSame(['publicacion'], $gobernador->clasesConDemanda($store, $ahora));
            for ($n = 0; $n < 10; $n++) {
                $this->assertTrue($gobernador->adquirir($store, 'publicacion', 'intento', 'p-'.$n, 'sin_escritura', 0, $ahora)->concedido);
            }

            $gobernador->anunciarDemanda($store, 'catalogo', 'sync-viejo', $ahora - 1, $ahora);
            $this->assertNotContains('catalogo', $gobernador->clasesConDemanda($store, $ahora));
        } finally {
            $gobernador->olvidar();
        }
    }

    public function test_dos_procesos_no_obtienen_el_mismo_turno(): void
    {
        [$gobernador, $store, $prefijo] = $this->gobernador(true);
        try {
            $script = base_path('tests/Support/tiendanube_gobernador_competidor.php');
            $procesos = [];
            $tubos = [];
            foreach (['a', 'b'] as $marca) {
                $pipes = [];
                $proceso = proc_open(
                    [PHP_BINARY, $script, $prefijo, (string) $store, '60000', 'carrera-'.$marca],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
                $this->assertIsResource($proceso);
                $procesos[] = $proceso;
                $tubos[] = $pipes;
            }

            $concedidos = 0;
            $detalle = '';
            foreach ($procesos as $indice => $proceso) {
                $salida = stream_get_contents($tubos[$indice][1]);
                $error = stream_get_contents($tubos[$indice][2]);
                fclose($tubos[$indice][1]);
                fclose($tubos[$indice][2]);
                $codigo = proc_close($proceso);
                $detalle .= $error.$salida;
                $this->assertSame(0, $codigo, $detalle);
                $lineas = array_values(array_filter(array_map('trim', explode("\n", (string) $salida))));
                $json = json_decode((string) ($lineas === [] ? '' : $lineas[array_key_last($lineas)]), true);
                $concedidos += is_array($json) && ! empty($json['concedido']) ? 1 : 0;
            }
            $this->assertSame(1, $concedidos, $detalle);
        } finally {
            $gobernador->olvidar();
        }
    }

    /**
     * @return array{0: TiendanubeGobernador, 1: int, 2?: string}
     */
    private function gobernador(bool $conPrefijo = false): array
    {
        $prefijo = 'tn-gov-test-'.bin2hex(random_bytes(4));
        $store = random_int(100_000, 999_999);
        $gobernador = TiendanubeGobernador::conectarPrueba($prefijo);

        return $conPrefijo ? [$gobernador, $store, $prefijo] : [$gobernador, $store];
    }
}
