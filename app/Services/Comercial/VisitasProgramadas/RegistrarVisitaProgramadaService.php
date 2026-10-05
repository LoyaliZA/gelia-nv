<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Mobile\MobileClienteAlcanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrarVisitaProgramadaService
{
    public function __construct(
        private readonly MobileClienteAlcanceService $alcanceCliente,
    ) {}

    /**
     * @param  array{
     *     cliente_id: int,
     *     sucursal_id: int,
     *     fecha: string,
     *     tipo_hora: string,
     *     hora_exacta?: ?string,
     *     hora_inicio?: ?string,
     *     hora_fin?: ?string,
     *     intencion: string,
     *     idempotency_key?: ?string,
     * }  $datos
     */
    public function handle(User $user, array $datos): VisitaClienteProgramada
    {
        $cliente = Cliente::query()->find($datos['cliente_id']);
        if (! $cliente instanceof Cliente || ! $this->alcanceCliente->puedeAcceder($user, $cliente)) {
            throw new AuthorizationException('No tiene acceso a este cliente.');
        }

        $sucursal = Sucursal::query()
            ->whereKey($datos['sucursal_id'])
            ->where('activo', true)
            ->first();

        if (! $sucursal instanceof Sucursal) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'La sucursal seleccionada no está disponible.',
            ]);
        }

        $this->validarHoras($datos);

        if (! empty($datos['idempotency_key'])) {
            $existente = VisitaClienteProgramada::query()
                ->where('idempotency_key', $datos['idempotency_key'])
                ->first();

            if ($existente instanceof VisitaClienteProgramada) {
                return $existente;
            }
        }

        return DB::transaction(function () use ($user, $datos, $cliente, $sucursal) {
            $esDemo = (bool) $cliente->es_demo || (bool) $sucursal->es_demo || (bool) $user->es_demo;

            return VisitaClienteProgramada::query()->create([
                'cliente_id' => $cliente->id,
                'sucursal_id' => $sucursal->id,
                'es_demo' => $esDemo,
                'fecha' => $datos['fecha'],
                'tipo_hora' => $datos['tipo_hora'],
                'hora_exacta' => $datos['hora_exacta'] ?? null,
                'hora_inicio' => $datos['hora_inicio'] ?? null,
                'hora_fin' => $datos['hora_fin'] ?? null,
                'intencion' => $datos['intencion'],
                'estado' => VisitaClienteProgramada::ESTADO_PROGRAMADA,
                'registrado_por_user_id' => $user->id,
                'idempotency_key' => $datos['idempotency_key'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarHoras(array $datos): void
    {
        $tipo = $datos['tipo_hora'];

        if ($tipo === VisitaClienteProgramada::TIPO_HORA_EXACTA && empty($datos['hora_exacta'])) {
            throw ValidationException::withMessages(['hora_exacta' => 'Indica la hora exacta de la visita.']);
        }

        if ($tipo === VisitaClienteProgramada::TIPO_HORA_RANGO) {
            if (empty($datos['hora_inicio']) || empty($datos['hora_fin'])) {
                throw ValidationException::withMessages(['hora_inicio' => 'Indica el rango de hora completo.']);
            }

            if ($datos['hora_inicio'] >= $datos['hora_fin']) {
                throw ValidationException::withMessages(['hora_fin' => 'La hora final debe ser posterior a la inicial.']);
            }
        }
    }
}
