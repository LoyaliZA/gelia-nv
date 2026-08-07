<?php

/**
 * Self-check (sin PHPUnit 8.3+): envío exige costo de envío y comprobante tras pesaje.
 * Uso: php tests/Unit/ControlPedidos/check_cotizacion_comprobante.php
 */

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Illuminate\Database\Eloquent\Relations\HasMany;

$probe = new class {
    use ValidacionCamposPedidoBma;

    public function check(PedidoBma $p): void
    {
        $this->validarCamposRequeridos($p);
    }
};

$hacerPedido = function (int $comprobantes, ?float $costoEnvio) {
    $origen = new CatalogoOrigenPedido(['requiere_logistica' => true]);

    $rel = \Mockery::mock(HasMany::class);
    $rel->shouldReceive('where')->with('tipo', PedidoBmaDocumento::TIPO_COMPROBANTE)->andReturnSelf();
    $rel->shouldReceive('count')->andReturn($comprobantes);

    $pedido = \Mockery::mock(PedidoBma::class)->makePartial();
    $pedido->shouldReceive('documentos')->andReturn($rel);
    $pedido->shouldReceive('loadMissing')->andReturnSelf();
    $pedido->shouldReceive('tienePesajeRespondido')->andReturn(true);
    $pedido->shouldReceive('esMunicipioDiferido')->andReturn(false);
    $pedido->shouldReceive('esResguardoAbierto')->andReturn(false);
    $pedido->shouldReceive('esResguardoComplementario')->andReturn(false);

    $pedido->forceFill([
        'folio_remision' => 'REM-COT-1',
        'cliente_id' => 1,
        'origen_id' => 1,
        'catalogo_banco_id' => 1,
        'almacen_id' => 1,
        'total_mercancia' => 1000,
        'pesaje_respondido_at' => now(),
        'peso_real_kg' => 2,
        'catalogo_tipo_caja_id' => 1,
        'numero_cajas' => 1,
        'catalogo_tipo_guia_id' => 1,
        'catalogo_paqueteria_id' => 1,
        'catalogo_zona_id' => 1,
        'costo_envio' => $costoEnvio,
        'codigo_postal' => '86000',
        'domicilio_entrega' => 'Calle Falsa 123',
        'cliente_proporciona_guia' => false,
    ]);
    $pedido->setRelation('origen', $origen);
    $pedido->setRelation('tipoOperacionEnvio', null);
    $pedido->setRelation('cajas', collect([
        (object) [
            'catalogo_tipo_caja_id' => 1,
            'peso_real_kg' => 2.0,
            'peso_volumetrico_kg' => 1.5,
        ],
    ]));

    return $pedido;
};

$fallos = 0;

try {
    $probe->check($hacerPedido(1, null));
    fwrite(STDERR, "FAIL: se esperaba excepción por costo de envío\n");
    $fallos++;
} catch (InvalidArgumentException $e) {
    if (! str_contains($e->getMessage(), 'costo de envío')) {
        fwrite(STDERR, "FAIL: mensaje inesperado (costo): {$e->getMessage()}\n");
        $fallos++;
    } else {
        echo "OK: sin costo de envío → {$e->getMessage()}\n";
    }
}

try {
    $probe->check($hacerPedido(0, 150.0));
    fwrite(STDERR, "FAIL: se esperaba excepción por comprobante\n");
    $fallos++;
} catch (InvalidArgumentException $e) {
    if (! str_contains($e->getMessage(), 'comprobante de pago')) {
        fwrite(STDERR, "FAIL: mensaje inesperado (comprobante): {$e->getMessage()}\n");
        $fallos++;
    } else {
        echo "OK: sin comprobante → {$e->getMessage()}\n";
    }
}

$cotizacionLista = static function (array $p): bool {
    if (! ($p['requiereLogistica'] ?? true)) {
        return (float) ($p['total_mercancia'] ?? 0) > 0;
    }
    if (! ($p['cotizacionHabilitada'] ?? false)) {
        return false;
    }
    if (($p['guiaCliente'] ?? false) || ($p['esResguardoComplementario'] ?? false)) {
        return true;
    }
    if (empty($p['catalogo_paqueteria_id'])) {
        return false;
    }
    if (! ($p['omiteCosto'] ?? false) && ($p['costo_envio'] === '' || $p['costo_envio'] === null)) {
        return false;
    }
    if (empty($p['catalogo_tipo_guia_id']) || empty($p['catalogo_zona_id'])) {
        return false;
    }

    return true;
};

if ($cotizacionLista([
    'requiereLogistica' => true,
    'cotizacionHabilitada' => true,
    'catalogo_paqueteria_id' => 1,
    'catalogo_tipo_guia_id' => 1,
    'catalogo_zona_id' => 1,
    'costo_envio' => null,
])) {
    fwrite(STDERR, "FAIL: cotizacionLista no debe ser true sin costo\n");
    $fallos++;
} else {
    echo "OK: cotizacionLista false sin costo\n";
}

if (! $cotizacionLista([
    'requiereLogistica' => true,
    'cotizacionHabilitada' => true,
    'catalogo_paqueteria_id' => 1,
    'catalogo_tipo_guia_id' => 1,
    'catalogo_zona_id' => 1,
    'costo_envio' => 120,
])) {
    fwrite(STDERR, "FAIL: cotizacionLista debe ser true con cotización completa\n");
    $fallos++;
} else {
    echo "OK: cotizacionLista true con cotización completa\n";
}

\Mockery::close();
exit($fallos > 0 ? 1 : 0);
