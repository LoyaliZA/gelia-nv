<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

class AccionRegistry
{
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

    /**
     * @return list<array<string, mixed>>
     */
    public function schemasParaTools(): array
    {
        $props = [];
        $enum = [];
        foreach ($this->acciones as $accion) {
            $enum[] = $accion->id();
            $schema = $accion->proponerSchema();
            $props[$accion->id()] = $schema['parameters'] ?? ['type' => 'object'];
        }

        return [[
            'type' => 'function',
            'function' => [
                'name' => 'proponer_accion_operativa',
                'description' => 'Propone importar costos/inventarios o generar listado. No ejecuta; el usuario confirma.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'accion' => ['type' => 'string', 'enum' => $enum],
                        'payload' => [
                            'type' => 'object',
                            'description' => 'Params según acción (file_id, almacen_codigo, tipo_lista, file_ids).',
                        ],
                        'resumen_corto' => [
                            'type' => 'string',
                            'description' => '1 frase para mostrar al usuario antes de confirmar.',
                        ],
                    ],
                    'required' => ['accion', 'payload', 'resumen_corto'],
                ],
            ],
        ]];
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
