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

        return [
            'estado' => $estado,
            'etiqueta' => 'En línea',
            'emoji' => null,
            'color' => null,
            'mensaje' => null,
            'modo' => 'automatico',
            'automatizar' => true,
            'expira_at' => null,
            'en_linea' => PresenciaCatalogo::enLinea($prefs),
            'ultima_actividad_at' => $prefs['ultima_actividad_at'] ?? null,
        ];
    }
}
