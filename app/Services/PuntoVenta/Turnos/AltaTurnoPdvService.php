<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\TurnoCreado;
use App\Models\Cliente;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\EstadosActivosTurnoPdv;
use App\Support\PuntoVenta\Turnos\FolioTurnoGenerado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AltaTurnoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly GenerarFolioTurnoService $generarFolio,
        private readonly ResolverPrioridadesTurnoPdvService $resolverPrioridades,
        private readonly AsignarTurnoPdvService $asignarTurno,
    ) {}

    public function ejecutar(
        User $actor,
        string $idempotencyKey,
        ?int $clienteId,
        ?string $nombreLlamado,
        bool $prioridadAdultoMayor,
        bool $prioridadDiscapacidad,
    ): TurnoPdv {
        $sucursal = $this->resolverSucursalActiva($actor);
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_ALTA,
            $sucursal->id
        );

        return DB::transaction(function () use (
            $actor,
            $idempotencyKey,
            $clienteId,
            $nombreLlamado,
            $prioridadAdultoMayor,
            $prioridadDiscapacidad,
            $sucursal,
        ): TurnoPdv {
            $reintento = $this->resolverReintentoIdempotente($idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            $cliente = $this->resolverCliente($clienteId);
            $this->assertSinTurnoActivo($sucursal->id, $cliente);
            $nombreParaLlamado = $this->resolverNombreLlamado($cliente, $nombreLlamado);
            $prioridades = $this->resolverPrioridades->resolver(
                $cliente,
                $prioridadAdultoMayor,
                $prioridadDiscapacidad,
            );

            $folio = $this->generarFolio->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);
            $ahora = now();

            $turno = TurnoPdv::query()->create([
                'sucursal_id' => $sucursal->id,
                'cliente_id' => $cliente?->id,
                'folio' => $folio->folio,
                'servicio' => TurnoPdv::SERVICIO_VENTAS,
                'origen' => TurnoPdv::ORIGEN_RECEPCION,
                'estado' => TurnoPdv::ESTADO_EN_COLA,
                'prioridad' => TurnoPdv::PRIORIDAD_NORMAL,
                'prioridad_adulto_mayor' => $prioridades['prioridad_adulto_mayor'],
                'prioridad_discapacidad' => $prioridades['prioridad_discapacidad'],
                'prioridad_diamante' => $prioridades['prioridad_diamante'],
                'prioridad_vip' => $prioridades['prioridad_vip'],
                'snapshot_nombre_llamado' => $nombreParaLlamado,
                'snapshot_cliente_nombre' => $cliente?->nombre,
                'snapshot_json' => $this->construirSnapshot($cliente, $folio, $prioridades),
                'alta_at' => $ahora,
                'alta_por_id' => $actor->id,
                'version' => 1,
            ]);

            $asignacion = $this->asignarTurno->ejecutar($turno, $ahora, 'alta_inmediata');
            $estadoFinal = $asignacion !== null
                ? TurnoPdv::ESTADO_ASIGNADO
                : TurnoPdv::ESTADO_EN_COLA;

            try {
                $evento = TurnoPdvEvento::query()->create([
                    'turno_id' => $turno->id,
                    'tipo_evento' => TurnoPdvEvento::TIPO_ALTA,
                    'estado_anterior' => null,
                    'estado_nuevo' => $estadoFinal,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'folio' => $folio->folio,
                        'servicio' => TurnoPdv::SERVICIO_VENTAS,
                        'origen' => TurnoPdv::ORIGEN_RECEPCION,
                        'cliente_id' => $cliente?->id,
                        'nombre_llamado' => $nombreParaLlamado,
                        'prioridades' => $prioridades,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                $recuperado = $this->resolverReintentoIdempotente($idempotencyKey);
                if ($recuperado !== null) {
                    return $recuperado;
                }

                throw $exception;
            }

            TurnoCreado::dispatch($turno->fresh(), $evento, $sucursal->id);

            if ($asignacion !== null) {
                $this->asignarTurno->publicarEventoDominio($asignacion);
            }

            return $turno->fresh(['cliente', 'sucursal', 'altaPor', 'atencionActual']);
        });
    }

    private function resolverSucursalActiva(User $actor): Sucursal
    {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa para dar de alta turnos.',
            ]);
        }

        $sucursal = Sucursal::query()->find($sucursalId);
        if (! $sucursal instanceof Sucursal) {
            throw (new ModelNotFoundException)->setModel(Sucursal::class, [$sucursalId]);
        }

        return $sucursal;
    }

    private function resolverCliente(?int $clienteId): ?Cliente
    {
        if ($clienteId === null) {
            return null;
        }

        $cliente = Cliente::query()->with('listaDescuento')->find($clienteId);
        if (! $cliente instanceof Cliente) {
            throw ValidationException::withMessages([
                'cliente_id' => 'El cliente indicado no existe.',
            ]);
        }

        return $cliente;
    }

    private function resolverNombreLlamado(?Cliente $cliente, ?string $nombreLlamado): string
    {
        if ($cliente instanceof Cliente) {
            $nombre = trim((string) $cliente->nombre);

            return $nombre !== '' ? $nombre : trim((string) $nombreLlamado);
        }

        $nombre = trim((string) $nombreLlamado);
        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre_llamado' => 'El nombre para llamado es obligatorio para visitantes.',
            ]);
        }

        return $nombre;
    }

    private function assertSinTurnoActivo(int $sucursalId, ?Cliente $cliente): void
    {
        if (! $cliente instanceof Cliente) {
            // ponytail: unicidad de visitante pendiente de decisión 0C §16.5
            return;
        }

        $activo = TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $cliente->id)
            ->whereIn('estado', EstadosActivosTurnoPdv::valores())
            ->exists();

        if ($activo) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Esta persona ya tiene un turno activo en la sucursal.',
            ]);
        }
    }

    /**
     * @param  array{
     *     prioridad_adulto_mayor: bool,
     *     prioridad_discapacidad: bool,
     *     prioridad_diamante: bool,
     *     prioridad_vip: bool
     * }  $prioridades
     * @return array<string, mixed>
     */
    private function construirSnapshot(?Cliente $cliente, FolioTurnoGenerado $folio, array $prioridades): array
    {
        return [
            'folio' => $folio->folio,
            'secuencia' => $folio->secuencia,
            'fecha_operativa' => $folio->fechaOperativa,
            'cliente_id' => $cliente?->id,
            'lista_actual_id' => $cliente?->lista_actual_id,
            'prioridades' => $prioridades,
        ];
    }

    private function resolverReintentoIdempotente(string $idempotencyKey): ?TurnoPdv
    {
        $evento = TurnoPdvEvento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($evento === null) {
            return null;
        }

        if ($evento->tipo_evento !== TurnoPdvEvento::TIPO_ALTA) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return TurnoPdv::query()
            ->with(['cliente', 'sucursal', 'altaPor', 'atencionActual'])
            ->find($evento->turno_id);
    }
}
