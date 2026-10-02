<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Tests\TestCase;

class BodegaCedisObservabilidadTest extends TestCase
{
    public function test_eventos_traspaso_en_catalogo(): void
    {
        $this->assertContains(
            PedidoBmaCumplimientoEvento::TIPO_TRASPASO_SALIDA,
            PedidoBmaCumplimientoEvento::TIPOS
        );
        $this->assertContains(
            PedidoBmaCumplimientoEvento::TIPO_TRASPASO_RECIBIDO_CEDIS,
            PedidoBmaCumplimientoEvento::TIPOS
        );
    }

    public function test_tiempo_atencion_en_curso(): void
    {
        $tarea = new PedidoBmaTareaPreparacion([
            'estado' => PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            'solicitada_at' => now()->subMinutes(30),
        ]);

        $tiempo = VisibilidadTareaPreparacion::tiempoAtencion($tarea);
        $this->assertGreaterThanOrEqual(29, $tiempo['minutos']);
        $this->assertTrue($tiempo['en_curso']);
    }

    public function test_producto_tarea_guarda_origen_expediente(): void
    {
        $fillable = (new PedidoBmaTareaProducto)->getFillable();
        $this->assertContains('pedido_bma_origen_id', $fillable);
    }

    public function test_servicios_fase6_existen(): void
    {
        $this->assertTrue(class_exists(\App\Services\ControlPedidos\ConciliarTraspasoTiendaCedisService::class));
        $this->assertTrue(class_exists(\App\Services\ControlPedidos\ColaEstadosCuentaPreparacionService::class));
        $this->assertStringContainsString(
            'pedido_bma_origen_id',
            file_get_contents(app_path('Services/ControlPedidos/CrearTraspasoDesdeTareaPreparacionService.php'))
        );
    }
}
