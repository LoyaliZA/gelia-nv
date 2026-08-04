<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\User;
use App\Services\GeliaAi\Knowledge\ModulosKnowledge;

class ConsultarAyudaModuloTool
{
    public function __construct(private ModulosKnowledge $knowledge) {}

    public function name(): string
    {
        return 'consultar_ayuda_modulo';
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Obtiene ayuda compacta sobre cómo funciona un módulo de Gelia (listados, solicitudes o inventario).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'modulo' => [
                            'type' => 'string',
                            'enum' => ['listados', 'solicitudes', 'inventario'],
                            'description' => 'Módulo a explicar',
                        ],
                    ],
                    'required' => ['modulo'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, texto?: string, error?: string}
     */
    public function ejecutar(User $user, array $args): array
    {
        $modulo = (string) ($args['modulo'] ?? '');
        if ($modulo === '') {
            return ['ok' => false, 'error' => 'Indica el módulo: listados, solicitudes o inventario.'];
        }

        return ['ok' => true, 'texto' => $this->knowledge->fragmento($modulo)];
    }
}
