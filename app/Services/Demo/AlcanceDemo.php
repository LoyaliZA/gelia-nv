<?php

namespace App\Services\Demo;

use App\Models\User;

/**
 * Contexto del alcance demo de la petición.
 * Sin usuario (consola, cola) el filtro deja fuera las filas demo.
 */
class AlcanceDemo
{
    private bool $sinFiltro = false;

    private bool $usuarioResuelto = false;

    private bool $soloDemo = false;

    public function fijarDesdeUsuario(?User $user): void
    {
        $this->sinFiltro = false;

        if (! $user instanceof User) {
            $this->usuarioResuelto = false;
            $this->soloDemo = false;

            return;
        }

        $this->usuarioResuelto = true;
        $this->soloDemo = (bool) $user->es_demo;
    }

    public function aplicaFiltro(): bool
    {
        return ! $this->sinFiltro;
    }

    public function soloDemo(): bool
    {
        if ($this->usuarioResuelto) {
            return $this->soloDemo;
        }

        $user = null;
        if (app()->bound('request')) {
            $candidato = request()->user();
            if ($candidato instanceof User) {
                $user = $candidato;
            }
        }

        $user ??= auth()->user();

        return $user instanceof User && (bool) $user->es_demo;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function sinFiltro(callable $callback): mixed
    {
        $anterior = $this->sinFiltro;
        $this->sinFiltro = true;

        try {
            return $callback();
        } finally {
            $this->sinFiltro = $anterior;
        }
    }
}
