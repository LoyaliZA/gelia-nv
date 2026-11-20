<?php

namespace Tests\Feature\ControlPedidos;

use App\Support\ControlPedidos\UnidadesRevisionConsulta;
use Tests\TestCase;

class UnidadesRevisionConsultaTest extends TestCase
{
    public function test_lineas_nuevas_comparten_solicitud_documento_y_sku_sin_compartir_identidad(): void
    {
        $base = ['solicitud_id' => 'sol-compartida', 'documento_id' => 71, 'sku' => 'MISMO', 'pendiente_revision' => true];
        $a = UnidadesRevisionConsulta::nuevaLineaTienda($base);
        $b = UnidadesRevisionConsulta::nuevaLineaTienda($base);
        $this->assertNotSame($a['linea_id'], $b['linea_id']);
        $this->assertNotSame($a['unidades'][0]['unidad_id'], $b['unidades'][0]['unidad_id']);
        $json = UnidadesRevisionConsulta::materializar(['anexo_sin_remision' => [$a, $b]]);
        $this->assertSame($b['unidades'], $json['anexo_sin_remision'][1]['unidades']);
        $this->assertSame(1, UnidadesRevisionConsulta::localizar($json, $b['unidades'][0]['unidad_id'])['indice']);
    }

    public function test_normaliza_origen_provisional_y_cambio_juntos_sin_reidentificar_lo_guardado(): void
    {
        $json = ['anexo_sin_remision' => [['documento_id' => 71, 'pendiente_revision' => true],
            ['documento_id' => 72, 'unidades' => [['unidad_id' => 'historia-fija', 'documento_id' => 72, 'resultado' => 'correcto']]]]];
        $datos = ['cambios_revisados' => [['tipo' => 'anexo', 'indice' => 0, 'documento_id' => 71, 'unidad_id' => 'anexo:doc:71', 'resultado' => 'incidencia']],
            'excepciones' => [['origen_unidad_id' => 'anexo:doc:71']]];
        [$normalizado, $resuelto] = UnidadesRevisionConsulta::normalizarReferenciasTienda($json, $datos);
        $id = $normalizado['anexo_sin_remision'][0]['unidades'][0]['unidad_id'];
        $this->assertSame($id, $resuelto['cambios_revisados'][0]['unidad_id']);
        $this->assertSame($id, $resuelto['excepciones'][0]['origen_unidad_id']);
        $this->assertSame('historia-fija', $normalizado['anexo_sin_remision'][1]['unidades'][0]['unidad_id']);
        $this->assertArrayNotHasKey('linea_id', $json['anexo_sin_remision'][0]);
        [, $reintento] = UnidadesRevisionConsulta::normalizarReferenciasTienda($normalizado, $datos);
        $this->assertSame($id, $reintento['excepciones'][0]['origen_unidad_id']);
    }

    public function test_alias_compartido_no_se_desambigua_por_indice(): void
    {
        $base = ['solicitud_id' => 'sol', 'documento_id' => 71, 'pendiente_revision' => true];
        $json = ['anexo_sin_remision' => [UnidadesRevisionConsulta::nuevaLineaTienda($base), UnidadesRevisionConsulta::nuevaLineaTienda($base)]];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        UnidadesRevisionConsulta::normalizarReferenciasTienda($json, ['cambios_revisados' => [['tipo' => 'anexo', 'indice' => 1, 'documento_id' => 71, 'unidad_id' => 'anexo:sol:doc:71']]]);
    }

    public function test_rechaza_origen_ajeno_aunque_la_unidad_del_cambio_sea_valida(): void
    {
        $json = ['anexo_sin_remision' => [['documento_id' => 71], ['documento_id' => 72]]];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        UnidadesRevisionConsulta::normalizarReferenciasTienda($json, ['cambios_revisados' => [['tipo' => 'anexo', 'documento_id' => 71, 'unidad_id' => 'anexo:doc:71']],
            'excepciones' => [['origen_unidad_id' => 'anexo:doc:72']]]);
    }

    public function test_rechaza_documento_distinto_del_identificado_por_la_clave(): void
    {
        $json = ['anexo_sin_remision' => [['documento_id' => 71], ['documento_id' => 72]]];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        UnidadesRevisionConsulta::normalizarReferenciasTienda($json, ['cambios_revisados' => [['tipo' => 'anexo', 'indice' => 1, 'documento_id' => 72, 'unidad_id' => 'anexo:doc:71']]]);
    }

    public function test_sku_con_varias_capturas_emite_una_unidad_por_archivo(): void
    {
        $linea = [
            'solicitud_id' => 'sol-a',
            'sku' => 'SKU-1',
            'descripcion' => 'Aroma',
            'entrada' => 'catalogo',
            'documentos' => [['id' => 10], ['id' => 11]],
        ];
        $unidades = UnidadesRevisionConsulta::derivar('anexo', $linea);
        $this->assertCount(2, $unidades);
        $this->assertSame('captura', $unidades[0]['clase']);
        $this->assertSame('anexo:sol-a:doc:10', $unidades[0]['unidad_id']);
        $this->assertSame('anexo:sol-a:doc:11', $unidades[1]['unidad_id']);
        $this->assertSame('SKU-1', $unidades[0]['sku']);
        $this->assertSame('SKU-1', $unidades[1]['sku']);
    }

