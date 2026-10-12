<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\BancosDepartamentoService;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BancosContextoDepartamentoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('catalogo_bancos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->boolean('activo');
            $t->boolean('compartido')->default(true);
            $t->timestamps();
        });
        Schema::create('catalogo_banco_departamento', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('catalogo_banco_id');
            $t->unsignedBigInteger('departamento_id');
            $t->boolean('activo');
            $t->integer('orden');
            $t->timestamps();
        });
    }

    public function test_catalogo_compartido_y_asignado_sin_respaldo_a_todos_los_bancos(): void
    {
        $service = app(BancosDepartamentoService::class);
        $compartido = $service->guardar(new CatalogoBanco, ['nombre' => 'Compartido', 'activo' => true, 'compartido' => true]);
        $privado = $service->guardar(new CatalogoBanco, ['nombre' => 'Departamento A', 'activo' => true, 'compartido' => false, 'departamento_ids' => [7, 7]]);
        $otro = $service->guardar(new CatalogoBanco, ['nombre' => 'Departamento B', 'activo' => true, 'compartido' => false, 'departamento_ids' => [9]]);
        $service->guardar(new CatalogoBanco, ['nombre' => 'Desactivado', 'activo' => false, 'compartido' => true]);
        $this->assertEqualsCanonicalizing([$compartido->id, $privado->id], $service->disponibles(7)->pluck('id')->all());
        $this->assertSame([$compartido->id], $service->disponibles(123)->pluck('id')->all());
        $this->assertSame([$compartido->id], $service->disponibles(null)->pluck('id')->all());
        $this->assertSame(1, $privado->departamentos()->count());
        $service->guardar($privado, ['departamento_ids' => [9]]);
        $this->assertEqualsCanonicalizing([$compartido->id, $otro->id, $privado->id], $service->disponibles(9)->pluck('id')->all());
        $this->assertSame([$compartido->id], $service->disponibles(7)->pluck('id')->all());
    }

    public function test_usa_departamento_del_pedido_aunque_cambie_el_departamento_de_la_vendedora(): void
    {
        $service = app(BancosDepartamentoService::class);
        $banco = $service->guardar(new CatalogoBanco, ['nombre' => 'Solo A', 'activo' => true, 'compartido' => false, 'departamento_ids' => [7]]);
        $pedido = new PedidoBma(['departamento_operacion_id' => 9]);
        $pedido->setRelation('vendedor', new User(['departamento_id' => 7]));
        $this->expectException(ValidationException::class);
        $service->validar($pedido, $banco->id);
    }

    public function test_pedido_historico_utiliza_departamento_principal_y_no_union_de_membresias(): void
    {
        $service = app(BancosDepartamentoService::class);
        $banco = $service->guardar(new CatalogoBanco, ['nombre' => 'Solo A', 'activo' => true, 'compartido' => false, 'departamento_ids' => [7]]);
        $pedido = new PedidoBma;
        $pedido->setRelation('vendedor', new User(['departamento_id' => 7]));
        $service->validar($pedido, $banco->id);
        $this->assertSame(7, ContextoDepartamentoPedido::dePedido($pedido));
        DB::table('catalogo_banco_departamento')->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        $service->validar($pedido, $banco->id);
    }
}
