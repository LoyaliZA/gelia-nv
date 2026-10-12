<?php

namespace Tests\Feature\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AprobarSinTipoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_aprobar_solicitud_comercial_sin_tipo_registrado(): void
    {
        foreach (['Pendiente', 'Respondida'] as $nombre) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $nombre]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();

        Permission::findOrCreate('solicitudes.reportar', 'web');
        $encargada = User::factory()->create();
        $encargada->givePermissionTo('solicitudes.reportar');

        $proceso = CatalogoProceso::create([
            'nombre' => 'CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
        ]);
        $lista = CatalogoListaDescuento::create(['nombre' => 'Plata', 'monto_requerido' => 1000]);
        $cliente = Cliente::create([
            'numero_cliente' => '9001',
            'nombre' => 'Cliente prueba',
            'lista_actual_id' => $lista->id,
        ]);

        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $encargada->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'catalogo_lista_descuento_id' => $lista->id,
            'catalogo_tipo_cliente_id' => null,
            'monto_cotizado' => 5000,
        ]);

        $this->actingAs($encargada)
            ->put(route('solicitudes.actualizar_estado', $solicitud), [
                'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'),
            ])
            ->assertStatus(422);
    }
}
