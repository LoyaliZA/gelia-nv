<?php

namespace App\Services\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\CatalogoAlmacenSalida;
use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;

class ObtenerCatalogosPedidoBmaService
{
    public function ejecutar(): array
    {
        return [
            'estatus' => CatalogoEstatusPedido::where('activo', true)->orderBy('orden')->get(['id', 'codigo_interno', 'nombre_visual', 'color_hex', 'fase_ciclo']),
            'almacenes_salida' => CatalogoAlmacenSalida::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bancos' => CatalogoBanco::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'tipos_caja' => CatalogoTipoCajaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'peso_volumetrico', 'medidas']),
            'paqueterias' => CatalogoPaqueteriaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'costo_seguro_default']),
            'tipos_guia' => CatalogoTipoGuiaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'zonas' => CatalogoZonaPedido::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'envios_tienda' => CatalogoEnvioTienda::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'es_otro']),
        ];
    }
}
