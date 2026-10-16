<?php

namespace Tests\Unit\ControlPedidos;

use App\Http\Requests\ControlPedidos\UpdatePedidoBmaRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class BooleanosPedidoBmaRequestTest extends TestCase
{
    public function test_las_cadenas_true_y_false_de_multipart_cuentan_como_booleano(): void
    {
        $request = UpdatePedidoBmaRequest::create('/control-pedidos/1', 'POST', [
            'aplica_saldo_favor' => 'false',
            'aplica_seguro' => 'true',
            'enviar' => '0',
            'direccion' => ['domicilio_irregular' => 'false', 'calle' => 'Centro'],
        ]);
        $request->setContainer($this->app);

        $preparar = new ReflectionMethod($request, 'prepareForValidation');
        $preparar->invoke($request);

        $this->assertSame(0, $request->input('aplica_saldo_favor'));
        $this->assertSame(1, $request->input('aplica_seguro'));
        $this->assertSame(0, $request->input('enviar'));
        $this->assertSame(0, $request->input('direccion.domicilio_irregular'));
        $this->assertSame('Centro', $request->input('direccion.calle'));

        $validador = Validator::make($request->all(), [
            'aplica_saldo_favor' => ['nullable', 'boolean'],
            'aplica_seguro' => ['nullable', 'boolean'],
            'enviar' => ['nullable', 'boolean'],
            'direccion.domicilio_irregular' => ['nullable', 'boolean'],
        ]);

        $this->assertTrue($validador->passes());
        $this->assertSame('es', app()->getLocale());

        $invalido = Validator::make(['aplica_saldo_favor' => 'quizas'], [
            'aplica_saldo_favor' => ['boolean'],
        ]);
        $this->assertSame(
            'El campo saldo a favor debe ser sí o no.',
            $invalido->errors()->first('aplica_saldo_favor')
        );
    }
}
