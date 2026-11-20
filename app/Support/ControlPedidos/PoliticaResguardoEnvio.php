<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\Municipal\PoliticaFinancieraMunicipal;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;

/**
 * Autoridad del resguardo de envío municipal. No redefine cubierto ni custodia PDV.
 */
final class PoliticaResguardoEnvio
{
    public const MENSAJE_SIN_PAGO = 'El pedido aún no está pagado; no puede proceder para resguardo.';

    public const MENSAJE_CREDITO = 'Un crédito de Wizerp no habilita el resguardo de envío. El crédito no equivale a una transferencia pagada.';

    public const MENSAJE_BLOQUEO_DESPACHO = 'Este envío está en resguardo municipal. La persona auxiliar debe liberarlo antes de empacar o despachar.';

    public const MENSAJE_MIEMBRO = 'Esta remisión forma parte de un envío autorizado. No se despacha por su cuenta.';

    public const MENSAJE_CONJUNTO = 'El conjunto de remisiones todavía no está listo para salir.';

    public const MENSAJE_MERCANCIA = 'El total de mercancía no está cubierto. El transporte pendiente no cuenta como pagado.';

    public const MOTIVO_FECHA = 'fecha_posterior';

    public const MOTIVO_ACUMULACION = 'acumulacion';

    public const MOTIVO_COMPLEMENTO = 'espera_complemento';

    public const MOTIVOS = [self::MOTIVO_FECHA, self::MOTIVO_ACUMULACION, self::MOTIVO_COMPLEMENTO];

    public const CAUSA_MUNICIPAL = 'municipal_envio';

    public const MECANISMO_CEDIS = 'custodia_cedis';

    public const MECANISMO_LOCAL = 'custodia_local';

    public const MECANISMO_RESGUARDO = 'resguardo_envio';

    public const MECANISMOS = [self::MECANISMO_CEDIS, self::MECANISMO_LOCAL, self::MECANISMO_RESGUARDO];

    public const MENSAJE_CONTINUACION = 'Solicite la continuación del envío antes de liberar el resguardo.';

    public const MENSAJE_M09 = 'La entrega en fecha posterior exige mercancía y transporte cubiertos y auditados.';

    public const MENSAJE_ACUMULACION = 'Cierre la acumulación antes de solicitar la continuación.';

    public const MENSAJE_LIBERADO = 'El resguardo ya fue liberado. No se modifica el conjunto.';

    public const MENSAJE_RECHAZO = 'Hay exhibiciones de pago rechazadas sin corregir.';

    public const MENSAJE_PAGO_SIN_AUDITAR = 'La mercancía no tiene un pago auditado vigente. La persona auxiliar debe verificarlo antes de liberar.';

    public const MENSAJE_CONTINUACION_OBSOLETA = 'El conjunto o sus condiciones cambiaron después de solicitar la continuación. Vuelva a solicitarla.';

    public const MENSAJE_REACTIVAR = 'El resguardo ya fue liberado. No se reactiva sobre este envío.';

    public const MENSAJE_IRREVERSIBLE = 'Esa remisión ya se movió o se integró. No se desvincula desde el selector.';

    public const MENSAJE_CERRADO = 'El cliente ya definió el conjunto al cerrar la acumulación. No se agregan ni se quitan remisiones por el flujo ordinario; las operaciones físicas pendientes continúan.';

    public const MENSAJE_SALIO = 'El envío ya salió. No se agregan ni se quitan remisiones y las salidas registradas no se reescriben.';

    public const MENSAJE_INCIDENCIA_PAGO = 'Una remisión del conjunto tiene una incidencia de pago. La persona auxiliar debe revisarla antes de liberar o despachar.';

    public const MENSAJE_UBICACION_LOCAL = 'La integración local solo aplica cuando la mercancía y el envío se reúnen en la misma sucursal.';

    /** @var list<string> */
    private const ESTADOS_IRREVERSIBLES = ['en_traslado', 'recibido_cedis', 'pendiente_integracion', 'integrado'];

