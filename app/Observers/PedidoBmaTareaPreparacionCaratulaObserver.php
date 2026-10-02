<?php

namespace App\Observers;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Services\ControlPedidos\InvalidarCaratulasVigentesService;

class PedidoBmaTareaPreparacionCaratulaObserver
{
    /** @var list<string> */
    private const CAMPOS_CARATULA = [
        'destinatario_nombre',
        'destinatario_telefono',
        'municipio_destino',
        'direccion_referencia',
        'modalidad_cobro',
        'catalogo_paqueteria_id',
    ];

    public function updating(PedidoBmaTareaPreparacion $tarea): void
    {
        $tarea->loadMissing('modalidad');
        if (! $tarea->modalidad?->esEnvioMunicipio()) {
            return;
        }

        foreach (self::CAMPOS_CARATULA as $campo) {
            if ($tarea->isDirty($campo)) {
                app(InvalidarCaratulasVigentesService::class)->ejecutar(
                    $tarea,
                    'Carátula invalidada por cambio de destino o condición de cobro.',
                );
                break;
            }
        }
    }
}
