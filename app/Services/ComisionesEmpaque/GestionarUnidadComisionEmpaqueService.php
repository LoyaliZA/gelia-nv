<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\IncidenciaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadLineaComisionEmpaque;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ComisionesEmpaque\FuentesComisionEmpaque;
use App\Support\ComisionesEmpaque\HuellaFuenteComisionEmpaque;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarUnidadComisionEmpaqueService
{
    public function __construct(private ValorarUnidadComisionEmpaque $valorar) {}

    /** @param  list<array{catalogo_tipo_caja_id: ?int, cantidad: int, es_bolsa: bool}>  $lineas */
    public function clasificar(UnidadComisionEmpaque $unidad, array $lineas, User $responsable): UnidadComisionEmpaque
    {
        if ($unidad->posible_duplicado || $unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO) {
            throw ValidationException::withMessages([
                'unidad' => 'Hay un posible duplicado. Reconózcalo como trabajo distinto o rechácelo antes de clasificar.',
            ]);
        }

        if ($unidad->corte_id) {
            throw ValidationException::withMessages([
                'unidad' => 'Esta unidad ya está en un corte confirmado. Registre un ajuste si el desglose debe corregirse.',
            ]);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'lineas' => 'Agregue el desglose: cajas por tipo y bolsas excluidas.',
            ]);
        }

        $suma = 0;
        foreach ($lineas as $linea) {
            $cantidad = (int) ($linea['cantidad'] ?? 0);
            if ($cantidad < 1) {
                throw ValidationException::withMessages([
                    'lineas' => 'Cada renglón del desglose debe tener al menos una pieza.',
                ]);
            }
            if (! empty($linea['es_bolsa']) && ! empty($linea['catalogo_tipo_caja_id'])) {
                throw ValidationException::withMessages([
                    'lineas' => 'Una bolsa excluida no lleva tipo de caja.',
                ]);
            }
            $suma += $cantidad;
        }

        if ($suma !== (int) $unidad->cantidad_origen) {
            throw ValidationException::withMessages([
                'lineas' => "El desglose suma {$suma} y el origen indica {$unidad->cantidad_origen}. Deben coincidir.",
            ]);
        }

        $antes = $unidad->lineas()->get(['catalogo_tipo_caja_id', 'cantidad', 'es_bolsa'])->toArray();

        DB::transaction(function () use ($unidad, $lineas, $responsable, $antes) {
            $bloqueada = UnidadComisionEmpaque::query()->whereKey($unidad->id)->lockForUpdate()->first();
            if ($bloqueada->corte_id) {
                throw ValidationException::withMessages([
                    'unidad' => 'Esta unidad ya está en un corte confirmado. Registre un ajuste si el desglose debe corregirse.',
                ]);
            }
            $unidad->lineas()->delete();
            foreach ($lineas as $linea) {
                UnidadLineaComisionEmpaque::query()->create([
                    'unidad_id' => $unidad->id,
                    'catalogo_tipo_caja_id' => ! empty($linea['es_bolsa']) ? null : ($linea['catalogo_tipo_caja_id'] ?: null),
                    'cantidad' => (int) $linea['cantidad'],
                    'es_bolsa' => (bool) $linea['es_bolsa'],
                ]);
            }
            $this->valorar->aplicar($unidad->fresh('lineas'));
            AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'clasificar', null, [
                'antes' => $antes,
                'despues' => $lineas,
            ]);
        });

        return $unidad->fresh('lineas');
    }

    public function decidirCancelacion(UnidadComisionEmpaque $unidad, string $decision, string $motivo, User $responsable): UnidadComisionEmpaque
    {
        if (! in_array($decision, ['conservar', 'excluir'], true)) {
            throw ValidationException::withMessages(['decision' => 'Elija conservar o excluir la comisión.']);
        }
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo de la decisión.']);
        }

        if ($unidad->corte_id) {
            throw ValidationException::withMessages([
                'unidad' => 'El corte ya está confirmado. Conserve ese original y registre un ajuste con motivo.',
            ]);
        }

        $pedidoCancelado = null;
        if ($unidad->origen_registro === 'manual') {
            if (! $unidad->pedido_bma_id) {
                throw ValidationException::withMessages([
                    'unidad' => 'Este registro manual no está vinculado a un pedido cancelado.',
                ]);
            }
            $pedidoCancelado = PedidoBma::query()->find($unidad->pedido_bma_id);
            if (! $pedidoCancelado?->cancelado_at) {
                throw ValidationException::withMessages([
                    'unidad' => 'No hay una cancelación vigente. Actualice antes de decidir.',
                ]);
            }
        }

        DB::transaction(function () use ($unidad, $decision, $motivo, $responsable, $pedidoCancelado) {
            $this->valorar->fijarAncla($unidad, $decision, $motivo, $responsable);
            if ($pedidoCancelado) {
                $unidad->ancla_huella = HuellaFuenteComisionEmpaque::deCancelacion(
                    (int) $pedidoCancelado->id,
                    $pedidoCancelado->cancelado_at
                );
            }
            if ($decision === 'excluir') {
                $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
                $unidad->importe_centavos = 0;
                $unidad->cantidad_comisionable = 0;
                $unidad->save();
            } elseif ($unidad->posible_duplicado || $unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO) {
                $unidad->importe_centavos = null;
                $unidad->cantidad_comisionable = null;
                $unidad->save();
            } elseif ($unidad->origen_registro === 'manual' && $unidad->revision_estado === 'pendiente') {
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_REVISION;
                $unidad->importe_centavos = null;
                $unidad->cantidad_comisionable = null;
                $unidad->save();
            } else {
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
                $unidad->save();
                $this->valorar->aplicar($unidad->fresh('lineas'));
            }

            $this->cerrarIncidencias($unidad->fresh(), 'cancelacion', $decision, $motivo, $responsable);
            AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'decision_cancelacion', $motivo, [
                'decision' => $decision,
                'huella' => $unidad->huella,
                'hecho' => $unidad->ancla_huella,
            ]);
        });

        return $unidad->fresh();
    }

    public function resolverRevision(UnidadComisionEmpaque $unidad, string $decision, string $motivo, User $responsable): UnidadComisionEmpaque
    {
        if (! in_array($unidad->situacion, [UnidadComisionEmpaque::REEMPAQUE_REVISION, UnidadComisionEmpaque::SOLAPE_REVISION, UnidadComisionEmpaque::REVERTIDA_REVISION], true)) {
            throw ValidationException::withMessages(['unidad' => 'Esta unidad no está en revisión de reempaque, solape o reversión.']);
        }
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Escriba por qué acepta o descarta esta unidad.']);
        }

        DB::transaction(function () use ($unidad, $decision, $motivo, $responsable) {
            if (! in_array($decision, ['aceptar', 'descartar'], true)) {
                throw ValidationException::withMessages(['decision' => 'Elija aceptar o descartar.']);
            }
            $this->valorar->fijarAncla($unidad, $decision, $motivo, $responsable);
            if ($decision === 'descartar') {
                $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
                $unidad->importe_centavos = 0;
                $unidad->cantidad_comisionable = 0;
                $unidad->save();
            } else {
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
                $unidad->diferencia = null;
                $unidad->save();
                $this->valorar->aplicar($unidad->fresh('lineas'));
            }

            $this->cerrarIncidencias($unidad->fresh(), null, $decision, $motivo, $responsable);
            AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'resolver_revision', $motivo, [
                'decision' => $decision,
                'huella' => $unidad->huella,
            ]);
        });

        return $unidad->fresh();
    }

    public function registrarManual(array $datos, User $responsable): UnidadComisionEmpaque
    {
        return $this->registrarFila($datos, $responsable);
    }

    public function registrarFila(array $datos, User $responsable): UnidadComisionEmpaque
    {
        $area = $this->areaDeFila($datos);
        $clave = trim((string) ($datos['clave_idempotencia'] ?? ''));
        if ($clave === '') {
            throw ValidationException::withMessages(['clave_idempotencia' => 'Falta la clave de idempotencia de la captura.']);
        }

        $cantidad = (int) ($datos['cantidad'] ?? 0);
        if ($cantidad < 1) {
            throw ValidationException::withMessages(['cantidad' => 'Indique al menos una caja.']);
        }

        $liquidacion = ($datos['liquidacion'] ?? UnidadComisionEmpaque::LIQUIDACION_PENDIENTE) === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA
            ? UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA
            : UnidadComisionEmpaque::LIQUIDACION_PENDIENTE;
        $pedidoId = $datos['pedido_bma_id'] ?? null;
        $cliente = trim((string) ($datos['cliente_nombre'] ?? ''));
        $folio = trim((string) ($datos['folio_pedido'] ?? ''));
        $remision = trim((string) ($datos['folio_remision'] ?? ''));
        $pedido = $this->pedidoVerificable($pedidoId ? (int) $pedidoId : null, $folio, $remision);
        if ($pedido) {
            $cliente = (string) ($pedido->cliente?->nombre ?: $cliente);
            $folio = (string) $pedido->folio;
            $remision = (string) ($pedido->folio_remision ?: $pedido->numero_remision ?: $remision);
            $pedidoId = $pedido->id;
        } else {
            $pedidoId = null;
        }
        if ($liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA && $remision === '') {
            throw ValidationException::withMessages(['folio_remision' => 'La constancia pagada fuera necesita folio de remisión.']);
        }
        $referencia = ($pedidoId || $folio !== '' || $remision !== '')
            ? ($pedidoId ? 'pedido' : 'operativa')
            : 'insuficiente';
        $empacado = Carbon::parse($datos['empacado_at']);
        $nombreEmpacador = trim((string) ($datos['empacador_nombre'] ?? ''));
        $empacadorUserId = $responsable->id;
        if ($nombreEmpacador !== '') {
            $empacadorUserId = ! empty($datos['empacador_user_id']) ? (int) $datos['empacador_user_id'] : null;
            if ($empacadorUserId && ! User::query()->whereKey($empacadorUserId)->exists()) {
                $empacadorUserId = null;
            }
        } else {
            $nombreEmpacador = $responsable->name;
        }
        $origen = ($datos['origen_registro'] ?? 'manual') === 'carga' ? 'carga' : 'manual';
        $logistica = $this->logisticaDeclarada($datos, $origen, $referencia);
        $huella = $this->huellaFila($cantidad, $empacado, $pedidoId, $cliente, $folio, $remision, $datos, $nombreEmpacador, $area->id, $liquidacion, $logistica);

        $existente = UnidadComisionEmpaque::query()->where('clave_idempotencia', $clave)->first();
        if ($existente) {
            return $this->mismaCaptura($existente, $huella);
        }
        $this->afirmarConstanciaLibre($area->id, $remision, $empacado, $liquidacion);

        $pagadoFuera = $liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA;
        try {
            $unidad = UnidadComisionEmpaque::query()->create([
                'clave_origen' => FuentesComisionEmpaque::MANUAL.':'.bin2hex(random_bytes(8)),
                'clave_idempotencia' => $clave,
                'area_id' => $area->id,
                'pedido_bma_id' => $pedidoId,
                'procedencia' => FuentesComisionEmpaque::MANUAL,
                'origen_registro' => $origen,
                'origen_tipo' => $origen,
                'empacado_at' => $empacado,
                'incorporado_at' => now(),
                'empacador_user_id' => $empacadorUserId,
                'capturado_por_id' => $responsable->id,
                'empacador_nombre' => $nombreEmpacador,
                'cliente_nombre' => $cliente,
                'folio_pedido' => $folio !== '' ? $folio : null,
                'folio_remision' => $remision !== '' ? $remision : null,
                'cantidad_origen' => $cantidad,
                'situacion' => $pagadoFuera ? UnidadComisionEmpaque::LIQUIDADA_FUERA : UnidadComisionEmpaque::PENDIENTE_REVISION,
                'liquidacion' => $liquidacion,
                'revision_estado' => $pagadoFuera ? 'no_aplica' : 'pendiente',
                'huella' => $huella,
                'diferencia' => $referencia === 'insuficiente' ? $this->textoReferenciaInsuficiente() : null,
                'notas' => $datos['notas'] ?? null,
                'logistica' => $logistica,
            ]);
        } catch (QueryException $e) {
            $existente = UnidadComisionEmpaque::query()->where('clave_idempotencia', $clave)->first();
            if ($existente) {
                return $this->mismaCaptura($existente, $huella);
            }
            throw $e;
        }

        if ($pagadoFuera) {
            UnidadLineaComisionEmpaque::query()->create([
                'unidad_id' => $unidad->id,
                'catalogo_tipo_caja_id' => null,
                'cantidad' => $cantidad,
                'es_bolsa' => false,
            ]);
            $this->valorar->aplicar($unidad->fresh('lineas'));
        } else {
            $this->marcarSiDuplicado($unidad);
        }
        AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'registro_manual', $datos['notas'] ?? null, [
            'empacador_nombre' => $nombreEmpacador,
            'capturado_por_id' => $responsable->id,
            'liquidacion' => $liquidacion,
        ]);

        return $unidad->fresh();
    }

    public function claveDeCarga(int $areaId, string $remision, string $dia): string
    {
        return 'carga:'.hash('sha256', $areaId.'|'.mb_strtolower(trim($remision)).'|'.$dia);
    }

    public function cubiertaPorConstancia(int $areaId, ?string $remision, mixed $empacadoAt): bool
    {
        $remision = trim((string) $remision);
        if ($remision === '') {
            return false;
        }

        return UnidadComisionEmpaque::query()
            ->where('area_id', $areaId)
            ->where('folio_remision', $remision)
            ->where('liquidacion', UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA)
            ->whereDate('empacado_at', Carbon::parse($empacadoAt)->toDateString())
            ->exists();
    }

    /**
     * Coincidencias de la misma área y la misma referencia verificable.
     * La clave interna manual:* no es referencia y no demuestra que el trabajo sea distinto.
     *
     * Conflicto: mismo día civil. Puede ser el mismo trabajo físico capturado dos veces.
     * Antecedente informativo: otro día. Se muestra en la revisión porque una fecha distinta
     * no demuestra otro trabajo; la fecha pudo capturarse mal. No bloquea de forma permanente:
     * un trabajo legítimo de otro día se reconoce con motivo, responsable y la fecha de cada registro.
     *
     * Un resguardo de CEDIS y el empaque final de tienda quedan fuera: son áreas distintas.
     * El mismo trabajo dentro de una sola área sí se marca.
     * Sin pedido, folio ni remisión no hay contraste: no se comparan entre sí.
     */
    public function marcarSiDuplicado(UnidadComisionEmpaque $unidad): void
    {
        if (! in_array($unidad->procedencia, [FuentesComisionEmpaque::MANUAL, FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO], true)) {
            return;
        }
        if (! $unidad->pedido_bma_id && ! $unidad->folio_pedido && ! $unidad->folio_remision) {
            return;
        }

        $candidatos = $this->coincidencias($unidad);
        if ($candidatos->isEmpty()) {
            $this->limpiarMarcaDuplicado($unidad);

            return;
        }

        $this->aplicarMarcaDuplicado($unidad, $this->detalleCoincidencias($unidad, $candidatos));
        foreach ($candidatos as $candidato) {
            $relacionados = $this->coincidencias($candidato);
            $this->aplicarMarcaDuplicado($candidato, $this->detalleCoincidencias($candidato, $relacionados));
        }
    }

    public function revisarManual(UnidadComisionEmpaque $unidad, string $decision, string $motivo, User $responsable): UnidadComisionEmpaque
    {
        $manualPendiente = $unidad->origen_registro === 'manual' && $unidad->revision_estado === 'pendiente';
        $duplicado = $unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO || $unidad->posible_duplicado;
        if (! $manualPendiente && ! $duplicado) {
            throw ValidationException::withMessages(['unidad' => 'Solo un registro manual pendiente o un posible duplicado puede revisarse.']);
        }
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo de la revisión.']);
        }

        try {
            $this->transaccionRevisionManual($unidad, $decision, $motivo, $responsable);
        } catch (ValidationException $e) {
            $this->marcarSiDuplicado($unidad->fresh());
            throw $e;
        }

        return $unidad->fresh();
    }

    private function transaccionRevisionManual(UnidadComisionEmpaque $unidad, string $decision, string $motivo, User $responsable): void
    {
        DB::transaction(function () use ($unidad, $decision, $motivo, $responsable) {
            $unidad = UnidadComisionEmpaque::query()->whereKey($unidad->id)->lockForUpdate()->first();
            $this->marcarSiDuplicado($unidad);
            $unidad->refresh();
            $manualPendiente = $unidad->origen_registro === 'manual' && $unidad->revision_estado === 'pendiente';
            if ($this->referenciaInsuficiente($unidad) && $decision === 'aceptar') {
                throw ValidationException::withMessages([
                    'decision' => $this->textoReferenciaInsuficiente(),
                ]);
            }
            if ($decision === 'aceptar_sin_referencia') {
                if (! $this->referenciaInsuficiente($unidad)) {
                    throw ValidationException::withMessages([
                        'decision' => 'Esta captura ya tiene pedido o referencia operativa. Acéptela con la revisión normal.',
                    ]);
                }
                if ($unidad->posible_duplicado || $unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO) {
                    throw ValidationException::withMessages([
                        'decision' => 'Hay un posible duplicado. Reconozca un trabajo distinto, con motivo, o rechace el registro.',
                    ]);
                }
                $decision = 'aceptar';
            }
            if ($decision === 'aceptar' && ($unidad->posible_duplicado || $unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO)) {
                throw ValidationException::withMessages([
                    'decision' => 'Hay un posible duplicado. Reconozca un trabajo distinto, con motivo, o rechace el registro.',
                ]);
            }
            $unidad->revision_motivo = $motivo;
            $unidad->revision_por_id = $responsable->id;
            $unidad->revision_at = now();
            if ($decision === 'trabajo_distinto') {
                $reconocidos = array_values(array_unique(array_merge(
                    $unidad->duplicados_reconocidos ?? [],
                    $unidad->duplicados_relacionados ?? []
                )));
                $unidad->duplicados_reconocidos = $reconocidos;
                $unidad->posible_duplicado = false;
                $this->valorar->fijarAncla($unidad, 'trabajo_distinto', $motivo, $responsable);
                $unidad->save();
                $this->cerrarIncidencias($unidad->fresh(), 'duplicado', 'trabajo_distinto', $motivo, $responsable);
                $this->liberarContrapartes($unidad, $reconocidos, $motivo, $responsable);
                if (! $manualPendiente) {
                    $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
                    $unidad->diferencia = null;
                    $unidad->save();
                    $this->valorar->aplicar($unidad->fresh('lineas'));
                    AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'revision_manual', $motivo, ['decision' => 'trabajo_distinto']);

                    return;
                }
                if ($this->referenciaInsuficiente($unidad)) {
                    $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_REVISION;
                    $unidad->posible_duplicado = false;
                    $unidad->diferencia = $this->textoReferenciaInsuficiente();
                    $unidad->save();
                    AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'revision_manual', $motivo, ['decision' => 'trabajo_distinto']);

                    return;
                }
                $decision = 'aceptar';
            }
            if ($decision === 'aceptar') {
                $unidad->revision_estado = 'aceptada';
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
                $unidad->save();
                if (! $unidad->lineas()->exists()) {
                    UnidadLineaComisionEmpaque::query()->create([
                        'unidad_id' => $unidad->id,
                        'catalogo_tipo_caja_id' => null,
                        'cantidad' => (int) $unidad->cantidad_origen,
                        'es_bolsa' => false,
                    ]);
                }
                $fresca = $unidad->fresh();
                $this->valorar->marcarTardiaSiCorresponde($fresca);
                $this->valorar->aplicar($fresca->fresh('lineas'));
                $this->reconciliarCancelacionManual($fresca->fresh(), $responsable);
            } elseif ($decision === 'rechazar') {
                $unidad->revision_estado = 'rechazada';
                $unidad->situacion = UnidadComisionEmpaque::RECHAZADA;
                $unidad->posible_duplicado = false;
                $unidad->importe_centavos = null;
                $unidad->save();
            } else {
                throw ValidationException::withMessages(['decision' => 'Elija aceptar, rechazar o reconocer un trabajo distinto.']);
            }
            AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $unidad->id, 'revision_manual', $motivo, ['decision' => $decision]);
        });
    }

    /** @param  list<int>  $ids */
    private function liberarContrapartes(UnidadComisionEmpaque $unidad, array $ids, string $motivo, User $responsable): void
    {
        foreach ($ids as $id) {
            $otro = UnidadComisionEmpaque::query()->whereKey($id)->lockForUpdate()->first();
            if (! $otro) {
                continue;
            }
            $reconocidos = array_values(array_unique(array_merge(
                array_map('intval', $otro->duplicados_reconocidos ?? []),
                [(int) $unidad->id]
            )));
            $otro->duplicados_reconocidos = $reconocidos;
            $pendientes = array_diff(array_map('intval', $otro->duplicados_relacionados ?? []), $reconocidos);
            $otro->posible_duplicado = $pendientes !== [];
            if ($pendientes === [] && ! $otro->corte_id && $otro->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO) {
                $otro->situacion = $otro->origen_registro === 'manual' && $otro->revision_estado === 'pendiente'
                    ? UnidadComisionEmpaque::PENDIENTE_REVISION
                    : UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
                $otro->diferencia = null;
            }
            $otro->save();
            $this->cerrarIncidencias($otro, 'duplicado', 'trabajo_distinto', $motivo, $responsable);
            AuditoriaComisionEmpaque::anotar($responsable, 'unidad', $otro->id, 'trabajo_distinto_relacionado', $motivo, [
                'unidad_origen' => $unidad->id,
            ]);
        }
    }

    private function mismaCaptura(UnidadComisionEmpaque $existente, string $huella): UnidadComisionEmpaque
    {
        if (! hash_equals((string) $existente->huella, $huella)) {
            throw ValidationException::withMessages([
                'clave_idempotencia' => 'Esa clave de captura ya se usó con datos distintos. Recargue el formulario antes de registrar otra.',
            ]);
        }

        return $existente;
    }

    /**
     * @param  list<array{id: int, criterio: string, empacado_at: string}>  $detalle
     */
    private function aplicarMarcaDuplicado(UnidadComisionEmpaque $unidad, array $detalle): void
    {
        $ids = array_map(fn (array $fila) => (int) $fila['id'], $detalle);
        sort($ids);
        $reconocidos = array_map('intval', $unidad->duplicados_reconocidos ?? []);
        $nuevos = array_values(array_diff($ids, $reconocidos));
        if ($nuevos === []) {
            return;
        }

        $marca = $unidad->huella.'|'.implode(',', $ids);
        $logistica = $unidad->logistica ?? [];
        $logistica['coincidencias'] = $detalle;
        $unidad->logistica = $logistica;
        $unidad->posible_duplicado = true;
        $unidad->duplicados_relacionados = $ids;
        if ($unidad->corte_id) {
            $unidad->diferencia = 'Apareció un registro equivalente. El corte conserva esta unidad; resuelva el conflicto con un ajuste si hace falta.';
            $unidad->save();
        } else {
            $unidad->situacion = UnidadComisionEmpaque::POSIBLE_DUPLICADO;
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
            $unidad->diferencia = $this->textoCoincidencias($detalle);
            $unidad->save();
        }
        $this->valorar->abrirIncidencia($unidad, 'duplicado', null, $unidad->diferencia, [
            'huella' => $marca,
            'relacionados' => $ids,
            'coincidencias' => $detalle,
        ]);
    }

    public function reconciliarCancelacionManual(UnidadComisionEmpaque $unidad, ?User $responsable): void
    {
        if ($unidad->origen_registro !== 'manual' || ! $unidad->pedido_bma_id) {
            return;
        }
        $pedido = PedidoBma::query()->find($unidad->pedido_bma_id);
        if (! $pedido?->cancelado_at) {
            return;
        }

        $hecho = HuellaFuenteComisionEmpaque::deCancelacion((int) $pedido->id, $pedido->cancelado_at);
        $datos = [
            'huella' => $hecho,
            'cancelado_at' => (string) $pedido->cancelado_at,
            'decision_previa' => $unidad->ancla_decision,
        ];

        if ($unidad->corte_id) {
            $this->valorar->abrirIncidencia($unidad, 'cancelacion', $responsable, 'El pedido se canceló después de un corte confirmado. Conserve el corte y registre un ajuste si corresponde.', $datos);

            return;
        }

        if ($this->valorar->cancelacionVigente($unidad, $hecho, ['conservar', 'excluir'])) {
            return;
        }

        if ($unidad->situacion === UnidadComisionEmpaque::RECHAZADA) {
            return;
        }

        $this->valorar->abrirIncidencia($unidad, 'cancelacion', $responsable, 'Pedido cancelado. Falta la decisión de conservar o excluir la comisión.', $datos);

        if ($unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO || $unidad->posible_duplicado) {
            $unidad->diferencia = 'El pedido se canceló y hay una coincidencia por revisar. Resuelva esa revisión y después decida si se conserva la comisión.';
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
            $unidad->save();

            return;
        }

        if ($unidad->revision_estado === 'pendiente') {
            $unidad->diferencia = 'El pedido se canceló. La persona revisora debe aceptar o rechazar el registro. Si lo acepta, todavía falta decidir si se conserva la comisión.';
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
            $unidad->save();

            return;
        }

        $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_DECISION;
        $unidad->importe_centavos = null;
        $unidad->cantidad_comisionable = null;
        if ($unidad->ancla_decision) {
            $unidad->diferencia = 'La cancelación ya tenía una decisión («'.$unidad->ancla_decision.'») sobre otro hecho. Revise el cambio nuevo.';
        }
        $unidad->save();
    }

    private function areaDeFila(array $datos): AreaComisionEmpaque
    {
        if (! empty($datos['area_id'])) {
            $area = AreaComisionEmpaque::query()->whereKey((int) $datos['area_id'])->where('activo', true)->first();
        } elseif (! empty($datos['area_codigo'])) {
            $area = AreaComisionEmpaque::query()->where('codigo', $datos['area_codigo'])->where('activo', true)->first();
        } else {
            $area = AreaComisionEmpaque::query()->where('codigo', 'tienda')->where('activo', true)->first();
        }
        if (! $area) {
            throw ValidationException::withMessages(['area_id' => 'El área indicada no está activa.']);
        }

        return $area;
    }

    /** @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function logisticaDeclarada(array $datos, string $origen, string $referencia): array
    {
        $logistica = ['captura' => $origen, 'referencia' => $referencia];
        $nombre = trim((string) ($datos['paqueteria'] ?? ''));
        if ($nombre === '') {
            return $logistica;
        }
        $catalogo = CatalogoPaqueteriaPedido::query()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();
        $marca = ($datos['logistica'] ?? 'con') === 'sin' ? 'sin' : 'con';
        $logistica['paqueteria'] = $catalogo?->nombre ?: $nombre;
        $logistica['paqueteria_id'] = $catalogo?->id;
        $logistica['logistica'] = $marca;
        $logistica['sin_logistica'] = $marca === 'sin';

        return $logistica;
    }

    /** @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $logistica
     */
    private function huellaFila(int $cantidad, Carbon $empacado, ?int $pedidoId, string $cliente, string $folio, string $remision, array $datos, string $nombreEmpacador, int $areaId, string $liquidacion, array $logistica): string
    {
        return hash('sha256', json_encode([
            'cantidad' => $cantidad,
            'empacado_at' => (string) $empacado,
            'pedido' => $pedidoId,
            'cliente' => $cliente,
            'folio_pedido' => $folio,
            'folio_remision' => $remision,
            'notas' => trim((string) ($datos['notas'] ?? '')),
            'empacador' => $nombreEmpacador,
            'area_id' => $areaId,
            'liquidacion' => $liquidacion,
            'paqueteria' => $logistica['paqueteria'] ?? '',
            'logistica' => $logistica['logistica'] ?? '',
        ]));
    }

    private function afirmarConstanciaLibre(int $areaId, string $remision, Carbon $empacado, string $liquidacion): void
    {
        if ($remision === '') {
            return;
        }
        $hermanas = UnidadComisionEmpaque::query()
            ->where('area_id', $areaId)
            ->where('folio_remision', $remision)
            ->whereDate('empacado_at', $empacado->toDateString())
            ->where('situacion', '!=', UnidadComisionEmpaque::RECHAZADA)
            ->get();
        if ($hermanas->contains(fn (UnidadComisionEmpaque $unidad) => $unidad->liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA)) {
            throw ValidationException::withMessages([
                'folio_remision' => 'Esa remisión ya está registrada como constancia pagada fuera en la misma área y el mismo día.',
            ]);
        }
        if ($liquidacion === UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA && $hermanas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'folio_remision' => 'Ya hay un empaque de esta área, remisión y día. No se agrega otra fila pagada fuera.',
            ]);
        }
    }

    private function pedidoVerificable(?int $pedidoId, string $folio, string $remision): ?PedidoBma
    {
        if ($pedidoId) {
            $pedido = PedidoBma::query()->with('cliente:id,nombre')->find($pedidoId);
            if (! $pedido) {
                throw ValidationException::withMessages(['pedido_bma_id' => 'El pedido indicado no existe.']);
            }

            return $pedido;
        }

        if ($folio === '' && $remision === '') {
            return null;
        }

        $encontrados = PedidoBma::query()
            ->with('cliente:id,nombre')
            ->where(function ($q) use ($folio, $remision) {
                if ($folio !== '') {
                    $q->orWhere('folio', $folio);
                }
                if ($remision !== '') {
                    $q->orWhere('folio_remision', $remision)->orWhere('numero_remision', $remision);
                }
            })
            ->limit(2)
            ->get();

        return $encontrados->count() === 1 ? $encontrados->first() : null;
    }

    private function referenciaInsuficiente(UnidadComisionEmpaque $unidad): bool
    {
        return ($unidad->logistica['referencia'] ?? null) === 'insuficiente';
    }

    private function textoReferenciaInsuficiente(): string
    {
        return 'Falta una referencia verificable. Vincule un pedido existente o indique folio o remisión. La aceptación normal queda bloqueada hasta que la persona revisora acepte de forma explícita la falta de referencia.';
    }

    /** @return \Illuminate\Support\Collection<int, UnidadComisionEmpaque> */
    private function coincidencias(UnidadComisionEmpaque $unidad)
    {
        return UnidadComisionEmpaque::query()
            ->where('area_id', $unidad->area_id)
            ->where('id', '!=', $unidad->id)
            ->whereIn('procedencia', [FuentesComisionEmpaque::MANUAL, FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO])
            ->where('situacion', '!=', UnidadComisionEmpaque::RECHAZADA)
            ->where(function ($q) use ($unidad) {
                $q->whereRaw('1 = 0');
                if ($unidad->pedido_bma_id) {
                    $q->orWhere('pedido_bma_id', $unidad->pedido_bma_id);
                }
                if ($unidad->folio_remision) {
                    $q->orWhere('folio_remision', $unidad->folio_remision);
                }
                if ($unidad->folio_pedido) {
                    $q->orWhere('folio_pedido', $unidad->folio_pedido);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UnidadComisionEmpaque>  $candidatos
     * @return list<array{id: int, criterio: string, empacado_at: string}>
     */
    private function detalleCoincidencias(UnidadComisionEmpaque $unidad, $candidatos): array
    {
        $dia = $unidad->empacado_at->toDateString();

        return $candidatos->map(function (UnidadComisionEmpaque $candidato) use ($dia) {
            return [
                'id' => (int) $candidato->id,
                'criterio' => $candidato->empacado_at->toDateString() === $dia ? 'conflicto' : 'antecedente',
                'empacado_at' => $candidato->empacado_at->format('Y-m-d H:i:s'),
            ];
        })->sortBy('id')->values()->all();
    }

    private function limpiarMarcaDuplicado(UnidadComisionEmpaque $unidad): void
    {
        if (! $unidad->posible_duplicado || $unidad->corte_id) {
            return;
        }

        $unidad->posible_duplicado = false;
        $unidad->duplicados_relacionados = [];
        $logistica = $unidad->logistica ?? [];
        unset($logistica['coincidencias']);
        $unidad->logistica = $logistica;
        if ($unidad->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO) {
            $unidad->situacion = $unidad->origen_registro === 'manual' && $unidad->revision_estado === 'pendiente'
                ? UnidadComisionEmpaque::PENDIENTE_REVISION
                : UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
            $unidad->diferencia = $this->referenciaInsuficiente($unidad) ? $this->textoReferenciaInsuficiente() : null;
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
        }
        $unidad->save();
    }

    /** @param  list<array{id: int, criterio: string, empacado_at: string}>  $detalle */
    private function textoCoincidencias(array $detalle): string
    {
        $conflictos = array_values(array_filter($detalle, fn (array $fila) => $fila['criterio'] === 'conflicto'));
        $antecedentes = array_values(array_filter($detalle, fn (array $fila) => $fila['criterio'] === 'antecedente'));
        $partes = [];
        if ($conflictos !== []) {
            $partes[] = 'Conflicto del mismo día en los registros '.implode(', ', array_column($conflictos, 'id')).'. La misma referencia y área pueden ser el mismo trabajo físico.';
        }
        if ($antecedentes !== []) {
            $partes[] = 'Antecedente en otra fecha en los registros '.implode(', ', array_column($antecedentes, 'id')).'. Una fecha distinta no demuestra que sea otro trabajo.';
        }

        return implode(' ', $partes).' Reconozca un trabajo distinto, con motivo, o rechace el registro.';
    }

    private function cerrarIncidencias(UnidadComisionEmpaque $unidad, ?string $tipo, string $decision, string $motivo, User $responsable): void
    {
        $consulta = IncidenciaComisionEmpaque::query()
            ->where('unidad_id', $unidad->id)
            ->where('estado', 'pendiente');
        if ($tipo) {
            $consulta->where('tipo', $tipo);
        }
        foreach ($consulta->get() as $incidencia) {
            $datos = $incidencia->datos ?? [];
            $datos['huella'] = $datos['huella'] ?? $unidad->huella;
            $datos['ancla_huella'] = $unidad->ancla_huella;
            $incidencia->update([
                'estado' => 'resuelta',
                'decision' => $decision,
                'motivo' => $motivo,
                'responsable_id' => $responsable->id,
                'resuelta_at' => now(),
                'datos' => $datos,
            ]);
        }
    }
}