    /** @return array<string, mixed> */
    public static function datos(PedidoBma $pedido): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return is_array($json['resguardo_envio'] ?? null) ? $json['resguardo_envio'] : [];
    }

    public static function intencionActiva(PedidoBma $pedido): bool
    {
        return ConsultaMunicipal::es($pedido)
            && filter_var(self::datos($pedido)['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function motivo(PedidoBma $pedido): ?string
    {
        $motivo = self::datos($pedido)['motivo'] ?? null;

        return in_array($motivo, self::MOTIVOS, true) ? $motivo : null;
    }

    /**
     * M-10 y transporte diferido: avanzar con mercancía cubierta sin tratar el flete como pagado.
     * M-09 (fecha) y el ordinario municipal siguen exigiendo transporte cuando el costo es conocido.
     *
     * @param  RegistrarPagoPedidoBmaService::FASE_*  $fase
     */
    public static function exigeSoloMercanciaParaAvanzar(PedidoBma $pedido, string $fase): bool
    {
        if (! ConsultaMunicipal::es($pedido)) {
            return false;
        }
        if (self::transporteDiferido($pedido)) {
            return true;
        }
        if (! self::intencionActiva($pedido)) {
            return false;
        }
        if ($fase === RegistrarPagoPedidoBmaService::FASE_APROBAR) {
            // M-10: la aprobación documental no exige transporte pagado mientras el envío acumula o espera complemento.
            return in_array(self::motivo($pedido), [self::MOTIVO_ACUMULACION, self::MOTIVO_COMPLEMENTO], true);
        }
        if ($fase === RegistrarPagoPedidoBmaService::FASE_ENVIAR) {
            return true;
        }

        return in_array(self::motivo($pedido), [self::MOTIVO_ACUMULACION, self::MOTIVO_COMPLEMENTO], true);
    }

    public static function transporteDiferido(PedidoBma $pedido): bool
    {
        $eval = app(PoliticaFinancieraMunicipal::class)->evaluar($pedido);

        return ($eval['conocimiento_costo'] ?? null) === 'diferido';
    }

    public static function fuenteCreditoBloquea(PedidoBma $pedido): bool
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $credito = $json['credito_wizerp']['resultado'] ?? null;
        if (is_string($credito) && $credito !== '') {
            return true;
        }
        if (! $pedido->exists) {
            return false;
        }

        return PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->activosParaCobertura()
            ->where('forma_pago', 'credito')
            ->exists();
    }

    /** @return list<string> */
    public static function otrasRetencionesEsResguardo(PedidoBma $pedido): array
    {
        $causas = [];
        if ($pedido->esperando_pago_at) {
            $causas[] = 'espera_pago';
        }
        if ($pedido->esResguardoAbierto()) {
            $causas[] = 'resguardo_abierto';
        }
        if (self::intencionActiva($pedido)) {
            $causas[] = self::CAUSA_MUNICIPAL;
        }
        if (method_exists($pedido, 'tieneCancelacionOperativaActiva') && $pedido->tieneCancelacionOperativaActiva()) {
            $causas[] = 'cancelacion_operativa';
        }

        return $causas;
    }

    public static function puedeLimpiarFlagCompartido(PedidoBma $pedido): bool
    {
        return self::otrasRetencionesEsResguardo($pedido) === [];
    }

    public static function aplicarFlagCompartido(PedidoBma $pedido, bool $intencionActiva): bool
    {
        if ($intencionActiva) {
            return true;
        }

        return ! self::puedeLimpiarFlagCompartido($pedido);
    }

    public static function esMiembroConjunto(PedidoBma $pedido): bool
    {
        if ($pedido->pedido_principal_id !== null) {
            return true;
        }

        return $pedido->exists && $pedido->envioQueLoAnexa()->exists();
    }

    /**
     * Integrante de un envío municipal. Los complementos de Nacional o Tienda empacan en grupo y no pasan por esta regla.
     */
    public static function esMiembroConjuntoMunicipal(PedidoBma $pedido): bool
    {
        if ($pedido->pedido_principal_id !== null) {
            $principal = PedidoBma::query()->find($pedido->pedido_principal_id);
            if ($principal && ConsultaMunicipal::es($principal)) {
                return true;
            }
        }
        if (! $pedido->exists) {
            return false;
        }
        $anexo = $pedido->envioQueLoAnexa;
        $envio = $anexo ? PedidoBma::query()->find($anexo->pedido_envio_id) : null;

        return $envio !== null && ConsultaMunicipal::es($envio);
    }

    /** @return \Illuminate\Support\Collection<int, PedidoBma> */
    public static function miembros(PedidoBma $principal)
    {
        $ids = $principal->resguardosAnexados()->pluck('pedido_resguardo_id')
            ->merge($principal->complementos()->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $principal->id)
            ->values();

        if ($ids->isEmpty()) {
            return $principal->newCollection();
        }

        return PedidoBma::query()->whereIn('id', $ids->all())->orderBy('id')->get();
    }

    public static function motivoConjuntoIncompleto(PedidoBma $pedido): ?string
    {
        if ($pedido->tieneTrasladoCedisPendiente()) {
            return self::MENSAJE_CONJUNTO;
        }
        $pedido->loadMissing(['complementos', 'resguardosAnexados']);
        if (ConsultaMunicipal::es($pedido)) {
            // Una remisión vinculada no es una remisión integrada: cada una necesita su confirmación física.
            foreach (self::vinculos($pedido) as $vinculo) {
                if (! $vinculo['integrado']) {
                    return self::MENSAJE_CONJUNTO;
                }
            }

            return null;
        }
        foreach ($pedido->complementos as $complemento) {
            $json = is_array($complemento->solicitud_preparacion_json) ? $complemento->solicitud_preparacion_json : [];
            $retiro = $json['retiro_recoleccion']['estado'] ?? null;
            $local = $json['integracion_local']['estado'] ?? null;
            if (in_array($retiro, ['pendiente_traslado', 'en_traslado'], true) || $local === 'pendiente') {
                return self::MENSAJE_CONJUNTO;
            }
        }

        return null;
    }

    public static function estadoConjunto(PedidoBma $pedido): string
    {
        if (self::motivoConjuntoIncompleto($pedido)) {
            return 'recepcion_pendiente';
        }
        $vinculos = self::vinculos($pedido);
        if ($vinculos === [] && self::motivo($pedido) === self::MOTIVO_COMPLEMENTO && self::intencionActiva($pedido)) {
            return 'espera_complemento';
        }
        if ($vinculos === []) {
            return 'sin_vinculos';
        }

        return 'completo';
    }

    public static function conjuntoListoParaLiberar(PedidoBma $pedido): bool
    {
        $estado = self::estadoConjunto($pedido);

        return in_array($estado, ['completo', 'sin_vinculos'], true);
    }

    /** @return list<array<string, mixed>> */
    public static function vinculos(PedidoBma $pedido): array
    {
        $pedido->loadMissing(['resguardosAnexados.resguardo', 'complementos']);
        $filas = [];
        $vistos = [];
        foreach ($pedido->resguardosAnexados as $anexo) {
            $origen = $anexo->resguardo;
            if (! $origen) {
                continue;
            }
            $vistos[(int) $origen->id] = true;
            $filas[] = self::filaVinculo($pedido, $origen, self::MECANISMO_RESGUARDO);
        }
        foreach ($pedido->complementos as $origen) {
            if (isset($vistos[(int) $origen->id])) {
                continue;
            }
            $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
            $mecanismo = ! empty($json['integracion_local']['estado']) ? self::MECANISMO_LOCAL : self::MECANISMO_CEDIS;
            $filas[] = self::filaVinculo($pedido, $origen, $mecanismo);
        }

        return $filas;
    }

    /**
     * Estado físico por mecanismo. «integrado» solo con confirmación efectiva:
     * local confirmada en sucursal, recepción en CEDIS tras traslado, o identificación confirmada en CEDIS.
     */
    public static function estadoFisicoOrigen(PedidoBma $origen, string $mecanismo): string
    {
        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        if ($mecanismo === self::MECANISMO_LOCAL) {
            return (string) ($json['integracion_local']['estado'] ?? 'pendiente');
        }
        if ($mecanismo === self::MECANISMO_CEDIS) {
            $retiro = is_array($json['retiro_recoleccion'] ?? null) ? $json['retiro_recoleccion'] : [];
            $estado = (string) ($retiro['estado'] ?? 'pendiente_traslado');
            if ($estado === 'recibido_cedis' && empty($retiro['integrado_cedis'])) {
                return 'pendiente_integracion';
            }

            return $estado === '' ? 'pendiente_traslado' : $estado;
        }

        return ($json['integracion_envio']['estado'] ?? null) === 'integrado' ? 'integrado' : 'anexado';
    }

    public static function origenIntegrado(string $mecanismo, string $estado): bool
    {
        return $mecanismo === self::MECANISMO_CEDIS ? $estado === 'recibido_cedis' : $estado === 'integrado';
    }

    /** @return array<string, mixed> */
    private static function filaVinculo(PedidoBma $envio, PedidoBma $origen, string $mecanismo): array
    {
        $estado = self::estadoFisicoOrigen($origen, $mecanismo);
        $integrado = self::origenIntegrado($mecanismo, $estado);
        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        $retiro = is_array($json['retiro_recoleccion'] ?? null) ? $json['retiro_recoleccion'] : [];

        return [
            'origen_id' => (int) $origen->id,
            'folio' => $origen->folio,
            'remision' => $origen->numero_remision ?: $origen->folio_remision,
            'mecanismo' => $mecanismo,
            'estado' => $estado,
            'integrado' => $integrado,
            'pago_auditado' => $origen->pago_validado_at !== null,
            'incidencia_pago' => self::incidenciaPagoMiembro($origen),
            'estatus_sucursal' => $origen->estatus_sucursal,
            'area_responsable' => $integrado ? null : ($mecanismo === self::MECANISMO_LOCAL ? 'sucursal' : 'cedis'),
            'etapas' => [
                'seleccionada' => true,
                'vinculada' => true,
                'custodia_reservada' => $mecanismo === self::MECANISMO_RESGUARDO || $origen->pedido_principal_id !== null,
                'retirada_origen' => $mecanismo === self::MECANISMO_CEDIS ? in_array($estado, ['en_traslado', 'recibido_cedis', 'pendiente_integracion'], true) : $mecanismo === self::MECANISMO_LOCAL,
                'traslado_iniciado' => $mecanismo === self::MECANISMO_CEDIS && ! empty($retiro['salida_at']),
                'recibida' => $mecanismo === self::MECANISMO_CEDIS && ! empty($retiro['recibido_at']),
                'integrada' => $integrado,
            ],
            'exclusivo' => true,
            'puede_desvincular' => self::puedeDesvincular($envio, $origen, $estado),
        ];
    }

    /**
     * Facultad operativa física. Ventas (crear) y auditoría (auditar) no confirman hechos físicos.
     * Local: personal de la sucursal donde está la custodia. CEDIS/anexada: personal CEDIS con alcance sobre el pedido.
     */
    public static function puedeConfirmarIntegracionFisica(\App\Models\User $usuario, PedidoBma $origen, string $mecanismo): bool
    {
        if ($mecanismo === self::MECANISMO_LOCAL) {
            if (! ($usuario->can('pdv.resguardos.confirmar_custodia')
                || $usuario->can('control_pedidos.tienda.responder')
                || $usuario->can('control_pedidos.tienda.trasladar'))) {
                return false;
            }
            $sucursalId = (int) (\App\Models\PuntoVenta\ResguardoPdv::query()
                ->where('pedido_bma_id', $origen->id)
                ->where('estado', \App\Models\PuntoVenta\ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->value('sucursal_id') ?: $origen->sucursal_destino_id);

            return $sucursalId > 0 && $usuario->idsSucursalesOperables()->contains($sucursalId);
        }

        return $usuario->can('control_pedidos.cedis') && VisibilidadPedidoBma::puedeVerEnCedis($usuario, $origen);
    }

    public static function puedeDesvincular(PedidoBma $envio, PedidoBma $origen, ?string $estado = null): bool
    {
        if (self::motivoConjuntoNoEditable($envio) !== null) {
            return false;
        }
        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        $estado = $estado
            ?? ($json['integracion_local']['estado'] ?? null)
            ?? ($json['retiro_recoleccion']['estado'] ?? null)
            ?? ($json['integracion_envio']['estado'] ?? null)
            ?? 'anexado';

        return ! in_array($estado, self::ESTADOS_IRREVERSIBLES, true);
    }

    /** Huella de lo que decide si el conjunto puede salir: vínculos, estado físico y condiciones financieras. */
    public static function huellaConjunto(PedidoBma $pedido): string
    {
        $partes = [];
        foreach (self::vinculos($pedido) as $vinculo) {
            $partes[] = [(int) $vinculo['origen_id'], $vinculo['mecanismo'], $vinculo['estado'], (bool) $vinculo['incidencia_pago']];
        }
        sort($partes);

        // Longitud fija (64 hex) sin importar el tamaño del conjunto; el formato anterior contenía «#» y nunca coincide.
        return hash('sha256', json_encode([2, $partes, self::huellaFinanciera($pedido)]));
    }

    public static function huellaFinanciera(PedidoBma $pedido): string
    {
        $datos = self::datos($pedido);
        $pagos = $pedido->exists
            ? PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->activosParaCobertura()
                ->orderBy('id')->get(['id', 'monto'])
                ->map(fn ($p) => $p->id.':'.$p->monto)->implode(',')
            : '';
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return md5(json_encode([
            (string) $pedido->total_mercancia,
            (string) $pedido->costo_envio,
            (string) $pedido->saldo_a_favor,
            $pagos,
            $json['finanzas_municipales'] ?? null,
            $datos['motivo'] ?? null,
            $datos['fecha_solicitada'] ?? null,
            ! empty($datos['acumulacion_abierta']),
        ]));
    }

    public static function tieneRechazoSinCorregir(PedidoBma $pedido): bool
    {
        return $pedido->exists && PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado_revision', PedidoBmaPago::REVISION_RECHAZADO)
            ->whereDoesntHave('sustituto')
            ->exists();
    }

    /** Pago de mercancía auditado y vigente: ningún pago activo cambió o se agregó después de la auditoría. */
    public static function pagoAuditadoVigente(PedidoBma $pedido): bool
    {
        if (! $pedido->pago_validado_at || ! $pedido->exists || self::tieneRechazoSinCorregir($pedido)) {
            return false;
        }
        $pagos = PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->activosParaCobertura();
        if ((clone $pagos)->where('estado_revision', '!=', PedidoBmaPago::REVISION_VERIFICADO)->exists()) {
            return false;
        }

        return ! (clone $pagos)->where('created_at', '>', $pedido->pago_validado_at->copy()->addSecond())->exists();
    }

    public static function m09Validado(PedidoBma $pedido, ?array $finanzas = null): bool
    {
        if (self::motivo($pedido) !== self::MOTIVO_FECHA) {
            return false;
        }
        $finanzas ??= ConsultaMunicipal::es($pedido)
            ? app(PoliticaFinancieraMunicipal::class)->evaluar($pedido)
            : [];
        $fecha = self::datos($pedido)['fecha_solicitada'] ?? null;

        return $fecha
            && self::pagoAuditadoVigente($pedido)
            && (bool) ($finanzas['mercancia_cubierta'] ?? false)
            && (bool) ($finanzas['transporte_cubierto'] ?? false);
    }

    public static function m10EnAcumulacion(PedidoBma $pedido): bool
    {
        return self::intencionActiva($pedido)
            && self::motivo($pedido) === self::MOTIVO_ACUMULACION
            && (bool) (self::datos($pedido)['acumulacion_abierta'] ?? false);
    }

    /**
     * Hecho canónico de salida física: el estatus del pedido ya es enviado/en ruta/entregado o alguna tarea de
     * preparación tiene su despacho registrado. Pago, remisión, aprobación o «pendiente de envío» no lo acreditan.
     */
    public static function envioYaSalio(PedidoBma $pedido): bool
    {
        $pedido->loadMissing('estatus');
        if (in_array($pedido->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
            CatalogoEstatusPedido::FASE_ENTREGADO,
        ], true)) {
            return true;
        }

        return $pedido->exists && $pedido->tareasPreparacion()
            ->whereHas('cumplimientoFisico', fn ($q) => $q->whereNotNull('despachada_at'))
            ->exists();
    }

    public static function puedeReconducir(PedidoBma $pedido): bool
    {
        return ! $pedido->cancelado_at && ! self::envioYaSalio($pedido);
    }

    /** Conjunto comercial cerrado: el cliente lo declaró definitivo. Persiste aunque se guarde de nuevo la instrucción. */
    public static function acumulacionCerrada(PedidoBma $pedido): bool
    {
        $datos = self::datos($pedido);

        return ! empty($datos['acumulacion_cerrada_at']) && empty($datos['acumulacion_abierta']);
    }

    /** Motivo por el que la lista de remisiones ya no se edita por el flujo ordinario; null si aún es editable. */
    public static function motivoConjuntoNoEditable(PedidoBma $pedido): ?string
    {
        if (self::datos($pedido)['liberado_at'] ?? null) {
            return self::MENSAJE_LIBERADO;
        }
        if (self::envioYaSalio($pedido)) {
            return self::MENSAJE_SALIO;
        }
        if (self::acumulacionCerrada($pedido)) {
            return self::MENSAJE_CERRADO;
        }

        return null;
    }

    /** Vincular, desvincular o retirar custodia para el envío: modifica la lista de remisiones. */
    public static function assertConjuntoEditable(PedidoBma $pedido): void
    {
        $motivo = self::motivoConjuntoNoEditable($pedido);
        if ($motivo !== null) {
            throw new \RuntimeException($motivo);
        }
    }

    /** Integración física de una remisión ya vinculada: sigue permitida tras cerrar la acumulación, no tras liberar ni salir. */
    public static function assertIntegracionPermitida(PedidoBma $pedido): void
    {
        if (self::datos($pedido)['liberado_at'] ?? null) {
            throw new \RuntimeException(self::MENSAJE_LIBERADO);
        }
        if (self::envioYaSalio($pedido)) {
            throw new \RuntimeException(self::MENSAJE_SALIO);
        }
    }

    /** Sucursal donde se prepara el envío principal según su almacén; null si no se puede determinar. */
    public static function sucursalPreparacion(PedidoBma $principal): ?int
    {
        $principal->loadMissing('almacen');
        $id = $principal->almacen?->sucursal_id;

        return $id ? (int) $id : null;
    }

    /** Sucursal donde está la custodia vigente de la remisión; null si no hay custodia. */
    public static function sucursalCustodia(PedidoBma $origen): ?int
    {
        $id = \App\Models\PuntoVenta\ResguardoPdv::query()
            ->where('pedido_bma_id', $origen->id)
            ->where('estado', \App\Models\PuntoVenta\ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->value('sucursal_id');

        return $id ? (int) $id : null;
    }

    /**
     * La integración local exige custodia vigente y, si la preparación del principal está determinada,
     * que sea la misma sucursal. Si no está determinada no se inventa: confirma el personal de la sucursal.
     */
    public static function assertUbicacionIntegracionLocal(PedidoBma $origen, PedidoBma $principal): void
    {
        $custodia = self::sucursalCustodia($origen);
        if ($custodia === null) {
            throw new \RuntimeException('La mercancía ya no está en custodia vigente de la sucursal.');
        }
        $principal->loadMissing('almacen');
        if ($principal->almacen_id && $principal->almacen) {
            $preparacion = self::sucursalPreparacion($principal);
            if ($preparacion === null || $preparacion !== $custodia) {
                throw new \RuntimeException(self::MENSAJE_UBICACION_LOCAL);
            }
        }
    }

    /** Remisión integrante con pago no vigente (cancelada, rechazada, sustituida sin verificar o modificada tras auditar). */
    public static function incidenciaPagoMiembro(PedidoBma $origen): bool
    {
        return (bool) $origen->cancelado_at || ! self::pagoAuditadoVigente($origen);
    }

    /** @return list<array<string, mixed>> Vínculos con incidencia de pago. */
    public static function vinculosConIncidenciaPago(PedidoBma $pedido): array
    {
        return array_values(array_filter(self::vinculos($pedido), fn ($v) => $v['incidencia_pago']));
    }

    public static function assertPuedeEnviarAuditoria(PedidoBma $pedido): void
    {
        if (! self::intencionActiva($pedido)) {
            return;
        }
        if (self::fuenteCreditoBloquea($pedido)) {
            throw new \InvalidArgumentException(self::MENSAJE_CREDITO);
        }
        $eval = app(PoliticaFinancieraMunicipal::class)->evaluar($pedido);
        if (! ($eval['mercancia_cubierta'] ?? false)) {
            throw new \InvalidArgumentException(self::MENSAJE_SIN_PAGO);
        }
    }

    public static function assertPuedeEmpacar(?PedidoBma $pedido): void
    {
        self::assertPuedeDespachar($pedido);
    }

    public static function assertPuedeDespachar(?PedidoBma $pedido): void
    {
        if (! $pedido) {
            return;
        }
        if (self::esMiembroConjuntoMunicipal($pedido)) {
            throw new \RuntimeException(self::MENSAJE_MIEMBRO);
        }
        if (self::intencionActiva($pedido)) {
            throw new \RuntimeException(self::MENSAJE_BLOQUEO_DESPACHO);
        }
        $conjunto = self::motivoConjuntoIncompleto($pedido);
        if ($conjunto) {
            throw new \RuntimeException($conjunto);
        }
        if (ConsultaMunicipal::es($pedido)) {
            if ($pedido->esperando_pago_at) {
                throw new \RuntimeException('El pedido está en espera de pago; no se empaca ni se despacha.');
            }
            if (self::vinculosConIncidenciaPago($pedido) !== []) {
                throw new \RuntimeException(self::MENSAJE_INCIDENCIA_PAGO);
            }
            $datos = self::datos($pedido);
            if (! empty($datos['liberado_at']) && ! empty($datos['continuacion_huella'])
                && ! hash_equals((string) $datos['continuacion_huella'], self::huellaConjunto($pedido))) {
                throw new \RuntimeException(self::MENSAJE_CONTINUACION_OBSOLETA);
            }
        }
    }

    /**
     * Revalida la salida dentro de la sección crítica: recarga el principal con bloqueo y luego a sus miembros
     * por id ascendente (el mismo orden de vinculación y liberación), en lugar de confiar en el modelo cargado antes.
     */
    public static function assertPuedeDespacharBloqueado(?PedidoBma $pedido): void
    {
        if (! $pedido) {
            return;
        }
        $vigente = PedidoBma::query()->lockForUpdate()->with('estatus')->find($pedido->id) ?? $pedido;
        $miembros = self::miembros($vigente);
        if ($miembros->isNotEmpty()) {
            PedidoBma::query()->whereIn('id', $miembros->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        }
        self::assertPuedeDespachar($vigente);
    }

    /**
     * Único criterio de liberación. Lo usan la capacidad mostrada y la ejecución (esta última dentro de la transacción,
     * con el principal y los miembros bloqueados). Devuelve el bloqueo concreto o null si es ejecutable.
     */
    public static function motivoBloqueoLiberacion(PedidoBma $pedido): ?string
    {
        $datos = self::datos($pedido);
        if (! self::intencionActiva($pedido)) {
            return 'Este pedido no tiene un resguardo municipal activo.';
        }
        if (! empty($datos['liberado_at'])) {
            return self::MENSAJE_LIBERADO;
        }
        if (empty($datos['continuacion_solicitada_at'])) {
            return self::MENSAJE_CONTINUACION;
        }
        $motivo = self::motivo($pedido);
        if ($motivo === self::MOTIVO_ACUMULACION && ($datos['acumulacion_abierta'] ?? false)) {
            return self::MENSAJE_ACUMULACION;
        }
        if (self::tieneRechazoSinCorregir($pedido)) {
            return self::MENSAJE_RECHAZO;
        }
        $finanzas = app(PoliticaFinancieraMunicipal::class)->evaluar($pedido);
        if (! ($finanzas['mercancia_cubierta'] ?? false)) {
            return self::MENSAJE_SIN_PAGO;
        }
        if (! self::pagoAuditadoVigente($pedido)) {
            return self::MENSAJE_PAGO_SIN_AUDITAR;
        }
        if (self::vinculosConIncidenciaPago($pedido) !== []) {
            return self::MENSAJE_INCIDENCIA_PAGO;
        }
        if ($motivo === self::MOTIVO_FECHA && ! self::m09Validado($pedido, $finanzas)) {
            return self::MENSAJE_M09;
        }
        if (($datos['continuacion_huella'] ?? null) !== self::huellaConjunto($pedido)) {
            return self::MENSAJE_CONTINUACION_OBSOLETA;
        }
        if (! self::conjuntoListoParaLiberar($pedido)) {
            return self::MENSAJE_CONJUNTO;
        }

        return null;
    }

    public static function assertPuedeLiberar(PedidoBma $pedido): void
    {
        $datos = self::datos($pedido);
        if (! empty($datos['liberado_at']) && ! self::intencionActiva($pedido)) {
            return;
        }
        $motivo = self::motivoBloqueoLiberacion($pedido);
        if ($motivo === null) {
            return;
        }
        if (! self::intencionActiva($pedido)) {
            throw new \InvalidArgumentException($motivo);
        }
        throw new \RuntimeException($motivo);
    }

    public static function assertConjuntoMutable(PedidoBma $pedido): void
    {
        if (self::datos($pedido)['liberado_at'] ?? null) {
            throw new \RuntimeException(self::MENSAJE_LIBERADO);
        }
    }

    /** @return array<string, mixed> */
    public static function invalidarContinuacion(PedidoBma $pedido): array
    {
        return self::fusionarJson($pedido, [
            'continuacion_solicitada_at' => null,
            'continuacion_solicitada_por_id' => null,
            'continuacion_huella' => null,
            'continuacion_revision' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    public static function fusionarJson(PedidoBma $pedido, array $cambios): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $actual = is_array($json['resguardo_envio'] ?? null) ? $json['resguardo_envio'] : [];
        $json['resguardo_envio'] = array_merge($actual, $cambios);

        return $json;
    }

    /** @return array<string, mixed> */
    public static function evaluar(PedidoBma $pedido): array
    {
        $datos = self::datos($pedido);
        $activo = self::intencionActiva($pedido);
        $finanzas = ConsultaMunicipal::es($pedido)
            ? app(PoliticaFinancieraMunicipal::class)->evaluar($pedido)
            : null;
        $mercancia = (bool) ($finanzas['mercancia_cubierta'] ?? false);
        $consultaCerrada = ConsultaMunicipal::es($pedido)
            && ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA;
        $credito = self::fuenteCreditoBloquea($pedido);
        $solicitud = ConsultaMunicipal::es($pedido) && ! $pedido->cancelado_at;
        $motivoBloqueo = ($activo && ! $mercancia) ? self::MENSAJE_SIN_PAGO : ($credito && $activo ? self::MENSAJE_CREDITO : null);
        $m09 = self::m09Validado($pedido, $finanzas);
        $acumulacionAbierta = (bool) ($datos['acumulacion_abierta'] ?? false);
        $continuacion = ! empty($datos['continuacion_solicitada_at']);
        $conjunto = self::estadoConjunto($pedido);
        $conjuntoListo = self::conjuntoListoParaLiberar($pedido);
        $puedeContinuacion = $activo
            && ! ($datos['liberado_at'] ?? null)
            && self::vinculosConIncidenciaPago($pedido) === []
            && (
                (self::motivo($pedido) === self::MOTIVO_FECHA && $m09)
                || (self::motivo($pedido) === self::MOTIVO_ACUMULACION && ! $acumulacionAbierta && $conjuntoListo)
                || (self::motivo($pedido) === self::MOTIVO_COMPLEMENTO && $conjuntoListo)
            );
        $liberado = ! empty($datos['liberado_at']) && ! $activo;
        $bloqueoLiberacion = $activo ? self::motivoBloqueoLiberacion($pedido) : self::MENSAJE_LIBERADO;
        $estados = self::estadosVisibles($pedido, $finanzas, $conjunto, $liberado);

        return [
            'activo' => $activo,
            'motivo' => self::motivo($pedido),
            'fecha_solicitada' => $datos['fecha_solicitada'] ?? null,
            'acumulacion' => $acumulacionAbierta ? 'abierta' : (($datos['acumulacion_cerrada_at'] ?? null) ? 'cerrada' : null),
            'acumulacion_abierta' => $acumulacionAbierta,
            'conjunto_cerrado' => self::acumulacionCerrada($pedido),
            'envio_salio' => self::envioYaSalio($pedido),
            'motivo_conjunto_no_editable' => $solicitud ? self::motivoConjuntoNoEditable($pedido) : null,
            'continuacion_solicitada_at' => $datos['continuacion_solicitada_at'] ?? null,
            'liberado_at' => $datos['liberado_at'] ?? null,
            'causa_flag' => $datos['causa_flag'] ?? null,
            'otras_retenciones' => self::otrasRetencionesEsResguardo($pedido),
            'puede_limpiar_flag' => self::puedeLimpiarFlagCompartido($pedido),
            'exige_solo_mercancia' => self::exigeSoloMercanciaParaAvanzar($pedido, RegistrarPagoPedidoBmaService::FASE_VALIDAR),
            'transporte_diferido' => self::transporteDiferido($pedido),
            'm09_validado' => $m09,
            'm10_en_acumulacion' => self::m10EnAcumulacion($pedido),
            'cobertura_mercancia' => self::coberturaMercancia($pedido, $mercancia),
            'fuente_cobertura' => self::fuenteCobertura($pedido, $credito),
            'cobertura_transporte' => self::coberturaTransporte($finanzas),
            'cajas' => $pedido->numero_cajas === null ? 'N/A' : (int) $pedido->numero_cajas,
            'vinculos' => $solicitud ? self::vinculos($pedido) : [],
            'conjunto' => $conjunto,
            'motivo_bloqueo_resguardo' => $motivoBloqueo,
            'bloqueo_liberacion' => $activo ? $bloqueoLiberacion : null,
            'pago_auditado_vigente' => self::pagoAuditadoVigente($pedido),
            'pago_rechazado' => self::tieneRechazoSinCorregir($pedido),
            'huella_conjunto' => $activo ? self::huellaConjunto($pedido) : null,
            'estados' => $estados,
            'capacidades' => [
                'puede_activar_intencion' => $solicitud && self::puedeReconducir($pedido) && ! $credito,
                'puede_enviar_auditoria' => $consultaCerrada && $mercancia && ! $credito && ! ($activo && $motivoBloqueo),
                'puede_vincular' => $solicitud && self::motivoConjuntoNoEditable($pedido) === null,
                'puede_desvincular' => $solicitud && self::motivoConjuntoNoEditable($pedido) === null,
                'puede_cerrar_acumulacion' => $activo && self::motivo($pedido) === self::MOTIVO_ACUMULACION && $acumulacionAbierta,
                'puede_solicitar_continuacion' => $puedeContinuacion,
                'puede_liberar' => $bloqueoLiberacion === null,
                'puede_empacar' => $liberado || (! $activo && ! self::esMiembroConjunto($pedido) && self::motivoConjuntoIncompleto($pedido) === null),
                'puede_despachar' => $liberado || (! $activo && ! self::esMiembroConjunto($pedido) && self::motivoConjuntoIncompleto($pedido) === null),
                'puede_reconducir' => self::puedeReconducir($pedido),
            ],
        ];
    }

    /**
     * Estados distinguibles para la interfaz, derivados de campos existentes (sin máquina de estados nueva).
     *
     * @param  array<string, mixed>|null  $finanzas
     * @return list<string>
     */
    private static function estadosVisibles(PedidoBma $pedido, ?array $finanzas, string $conjunto, bool $liberado): array
    {
        $datos = self::datos($pedido);
        $activo = self::intencionActiva($pedido);
        if (! $activo && ! $liberado) {
            return [];
        }
        $e = [];
        $mercancia = (bool) ($finanzas['mercancia_cubierta'] ?? false);
        $exhibiciones = $pedido->exists && PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->activosParaCobertura()->exists();
        $auditado = self::pagoAuditadoVigente($pedido);
        if ($activo) {
            $e[] = 'intencion_registrada';
            $e[] = 'resguardo_vigente';
        }
        if (self::tieneRechazoSinCorregir($pedido)) {
            $e[] = 'pago_rechazado';
        } elseif ($auditado) {
            $e[] = 'pago_auditado';
        } elseif ($mercancia) {
            $e[] = $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR ? 'pendiente_auditoria' : 'pago_registrado_ventas';
        } elseif ($exhibiciones) {
            $e[] = 'pago_pendiente_registro';
        } else {
            $e[] = 'pago_pendiente_registro';
        }
        if (self::motivo($pedido) === self::MOTIVO_ACUMULACION) {
            $e[] = ($datos['acumulacion_abierta'] ?? false) ? 'acumulacion_abierta' : 'acumulacion_cerrada';
        }
        if (! empty($datos['continuacion_solicitada_at'])) {
            $e[] = 'continuacion_solicitada';
        }
        if ($conjunto === 'recepcion_pendiente') {
            $e[] = 'pendiente_integracion';
        }
        if ($liberado) {
            $e[] = 'liberacion_autorizada';
            if (self::motivoConjuntoIncompleto($pedido) === null) {
                $e[] = 'listo_continuar';
            }
        }

        return $e;
    }

    private static function coberturaMercancia(PedidoBma $pedido, bool $mercancia): string
    {
        $rechazada = $pedido->exists && PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado_revision', PedidoBmaPago::REVISION_RECHAZADO)
            ->whereDoesntHave('sustituto')
            ->exists();
        if ($rechazada) {
            return 'rechazada';
        }
        if ($pedido->pago_validado_at) {
            return 'auditada';
        }
        if ($mercancia) {
            return 'declarada';
        }

        return 'ausente';
    }

    private static function fuenteCobertura(PedidoBma $pedido, bool $credito): string
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $resultado = $json['credito_wizerp']['resultado'] ?? null;
        if ($credito) {
            return $resultado === 'aprobado' ? 'credito_aprobado' : ($resultado ? 'credito_pendiente' : 'credito_bloqueado');
        }
        if ((float) ($pedido->saldo_a_favor ?? 0) > 0) {
            return 'saf';
        }
        if ($pedido->exists && PedidoBmaPago::query()->where('pedido_bma_id', $pedido->id)->activosParaCobertura()->exists()) {
            return 'exhibicion';
        }

        return 'ninguna';
    }

    /** @param  array<string, mixed>|null  $finanzas */
    private static function coberturaTransporte(?array $finanzas): string
    {
        if (! $finanzas) {
            return 'no_aplica';
        }
        if (($finanzas['conocimiento_costo'] ?? null) === 'diferido') {
            return 'diferido';
        }
        if ($finanzas['transporte_cubierto'] ?? false) {
            return 'cubierto';
        }
        if (($finanzas['importe_transporte'] ?? null) !== null) {
            return 'conocido_exigible';
        }

        return 'pendiente';
    }
}
