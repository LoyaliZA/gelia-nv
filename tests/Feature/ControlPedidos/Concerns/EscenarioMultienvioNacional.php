<?php

namespace Tests\Feature\ControlPedidos\Concerns;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\GuardarBorradorMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\RegistrarDecisionMultienvio;
use Illuminate\Support\Str;

/** Fixtures compartidos de los tests de confirmación de multienvío (requiere $this->ventas). */
trait EscenarioMultienvioNacional
{
    private function confirmar(MultienvioNacional $grupo): MultienvioNacional
    {
        return app(ConfirmarMultienvio::class)->ejecutar($grupo, $this->ventas->id, $grupo->revision_consulta);
    }

    /** @return array<string, mixed> */
    private function foto(PedidoBma $origen): array
    {
        $o = PedidoBma::query()->findOrFail($origen->id);

        return [
            'pedidos' => PedidoBma::query()->count(),
            'origen' => [
                $o->total_mercancia, $o->folio_remision, $o->numero_cajas, $o->multienvio_nacional_id, $o->multienvio_orden,
                $o->costo_envio, $o->total_a_cobrar, $o->aplica_seguro, $o->peso_real_kg, $o->solicitud_preparacion_json,
            ],
            'cajas' => PedidoBmaCaja::query()->orderBy('id')->get(['id', 'pedido_bma_id', 'orden', 'costo_envio'])->toArray(),
            'documentos' => PedidoBmaDocumento::query()->orderBy('id')->get(['id', 'pedido_bma_id'])->toArray(),
            'referencias' => PedidoBmaReferencia::query()->count(),
            'historial' => PedidoBmaHistorialEstado::query()->count(),
            'grupo' => MultienvioNacional::query()->orderBy('id')->get(['id', 'estado', 'activado_at'])->toArray(),
        ];
    }

    /** @return array{0: PedidoBma, 1: MultienvioNacional, 2: array<int, PedidoBmaCaja>} */
    private function escenarioConBorrador(array $extra = []): array
    {
        [$origen, $grupo, $cajas] = $this->escenario($extra);
        $this->borrador($grupo, $cajas, [600, 500, 400]);

        return [$origen, $grupo->fresh(), $cajas];
    }

    /** @param  array<int, PedidoBmaCaja>  $cajas */
    private function borrador(MultienvioNacional $grupo, array $cajas, array $montos, array $folios = ['F-A', 'F-B', 'F-C']): void
    {
        $envios = [];
        foreach ($cajas as $i => $caja) {
            $envios[] = ['uuid_operativo' => $caja->uuid_operativo, 'folio_remision' => $folios[$i], 'total_mercancia' => $montos[$i], 'contenido' => 'Caja '.($i + 1)];
        }
        app(GuardarBorradorMultienvio::class)->ejecutar($grupo, $this->ventas->id, $envios);
    }

