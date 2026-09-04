<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\TraductorMetadataEventoResguardoPdv;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class ConsultaAuditoriaResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     timeline: list<array<string, mixed>>,
     *     filtros: array<string, mixed>,
     *     total: int
     * }
     */
    public function obtener(User $user, ResguardoPdv $resguardo, array $filtros = []): array
    {
        $this->asegurarAccesoPiso($user, $resguardo);

        return $this->construirPayload($user, $resguardo, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     timeline: list<array<string, mixed>>,
     *     filtros: array<string, mixed>,
     *     total: int
     * }
     */
    public function obtenerParaExportacion(User $user, ResguardoPdv $resguardo, array $filtros = []): array
    {
        $this->asegurarAccesoExportacion($user, $resguardo);

        return $this->construirPayload($user, $resguardo, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     timeline: list<array<string, mixed>>,
     *     filtros: array<string, mixed>,
     *     total: int
     * }
     */
    private function construirPayload(User $user, ResguardoPdv $resguardo, array $filtros = []): array
    {
        $filtrosNormalizados = $this->normalizarFiltros($filtros);
        $verDatosOperativos = $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR)
            || $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR);
        $verDetalleCompleto = $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR);

        $entregasPorId = ResguardoPdvEntrega::query()
            ->where('resguardo_id', $resguardo->id)
            ->get()
            ->keyBy('id');

        $query = ResguardoPdvEvento::query()
            ->where('resguardo_id', $resguardo->id)
            ->with([
                'actor:id,username',
                'bulto:id,folio',
                'evidencias' => fn ($q) => $q->orderBy('capturado_at')->orderBy('id'),
            ])
            ->orderBy('ocurrido_at')
            ->orderBy('id');

        $this->aplicarFiltrosEventos($query, $filtrosNormalizados);

        $eventos = $query->get();

        $items = collect();

        foreach ($eventos as $evento) {
            $items->push($this->serializarEvento(
                $evento,
                $entregasPorId,
                $verDatosOperativos,
                $verDetalleCompleto,
            ));

            $integracionEvento = $evento->snapshot_json['integracion_cp'] ?? null;
            if (is_array($integracionEvento)) {
                $sintetico = $this->serializarIntegracionDesdeEvento(
                    $evento,
                    $integracionEvento,
                    $verDetalleCompleto,
                );
                if ($sintetico !== null) {
                    $items->push($sintetico);
                }
            }
        }

        foreach ($entregasPorId as $entrega) {
            $integracion = $entrega->snapshot_json['integracion_cp'] ?? null;
            if (! is_array($integracion)) {
                continue;
            }

            $sintetico = $this->serializarIntegracionDesdeEntrega(
                $entrega,
                $integracion,
                $verDetalleCompleto,
            );
            if ($sintetico !== null) {
                $items->push($sintetico);
            }
        }

        $timeline = $items
            ->filter(fn (array $item) => $this->pasaFiltrosItem($item, $filtrosNormalizados))
            ->sortBy([
                ['ocurrido_at', 'asc'],
                ['orden_estable', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'timeline' => $timeline,
            'filtros' => $filtrosNormalizados,
            'total' => count($timeline),
        ];
    }

    private function asegurarAccesoPiso(User $user, ResguardoPdv $resguardo): void
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }
    }

    private function asegurarAccesoExportacion(User $user, ResguardoPdv $resguardo): void
    {
        $this->alcance->asegurarConsultaGlobal($user);

        if (! $this->alcance->idsSucursalesElegibles()->contains((int) $resguardo->sucursal_id)) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }
    }

    /**
     * @param  Collection<int, ResguardoPdvEntrega>  $entregasPorId
     * @return array<string, mixed>
     */
    private function serializarEvento(
        ResguardoPdvEvento $evento,
        Collection $entregasPorId,
        bool $verDatosOperativos,
        bool $verDetalleCompleto,
    ): array {
        $snapshot = $evento->snapshot_json ?? [];
        $entregaId = (int) ($snapshot['entrega_id'] ?? 0);
        $entrega = $entregaId > 0 ? $entregasPorId->get($entregaId) : null;

        if ($entrega instanceof ResguardoPdvEntrega) {
            $integracionEntrega = $entrega->snapshot_json['integracion_cp'] ?? null;
            if (is_array($integracionEntrega) && ! isset($snapshot['integracion_cp'])) {
                $snapshot['integracion_cp'] = $integracionEntrega;
            }
        }

        $metadataLegible = TraductorMetadataEventoResguardoPdv::metadataLegible(
            $snapshot,
            $evento->tipo_evento,
            $verDatosOperativos,
            $verDetalleCompleto,
        );

        if (isset($snapshot['integracion_cp']) && is_array($snapshot['integracion_cp'])) {
            $metadataLegible = array_merge(
                $metadataLegible,
                TraductorMetadataEventoResguardoPdv::integracionLegible(
                    $snapshot['integracion_cp'],
                    $verDetalleCompleto,
                ),
            );
        }

        return [
            'id' => 'evt:'.$evento->id,
            'origen' => 'evento',
            'evento_id' => $evento->id,
            'tipo_evento' => $evento->tipo_evento,
            'tipo_etiqueta' => EtiquetasResguardoPdv::etiquetaEvento($evento->tipo_evento),
            'categoria' => TraductorMetadataEventoResguardoPdv::categoriaEvento($evento->tipo_evento),
            'estado_anterior' => $evento->estado_anterior,
            'estado_nuevo' => $evento->estado_nuevo,
            'estado_anterior_etiqueta' => $evento->estado_anterior
                ? EtiquetasResguardoPdv::etiquetaEstado($evento->estado_anterior)
                : null,
            'estado_nuevo_etiqueta' => $evento->estado_nuevo
                ? EtiquetasResguardoPdv::etiquetaEstado($evento->estado_nuevo)
                : null,
            'ocurrido_at' => $evento->ocurrido_at?->toIso8601String(),
            'orden_estable' => $evento->id,
            'actor_referencia' => $this->referenciaActor($evento->actor),
            'bulto_id' => $evento->bulto_id,
            'bulto_folio' => $evento->bulto?->folio,
            'metadata_legible' => $metadataLegible,
            'metadata_original' => TraductorMetadataEventoResguardoPdv::metadataOriginal(
                $snapshot !== [] ? $snapshot : null,
                $verDetalleCompleto,
            ),
            'evidencias' => $evento->evidencias
                ->map(fn (ResguardoPdvEvidencia $evidencia) => $this->serializarEvidencia($evidencia))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $integracion
     * @return array<string, mixed>|null
     */
    private function serializarIntegracionDesdeEvento(
        ResguardoPdvEvento $evento,
        array $integracion,
        bool $verDetalleCompleto,
    ): ?array {
        $marca = (string) ($integracion['completada_at'] ?? $integracion['ultimo_intento_at'] ?? '');
        if ($marca === '') {
            return null;
        }

        $ocurrido = Carbon::parse($marca);
        if ($evento->ocurrido_at !== null && $ocurrido->equalTo($evento->ocurrido_at)) {
            return null;
        }

        return $this->itemIntegracion(
            origenId: 'evt-int:'.$evento->id,
            ordenEstable: 1000000 + $evento->id,
            ocurridoAt: $ocurrido,
            contexto: 'Devolución',
            integracion: $integracion,
            verDetalleCompleto: $verDetalleCompleto,
            eventoReferenciaId: $evento->id,
        );
    }

    /**
     * @param  array<string, mixed>  $integracion
     * @return array<string, mixed>|null
     */
    private function serializarIntegracionDesdeEntrega(
        ResguardoPdvEntrega $entrega,
        array $integracion,
        bool $verDetalleCompleto,
    ): ?array {
        $marca = (string) ($integracion['completada_at'] ?? $integracion['ultimo_intento_at'] ?? '');
        if ($marca === '') {
            return null;
        }

        $ocurrido = Carbon::parse($marca);
        if ($entrega->entregado_at !== null && $ocurrido->equalTo($entrega->entregado_at)) {
            return null;
        }

        return $this->itemIntegracion(
            origenId: 'ent-int:'.$entrega->id,
            ordenEstable: 2000000 + $entrega->id,
            ocurridoAt: $ocurrido,
            contexto: 'Entrega',
            integracion: $integracion,
            verDetalleCompleto: $verDetalleCompleto,
            entregaId: $entrega->id,
        );
    }

    /**
     * @param  array<string, mixed>  $integracion
     * @return array<string, mixed>
     */
    private function itemIntegracion(
        string $origenId,
        int $ordenEstable,
        Carbon $ocurridoAt,
        string $contexto,
        array $integracion,
        bool $verDetalleCompleto,
        ?int $eventoReferenciaId = null,
        ?int $entregaId = null,
    ): array {
        $estado = (string) ($integracion['estado'] ?? 'pendiente');
        $tipoEtiqueta = $estado === 'completada'
            ? 'Integración CP completada ('.$contexto.')'
            : 'Integración CP pendiente ('.$contexto.')';

        return [
            'id' => $origenId,
            'origen' => 'integracion_cp',
            'evento_id' => $eventoReferenciaId,
            'entrega_id' => $entregaId,
            'tipo_evento' => 'integracion_cp.'.$estado,
            'tipo_etiqueta' => $tipoEtiqueta,
            'categoria' => 'integracion',
            'estado_anterior' => null,
            'estado_nuevo' => null,
            'estado_anterior_etiqueta' => null,
            'estado_nuevo_etiqueta' => null,
            'ocurrido_at' => $ocurridoAt->toIso8601String(),
            'orden_estable' => $ordenEstable,
            'actor_referencia' => null,
            'bulto_id' => null,
            'bulto_folio' => null,
            'metadata_legible' => TraductorMetadataEventoResguardoPdv::integracionLegible(
                $integracion,
                $verDetalleCompleto,
            ),
            'metadata_original' => $verDetalleCompleto ? ['integracion_cp' => $integracion] : null,
            'evidencias' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarEvidencia(ResguardoPdvEvidencia $evidencia): array
    {
        return [
            'id' => $evidencia->id,
            'tipo' => $evidencia->tipo,
            'nombre_original' => $evidencia->nombre_original,
            'capturado_at' => $evidencia->capturado_at?->toIso8601String(),
            'ruta_publica' => $evidencia->tipo === ResguardoPdvEvidencia::TIPO_FIRMA
                ? null
                : '/storage/'.$evidencia->ruta_interna,
        ];
    }

    private function referenciaActor(?User $actor): ?string
    {
        if (! $actor instanceof User) {
            return null;
        }

        if (filled($actor->username)) {
            return '@'.$actor->username;
        }

        return 'Colaborador';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosEventos(\Illuminate\Database\Eloquent\Builder $query, array $filtros): void
    {
        if (! empty($filtros['tipo_evento'])) {
            $query->where('tipo_evento', $filtros['tipo_evento']);
        }

        if (! empty($filtros['desde'])) {
            $query->where('ocurrido_at', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->where('ocurrido_at', '<=', $filtros['hasta']);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $filtros
     */
    private function pasaFiltrosItem(array $item, array $filtros): bool
    {
        if (! empty($filtros['tipo_evento']) && $item['origen'] !== 'evento') {
            return false;
        }

        if (! empty($filtros['categoria']) && $item['categoria'] !== $filtros['categoria']) {
            return false;
        }

        if (! empty($filtros['desde']) || ! empty($filtros['hasta'])) {
            $ocurrido = Carbon::parse((string) $item['ocurrido_at']);
            if (! empty($filtros['desde']) && $ocurrido->lt($filtros['desde'])) {
                return false;
            }
            if (! empty($filtros['hasta']) && $ocurrido->gt($filtros['hasta'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros(array $filtros): array
    {
        $tiposValidos = array_keys(EtiquetasResguardoPdv::eventos());
        $categoriasValidas = [
            'recepcion',
            'incidencia',
            'entrega',
            'devolucion',
            'correccion',
            'sistema',
            'integracion',
            'operacion',
        ];

        $normalizados = [
            'tipo_evento' => null,
            'categoria' => null,
            'desde' => null,
            'hasta' => null,
        ];

        if (! empty($filtros['tipo_evento']) && in_array($filtros['tipo_evento'], $tiposValidos, true)) {
            $normalizados['tipo_evento'] = $filtros['tipo_evento'];
        }

        if (! empty($filtros['categoria']) && in_array($filtros['categoria'], $categoriasValidas, true)) {
            $normalizados['categoria'] = $filtros['categoria'];
        }

        if (! empty($filtros['desde'])) {
            $normalizados['desde'] = Carbon::parse((string) $filtros['desde'])->startOfDay();
        }

        if (! empty($filtros['hasta'])) {
            $normalizados['hasta'] = Carbon::parse((string) $filtros['hasta'])->endOfDay();
        }

        return $normalizados;
    }
}
