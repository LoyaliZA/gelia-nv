<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\User;
use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\SnapshotHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un grupo en configuración en N envíos: el origen queda como envío 1 con la caja de menor orden
 * y se crea un PedidoBma por cada caja restante. Todo o nada, en una sola transacción.
 * Camino directo: con hechos previos (pago, SAF, cierre, cancelación, remisión, guía, empaque, recolección) no se
 * mueve nada y el grupo pasa a `regularizacion_pendiente`. El evento en vivo lo emite el observador de PedidoBma tras el commit.
 */
class ConfirmarMultienvio
{
    /** Tope sano por envío: cabe en decimal(12,2). */
    private const MAX_CENTAVOS = 99_999_999_999;

    public function __construct(
        private readonly EvaluarMultienvioNacional $evaluador,
        private readonly DetectarHechosPreviosMultienvio $hechosPrevios,
        private readonly MaterializarEnvioMultienvio $materializar,
        private readonly CalcularSeguroPedidoService $seguro,
        private readonly RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(MultienvioNacional $grupo, int $usuarioId, ?string $revisionEsperada = null): MultienvioNacional
    {
        $usuario = User::query()->findOrFail($usuarioId);
        $this->materializar->reiniciarCopias();

        try {
            return $this->confirmar($grupo, $usuario, $usuarioId, $revisionEsperada);
        } catch (\Throwable $e) {
            // Si la BD se revirtió, los archivos copiados para el soporte de cada envío no; pero si el grupo ya quedó
            // confirmado (fallo posterior al commit) esas copias son los documentos vigentes de los envíos.
            $confirmado = MultienvioNacional::query()->whereKey($grupo->id)->whereIn('estado', [
                MultienvioNacional::ESTADO_ACTIVO,
                MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE,
            ])->exists();
            if (! $confirmado) {
                $this->materializar->eliminarCopias();
            }
            throw $e;
        } finally {
            $this->materializar->reiniciarCopias();
        }
    }

    private function confirmar(MultienvioNacional $grupo, User $usuario, int $usuarioId, ?string $revisionEsperada): MultienvioNacional
    {
        return DB::transaction(function () use ($grupo, $usuario, $usuarioId, $revisionEsperada) {
            $origen = PedidoBma::query()->lockForUpdate()->findOrFail($grupo->pedido_origen_id);

            if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $origen)) {
                throw new AuthorizationException('No tienes autorización para confirmar el multienvío de este pedido.');
            }

            $bloqueado = MultienvioNacional::query()->lockForUpdate()->findOrFail($grupo->id);

            // Estados terminales: se devuelven sin cambios (como RegistrarDecisionMultienvio).
            if (in_array($bloqueado->estado, [
                MultienvioNacional::ESTADO_ACTIVO,
                MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE,
            ], true)) {
                return $bloqueado;
            }
            if ($bloqueado->estado !== MultienvioNacional::ESTADO_CONFIGURANDO) {
                throw MultienvioNacionalException::noElegible('el multienvío no está en configuración.');
            }

            $evaluacion = $this->evaluador->ejecutar($origen);
            if ($evaluacion['revision'] !== $bloqueado->revision_consulta
                || ($revisionEsperada !== null && $revisionEsperada !== $bloqueado->revision_consulta)) {
                throw MultienvioNacionalException::revisionObsoleta();
            }
            if (! $evaluacion['elegible']) {
                throw MultienvioNacionalException::noElegible((string) $evaluacion['motivo']);
            }
            // Orden de comprobaciones: autorización, estado, revisión obsoleta, elegibilidad y, al final, hechos previos.
            $hechos = $this->hechosPrevios->ejecutar($origen);
            if (DetectarHechosPreviosMultienvio::hayHechos($hechos)) {
                return $this->pasarARegularizacion($bloqueado, $hechos, $usuarioId);
            }

            $cajas = $origen->cajas()->activas()->reorder()->orderBy('orden')->orderBy('id')->get();
            $plan = $this->validarBorrador($origen, $cajas, $bloqueado->borrador_json ?? []);
            $aplicaSeguro = $this->seguro->tieneCobertura($origen->paqueteria?->nombre);
            $total = count($plan);

            // Lo cotizado sobre el pedido completo se reescribe por envío: queda constancia de lo anterior.
            $previo = $this->estadoFinancieroPrevio($origen, $cajas);

            $this->materializarOrigen($origen, $bloqueado, $cajas, $plan[0], $aplicaSeguro, $usuarioId);
            $miembros = [];
            foreach (array_slice($plan, 1) as $i => $entrada) {
                $miembro = $this->materializar->crear($origen, $bloqueado, $cajas[$i + 1], $entrada, $i + 2, $aplicaSeguro, $usuarioId);
                $miembros[] = $miembro;
                $this->registrarHistorial($miembro, $usuarioId, sprintf(
                    'Envío %d de %d del multienvío creado desde el pedido %s. Folio Wizerp %s.',
                    $i + 2, $total, $origen->folio, $entrada['folio_remision']
                ), $bloqueado, $origen->id);
            }

            $this->registrarHistorial($origen, $usuarioId, sprintf(
                'Multienvío confirmado: este pedido queda como envío 1 de %d. Folio Wizerp %s. Envíos creados: %s.',
                $total, $plan[0]['folio_remision'], implode(', ', array_map(fn (PedidoBma $m) => $m->folio, $miembros))
            ), $bloqueado, $origen->id, [
                'financiero_previo' => $previo['financiero'],
                'cotizacion_nacional_previa' => $previo['cotizacion_nacional'],
                'cajas_previas' => $previo['cajas'],
            ]);

            $bloqueado->update([
                'snapshot_origen_json' => array_merge($bloqueado->snapshot_origen_json ?? [], ['al_confirmar' => $previo]),
                'estado' => MultienvioNacional::ESTADO_ACTIVO,
                'activado_por_id' => $usuarioId,
                'activado_at' => now(),
            ]);

            return $bloqueado->fresh();
        });
    }

    /**
     * Con hechos previos no se mueve ni reescribe nada: solo se marca el grupo para regularizar.
     * Decisión de la fase 1: `pago_capturado` (pago aún sin validar) también regulariza; conservarlo en el
     * origen y continuar dejaría el pago completo contra el envío 1 (sobrepago aparente) y no existe un
     * servicio seguro para reasignarlo. La reasignación autorizada es la fase 3.
     *
     * @param  array<string, bool>  $hechos
     */
    private function pasarARegularizacion(MultienvioNacional $grupo, array $hechos, int $usuarioId): MultienvioNacional
    {
        $grupo->update([
            'estado' => MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE,
            'regularizacion_json' => [
                'hechos' => array_keys(array_filter($hechos)),
                'motivo' => 'El pedido ya tiene hechos previos; el multienvío requiere regularización antes de repartirse.',
                'solicitado_por_id' => $usuarioId,
                'solicitado_at' => now()->toIso8601String(),
                'revision' => $grupo->revision_consulta,
            ],
        ]);

        return $grupo->fresh();
    }

    /**
     * El borrador debe cubrir cada caja activa una sola vez, con folio Wizerp y valor; la suma en centavos
     * debe igualar el total de mercancía vigente del origen.
     *
     * @param  Collection<int, PedidoBmaCaja>  $cajas
     * @param  array<int, array<string, mixed>>  $borrador
     * @return list<array{folio_remision: string, mercancia: float}>  en el orden de las cajas
     */
    private function validarBorrador(PedidoBma $origen, Collection $cajas, array $borrador): array
    {
        $activas = $cajas->pluck('uuid_operativo')->all();
        $porUuid = [];
        foreach ($borrador as $envio) {
            $uuid = (string) ($envio['uuid_operativo'] ?? '');
            if (! in_array($uuid, $activas, true)) {
                throw MultienvioNacionalException::cajaNoPertenece($uuid);
            }
            if (isset($porUuid[$uuid])) {
                throw MultienvioNacionalException::noElegible('la caja '.$uuid.' aparece más de una vez en el borrador.');
            }
            $porUuid[$uuid] = $envio;
        }

        $plan = [];
        $folios = [];
        $suma = 0;
        foreach ($activas as $uuid) {
            $envio = $porUuid[$uuid] ?? throw MultienvioNacionalException::noElegible('falta asignar la caja '.$uuid.' en el borrador.');
            $folio = trim((string) ($envio['folio_remision'] ?? ''));
            if ($folio === '') {
                throw MultienvioNacionalException::noElegible('falta el folio Wizerp de la caja '.$uuid.'.');
            }
            FolioWizerpMultienvio::asegurarDisponible($folio, $origen->id, $folios);
            $centavos = $this->centavos($envio['total_mercancia'] ?? null, $uuid);
            $suma += $centavos;
            $plan[] = ['folio_remision' => $folio, 'mercancia' => $centavos / 100];
        }

        $totalOrigen = $this->centavos($origen->total_mercancia, 'origen');
        if ($suma !== $totalOrigen) {
            throw MultienvioNacionalException::sumaNoCuadra(($suma - $totalOrigen) / 100);
        }

        return $plan;
    }

    private function centavos(mixed $valor, string $uuid): int
    {
        $numero = is_numeric($valor) ? (float) $valor : NAN;
        if (! is_finite($numero) || $numero <= 0 || $numero * 100 > self::MAX_CENTAVOS) {
            throw MultienvioNacionalException::noElegible('el valor de mercancía de la caja '.$uuid.' no es válido.');
        }

        return (int) round($numero * 100);
    }

    /**
     * Estado financiero del origen justo antes de repartirlo (importes del pedido completo, cotización y costos por caja).
     *
     * @param  Collection<int, PedidoBmaCaja>  $cajas
     * @return array{financiero: array<string, mixed>, cotizacion_nacional: mixed, cajas: array<string, array<string, mixed>>}
     */
    private function estadoFinancieroPrevio(PedidoBma $origen, Collection $cajas): array
    {
        $porCaja = [];
        foreach ($cajas as $caja) {
            $porCaja[$caja->uuid_operativo] = [
                'costo_envio' => $caja->costo_envio,
                'costo_seguro' => $caja->costo_seguro,
                'costo_adicional' => $caja->costo_adicional,
            ];
        }

        return [
            'financiero' => SnapshotHistorialPedidoBma::financiero($origen)['financiero'],
            'cotizacion_nacional' => ($origen->solicitud_preparacion_json ?? [])['cotizacion_nacional'] ?? null,
            'cajas' => $porCaja,
        ];
    }

    /**
     * El origen conserva la caja de menor orden y pasa a ser el envío 1. Las cajas pierden la cotización
     * anterior (se calculó con el total global) y se cotiza por envío.
     *
     * @param  Collection<int, PedidoBmaCaja>  $cajas
     * @param  array{folio_remision: string, mercancia: float}  $entrada
     */
    private function materializarOrigen(PedidoBma $origen, MultienvioNacional $grupo, Collection $cajas, array $entrada, bool $aplicaSeguro, int $usuarioId): void
    {
        PedidoBmaCaja::query()->whereIn('id', $cajas->pluck('id'))->update([
            'costo_envio' => null, 'costo_seguro' => null, 'costo_adicional' => null,
            'costos_actualizados_at' => null, 'costos_actualizados_por_id' => null,
        ]);

        $json = $origen->solicitud_preparacion_json ?? [];
        unset($json['cotizacion_nacional']);

        $origen->update(array_merge(
            MaterializarEnvioMultienvio::agregadosDeCaja($cajas[0], $entrada['mercancia'], (float) $origen->saldo_a_favor, $aplicaSeguro),
            [
                'folio_remision' => $entrada['folio_remision'],
                'solicitud_preparacion_json' => $json,
                'multienvio_nacional_id' => $grupo->id,
                'multienvio_orden' => 1,
            ]
        ));
        PedidoBmaReferencia::registrar($origen, PedidoBmaReferencia::TIPO_PEDIDO, $entrada['folio_remision'], $usuarioId);
    }

    /** @param  array<string, mixed>  $extra */
    private function registrarHistorial(PedidoBma $pedido, int $usuarioId, string $comentario, MultienvioNacional $grupo, int $origenId, array $extra = []): void
    {
        $this->historial->ejecutar(
            $pedido->id,
            $usuarioId,
            $pedido->catalogo_estatus_pedido_id,
            (int) $pedido->catalogo_estatus_pedido_id,
            $comentario,
            AccionesHistorialPedidoBma::MULTIENVIO_CONFIRMADO,
            null,
            array_merge([
                'multienvio_nacional_id' => $grupo->id,
                'multienvio_orden' => (int) $pedido->multienvio_orden,
                'pedido_origen_id' => $origenId,
                'folio_remision' => $pedido->folio_remision,
                'total_mercancia' => $pedido->total_mercancia,
                'cajas' => $pedido->cajas()->activas()->pluck('uuid_operativo')->all(),
            ], $extra)
        );
    }
}
