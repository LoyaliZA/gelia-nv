<?php

namespace App\Services\Presencia;

use App\Support\PresenciaCatalogo;

class FormatearPresenciaService
{
    public function __construct(
        private ResolverEstadoPresenciaService $resolver,
    ) {}

    public function ejecutar(array $prefs): array
    {
        $estado = $this->resolver->estadoEfectivo($prefs);
        $meta = PresenciaCatalogo::meta($estado) ?? PresenciaCatalogo::meta('disponible') ?? [];

        return [
            'estado' => $estado,
            'etiqueta' => $meta['etiqueta'] ?? 'Disponible',
            'emoji' => $meta['emoji'] ?? '🟢',
            'color' => $meta['color'] ?? '#22c55e',
            'mensaje' => $prefs['mensaje'] ?? null,
            'modo' => $prefs['modo'] ?? 'automatico',
            'automatizar' => (bool) ($prefs['automatizar'] ?? true),
            'expira_at' => $prefs['expira_at'] ?? null,
        ];
    }
}
