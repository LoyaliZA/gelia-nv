<?php

namespace Tests\Unit\ControlPedidos;

use App\Services\ControlPedidos\CalcularPlazosRetrasoPedidoBmaService;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use Carbon\Carbon;
use Tests\TestCase;

class ControlPedidosPlazosRetrasoTest extends TestCase
{
    private function config(array $overrides = []): array
    {
        return (new PlazosRetrasoPedidoBmaConfig)->normalizar(array_merge([
            'activo' => true,
            'hora_corte' => '18:00',
            'dias_habiles' => [1, 2, 3, 4, 5, 6],
            'temporada_alta' => false,
            'dias_extra_temporada_alta' => 1,
            'comercial' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
            'local_regional' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
        ], $overrides));
    }

    private function calc(): CalcularPlazosRetrasoPedidoBmaService
    {
        return new CalcularPlazosRetrasoPedidoBmaService(new PlazosRetrasoPedidoBmaConfig);
    }

    public function test_pagado_antes_del_corte_deadline_siguiente_dia_habil(): void
    {
        // Lunes 10:00 → origen lunes → +1 día hábil = martes 18:00
        $pago = Carbon::parse('2026-08-10 10:00:00', config('app.timezone'));
        $deadline = $this->calc()->deadlineDesdeAncla($pago, 1, $this->config());

        $this->assertSame('2026-08-11 18:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_pagado_despues_del_corte_cuenta_siguiente_dia_como_origen(): void
    {
        // Lunes 19:00 → origen martes → +1 = miércoles 18:00
        $pago = Carbon::parse('2026-08-10 19:00:00', config('app.timezone'));
        $deadline = $this->calc()->deadlineDesdeAncla($pago, 1, $this->config());

        $this->assertSame('2026-08-12 18:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_fin_de_semana_salta_a_siguiente_habil(): void
    {
        // Domingo 12:00, hábiles lun–sáb → origen lunes → +1 = martes 18:00
        $pago = Carbon::parse('2026-08-09 12:00:00', config('app.timezone'));
        $deadline = $this->calc()->deadlineDesdeAncla($pago, 1, $this->config());

        $this->assertSame('2026-08-11 18:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_temporada_alta_suma_dias_extra_en_plazos_categoria(): void
    {
        $config = $this->config([
            'temporada_alta' => true,
            'dias_extra_temporada_alta' => 1,
            'comercial' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
        ]);

        $plazos = (new PlazosRetrasoPedidoBmaConfig)->plazosParaCategoria($config, 'comercial');
        $this->assertSame(2, $plazos['dias_empaque']);
        $this->assertSame(2, $plazos['dias_recoleccion']);

        $pago = Carbon::parse('2026-08-10 10:00:00', config('app.timezone'));
        $deadline = $this->calc()->deadlineDesdeAncla($pago, $plazos['dias_empaque'], $config);
        // origen lun + 2 hábiles = miércoles 18:00
        $this->assertSame('2026-08-12 18:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_local_regional_usa_bloque_distinto(): void
    {
        $config = $this->config([
            'comercial' => ['dias_empaque' => 1, 'dias_recoleccion' => 1],
            'local_regional' => ['dias_empaque' => 2, 'dias_recoleccion' => 3],
        ]);

        $cfg = new PlazosRetrasoPedidoBmaConfig;
        $this->assertSame(2, $cfg->plazosParaCategoria($config, 'local_regional')['dias_empaque']);
        $this->assertSame(3, $cfg->plazosParaCategoria($config, 'local_regional')['dias_recoleccion']);
        $this->assertSame(1, $cfg->plazosParaCategoria($config, 'comercial')['dias_empaque']);
    }
}
