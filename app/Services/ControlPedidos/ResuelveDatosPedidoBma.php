<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;

trait ResuelveDatosPedidoBma
{
    protected function resolverPesoVolumetrico(array $datos): ?float
    {
        if (empty($datos['catalogo_tipo_caja_id'])) {
            return null;
        }

        $caja = CatalogoTipoCajaPedido::find($datos['catalogo_tipo_caja_id']);

        return $caja ? (float) $caja->peso_volumetrico : null;
    }

    protected function resolverSaldoFavor(array $datos): float
    {
        if (! empty($datos['saf_aplicaciones']) && is_array($datos['saf_aplicaciones'])) {
            $suma = 0.0;
            foreach ($datos['saf_aplicaciones'] as $item) {
                $suma += (float) ($item['monto'] ?? 0);
            }

            return round($suma, 2);
        }

        $aplica = filter_var($datos['aplica_saldo_favor'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $aplica ? (float) ($datos['saldo_a_favor'] ?? 0) : 0.0;
    }

    protected function resolverEnviaOtraPersona(array $datos): array
    {
        $aplica = filter_var($datos['envia_a_otra_persona'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'envia_a_otra_persona' => $aplica,
            'envia_otra_persona' => $aplica ? ($datos['envia_otra_persona'] ?? null) : null,
        ];
    }

    protected function resolverTotales(array $datos): array
    {
        $mercancia = (float) ($datos['total_mercancia'] ?? 0);
        $envio = isset($datos['costo_envio']) && $datos['costo_envio'] !== '' && $datos['costo_envio'] !== null
            ? (float) $datos['costo_envio']
            : 0.0;
        $saldoFavor = $this->resolverSaldoFavor($datos);
        $seguro = $this->resolverSeguro($datos, $mercancia, $envio);

        return [
            'total_mercancia' => $mercancia,
            'costo_envio' => isset($datos['costo_envio']) && $datos['costo_envio'] !== '' && $datos['costo_envio'] !== null
                ? $envio
                : null,
            'aplica_seguro' => $seguro['aplica_seguro'],
            'costo_seguro' => $seguro['costo_seguro'],
            'saldo_a_favor' => $saldoFavor,
            'total_a_cobrar' => PedidoBma::calcularTotal(
                $mercancia,
                $envio,
                $seguro['aplica_seguro'],
                $seguro['costo_seguro'],
                $saldoFavor
            ),
        ];
    }

    protected function resolverSeguro(array $datos, float $mercancia, float $envio): array
    {
        $paqueteriaId = $datos['catalogo_paqueteria_id'] ?? null;
        if (!$paqueteriaId) {
            return ['aplica_seguro' => false, 'costo_seguro' => 0.0];
        }

        $paqueteria = CatalogoPaqueteriaPedido::find($paqueteriaId);
        $calc = app(CalcularSeguroPedidoService::class);
        $costo = $calc->calcularCosto($paqueteria?->nombre, $envio, $mercancia);
        $aplicaSeguro = $calc->tieneCobertura($paqueteria?->nombre)
            && filter_var($datos['aplica_seguro'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'aplica_seguro' => $aplicaSeguro,
            'costo_seguro' => $costo,
        ];
    }

    protected function resolverEnvioTiendaDesdeOrigen(array $datos): array
    {
        $origen = ! empty($datos['origen_id'])
            ? CatalogoOrigenPedido::find($datos['origen_id'])
            : null;

        if (! $origen) {
            return [
                'catalogo_envio_tienda_id' => null,
                'envio_tienda_otro' => null,
            ];
        }

        $termino = $origen->requiere_logistica ? 'Envío' : 'Tienda';

        $match = CatalogoEnvioTienda::query()
            ->where('activo', true)
            ->where('es_otro', false)
            ->where(function ($q) use ($termino, $origen) {
                $q->where('nombre', $termino)
                    ->orWhere('nombre', 'like', $termino.'%');
                if ($origen->requiere_logistica) {
                    $q->orWhere('nombre', 'like', 'Envio%');
                }
            })
            ->orderBy('nombre')
            ->first();

        return [
            'catalogo_envio_tienda_id' => $match?->id,
            'envio_tienda_otro' => null,
        ];
    }

    protected function resolverTipoOperacionEnvioId(array $datos): ?int
    {
        $esResguardo = filter_var($datos['es_resguardo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $modo = strtolower(trim((string) ($datos['modo_resguardo'] ?? '')));

        if ($esResguardo) {
            // Preferencia explícita de modo; si no, inferir de tipo enviado (edición legacy).
            if ($modo === 'complementario') {
                return CatalogoTipoOperacionEnvio::porCodigo(
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO
                )?->id ?? CatalogoTipoOperacionEnvio::idNormal();
            }

            if ($modo === 'abierto') {
                return CatalogoTipoOperacionEnvio::porCodigo(
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO
                )?->id ?? CatalogoTipoOperacionEnvio::idNormal();
            }

            if (!empty($datos['tipo_operacion_envio_id'])) {
                $tipo = CatalogoTipoOperacionEnvio::find((int) $datos['tipo_operacion_envio_id']);
                if ($tipo && in_array($tipo->codigo, [
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO,
                    CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
                ], true)) {
                    return $tipo->id;
                }
            }

            return CatalogoTipoOperacionEnvio::porCodigo(
                CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO
            )?->id ?? CatalogoTipoOperacionEnvio::idNormal();
        }

        // No resguardo: nunca persistir tipos de resguardo aunque el FE envíe id viejo.
        $paqueteriaId = $datos['catalogo_paqueteria_id'] ?? null;
        if ($paqueteriaId) {
            $paqueteria = CatalogoPaqueteriaPedido::find($paqueteriaId);
            if ($paqueteria?->permiteCostoDiferido()) {
                return CatalogoTipoOperacionEnvio::porCodigo(
                    CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO
                )?->id ?? CatalogoTipoOperacionEnvio::idNormal();
            }
        }

        return CatalogoTipoOperacionEnvio::idNormal();
    }

    protected function atributosPedidoBase(array $datos): array
    {
        $tipoId = $this->resolverTipoOperacionEnvioId($datos);
        $tipo = $tipoId ? CatalogoTipoOperacionEnvio::find($tipoId) : null;
        $esResguardoAbierto = $tipo?->esResguardoAbierto() ?? false;
        $esComplementario = $tipo?->esResguardoComplementario() ?? false;

        if ($esComplementario && !empty($datos['pedido_principal_id'])) {
            $principal = PedidoBma::find((int) $datos['pedido_principal_id']);
            if ($principal) {
                $datos = $this->aplicarLogisticaDesdePrincipal($datos, $principal);
            }
        }

        if ($esResguardoAbierto || $esComplementario) {
            $datos['costo_envio'] = null;
            $datos['numero_cajas'] = null;
            $datos['peso_real_kg'] = null;
            $datos['peso_cobrado_guia_kg'] = null;
            $datos['es_resguardo'] = true;
        }

        $clienteProporcionaGuia = filter_var($datos['cliente_proporciona_guia'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($clienteProporcionaGuia) {
            $datos['costo_envio'] = null;
            $datos['aplica_seguro'] = false;
            $datos['catalogo_tipo_guia_id'] = null;
            $datos['catalogo_zona_id'] = null;
        }

        $totales = $this->resolverTotales($datos);
        $envia = $this->resolverEnviaOtraPersona($datos);
        $envioTienda = $this->resolverEnvioTiendaDesdeOrigen($datos);
        $pesoVolumetrico = ($esResguardoAbierto || $esComplementario) ? null : $this->resolverPesoVolumetrico($datos);
        $pesoReal = isset($datos['peso_real_kg']) && $datos['peso_real_kg'] !== '' && $datos['peso_real_kg'] !== null
            ? (float) $datos['peso_real_kg']
            : null;
        $pesoCobrado = ($esResguardoAbierto || $esComplementario)
            ? null
            : PedidoBma::calcularPesoCobradoGuia($pesoReal, $pesoVolumetrico);

        $attrs = array_merge([
            'folio_remision' => isset($datos['folio_remision']) && trim((string) $datos['folio_remision']) !== ''
                ? trim((string) $datos['folio_remision'])
                : null,
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'origen_id' => $datos['origen_id'] ?? null,
            'tipo_operacion_envio_id' => $tipoId,
            'pedido_principal_id' => $esComplementario && !empty($datos['pedido_principal_id'])
                ? (int) $datos['pedido_principal_id']
                : null,
            'almacen_id' => $datos['almacen_id'] ?? null,
            // Banco receptor vive en exhibiciones; no persistir banco general desde el form.
            'catalogo_tipo_caja_id' => $datos['catalogo_tipo_caja_id'] ?? null,
            'numero_cajas' => isset($datos['numero_cajas']) && $datos['numero_cajas'] !== ''
                ? (int) $datos['numero_cajas']
                : null,
            'peso_real_kg' => $pesoReal,
            'peso_volumetrico_kg' => $pesoVolumetrico,
            'peso_cobrado_guia_kg' => $pesoCobrado,
            'catalogo_paqueteria_id' => $datos['catalogo_paqueteria_id'] ?? null,
            'catalogo_tipo_guia_id' => $datos['catalogo_tipo_guia_id'] ?? null,
            'catalogo_zona_id' => $datos['catalogo_zona_id'] ?? null,
            'codigo_postal' => $datos['codigo_postal'] ?? null,
            'domicilio_entrega' => $datos['domicilio_entrega'] ?? null,
            'cliente_direccion_id' => isset($datos['cliente_direccion_id']) && $datos['cliente_direccion_id'] !== ''
                ? (int) $datos['cliente_direccion_id']
                : null,
            'es_resguardo' => $esResguardoAbierto || $esComplementario
                ? true
                : filter_var($datos['es_resguardo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'cliente_proporciona_guia' => $clienteProporcionaGuia,
            'anexar_remision' => filter_var($datos['anexar_remision'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'comentarios_drive' => $datos['comentarios_drive'] ?? null,
        ], $envioTienda, $envia, $totales);

        if ($clienteProporcionaGuia) {
            $attrs['costo_envio'] = null;
            $attrs['aplica_seguro'] = false;
            $attrs['costo_seguro'] = 0;
            $attrs['catalogo_tipo_guia_id'] = null;
            $attrs['catalogo_zona_id'] = null;
        }

        if ($esResguardoAbierto || $esComplementario) {
            $attrs['costo_envio'] = null;
            $attrs['numero_cajas'] = null;
            $attrs['peso_real_kg'] = null;
            $attrs['peso_cobrado_guia_kg'] = null;
            $attrs['peso_volumetrico_kg'] = null;
            $attrs['es_resguardo'] = true;
        }

        return $attrs;
    }

    /** Copia logística del padre hacia el hijo (peso/cajas/costo se anulan aparte). */
    public function aplicarLogisticaDesdePrincipal(array $datos, PedidoBma $principal): array
    {
        $datos['cliente_id'] = $principal->cliente_id;
        $datos['origen_id'] = $principal->origen_id;
        $datos['almacen_id'] = $principal->almacen_id;
        $datos['cliente_direccion_id'] = $principal->cliente_direccion_id;
        $datos['domicilio_entrega'] = $principal->domicilio_entrega;
        $datos['codigo_postal'] = $principal->codigo_postal;
        $datos['catalogo_paqueteria_id'] = $principal->catalogo_paqueteria_id;
        $datos['catalogo_tipo_guia_id'] = $principal->catalogo_tipo_guia_id;
        $datos['catalogo_zona_id'] = $principal->catalogo_zona_id;
        $datos['catalogo_tipo_caja_id'] = $principal->catalogo_tipo_caja_id;
        $datos['envia_a_otra_persona'] = $principal->envia_a_otra_persona;
        $datos['envia_otra_persona'] = $principal->envia_otra_persona;
        $datos['anexar_remision'] = $principal->anexar_remision;
        $datos['costo_envio'] = null;
        $datos['numero_cajas'] = null;
        $datos['peso_real_kg'] = null;
        $datos['peso_cobrado_guia_kg'] = null;

        return $datos;
    }
}
