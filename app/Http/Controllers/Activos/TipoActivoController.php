<?php

namespace App\Http\Controllers\Activos;

use App\Http\Controllers\Controller;
use App\Models\CatalogoTipoActivo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TipoActivoController extends Controller
{
    private const TIPOS_CAMPO = [
        'text', 'number', 'date', 'select', 'boolean', 'textarea',
        'catalog_marca', 'catalog_modelo',
    ];

    public function store(Request $request)
    {
        $datos = $this->validarRequest($request);
        $datos['slug'] = Str::slug($datos['nombre']);
        CatalogoTipoActivo::create($datos);

        return back()->with('success', 'Tipo de activo creado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $tipo = CatalogoTipoActivo::findOrFail($id);
        $datos = $this->validarRequest($request);
        $datos['slug'] = Str::slug($datos['nombre']);
        $tipo->update($datos);

        return back()->with('success', 'Tipo de activo actualizado.');
    }

    public function destroy(int $id)
    {
        $tipo = CatalogoTipoActivo::findOrFail($id);

        if ($tipo->activos()->exists()) {
            return back()->with('error', 'No se puede eliminar un tipo con activos registrados.');
        }

        $tipo->delete();

        return back()->with('success', 'Tipo de activo eliminado.');
    }

    private function validarRequest(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|in:fisico,tecnologico,intangible,vestimenta',
            'icono' => 'nullable|string|max:50',
            'esquema_atributos' => 'nullable|array',
            'esquema_atributos.fields' => 'nullable|array',
            'esquema_atributos.fields.*.key' => 'nullable|string|max:100',
            'esquema_atributos.fields.*.label' => 'nullable|string|max:255',
            'esquema_atributos.fields.*.type' => 'nullable|string|in:' . implode(',', self::TIPOS_CAMPO),
            'esquema_atributos.fields.*.required' => 'nullable|boolean',
            'esquema_atributos.fields.*.min_length' => 'nullable|integer|min:0',
            'esquema_atributos.fields.*.max_length' => 'nullable|integer|min:1',
            'esquema_atributos.fields.*.min' => 'nullable|numeric',
            'esquema_atributos.fields.*.max' => 'nullable|numeric',
            'esquema_atributos.fields.*.pattern' => 'nullable|string|max:500',
            'esquema_atributos.fields.*.pattern_message' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        $this->validarEsquemaAtributos($datos['esquema_atributos'] ?? []);

        return $datos;
    }

    private function validarEsquemaAtributos(array $esquema): void
    {
        $fields = $esquema['fields'] ?? [];
        $keys = [];

        foreach ($fields as $index => $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            if (in_array($key, $keys, true)) {
                throw ValidationException::withMessages([
                    "esquema_atributos.fields.{$index}.key" => "La clave «{$key}» está duplicada.",
                ]);
            }
            $keys[] = $key;

            if (!empty($field['pattern']) && @preg_match('~' . str_replace('~', '\~', $field['pattern']) . '~', '') === false) {
                throw ValidationException::withMessages([
                    "esquema_atributos.fields.{$index}.pattern" => 'La expresión regular no es válida.',
                ]);
            }

            if (isset($field['min_length'], $field['max_length']) && (int) $field['min_length'] > (int) $field['max_length']) {
                throw ValidationException::withMessages([
                    "esquema_atributos.fields.{$index}.min_length" => 'La longitud mínima no puede ser mayor que la máxima.',
                ]);
            }

            if (isset($field['min'], $field['max']) && (float) $field['min'] > (float) $field['max']) {
                throw ValidationException::withMessages([
                    "esquema_atributos.fields.{$index}.min" => 'El mínimo numérico no puede ser mayor que el máximo.',
                ]);
            }
        }
    }
}
