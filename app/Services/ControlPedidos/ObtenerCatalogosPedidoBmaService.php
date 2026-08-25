<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\CatalogoBancoDepartamento;
use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoReexpedicionPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\Departamento;

class ObtenerCatalogosPedidoBmaService
{
    public function __construct(
        private PagosPedidoBmaConfig $pagosConfig,
        private EnviosPedidoBmaConfig $enviosConfig,
        private FormularioProgresivoPedidoBmaConfig $formularioConfig,
        private PreparacionTiendaConfig $preparacionConfig,
    ) {}

    /**
     * @param  list<int>|null  $departamentoIds  Si hay mapeo banco-depto, filtra bancos.
     */
    public function ejecutar(?array $departamentoIds = null): array
    {
        return [
            'origenes' => CatalogoOrigenPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'requiere_logistica']),
            'tipos_operacion_envio' => CatalogoTipoOperacionEnvio::where('activo', true)
                ->whereIn('codigo', [
                    CatalogoTipoOperacionEnvio::CODIGO_NORMAL,
                    CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO,
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO,
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
                ])
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre', 'descripcion']),
            'estatus' => CatalogoEstatusPedido::where('activo', true)->orderBy('orden')->get(['id', 'codigo_interno', 'nombre_visual', 'color_hex', 'fase_ciclo']),
            'almacenes' => Almacen::where('activo', true)
                ->where('visible_en_pedidos', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'bancos' => $this->bancosParaDepartamentos($departamentoIds),
            'formas_pago' => PedidoBmaPago::formasPagoCatalogo(),
            'tipos_caja' => CatalogoTipoCajaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'peso_volumetrico', 'medidas', 'largo', 'ancho', 'alto']),
            'paqueterias' => CatalogoPaqueteriaPedido::where('activo', true)->orderBy('categoria')->orderBy('nombre')->get([
                'id', 'nombre', 'categoria', 'permite_costo_diferido',
                'requiere_caratula', 'requiere_identificacion', 'requiere_remision', 'permite_por_cobrar',
                'requiere_peso', 'requiere_caja', 'requiere_evidencia_conjunto', 'campos_destino_obligatorios',
                'plantilla_caratula', 'habilitado_envio_municipio', 'reglas_municipio_pendientes',
            ]),
            'departamentos' => Departamento::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']),
            'paqueterias_municipio' => CatalogoPaqueteriaPedido::query()
                ->where('activo', true)
                ->where('habilitado_envio_municipio', true)
                ->where('reglas_municipio_pendientes', false)
                ->orderBy('nombre')
                ->get([
                    'id', 'nombre', 'categoria',
                    'requiere_caratula', 'requiere_identificacion', 'requiere_remision', 'permite_por_cobrar',
                    'requiere_peso', 'requiere_caja', 'requiere_evidencia_conjunto', 'campos_destino_obligatorios',
                    'plantilla_caratula', 'habilitado_envio_municipio',
                ]),
            'tipos_guia' => CatalogoTipoGuiaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'zonas' => CatalogoZonaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'costo_adicional']),
            'envios_tienda' => CatalogoEnvioTienda::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'es_otro']),
            'reexpediciones' => CatalogoReexpedicionPedido::where('activo', true)
                ->orderBy('codigo_postal')
                ->get(['id', 'codigo_postal', 'paqueteria_id', 'costo_adicional']),
            'pagos_config' => $this->pagosConfig->todas(),
            'envios_config' => array_merge($this->enviosConfig->todas(), [
                'matriz' => [
                    'ventas' => $this->enviosConfig->matrizActor('ventas'),
                    'cedis' => $this->enviosConfig->matrizActor('cedis'),
                    'auxiliar' => $this->enviosConfig->matrizActor('auxiliar'),
                ],
            ]),
            'formulario_config' => $this->formularioConfig->todas(),
            'preparacion_config' => $this->preparacionConfig->todas(),
        ];
    }

    /**
     * @param  list<int>|null  $departamentoIds
     * @return \Illuminate\Support\Collection<int, CatalogoBanco>
     */
    private function bancosParaDepartamentos(?array $departamentoIds)
    {
        $query = CatalogoBanco::where('activo', true)->orderBy('nombre');

        $ids = collect($departamentoIds ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return $query->get(['id', 'nombre']);
        }

        $hayMapeo = CatalogoBancoDepartamento::query()
            ->whereIn('departamento_id', $ids)
            ->where('activo', true)
            ->exists();

        if (! $hayMapeo) {
            return $query->get(['id', 'nombre']);
        }

        $bancoIds = CatalogoBancoDepartamento::query()
            ->whereIn('departamento_id', $ids)
            ->where('activo', true)
            ->orderBy('orden')
            ->pluck('catalogo_banco_id')
            ->unique()
            ->all();

        return $query->whereIn('id', $bancoIds ?: [0])->get(['id', 'nombre']);
    }
}
