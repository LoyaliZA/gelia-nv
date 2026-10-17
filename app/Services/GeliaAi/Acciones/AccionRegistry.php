<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

class AccionRegistry
{
    public const PREFIJO_TOOL = 'proponer_';

    /** @var array<string, AccionGeliaAi> */
    private array $acciones = [];

    /**
     * @param  iterable<AccionGeliaAi>  $acciones
     */
    public function __construct(iterable $acciones = [])
    {
        foreach ($acciones as $accion) {
            $this->registrar($accion);
        }
    }

    public function registrar(AccionGeliaAi $accion): void
    {
        $this->acciones[$accion->id()] = $accion;
    }

    public function obtener(string $id): AccionGeliaAi
    {
        if (! isset($this->acciones[$id])) {
            throw new InvalidArgumentException("Acción no soportada: {$id}");
        }

        return $this->acciones[$id];
    }

    public function soporta(string $id): bool
    {
        return isset($this->acciones[$id]);
    }

    /** @return list<AccionGeliaAi> */
    public function todas(): array
    {
        return array_values($this->acciones);
    }

    public function accionDeTool(string $toolName): ?AccionGeliaAi
    {
        if (! str_starts_with($toolName, self::PREFIJO_TOOL)) {
            return null;
        }

        return $this->acciones[substr($toolName, strlen(self::PREFIJO_TOOL))] ?? null;
    }

    /** @return array<string, mixed> */
    public function schemaTool(AccionGeliaAi $accion): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::PREFIJO_TOOL.$accion->id(),
                'description' => $accion->descripcion(),
                'parameters' => $accion->parametros(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, accion: string, reporte: array<string, mixed>}
     */
    public function ejecutar(User $user, string $accionId, array $payload): array
    {
        $accion = $this->obtener($accionId);

        if (! $user->can($accion->permiso())) {
            throw new RuntimeException('No tienes permiso para: '.$accion->permiso());
        }

        return $accion->ejecutar($user, $payload);
    }
}
