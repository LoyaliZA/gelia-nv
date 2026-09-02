<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiRecepcionFisicaResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Almacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->almacen = Almacen::query()->create([
            'codigo' => 'PISO-UI',
            'nombre' => 'Piso recepción',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_formulario_recepcion_renderiza_inertia_con_snapshot_y_almacenes(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.recepcion.create', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Recepcion', false)
                ->where('resguardo.id', $resguardo->id)
                ->where('resguardo.version', 1)
                ->where('resguardo.snapshot_folio', 'REM-REC-UI')
                ->where('puede_recibir', true)
                ->has('almacenes', 1)
                ->where('almacenes.0.id', $this->almacen->id)
                ->has('catalogos.tipos_bulto')
                ->has('catalogos.condiciones_bulto'));
    }

    public function test_formulario_recepcion_sin_permiso_recibir(): void
    {
        $resguardo = $this->crearResguardoPendiente();
        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $soloVer->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($soloVer)
            ->get(route('punto_venta.resguardos.recepcion.create', $resguardo))
            ->assertForbidden();
    }

    public function test_formulario_recepcion_en_custodia_marca_no_disponible(): void
    {
        $resguardo = $this->crearResguardoPendiente([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => now(),
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.recepcion.create', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('puede_recibir', false)
                ->where('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA));
    }

    public function test_index_expone_permiso_recibir(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permisos.recibir', true));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardoPendiente(array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-REC-UI',
            'cantidad_bultos_esperada' => 1,
            'salida_cedis_at' => now()->subHour(),
            'version' => 1,
        ], $overrides));
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
