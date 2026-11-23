<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService;
use App\Services\ControlPedidos\Municipal\ResolverSucursalDestinoMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;

/**
 * Lo que la interfaz necesita del cambio de un envío municipal a recolección en sucursal: quién puede solicitarlo,
 * en qué punto está y quién puede verificarlo hoy. Los permisos se vuelven a validar en servidor.
 */
final class PresentadorCambioATienda
{
    /** @return array<string, mixed>|null */
    public static function para(PedidoBma $pedido, ?User $usuario): ?array
    {
        if (! RecorridoFisicoMunicipal::aplica($pedido) || $pedido->esComplemento()) {
            return null;
        }
        $datos = CambioMunicipalATienda::datos($pedido);
        $salio = RecorridoFisicoMunicipal::envioYaSalioParaAjustes($pedido);
        $ubicacion = IntegrarComplementoMunicipalService::ubicacion($pedido);
        $esVendedora = $usuario && VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido);

        $puedeSolicitar = (bool) $esVendedora
            && $datos === null
            && ! $salio
            && ! $pedido->cancelado_at
            && $pedido->pedidoPagadoORemisionado()
            && ! ComplementosMunicipales::hayAbiertos($pedido);
        if ($datos === null && ! $puedeSolicitar) {
            return null;
        }

        $mecanismo = $ubicacion === IntegrarComplementoMunicipalService::UBICACION_PDV
            ? PoliticaResguardoEnvio::MECANISMO_LOCAL
            : PoliticaResguardoEnvio::MECANISMO_RESGUARDO;
        $requiereGerencia = (bool) ($datos['requiere_autorizacion_gerencia'] ?? false);
        $puedeAplicar = $usuario
            && ($datos['estado'] ?? null) === CambioMunicipalATienda::ESTADO_SOLICITADO
            && ! $salio
            && ! $requiereGerencia
            && ! $pedido->cancelado_at
            && $ubicacion !== IntegrarComplementoMunicipalService::UBICACION_TRANSITO
            && PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $pedido, $mecanismo);

        $etiqueta = match ($datos['estado'] ?? null) {
            CambioMunicipalATienda::ESTADO_SOLICITADO => $requiereGerencia
                ? 'Solicitado, requiere autorización de gerencia'
                : ($ubicacion === IntegrarComplementoMunicipalService::UBICACION_TRANSITO ? 'Solicitado, se verifica al recibir' : 'Solicitado, pendiente de verificar'),
            CambioMunicipalATienda::ESTADO_APLICADO => 'Verificado, recoge en sucursal',
            default => null,
        };

        $saldo = is_array($datos['saldo_envio'] ?? null) ? $datos['saldo_envio'] : null;
        // S-06: solo Administración, con el cambio verificado, una vez por cambio y nunca quien vendió o solicitó.
        $puedeAutorizarSaldo = $usuario
            && $usuario->can('saldos_favor.autorizar_envio')
            && ($datos['estado'] ?? null) === CambioMunicipalATienda::ESTADO_APLICADO
            && $saldo === null
            && $pedido->pago_validado_at !== null
            && ! in_array((int) $usuario->id, [(int) $pedido->vendedor_id, (int) ($datos['solicitado_por_id'] ?? 0)], true);

        return [
            'estado' => $datos['estado'] ?? null,
            'etiqueta' => $etiqueta,
            'motivo' => $datos['motivo'] ?? null,
            'error_captura' => (bool) ($datos['error_captura'] ?? false),
            'requiere_autorizacion_gerencia' => $requiereGerencia,
            'ubicacion' => $ubicacion,
            'puede_solicitar' => $puedeSolicitar,
            'puede_aplicar' => (bool) $puedeAplicar,
            'puede_autorizar_saldo' => (bool) $puedeAutorizarSaldo,
            'saldo_envio' => $saldo ? ['folio' => $saldo['folio'] ?? null, 'monto' => $saldo['monto'] ?? null] : null,
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'sucursales' => $puedeSolicitar ? self::sucursales() : [],
        ];
    }

    /** @return list<array{id: int, nombre: string}> */
    private static function sucursales(): array
    {
        $ids = app(ResolverSucursalDestinoMunicipal::class)->habilitadas();
        if ($ids === []) {
            return [];
        }

        return Sucursal::query()->whereIn('id', $ids)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (Sucursal $s) => ['id' => (int) $s->id, 'nombre' => (string) $s->nombre])->all();
    }
}
