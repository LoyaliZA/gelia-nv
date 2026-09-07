<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Services\PersonalizacionCatalogoService;

class NormalizarPreferenciasAlertasPdvService
{
    /**
     * @param  array<string, mixed>  $prefs
     * @return array{canales: array{sonido: bool, voz: bool, web_push: bool}, tono_id: string}
     */
    public function ejecutar(array $prefs): array
    {
        $defaults = config('pdv_alertas.defaults', []);
        $tonosValidos = PersonalizacionCatalogoService::tonoIdsValidos();

        $canales = array_merge(
            $defaults['canales'] ?? [],
            array_intersect_key($prefs['canales'] ?? [], array_flip(['sonido', 'voz', 'web_push']))
        );

        foreach (['sonido', 'voz', 'web_push'] as $canal) {
            $canales[$canal] = filter_var($canales[$canal] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        $tonoId = $prefs['tono_id'] ?? ($defaults['tono_id'] ?? 'default');
        if (! in_array($tonoId, $tonosValidos, true)) {
            $tonoId = $defaults['tono_id'] ?? 'default';
        }

        return [
            'canales' => $canales,
            'tono_id' => $tonoId,
        ];
    }
}
