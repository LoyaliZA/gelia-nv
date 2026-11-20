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

    public const MENSAJE_IRREVERSIBLE = 'Esa remisión ya se movió o se integró. No se desvincula desde el selector.';

    /** @var list<string> */
    private const ESTADOS_IRREVERSIBLES = ['en_traslado', 'recibido_cedis', 'integrado'];

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
        if ($fase === RegistrarPagoPedidoBmaService::FASE_APROBAR) {
            return false;
        }
        if (! self::intencionActiva($pedido)) {
            return false;
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
            $filas[] = self::filaVinculo($pedido, $origen, self::MECANISMO_RESGUARDO, 'anexado');
        }
        foreach ($pedido->complementos as $origen) {
            if (isset($vistos[(int) $origen->id])) {
                continue;
            }
            $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
            $local = $json['integracion_local']['estado'] ?? null;
            $retiro = $json['retiro_recoleccion']['estado'] ?? null;
            $mecanismo = $local ? self::MECANISMO_LOCAL : self::MECANISMO_CEDIS;
            $filas[] = self::filaVinculo($pedido, $origen, $mecanismo, $local ?: ($retiro ?: 'vinculado'));
        }

        return $filas;
    }

    /** @return array<string, mixed> */
    private static function filaVinculo(PedidoBma $envio, PedidoBma $origen, string $mecanismo, string $estado): array
    {
        return [
            'origen_id' => (int) $origen->id,
            'folio' => $origen->folio,
            'remision' => $origen->numero_remision ?: $origen->folio_remision,
            'mecanismo' => $mecanismo,
            'estado' => $estado,
            'exclusivo' => true,
            'puede_desvincular' => self::puedeDesvincular($envio, $origen, $estado),
        ];
    }

    public static function puedeDesvincular(PedidoBma $envio, PedidoBma $origen, ?string $estado = null): bool
    {
        if (self::datos($envio)['liberado_at'] ?? null) {
            return false;
        }
        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        $estado = $estado
            ?? ($json['integracion_local']['estado'] ?? null)
            ?? ($json['retiro_recoleccion']['estado'] ?? null)
            ?? 'anexado';

        return ! in_array($estado, self::ESTADOS_IRREVERSIBLES, true);
    }

    public static function huellaConjunto(PedidoBma $pedido): string
    {
        $partes = [];
        foreach (self::vinculos($pedido) as $vinculo) {
            $partes[] = $vinculo['origen_id'].':'.$vinculo['mecanismo'].':'.$vinculo['estado'];
        }
        sort($partes);

        return implode('|', $partes);
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
            && $pedido->pago_validado_at
            && (bool) ($finanzas['mercancia_cubierta'] ?? false)
            && (bool) ($finanzas['transporte_cubierto'] ?? false);
    }

    public static function m10EnAcumulacion(PedidoBma $pedido): bool
    {
        return self::intencionActiva($pedido)
            && self::motivo($pedido) === self::MOTIVO_ACUMULACION
            && (bool) (self::datos($pedido)['acumulacion_abierta'] ?? false);
    }

    public static function puedeReconducir(PedidoBma $pedido): bool
    {
        if ($pedido->cancelado_at) {
            return false;
        }
        $fase = $pedido->estatus?->fase_ciclo;
        if (in_array($fase, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
            CatalogoEstatusPedido::FASE_ENTREGADO,
        ], true)) {
            return false;
        }
        if ($pedido->tareasPreparacion()
            ->whereHas('cumplimientoFisico', fn ($q) => $q->whereNotNull('despachada_at'))
            ->exists()) {
            return false;
        }

        return true;
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
        if (self::esMiembroConjunto($pedido)) {
            throw new \RuntimeException(self::MENSAJE_MIEMBRO);
        }
        if (self::intencionActiva($pedido)) {
            throw new \RuntimeException(self::MENSAJE_BLOQUEO_DESPACHO);
        }
        $conjunto = self::motivoConjuntoIncompleto($pedido);
        if ($conjunto) {
            throw new \RuntimeException($conjunto);
        }
    }

    public static function assertPuedeLiberar(PedidoBma $pedido): void
    {
        $datos = self::datos($pedido);
        if (! empty($datos['liberado_at']) && ! self::intencionActiva($pedido)) {
            return;
        }
        if (! self::intencionActiva($pedido)) {
            throw new \InvalidArgumentException('Este pedido no tiene un resguardo municipal activo.');
        }
        if (empty($datos['continuacion_solicitada_at'])) {
            throw new \RuntimeException(self::MENSAJE_CONTINUACION);
        }
        $huella = $datos['continuacion_huella'] ?? null;
        if ($huella !== null && $huella !== self::huellaConjunto($pedido)) {
            throw new \RuntimeException('El conjunto cambió después de solicitar la continuación. Vuelva a solicitarla.');
        }
        if (! self::conjuntoListoParaLiberar($pedido)) {
            throw new \RuntimeException(self::MENSAJE_CONJUNTO);
        }
        $finanzas = app(PoliticaFinancieraMunicipal::class)->evaluar($pedido);
        if (self::motivo($pedido) === self::MOTIVO_FECHA && ! self::m09Validado($pedido, $finanzas)) {
            throw new \RuntimeException(self::MENSAJE_M09);
        }
        if (self::motivo($pedido) === self::MOTIVO_ACUMULACION && ($datos['acumulacion_abierta'] ?? false)) {
            throw new \RuntimeException(self::MENSAJE_ACUMULACION);
        }
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
            && (
                (self::motivo($pedido) === self::MOTIVO_FECHA && $m09)
                || (self::motivo($pedido) === self::MOTIVO_ACUMULACION && ! $acumulacionAbierta && $conjuntoListo)
                || (self::motivo($pedido) === self::MOTIVO_COMPLEMENTO && $conjuntoListo)
            );
        $liberado = ! empty($datos['liberado_at']) && ! $activo;

        return [
            'activo' => $activo,
            'motivo' => self::motivo($pedido),
            'fecha_solicitada' => $datos['fecha_solicitada'] ?? null,
            'acumulacion' => $acumulacionAbierta ? 'abierta' : (($datos['acumulacion_cerrada_at'] ?? null) ? 'cerrada' : null),
            'acumulacion_abierta' => $acumulacionAbierta,
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
            'capacidades' => [
                'puede_activar_intencion' => $solicitud && self::puedeReconducir($pedido) && ! $credito,
                'puede_enviar_auditoria' => $consultaCerrada && $mercancia && ! $credito && ! ($activo && $motivoBloqueo),
                'puede_vincular' => $solicitud && empty($datos['liberado_at']),
                'puede_desvincular' => $solicitud && empty($datos['liberado_at']),
                'puede_cerrar_acumulacion' => $activo && self::motivo($pedido) === self::MOTIVO_ACUMULACION && $acumulacionAbierta,
                'puede_solicitar_continuacion' => $puedeContinuacion,
                'puede_liberar' => $activo && $continuacion && $conjuntoListo,
                'puede_empacar' => $liberado || (! $activo && ! self::esMiembroConjunto($pedido) && self::motivoConjuntoIncompleto($pedido) === null),
                'puede_despachar' => $liberado || (! $activo && ! self::esMiembroConjunto($pedido) && self::motivoConjuntoIncompleto($pedido) === null),
                'puede_reconducir' => self::puedeReconducir($pedido),
            ],
        ];
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
