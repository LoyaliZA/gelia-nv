<?php

namespace Tests\Unit\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SujetasAPlazoDePagoScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujos_tienda_quedan_fuera_del_plazo_de_pago(): void
    {
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta'] as $nombre) {
            CatalogoEstadoSolicitud::create(['nombre' => $nombre, 'activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();

        $proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $vendedor = User::factory()->create();
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

        $normal = SolicitudTag::create([
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $idRespondida,
            'pago_confirmado' => false,
            'compra_en_tienda' => false,
            'compra_en_tienda_solo_tag' => false,
            'monto_cotizado' => 100,
        ]);

        $tienda = SolicitudTag::create([
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $idRespondida,
            'pago_confirmado' => false,
            'compra_en_tienda' => true,
            'compra_en_tienda_solo_tag' => false,
            'monto_cotizado' => 0,
        ]);

        $soloTag = SolicitudTag::create([
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $idRespondida,
            'pago_confirmado' => false,
            'compra_en_tienda' => false,
            'compra_en_tienda_solo_tag' => true,
            'monto_cotizado' => 0,
        ]);

        $ids = SolicitudTag::query()->sujetasAPlazoDePago()->pluck('id')->all();

        $this->assertContains($normal->id, $ids);
        $this->assertNotContains($tienda->id, $ids);
        $this->assertNotContains($soloTag->id, $ids);
    }
}
