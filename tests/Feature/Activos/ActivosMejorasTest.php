<?php

namespace Tests\Feature\Activos;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\CatalogoCategoriaActivo;
use App\Models\CatalogoTipoActivo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivosMejorasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Departamento $departamento;
    protected CatalogoTipoActivo $tipoEquipo;
    protected CatalogoTipoActivo $tipoAccesorio;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'activos.ver', 'activos.crear', 'activos.asignar', 'activos.editar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['activos.ver', 'activos.crear', 'activos.asignar', 'activos.editar']);

        $this->departamento = Departamento::create([
            'nombre' => 'TI Test ' . Str::random(5),
            'codigo' => 'TI' . random_int(100, 999),
            'activo' => true,
        ]);

        $this->tipoEquipo = CatalogoTipoActivo::create([
            'nombre' => 'Equipo TI Test',
            'slug' => 'equipo-ti-test-' . Str::random(4),
            'categoria' => 'tecnologico',
            'esquema_atributos' => ['fields' => []],
            'activo' => true,
        ]);

        $this->tipoAccesorio = CatalogoTipoActivo::firstOrCreate(
            ['slug' => 'accesorio'],
            [
                'nombre' => 'Accesorio',
                'categoria' => 'tecnologico',
                'esquema_atributos' => [
                    'fields' => [
                        ['key' => 'condicion', 'label' => 'Condición', 'type' => 'select', 'required' => true, 'options' => ['Bueno']],
                    ],
                ],
                'activo' => true,
            ]
        );
    }

    public function test_vista_previa_responsiva_retorna_pdf_inline(): void
    {
        $colaborador = User::factory()->create();

        $activo = Activo::create([
            'folio' => 'TEC-TI-2026-0001',
            'consulta_token' => (string) \Illuminate\Support\Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Laptop prueba',
            'estado' => 'asignado',
            'responsable_user_id' => $colaborador->id,
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ]);

        $asignacion = ActivoAsignacion::create([
            'activo_id' => $activo->id,
            'user_id' => $colaborador->id,
            'asignado_por_id' => $this->admin->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('activos.asignaciones.responsiva_vista_previa', $asignacion));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_asignar_padre_asigna_accesorios_en_cascada(): void
    {
        $colaborador = User::factory()->create();

        $padre = Activo::create([
            'folio' => 'TEC-TI-2026-0002',
            'consulta_token' => (string) \Illuminate\Support\Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Laptop principal',
            'estado' => 'disponible',
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ]);

        $accesorio = Activo::create([
            'folio' => 'TEC-TI-2026-0003',
            'consulta_token' => (string) \Illuminate\Support\Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoAccesorio->id,
            'activo_padre_id' => $padre->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Cargador USB-C',
            'estado' => 'disponible',
            'registrado_por_id' => $this->admin->id,
            'atributos' => ['condicion' => 'Bueno'],
        ]);

        $this->actingAs($this->admin)->post(route('activos.asignar', $padre), [
            'user_id' => $colaborador->id,
        ])->assertRedirect();

        $padre->refresh();
        $accesorio->refresh();

        $this->assertSame($colaborador->id, $padre->responsable_user_id);
        $this->assertSame('asignado', $padre->estado);
        $this->assertSame($colaborador->id, $accesorio->responsable_user_id);
        $this->assertSame('asignado', $accesorio->estado);
    }

    public function test_devolver_padre_devuelve_accesorios_en_cascada(): void
    {
        $colaborador = User::factory()->create();

        $padre = Activo::create([
            'folio' => 'TEC-TI-2026-0004',
            'consulta_token' => (string) \Illuminate\Support\Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Laptop devolución',
            'estado' => 'asignado',
            'responsable_user_id' => $colaborador->id,
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ]);

        $accesorio = Activo::create([
            'folio' => 'TEC-TI-2026-0005',
            'consulta_token' => (string) \Illuminate\Support\Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoAccesorio->id,
            'activo_padre_id' => $padre->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Mouse',
            'estado' => 'asignado',
            'responsable_user_id' => $colaborador->id,
            'registrado_por_id' => $this->admin->id,
            'atributos' => ['condicion' => 'Bueno'],
        ]);

        ActivoAsignacion::create([
            'activo_id' => $padre->id,
            'user_id' => $colaborador->id,
            'asignado_por_id' => $this->admin->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => false,
        ]);

        ActivoAsignacion::create([
            'activo_id' => $accesorio->id,
            'user_id' => $colaborador->id,
            'asignado_por_id' => $this->admin->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => false,
        ]);

        $this->actingAs($this->admin)->post(route('activos.devolver', $padre))->assertRedirect();

        $padre->refresh();
        $accesorio->refresh();

        $this->assertSame('disponible', $padre->estado);
        $this->assertNull($padre->responsable_user_id);
        $this->assertSame('disponible', $accesorio->estado);
        $this->assertNull($accesorio->responsable_user_id);
    }

    public function test_categoria_activo_es_opcional_al_registrar(): void
    {
        $categoria = CatalogoCategoriaActivo::create([
            'nombre' => 'Computadora',
            'slug' => 'computadora',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('activos.store'), [
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'catalogo_categoria_activo_id' => $categoria->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'PC categorizada',
            'atributos' => [],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('activos', [
            'nombre' => 'PC categorizada',
            'catalogo_categoria_activo_id' => $categoria->id,
        ]);
    }

    public function test_filtro_pendientes_firma_solo_lista_asignaciones_sin_firmar(): void
    {
        $colaborador = User::factory()->create();

        $firmado = Activo::create([
            'folio' => 'TEC-TI-2026-0091',
            'consulta_token' => (string) Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Activo firmado',
            'estado' => 'asignado',
            'responsable_user_id' => $colaborador->id,
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ]);

        $pendiente = Activo::create([
            'folio' => 'TEC-TI-2026-0092',
            'consulta_token' => (string) Str::uuid(),
            'catalogo_tipo_activo_id' => $this->tipoEquipo->id,
            'departamento_id' => $this->departamento->id,
            'nombre' => 'Activo sin firmar',
            'estado' => 'asignado',
            'responsable_user_id' => $colaborador->id,
            'registrado_por_id' => $this->admin->id,
            'atributos' => [],
        ]);

        ActivoAsignacion::create([
            'activo_id' => $firmado->id,
            'user_id' => $colaborador->id,
            'asignado_por_id' => $this->admin->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => true,
        ]);

        ActivoAsignacion::create([
            'activo_id' => $pendiente->id,
            'user_id' => $colaborador->id,
            'asignado_por_id' => $this->admin->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('activos.index', ['pendientes_firma' => '1']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Activos/Index')
            ->where('filtros.pendientes_firma', '1')
            ->has('activos.data', 1)
            ->where('activos.data.0.id', $pendiente->id)
        );
    }
}
