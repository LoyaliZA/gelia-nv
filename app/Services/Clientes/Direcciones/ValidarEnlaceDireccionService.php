<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\EnlaceDireccion;

class ValidarEnlaceDireccionService
{
    public function porToken(string $token): ?EnlaceDireccion
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);

        return EnlaceDireccion::query()
            ->where(function ($q) use ($token, $hash) {
                $q->where('token_hash', $hash)
                    ->orWhere('codigo_publico', $token);
            })
            ->first();
    }

    public function ejecutar(string $token, ?string $accion = null): EnlaceDireccion
    {
        $enlace = $this->porToken($token);

        if (! $enlace) {
            throw new \InvalidArgumentException('Enlace no válido.');
        }

        if (! $enlace->estaVigente()) {
            throw new \InvalidArgumentException('El enlace expiró o fue revocado.');
        }

        if ($accion !== null && $enlace->accion_permitida !== null && $enlace->accion_permitida !== $accion) {
            throw new \InvalidArgumentException('El enlace no permite esta acción.');
        }

        return $enlace;
    }

    public function marcarUsado(EnlaceDireccion $enlace): void
    {
        if ($enlace->usado_en === null) {
            $enlace->update(['usado_en' => now()]);
        }
    }

    public function revocar(EnlaceDireccion $enlace, ?int $usuarioId = null): EnlaceDireccion
    {
        $enlace->update(['revocado_en' => now()]);

        return $enlace->fresh();
    }
}