    /** @return array{0: PedidoBma, 1: MultienvioNacional, 2: array<int, PedidoBmaCaja>} */
    private function escenario(array $extra = []): array
    {
        $pedido = $this->pedidoNacional($extra);
        $cajas = [];
        foreach ([10, 12, 14] as $peso) {
            $cajas[] = $this->caja($pedido, $peso);
        }

        $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO, 'ruta_archivo' => 'pedidos_bma/soporte.pdf', 'nombre_original' => 'soporte.pdf', 'activo' => true]);
        $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, 'ruta_archivo' => 'pedidos_bma/caja2.jpg', 'nombre_original' => 'caja2.jpg', 'pedido_bma_caja_id' => $cajas[1]->id, 'relacion_tipo' => PedidoBmaDocumento::RELACION_ENVIO_CAJA, 'relacion_id' => $cajas[1]->id, 'activo' => true]);
        $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, 'ruta_archivo' => 'pedidos_bma/caja3.jpg', 'nombre_original' => 'caja3.jpg', 'relacion_tipo' => PedidoBmaDocumento::RELACION_ENVIO_CAJA, 'relacion_id' => $cajas[2]->id, 'activo' => true]);
        $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION, 'ruta_archivo' => 'pedidos_bma/general.jpg', 'nombre_original' => 'general.jpg', 'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL, 'activo' => true]);
        PedidoBmaCajaRemision::query()->create([
            'pedido_bma_caja_id' => $cajas[2]->id, 'pedido_bma_id' => $this->pedidoNacional(['folio_remision' => 'REM-'.uniqid()])->id, 'solicitud_id' => (string) Str::uuid(),
            'folio_externo' => 'REM-X', 'valor_asegurado' => 400, 'activa' => true,
        ]);
        $pedido->revisionesProducto()->create(['descripcion_producto' => 'Pieza 1', 'estado_fisico' => 'en_buen_estado']);
        PedidoBmaDireccion::query()->create([
            'pedido_bma_id' => $pedido->id, 'version_snapshot' => 1, 'es_vigente' => true, 'nombre_destinatario' => 'Cliente',
            'calle' => 'Calle 1', 'colonia' => 'Centro', 'codigo_postal' => '01000', 'municipio' => 'CDMX', 'estado' => 'CDMX',
            'origen' => PedidoBmaDireccion::ORIGEN_MANUAL,
        ]);

        $grupo = app(RegistrarDecisionMultienvio::class)->ejecutar($pedido, $this->ventas->id, 'activar');

        return [$pedido->fresh(), $grupo, $cajas];
    }

    private function caja(PedidoBma $pedido, float $peso): PedidoBmaCaja
    {
        $tipo = CatalogoTipoCajaPedido::query()->firstOrCreate(
            ['nombre' => 'CAJA #202'],
            ['peso_volumetrico' => 10, 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'activo' => true]
        );

        return $pedido->cajas()->create([
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => $tipo->id,
            'orden' => $pedido->cajas()->count(),
            'largo' => 46,
            'ancho' => 32,
            'alto' => 35,
            'peso_real_kg' => $peso,
            'peso_volumetrico_kg' => 10.304,
            'peso_cobrado_kg' => $peso,
            'costo_envio' => 90,
            'costo_seguro' => 40,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);
    }

    private function pedidoNacional(array $extra = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'NAC-'.uniqid(),
            'nombre' => 'Nacional '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'NAC-'.uniqid(),
            'folio_remision' => 'WIZ-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'catalogo_paqueteria_id' => CatalogoPaqueteriaPedido::query()->firstOrCreate(
                ['nombre' => 'DHL'],
                ['categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'requiere_peso' => true, 'activo' => true, 'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA]
            )->id,
            'solicitud_preparacion_json' => [
                'modalidad_entrega' => 'nacional',
                'captura_nacional' => 'compartida',
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                'piezas_nacionales' => [['client_uuid' => 'p-1', 'descripcion' => 'Pieza 1']],
                'piezas_nacionales_vigentes' => [['client_uuid' => 'p-1', 'descripcion' => 'Pieza 1']],
                'respuestas_piezas_nacionales' => ['p-1' => 1],
                'cotizacion_nacional' => ['total' => 1800],
                'ajustes_nacionales' => [['tipo' => 'quita']],
            ],
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            'pesaje_solicitado_at' => now()->subHour(),
            'pesaje_respondido_at' => now()->subMinutes(30),
            'pesaje_respondido_por_id' => $this->ventas->id,
            'consulta_cerrada_at' => now()->subMinutes(10),
            'consulta_cerrada_por_id' => $this->ventas->id,
            'consulta_actualizacion_pendiente' => false,
            'numero_cajas' => 3,
            'peso_real_kg' => 36,
            'peso_cobrado_guia_kg' => 36,
            'total_mercancia' => 1500,
            'costo_envio' => 270,
            'saldo_a_favor' => 0,
            'costo_seguro' => 120,
            'total_a_cobrar' => 1890,
            'aplica_seguro' => true,
            'es_resguardo' => false,
        ], $extra));
    }
}
