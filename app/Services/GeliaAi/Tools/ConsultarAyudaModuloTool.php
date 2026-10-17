<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\User;
use App\Services\GeliaAi\Knowledge\ModulosKnowledge;

class ConsultarAyudaModuloTool implements HerramientaGeliaAi
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
                'description' => 'Devuelve la guía documentada de un módulo de Gelia (qué hace, flujo, permisos). Úsala para preguntas de "cómo funciona" o "para qué sirve". No consulta datos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'modulo' => [
                            'type' => 'string',
                            'enum' => ModulosKnowledge::MODULOS,
                            'description' => 'Módulo a explicar',
                        ],
                    ],
                    'required' => ['modulo'],
                ],
            ],
        ];
    }

    public function disponiblePara(User $user): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{ok: bool, modulo?: string, texto?: string, error?: string, error_code?: string}
     */
    public function ejecutar(User $user, array $args, array $archivos = []): array
    {
        $modulo = (string) ($args['modulo'] ?? '');
        if (! in_array($modulo, ModulosKnowledge::MODULOS, true)) {
            return [
                'ok' => false,
                'error_code' => 'argumento_invalido',
                'error' => 'Indica el módulo: '.implode(', ', ModulosKnowledge::MODULOS).'.',
            ];
        }

        return ['ok' => true, 'modulo' => $modulo, 'texto' => $this->knowledge->fragmento($modulo)];
    }
}
