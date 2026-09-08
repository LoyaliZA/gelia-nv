<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\EquipoAsistenciaActualizada;
use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarEquipoOperativoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly OperacionPdvConfig $config,
        private readonly AbrirJornadaPdvService $abrirJornada,
        private readonly CerrarJornadaPdvService $cerrarJornada,
        private readonly CancelarCierrePendienteJornadaPdvService $cancelarCierrePendiente,
        private readonly IniciarPausaPdvService $iniciarPausa,
        private readonly FinalizarPausaPdvService $finalizarPausa,
        private readonly ResolverEstadoVendedorOperacionPdvService $resolverEstado,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
        private readonly RegistrarAuditoriaGestionOperativaPdvService $auditoria,
    ) {}

    public function activar(User $gerente, User $vendedor, CarbonInterface $ahora, ?string $idempotencyKey = null): array
    {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'activar',
            $ahora,
            $idempotencyKey,
            function (int $sucursalId, int $actorId) use ($vendedor, $ahora): array {
                return DB::transaction(function () use ($vendedor, $sucursalId, $ahora, $actorId): array {
                    $this->limpiarMarcaNoLlego($vendedor->id, $sucursalId, $ahora);

                    return $this->abrirJornada->abrirParaUsuario(
                        (int) $vendedor->id,
                        $sucursalId,
                        $ahora,
                        $actorId,
                    );
                });
            },
        );
    }

    public function reactivar(User $gerente, User $vendedor, CarbonInterface $ahora, ?string $idempotencyKey = null): array
    {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'reactivar',
            $ahora,
            $idempotencyKey,
            function (int $sucursalId, int $actorId) use ($vendedor, $ahora): array {
                return DB::transaction(function () use ($vendedor, $sucursalId, $ahora, $actorId): array {
                    $this->limpiarMarcaNoLlego($vendedor->id, $sucursalId, $ahora);

                    return $this->abrirJornada->reactivarParaUsuario(
                        (int) $vendedor->id,
                        $sucursalId,
                        $ahora,
                        $actorId,
                    );
                });
            },
        );
    }

    public function marcarNoLlego(
        User $gerente,
        User $vendedor,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): EquipoAsistenciaDiaPdv {
        [$sucursalId, $actorId] = $this->contextoGerencia($gerente, $vendedor);
        $estadoAnterior = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->asegurarAccionPermitida($vendedor, $sucursalId, 'no_llego', $ahora);

        if (JornadaPdv::query()
            ->where('user_id', $vendedor->id)
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado', [EstadoJornadaPdv::Abierta, EstadoJornadaPdv::CerradaConAtencion])
            ->exists()) {
            throw ValidationException::withMessages([
                'vendedor' => 'No se puede marcar ausencia con jornada activa.',
            ]);
        }

        if (TurnoPdvAtencion::query()
            ->where('user_id', $vendedor->id)
            ->whereNull('fin_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'vendedor' => 'No se puede marcar ausencia con atención abierta.',
            ]);
        }

        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

        $asistencia = EquipoAsistenciaDiaPdv::query()->firstOrCreate(
            [
                'sucursal_id' => $sucursalId,
                'user_id' => $vendedor->id,
                'fecha_operativa' => $fechaOperativa,
            ],
            ['version' => 1],
        );

        $asistencia->update([
            'no_llego_at' => $ahora,
            'no_llego_por_id' => $actorId,
        ]);

        EquipoAsistenciaActualizada::dispatch($asistencia->fresh(), $sucursalId, $actorId);

        $estadoNuevo = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->auditoria->registrar(
            $actorId,
            (int) $vendedor->id,
            $sucursalId,
            'no_llego',
            $estadoAnterior,
            $estadoNuevo,
            $ahora,
            $idempotencyKey,
        );

        return $asistencia->fresh();
    }

    public function desactivar(
        User $gerente,
        User $vendedor,
        int $version,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): array {
        [$sucursalId, $actorId] = $this->contextoGerencia($gerente, $vendedor);
        $estadoAnterior = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->asegurarAccionPermitida($vendedor, $sucursalId, 'desactivar', $ahora);

        if (TurnoPdvAtencion::query()
            ->where('user_id', $vendedor->id)
            ->whereNull('fin_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'vendedor' => 'No se puede desactivar con atención abierta. Use cerrar jornada para finalizar al terminar la atención.',
            ]);
        }

        $resultado = $this->cerrarJornada->cerrarParaUsuario(
            (int) $vendedor->id,
            $sucursalId,
            $version,
            $ahora,
            $actorId,
        );

        $estadoNuevo = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->auditoria->registrar(
            $actorId,
            (int) $vendedor->id,
            $sucursalId,
            'desactivar',
            $estadoAnterior,
            $estadoNuevo,
            $ahora,
            $idempotencyKey,
        );

        return $resultado;
    }

    public function cerrarJornada(
        User $gerente,
        User $vendedor,
        int $version,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): array {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'cerrar_jornada',
            $ahora,
            $idempotencyKey,
            fn (int $sucursalId, int $actorId): array => $this->cerrarJornada->cerrarParaUsuario(
                (int) $vendedor->id,
                $sucursalId,
                $version,
                $ahora,
                $actorId,
            ),
        );
    }

    public function cancelarCierrePendiente(
        User $gerente,
        User $vendedor,
        int $version,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): array {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'cancelar_cierre_pendiente',
            $ahora,
            $idempotencyKey,
            fn (int $sucursalId, int $actorId): array => $this->cancelarCierrePendiente->cancelarParaUsuario(
                (int) $vendedor->id,
                $sucursalId,
                $version,
                $ahora,
                $actorId,
            ),
        );
    }

    public function iniciarPausa(
        User $gerente,
        User $vendedor,
        int $motivoPausaId,
        ?string $motivoDetalle,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): array {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'pausa_iniciar',
            $ahora,
            $idempotencyKey,
            fn (int $sucursalId, int $actorId): array => $this->iniciarPausa->iniciarParaUsuario(
                (int) $vendedor->id,
                $sucursalId,
                $ahora,
                $actorId,
                $motivoPausaId,
                $motivoDetalle,
                true,
            ),
            [
                'motivo_pausa_id' => $motivoPausaId,
                'motivo_detalle' => $motivoDetalle,
            ],
        );
    }

    public function finalizarPausa(
        User $gerente,
        User $vendedor,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
    ): array {
        return $this->ejecutarConAuditoria(
            $gerente,
            $vendedor,
            'pausa_finalizar',
            $ahora,
            $idempotencyKey,
            fn (int $sucursalId, int $actorId): array => $this->finalizarPausa->finalizarParaUsuario(
                (int) $vendedor->id,
                $sucursalId,
                $ahora,
                $actorId,
            ),
        );
    }

    /**
     * @param  callable(int, int): mixed  $accion
     * @param  array<string, mixed>|null  $contexto
     */
    private function ejecutarConAuditoria(
        User $gerente,
        User $vendedor,
        string $nombreAccion,
        CarbonInterface $ahora,
        ?string $idempotencyKey,
        callable $accion,
        ?array $contexto = null,
    ): array {
        [$sucursalId, $actorId] = $this->contextoGerencia($gerente, $vendedor);
        $estadoAnterior = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->asegurarAccionPermitida($vendedor, $sucursalId, $nombreAccion, $ahora);

        $resultado = $accion($sucursalId, $actorId);
        if (! is_array($resultado)) {
            $resultado = [];
        }

        $estadoNuevo = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $this->auditoria->registrar(
            $actorId,
            (int) $vendedor->id,
            $sucursalId,
            $nombreAccion,
            $estadoAnterior,
            $estadoNuevo,
            $ahora,
            $idempotencyKey,
            $contexto,
        );

        return $resultado;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function contextoGerencia(User $gerente, User $vendedor): array
    {
        $sucursalId = $this->alcance->sucursalActivaId($gerente);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $gerente,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            $sucursalId,
        );

        $this->asegurarVendedorEnSucursal($vendedor, $sucursalId);

        return [$sucursalId, (int) $gerente->id];
    }

    private function asegurarVendedorEnSucursal(User $vendedor, int $sucursalId): void
    {
        if (! $this->vendedoresElegibles->esElegible($vendedor, $sucursalId)) {
            throw ValidationException::withMessages([
                'vendedor' => 'La persona no está autorizada para atender turnos en esta sucursal.',
            ]);
        }
    }

    private function asegurarAccionPermitida(
        User $vendedor,
        int $sucursalId,
        string $accion,
        CarbonInterface $ahora,
    ): void {
        $estado = $this->estadoActual($vendedor, $sucursalId, $ahora);
        $permitidas = $this->resolverEstado->accionesDisponibles($estado);

        if (! in_array($accion, $permitidas, true)) {
            throw ValidationException::withMessages([
                'accion' => 'La acción no está permitida en el estado actual de la persona.',
            ]);
        }
    }

    private function estadoActual(User $vendedor, int $sucursalId, CarbonInterface $ahora): EstadoVendedorOperacionPdv
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

        $jornada = JornadaPdv::query()
            ->where('user_id', $vendedor->id)
            ->where('sucursal_id', $sucursalId)
            ->where(function (Builder $query) use ($fechaOperativa): void {
                $query->whereIn('estado', [
                    EstadoJornadaPdv::Abierta,
                    EstadoJornadaPdv::CerradaConAtencion,
                ])->orWhere(function (Builder $cerrada) use ($fechaOperativa): void {
                    $cerrada->where('estado', EstadoJornadaPdv::Cerrada)
                        ->whereDate('apertura_at', $fechaOperativa);
                });
            })
            ->latest('id')
            ->first();

        $intervalo = $jornada
            ? $jornada->intervalos()->whereNull('fin_at')->first()
            : null;

        $asistencia = EquipoAsistenciaDiaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $vendedor->id)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        $tieneAtencion = TurnoPdvAtencion::query()
            ->where('user_id', $vendedor->id)
            ->whereNull('fin_at')
            ->exists();

        return $this->resolverEstado->resolver(
            $sucursalId,
            $jornada,
            $intervalo,
            $tieneAtencion,
            $asistencia,
            $ahora,
        );
    }

    private function limpiarMarcaNoLlego(int $userId, int $sucursalId, CarbonInterface $ahora): void
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

        EquipoAsistenciaDiaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $userId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->whereNotNull('no_llego_at')
            ->update([
                'no_llego_at' => null,
                'no_llego_por_id' => null,
            ]);
    }
}
