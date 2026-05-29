<?php

namespace App\Services\Activos;

use App\Models\Activo;

class PresentarConsultaPublicaActivoService
{
    public function ejecutar(Activo $activo): array
    {
        $activo->loadMissing(['tipo', 'departamento', 'responsable', 'fotos']);

        $atributosPublicos = $this->filtrarAtributosPublicos($activo);
        $fotoPrincipal = $activo->fotos->firstWhere('es_principal', true) ?? $activo->fotos->first();

        return [
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
            'estado' => $activo->estado,
            'tipo' => $activo->tipo ? [
                'nombre' => $activo->tipo->nombre,
            ] : null,
            'departamento' => $activo->departamento ? [
                'nombre' => $activo->departamento->nombre,
            ] : null,
            'responsable' => $activo->responsable ? [
                'name' => $activo->responsable->name,
            ] : null,
            'atributos' => $atributosPublicos,
            'foto_principal' => $fotoPrincipal ? [
                'url' => $fotoPrincipal->url ?? ($fotoPrincipal->ruta ? '/storage/' . $fotoPrincipal->ruta : null),
            ] : null,
            'fotos' => $activo->fotos->map(fn ($foto) => [
                'id' => $foto->id,
                'url' => $foto->url ?? ($foto->ruta ? '/storage/' . $foto->ruta : null),
                'es_principal' => (bool) $foto->es_principal,
            ])->values()->all(),
            'esquema_atributos' => $this->esquemaPublico($activo),
        ];
    }

    private function filtrarAtributosPublicos(Activo $activo): array
    {
        $atributos = $activo->atributos ?? [];
        $camposSensibles = $this->keysSensibles($activo);
        $filtrados = [];

        foreach ($atributos as $key => $value) {
            if (in_array($key, $camposSensibles, true)) {
                continue;
            }
            if (in_array($key, ['marca_id', 'modelo_id'], true)) {
                continue;
            }
            $filtrados[$key] = $value;
        }

        return $filtrados;
    }

    private function keysSensibles(Activo $activo): array
    {
        $fields = $activo->tipo?->esquema_atributos['fields'] ?? [];
        $keys = [];

        foreach ($fields as $field) {
            if (!empty($field['sensitive']) && !empty($field['key'])) {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    private function esquemaPublico(Activo $activo): ?array
    {
        $fields = $activo->tipo?->esquema_atributos['fields'] ?? [];
        if (!$fields) {
            return null;
        }

        $publicFields = array_values(array_filter($fields, function ($field) {
            return empty($field['sensitive']);
        }));

        return $publicFields ? ['fields' => $publicFields] : null;
    }
}
