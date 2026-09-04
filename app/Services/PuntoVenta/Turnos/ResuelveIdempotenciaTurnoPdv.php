<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvEvento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

trait ResuelveIdempotenciaTurnoPdv
{
    /**
     * @return array{turno: TurnoPdv, evento: TurnoPdvEvento}|null
     */
    private function resolverReintentoIdempotente(string $idempotencyKey, string $tipoEventoEsperado): ?array
    {
        $evento = TurnoPdvEvento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($evento === null) {
            return null;
        }

        if ($evento->tipo_evento !== $tipoEventoEsperado) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        $turno = TurnoPdv::query()
            ->with(['cliente', 'sucursal', 'atencionActual'])
            ->find($evento->turno_id);

        if (! $turno instanceof TurnoPdv) {
            return null;
        }

        return [
            'turno' => $turno,
            'evento' => $evento,
        ];
    }

    private function manejarColisionIdempotencia(
        UniqueConstraintViolationException $exception,
        string $idempotencyKey,
        string $tipoEventoEsperado,
    ): ?array {
        $recuperado = $this->resolverReintentoIdempotente($idempotencyKey, $tipoEventoEsperado);
        if ($recuperado !== null) {
            return $recuperado;
        }

        throw $exception;
    }

    private function assertVersionTurno(TurnoPdv $turno, int $versionEsperada): void
    {
        if ((int) $turno->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este turno. Actualice la página e intente de nuevo.',
            ]);
        }
    }
}
