<?php

namespace Tests\Feature\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivosEtiquetasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Departamento $departamento;

    protected CatalogoTipoActivo $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('activos.ver', 'web');
        Permission::findOrCreate('activos.exportar', 'web');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['activos.ver', 'activos.exportar']);

        $this->departamento = Departamento::create([
            'nombre' => 'TI Etiquetas ' . Str::random(4),
            'codigo' => 'TI' . random_int(100, 999),
            'activo' => true,
        ]);

        $this->tipo = CatalogoTipoActivo::create([
            'nombre' => 'Equipo',
            'slug' => 'equipo-' . Str::random(4),
            'categoria' => 'tecnologico',
            'esquema_atributos' => ['fields' => []],
            'activo' => true,
        ]);
    }

    private function crearActivo(array $overrides = []): Activo
    {
        return Activo::create(array_merge([
            'folio' => 'TEC-TI-2026-' . random_int(1000, 9999),
            'consulta_token' => (string) Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Activo prueba',
            'estado' => 'disponible',
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ], $overrides));
    }

    public function test_pagina_etiquetas_carga(): void
    {
        $response = $this->actingAs($this->admin)->get(route('activos.etiquetas'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Activos/Etiquetas'));
    }

    public function test_pagina_etiquetas_rechaza_ratio_invalido(): void
    {
        $response = $this->actingAs($this->admin)->get(route('activos.etiquetas', [
            'ancho_mm' => 100,
            'alto_mm' => 40,
        ]));

        $response->assertSessionHasErrors('ancho_mm');
    }

    public function test_descargar_etiquetas_retorna_pdf(): void
    {
        $this->crearActivo();

        $response = $this->actingAs($this->admin)->get(route('activos.etiquetas.descargar'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_contar_etiquetas_filtra_por_responsables(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->crearActivo(['responsable_user_id' => $userA->id, 'estado' => 'asignado']);
        $this->crearActivo(['responsable_user_id' => $userB->id, 'estado' => 'asignado']);
        $this->crearActivo(['responsable_user_id' => $userA->id, 'estado' => 'asignado']);

        $response = $this->actingAs($this->admin)->getJson(route('activos.etiquetas.contar', [
            'responsable_user_ids' => [$userA->id],
        ]));

        $response->assertOk();
        $response->assertJson(['total' => 2]);
    }

    public function test_contar_etiquetas_excluye_activos_en_baja(): void
    {
        $this->crearActivo(['estado' => 'disponible']);
        $this->crearActivo(['estado' => 'baja']);

        $response = $this->actingAs($this->admin)->getJson(route('activos.etiquetas.contar'));

        $response->assertOk();
        $response->assertJson(['total' => 1]);
    }
}