    public function test_sku_sin_capturas_es_una_unidad_de_producto(): void
    {
        $unidades = UnidadesRevisionConsulta::derivar('anexo', [
            'solicitud_id' => 'sol-b',
            'sku' => 'SKU-2',
            'descripcion' => 'Crema',
            'entrada' => 'catalogo',
        ]);
        $this->assertCount(1, $unidades);
        $this->assertSame('sku', $unidades[0]['clase']);
        $this->assertSame('anexo:sol-b:sku:SKU-2', $unidades[0]['unidad_id']);
    }

    public function test_documentos_peticion_no_son_unidades_de_retiro(): void
    {
        $unidades = UnidadesRevisionConsulta::derivar('retiro', [
            'solicitud_id' => 'sol-r',
            'documentos_piezas' => [['id' => 21]],
            'documentos_peticion' => [['id' => 99]],
        ]);
        $this->assertCount(1, $unidades);
        $this->assertSame('retiro:sol-r:doc:21', $unidades[0]['unidad_id']);
        $this->assertSame(21, $unidades[0]['documento_id']);
    }

    public function test_materializar_conserva_unidad_id_historica_y_no_usa_solo_el_indice(): void
    {
        $json = UnidadesRevisionConsulta::materializar([
            'anexo_sin_remision' => [[
                'sku' => 'LEG',
                'descripcion' => 'Pieza legado',
                'unidades' => [['unidad_id' => 'anexo:fijo:sku:LEG', 'clase' => 'sku', 'resultado' => 'correcto']],
            ]],
        ]);
        $this->assertSame('anexo:fijo:sku:LEG', $json['anexo_sin_remision'][0]['unidades'][0]['unidad_id']);
        $this->assertSame('correcto', $json['anexo_sin_remision'][0]['unidades'][0]['resultado']);
        $this->assertNotEmpty($json['anexo_sin_remision'][0]['linea_id']);
    }

    public function test_aplicar_una_unidad_no_toca_a_la_hermana_y_conserva_historial(): void
    {
        $linea = [
            'solicitud_id' => 'sol-c',
            'sku' => 'SKU-1',
            'documentos' => [['id' => 1], ['id' => 2]],
        ];
        $linea['unidades'] = UnidadesRevisionConsulta::derivar('anexo', $linea);
        $linea = UnidadesRevisionConsulta::aplicar($linea, 'anexo:sol-c:doc:1', ['resultado' => 'correcto'], 7, 'QA');
        $this->assertSame('correcto', $linea['unidades'][0]['resultado']);
        $this->assertNull($linea['unidades'][1]['resultado']);
        $linea = UnidadesRevisionConsulta::aplicar($linea, 'anexo:sol-c:doc:1', ['resultado' => 'incidencia'], 7, 'QA');
        $this->assertSame('incidencia', $linea['unidades'][0]['resultado']);
        $this->assertSame('correcto', $linea['unidades'][0]['historial'][0]['resultado']);
        $this->assertNull($linea['unidades'][1]['resultado']);
    }

    public function test_dos_solicitudes_del_mismo_sku_tienen_identidad_distinta(): void
    {
        $primera = UnidadesRevisionConsulta::id('anexo', ['solicitud_id' => 's1', 'sku' => 'X'], 'sku', null);
        $segunda = UnidadesRevisionConsulta::id('anexo', ['solicitud_id' => 's2', 'sku' => 'X'], 'sku', null);
        $this->assertNotSame($primera, $segunda);
    }

    public function test_documentos_de_soporte_no_son_unidades_de_revision(): void
    {
        $unidades = UnidadesRevisionConsulta::derivar('anexo', [
            'solicitud_id' => 'sol-s',
            'sku' => 'SKU-1',
            'documentos' => [['id' => 10], ['id' => 11]],
            'documentos_soporte' => [['id' => 11]],
        ]);
        $this->assertCount(1, $unidades);
        $this->assertSame(10, $unidades[0]['documento_id']);
    }

    public function test_materializar_sella_un_anexo_ya_atendido_sin_volverlo_pendiente(): void
    {
        $json = UnidadesRevisionConsulta::materializar([
            'anexo_sin_remision' => [[
                'solicitud_id' => 'sol-atendida',
                'pendiente_revision' => false,
                'estado' => 'disponible',
                'respondida_at' => '2026-11-18T10:00:00Z',
                'documento_id' => 8,
            ]],
        ]);
        $this->assertSame('correcto', $json['anexo_sin_remision'][0]['unidades'][0]['resultado']);
        $this->assertTrue(UnidadesRevisionConsulta::lineaYaAtendida('anexo', $json['anexo_sin_remision'][0]));
    }
}
