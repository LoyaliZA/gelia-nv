<?php

namespace Tests\Unit\Services\Tiendanube\Precios;

use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCalculoService;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCondicionDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCondicionEvaluador;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorPoliticaDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorRedondeoService;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorSnapshotDto;
use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioCondicionOperador;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoDireccion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoModo;
use PHPUnit\Framework\TestCase;
use Tests\Support\TiendanubePrecioFuenteFixtures;
use Tests\Support\TiendanubePrecioMotorFixtures;

class TiendanubePrecioMotorCalculoServiceTest extends TestCase
{
    private TiendanubePrecioMotorCalculoService $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor = new TiendanubePrecioMotorCalculoService;
    }

    public function test_costo_330_aumento_30_por_ciento(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('429.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame(TiendanubePrecioMotorCalculoService::MOTOR_VERSION, $resultado->motorVersion);
    }

    public function test_costo_330_margen_30_por_ciento(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'margen_objetivo', 'normal', '30')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('471.43', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_costo_330_aumento_75_por_ciento(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '75')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('577.50', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_normal_90_descuento_6_por_ciento_a_promocion(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotNormal('90.00'),
            [TiendanubePrecioMotorFixtures::regla('r1', 'precio_normal_actual', 'reducir_porcentaje', 'promocional', '6')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('84.60', $resultado->campo(TiendanubePrecioDestino::Promocional)->valorFinal);
        $this->assertSame('90.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame(TiendanubePrecioIntencion::Conservar, $resultado->campo(TiendanubePrecioDestino::Normal)->intencion);
    }

    public function test_normal_1600_aumento_12_por_ciento(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotNormal('1600.00'),
            [TiendanubePrecioMotorFixtures::regla('r1', 'precio_normal_actual', 'aumentar_porcentaje', 'normal', '12')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('1792.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_base_577_50_reduccion_10_por_ciento(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotNormal('577.50'),
            [TiendanubePrecioMotorFixtures::regla('r1', 'precio_normal_actual', 'reducir_porcentaje', 'normal', '10')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('519.75', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_condicion_menor_100_y_mayor_1500_no_coinciden_en_extremos(): void
    {
        $menor = TiendanubePrecioMotorFixtures::regla(
            'r-menor',
            'precio_normal_actual',
            'aumentar_porcentaje',
            'normal',
            '12',
            [['campo' => 'precio_normal_actual', 'operador' => '<', 'valor' => '100']]
        );
        $mayor = TiendanubePrecioMotorFixtures::regla(
            'r-mayor',
            'precio_normal_actual',
            'aumentar_porcentaje',
            'normal',
            '12',
            [['campo' => 'precio_normal_actual', 'operador' => '>', 'valor' => '1500']]
        );
        $ambas = TiendanubePrecioMotorFixtures::regla(
            'r-and',
            'precio_normal_actual',
            'aumentar_porcentaje',
            'normal',
            '12',
            [
                ['campo' => 'precio_normal_actual', 'operador' => '<', 'valor' => '100'],
                ['campo' => 'precio_normal_actual', 'operador' => '>', 'valor' => '1500'],
            ]
        );

        $enCien = $this->motor->calcular(TiendanubePrecioMotorFixtures::snapshotNormal('100.00'), [$menor]);
        $enMilQuinientos = $this->motor->calcular(TiendanubePrecioMotorFixtures::snapshotNormal('1500.00'), [$mayor]);
        $andNunca = $this->motor->calcular(TiendanubePrecioMotorFixtures::snapshotNormal('100.00'), [$ambas]);

        $this->assertSame(TiendanubePrecioIntencion::Conservar, $enCien->campo(TiendanubePrecioDestino::Normal)->intencion);
        $this->assertSame('100.00', $enCien->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame(TiendanubePrecioIntencion::Conservar, $enMilQuinientos->campo(TiendanubePrecioDestino::Normal)->intencion);
        $this->assertSame('1500.00', $enMilQuinientos->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame(TiendanubePrecioIntencion::Conservar, $andNunca->campo(TiendanubePrecioDestino::Normal)->intencion);
    }

    public function test_577_50_redondeo_arriba_a_99(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla(
                'r1',
                'costo_local',
                'aumentar_porcentaje',
                'normal',
                '75',
                [],
                'terminacion_99',
                'arriba'
            )]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('577.5000', $resultado->campo(TiendanubePrecioDestino::Normal)->valorBruto);
        $this->assertSame('577.99', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_fuente_ausente_sin_resultado_publicable(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshot([
            TiendanubePrecioFuenteFixtures::costoLocalAusente(),
        ]);
        $resultado = $this->motor->calcular(
            $snapshot,
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')]
        );

        $this->assertFalse($resultado->publicable);
        $this->assertContains('fuente_ausente', $resultado->errores);
        $this->assertNull($resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_margen_100_por_ciento_sin_resultado_publicable(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'margen_objetivo', 'normal', '100')]
        );

        $this->assertFalse($resultado->publicable);
        $this->assertTrue(
            in_array('regla:r1:margen_invalido', $resultado->errores, true)
            || in_array('margen_invalido', $resultado->errores, true)
        );
        $this->assertSame(TiendanubePrecioIntencion::Conservar, $resultado->campo(TiendanubePrecioDestino::Normal)->intencion);
    }

    public function test_misma_entrada_misma_salida_y_recalcular_no_acumula(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshotCosto330();
        $reglas = [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')];

        $a = $this->motor->calcular($snapshot, $reglas);
        $b = $this->motor->calcular($snapshot, $reglas);
        $c = $this->motor->calcular($snapshot, $reglas);

        $this->assertSame($a->toArray(), $b->toArray());
        $this->assertSame($a->toArray(), $c->toArray());
        $this->assertSame('429.00', $c->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_condicion_base_y_destino_independientes(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshotCosto330('90.00');
        $regla = TiendanubePrecioMotorFixtures::regla(
            'r1',
            'costo_local',
            'aumentar_porcentaje',
            'normal',
            '10',
            [['campo' => 'precio_normal_actual', 'operador' => '<', 'valor' => '100']]
        );

        $resultado = $this->motor->calcular($snapshot, [$regla]);

        $this->assertTrue($resultado->publicable);
        $this->assertSame('363.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame('r1', $resultado->campo(TiendanubePrecioDestino::Normal)->reglaId);
    }

    public function test_conflicto_dos_reglas_mismo_destino(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [
                TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30'),
                TiendanubePrecioMotorFixtures::regla('r2', 'costo_local', 'aumentar_porcentaje', 'normal', '30'),
            ]
        );

        $this->assertFalse($resultado->publicable);
        $this->assertContains('conflicto', $resultado->errores);
        $this->assertNull($resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_reglas_a_campos_distintos_conviven(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330('90.00'),
            [
                TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30'),
                TiendanubePrecioMotorFixtures::regla('r2', 'precio_normal_actual', 'reducir_porcentaje', 'promocional', '6'),
            ]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertSame('429.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame('84.60', $resultado->campo(TiendanubePrecioDestino::Promocional)->valorFinal);
    }

    public function test_cambiar_normal_conserva_promocion_y_bloquea_si_queda_invalida(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotNormal('200.00', null, '150.00'),
            [TiendanubePrecioMotorFixtures::regla('r1', 'precio_normal_actual', 'reducir_porcentaje', 'normal', '30')]
        );

        $this->assertFalse($resultado->publicable);
        $this->assertContains('promocional_invalido', $resultado->errores);
        $this->assertSame('140.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
        $this->assertSame('150.00', $resultado->campo(TiendanubePrecioDestino::Promocional)->valorFinal);
        $this->assertSame(TiendanubePrecioIntencion::Conservar, $resultado->campo(TiendanubePrecioDestino::Promocional)->intencion);
    }

    public function test_costo_cero_aumento_porcentual_no_es_finito(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshot([
            TiendanubePrecioFuenteFixtures::costoLocalCero(),
        ]);
        $resultado = $this->motor->calcular(
            $snapshot,
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')]
        );

        $this->assertFalse($resultado->publicable);
        $this->assertContains('aumento_sobre_cero', $resultado->errores);
    }

    public function test_proteccion_margen_bloquea_sin_ajustar(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotCosto330(),
            [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')],
            new TiendanubePrecioMotorPoliticaDto(true, '50')
        );

        $this->assertFalse($resultado->publicable);
        $this->assertContains('proteccion_margen', $resultado->errores);
        $this->assertSame('429.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_sin_costo_margen_no_calculable(): void
    {
        $resultado = $this->motor->calcular(
            TiendanubePrecioMotorFixtures::snapshotNormal('90.00'),
            [TiendanubePrecioMotorFixtures::regla('r1', 'precio_normal_actual', 'copiar', 'normal')]
        );

        $this->assertTrue($resultado->publicable);
        $this->assertFalse($resultado->margenCalculable);
        $this->assertNull($resultado->margenEstimado);
        $this->assertNull($resultado->costoUsado);
    }

    public function test_motor_no_muta_entradas(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshotCosto330('90.00');
        $reglas = [TiendanubePrecioMotorFixtures::regla('r1', 'costo_local', 'aumentar_porcentaje', 'normal', '30')];
        $snapshotOriginal = $snapshot;
        $reglasOriginal = $reglas;

        $this->motor->calcular($snapshot, $reglas);
        $snapshot['fuentes'][0]['valor_decimal'] = '999.00';
        $reglas[0]['parametro'] = '99';

        $this->assertSame('330.00', $snapshotOriginal['fuentes'][0]['valor_decimal']);
        $this->assertSame('30', $reglasOriginal[0]['parametro']);

        $otra = $this->motor->calcular($snapshotOriginal, $reglasOriginal);
        $this->assertSame('429.00', $otra->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }

    public function test_terminacion_99_exacta_desempate_hacia_arriba(): void
    {
        $redondeo = new TiendanubePrecioMotorRedondeoService;
        $this->assertSame(
            '578.99',
            $redondeo->aplicar('577.99', TiendanubePrecioRedondeoModo::Terminacion99, TiendanubePrecioRedondeoDireccion::Arriba)
        );
    }

    public function test_condiciones_entre_inclusivo_y_exclusivo(): void
    {
        $evaluador = new TiendanubePrecioMotorCondicionEvaluador;
        $snapshot = TiendanubePrecioMotorSnapshotDto::fromArray(
            TiendanubePrecioMotorFixtures::snapshotNormal('100.00')
        );

        $inclusivo = TiendanubePrecioMotorCondicionDto::fromArray([
            'campo' => 'precio_normal_actual',
            'operador' => 'entre',
            'valor' => '100',
            'valor_hasta' => '1500',
            'inclusivo_desde' => true,
            'inclusivo_hasta' => true,
        ]);
        $exclusivo = TiendanubePrecioMotorCondicionDto::fromArray([
            'campo' => 'precio_normal_actual',
            'operador' => 'entre',
            'valor' => '100',
            'valor_hasta' => '1500',
            'inclusivo_desde' => false,
            'inclusivo_hasta' => true,
        ]);

        $this->assertTrue($evaluador->cumpleUna($inclusivo, $snapshot));
        $this->assertFalse($evaluador->cumpleUna($exclusivo, $snapshot));
        $this->assertSame(TiendanubePrecioCondicionOperador::Entre, $inclusivo->operador);
        $this->assertSame(TiendanubePrecioCampoCondicion::PrecioNormalActual, $inclusivo->campo);
    }

    public function test_tiene_valor_distingue_cero_de_ausencia(): void
    {
        $evaluador = new TiendanubePrecioMotorCondicionEvaluador;
        $cero = TiendanubePrecioMotorSnapshotDto::fromArray(TiendanubePrecioMotorFixtures::snapshot([
            TiendanubePrecioFuenteFixtures::costoLocalCero(),
        ]));
        $ausente = TiendanubePrecioMotorSnapshotDto::fromArray(TiendanubePrecioMotorFixtures::snapshot([
            TiendanubePrecioFuenteFixtures::costoLocalAusente(),
        ]));
        $tiene = TiendanubePrecioMotorCondicionDto::fromArray([
            'campo' => 'costo_local',
            'operador' => 'tiene_valor',
        ]);

        $this->assertTrue($evaluador->cumpleUna($tiene, $cero));
        $this->assertFalse($evaluador->cumpleUna($tiene, $ausente));
    }

    public function test_lista_referencia_como_base(): void
    {
        $snapshot = TiendanubePrecioMotorFixtures::snapshot([
            TiendanubePrecioFuenteFixtures::listaReferencia(3, '200.00'),
        ]);
        $regla = TiendanubePrecioMotorFixtures::regla(
            'r1',
            'lista_referencia',
            'copiar',
            'normal',
            null,
            [],
            'dos_decimales_half_up',
            'arriba',
            3
        );

        $resultado = $this->motor->calcular($snapshot, [$regla]);
        $this->assertTrue($resultado->publicable);
        $this->assertSame('200.00', $resultado->campo(TiendanubePrecioDestino::Normal)->valorFinal);
    }
}
