<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;

/**
 * Lo que la interfaz necesita saber de las piezas adicionales (M-03b) de un envío municipal: si Ventas puede agregarlas,
 * qué complementos existen, en qué punto están y quién puede actuar hoy. Los permisos se vuelven a validar en servidor.
 */
final class PresentadorPiezasAdicionales
{
    /** @return array{puede_agregar: bool, motivo: ?string, items: list<array<string, mixed>>}|null */
    public static function para(PedidoBma $principal, ?User $usuario): ?array
    {
        if (! ConsultaMunicipal::es($principal) || $principal->esComplemento()) {
            return null;
        }
        $items = PedidoBma::query()
            ->with(['estatus:id,fase_ciclo,nombre_visual', 'documentos:id,pedido_bma_id,tipo'])
            ->where('pedido_principal_id', $principal->id)
            ->whereNotNull('solicitud_preparacion_json->'.ComplementosMunicipales::CLAVE)
            ->orderBy('id')
            ->get();
        $tieneRemision = filled($principal->numero_remision) || $principal->tieneRemision();
        $salio = RecorridoFisicoMunicipal::envioYaSalioParaAjustes($principal);
        $esVendedora = $usuario && VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $principal);
        if ($items->isEmpty() && ! ($tieneRemision && $esVendedora)) {
            return null;
        }

        $ubicacion = IntegrarComplementoMunicipalService::ubicacion($principal);
        $mecanismo = $ubicacion === IntegrarComplementoMunicipalService::UBICACION_PDV
            ? PoliticaResguardoEnvio::MECANISMO_LOCAL
            : PoliticaResguardoEnvio::MECANISMO_RESGUARDO;
        $puedeConfirmar = $usuario
            && ! $salio
            && $ubicacion !== IntegrarComplementoMunicipalService::UBICACION_TRANSITO
            && PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $principal, $mecanismo);

        $motivo = null;
        if ($principal->cancelado_at) {
            $motivo = 'El pedido está cancelado.';
        } elseif ($salio) {
            $motivo = RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO;
        } elseif (! $tieneRemision) {
            $motivo = 'Sin remisión se modifica el pedido original.';
        }

        return [
            'puede_agregar' => (bool) $esVendedora && $motivo === null,
            'motivo' => $motivo,
            'ubicacion' => $ubicacion,
            'items' => $items->map(fn (PedidoBma $c) => self::item($c, (bool) $esVendedora, (bool) $puedeConfirmar))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(PedidoBma $c, bool $esVendedora, bool $puedeConfirmar): array
    {
        $estado = ComplementosMunicipales::estado($c);
        $fase = $c->estatus?->fase_ciclo;
        $abierto = $estado === ComplementosMunicipales::ESTADO_ABIERTO && ! $c->cancelado_at;
        $editable = $abierto && in_array($fase, [CatalogoEstatusPedido::FASE_BORRADOR, CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA], true);
        $pagoRegistrado = $c->pagosExhibicion()->activosParaCobertura()->exists();
        $pagado = $c->pago_validado_at !== null && ! PoliticaResguardoEnvio::incidenciaPagoMiembro($c);
        $remisionado = filled($c->numero_remision) || $c->tieneRemision();

        if ($estado === ComplementosMunicipales::ESTADO_INTEGRADO) {
            $etiqueta = 'Integradas';
        } elseif ($c->cancelado_at) {
            $etiqueta = 'Canceladas';
        } elseif ($fase === CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA) {
            $etiqueta = 'Rechazadas por el auxiliar';
        } elseif ($editable) {
            $etiqueta = $pagoRegistrado ? 'Listas para enviar a revisión' : 'Pendientes de pago';
        } elseif (! $pagado || ! $remisionado) {
            $etiqueta = $pagado ? 'Pendientes de remisión' : 'En revisión del auxiliar';
        } else {
            $etiqueta = 'Pendientes de integrar';
        }

        return [
            'id' => $c->id,
            'folio' => $c->folio,
            'folio_pedido' => $c->folio_remision,
            'estado' => $estado,
            'etiqueta' => $etiqueta,
            'total_mercancia' => $c->total_mercancia,
            'piezas' => ComplementosMunicipales::marca($c)['piezas'] ?? [],
            'pago_registrado' => $pagoRegistrado,
            'pagado' => $pagado,
            'remisionado' => $remisionado,
            'puede_registrar_pago' => $esVendedora && $editable,
            'puede_enviar' => $esVendedora && $editable && $pagoRegistrado,
            'puede_integrar' => $puedeConfirmar && $abierto && $pagado && $remisionado,
        ];
    }
}
