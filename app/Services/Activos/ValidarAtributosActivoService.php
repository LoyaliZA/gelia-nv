<?php

namespace App\Services\Activos;

use App\Models\CatalogoTipoActivo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidarAtributosActivoService
{
    public function ejecutar(CatalogoTipoActivo $tipo, array $atributos): array
    {
        $fields = $tipo->esquema_atributos['fields'] ?? [];
        $rules = [];
        $messages = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            $fieldRules = ['nullable'];
            if (!empty($field['required'])) {
                $fieldRules = ['required'];
            }

            switch ($field['type'] ?? 'text') {
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'catalog_marca':
                case 'catalog_modelo':
                case 'select':
                    if (!empty($field['options'])) {
                        $fieldRules[] = 'in:' . implode(',', $field['options']);
                    } else {
                        $fieldRules[] = 'string';
                    }
                    break;
                default:
                    $fieldRules[] = 'string';
                    break;
            }

            $rules["atributos.{$key}"] = $fieldRules;
            $messages["atributos.{$key}.required"] = "El campo {$field['label']} es obligatorio.";
        }

        $validator = Validator::make(['atributos' => $atributos], $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated()['atributos'] ?? [];
    }

    public function sincronizarFechaVencimiento(CatalogoTipoActivo $tipo, array $atributos): ?string
    {
        $fields = $tipo->esquema_atributos['fields'] ?? [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'date' && ($field['key'] ?? '') === 'fecha_vencimiento') {
                return $atributos['fecha_vencimiento'] ?? null;
            }
        }

        return null;
    }
}
