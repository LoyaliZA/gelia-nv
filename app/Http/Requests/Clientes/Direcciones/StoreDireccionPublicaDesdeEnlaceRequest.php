<?php

namespace App\Http\Requests\Clientes\Direcciones;

use App\Support\Clientes\Direcciones\SanitizarEntradaDireccionPublica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDireccionPublicaDesdeEnlaceRequest extends FormRequest
{
    public const ETIQUETAS = ['Casa', 'Trabajo', 'Local', 'Otro'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $campos = [
            'token',
            'nombres_destinatario',
            'apellidos_destinatario',
            'telefono_destinatario',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'codigo_postal',
            'municipio',
            'ciudad',
            'estado',
            'pais',
            'referencias',
            'indicaciones_entrega',
            'etiqueta_opcion',
            'etiqueta_personalizada',
        ];

        $sanitizados = [];
        foreach ($campos as $campo) {
            if (! $this->has($campo) || ! is_string($this->input($campo))) {
                continue;
            }
            $sanitizados[$campo] = SanitizarEntradaDireccionPublica::texto(
                (string) $this->input($campo),
                permitirMultilinea: in_array($campo, ['referencias', 'indicaciones_entrega'], true),
            );
        }

        if ($this->has('codigo_postal')) {
            $sanitizados['codigo_postal'] = preg_replace('/\D+/', '', (string) ($sanitizados['codigo_postal'] ?? $this->input('codigo_postal'))) ?? '';
            $sanitizados['codigo_postal'] = substr($sanitizados['codigo_postal'], 0, 5);
        }

        if ($this->has('telefono_destinatario')) {
            $tel = preg_replace('/[^\d+\-\s()]/', '', (string) ($sanitizados['telefono_destinatario'] ?? $this->input('telefono_destinatario'))) ?? '';
            $sanitizados['telefono_destinatario'] = trim($tel);
        }

        if ($this->has('anexa_remision')) {
            $sanitizados['anexa_remision'] = filter_var($this->input('anexa_remision'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        $this->merge($sanitizados);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
            'nombres_destinatario' => ['required', 'string', 'max:120', 'regex:/^[\p{L}\p{M}\s\'\.\-]+$/u'],
            'apellidos_destinatario' => ['required', 'string', 'max:120', 'regex:/^[\p{L}\p{M}\s\'\.\-]+$/u'],
            'telefono_destinatario' => ['nullable', 'string', 'max:30', 'regex:/^[\d+\-\s()]+$/'],
            'calle' => ['required', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:30'],
            'numero_interior' => ['nullable', 'string', 'max:30'],
            'colonia' => ['required', 'string', 'max:255'],
            'codigo_postal' => ['required', 'string', 'regex:/^\d{5}$/'],
            'municipio' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'referencias' => ['nullable', 'string', 'max:2000'],
            'indicaciones_entrega' => ['nullable', 'string', 'max:2000'],
            'etiqueta_opcion' => ['required', Rule::in(self::ETIQUETAS)],
            'etiqueta_personalizada' => [
                Rule::requiredIf(fn () => $this->input('etiqueta_opcion') === 'Otro'),
                'nullable',
                'string',
                'max:100',
            ],
            'anexa_remision' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ([
                'nombres_destinatario',
                'apellidos_destinatario',
                'calle',
                'colonia',
                'municipio',
                'ciudad',
                'estado',
                'pais',
                'referencias',
                'indicaciones_entrega',
                'etiqueta_personalizada',
            ] as $campo) {
                $valor = (string) $this->input($campo, '');
                if ($valor !== '' && ! SanitizarEntradaDireccionPublica::esTextoSeguro($valor)) {
                    $validator->errors()->add($campo, 'El valor contiene contenido no permitido.');
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDireccion(): array
    {
        $validated = $this->validated();
        $etiqueta = $validated['etiqueta_opcion'] === 'Otro'
            ? trim((string) ($validated['etiqueta_personalizada'] ?? ''))
            : $validated['etiqueta_opcion'];

        $nombreCompleto = trim($validated['nombres_destinatario'].' '.$validated['apellidos_destinatario']);

        return SanitizarEntradaDireccionPublica::ejecutar([
            'nombres_destinatario' => $validated['nombres_destinatario'],
            'apellidos_destinatario' => $validated['apellidos_destinatario'],
            'nombre_destinatario' => $nombreCompleto,
            'telefono_destinatario' => $validated['telefono_destinatario'] ?? null,
            'calle' => $validated['calle'],
            'numero_exterior' => $validated['numero_exterior'] ?? null,
            'numero_interior' => $validated['numero_interior'] ?? null,
            'colonia' => $validated['colonia'],
            'codigo_postal' => $validated['codigo_postal'],
            'municipio' => $validated['municipio'],
            'ciudad' => $validated['ciudad'] ?? null,
            'estado' => $validated['estado'],
            'pais' => $validated['pais'] ?? 'México',
            'referencias' => $validated['referencias'] ?? null,
            'indicaciones_entrega' => $validated['indicaciones_entrega'] ?? null,
            'etiqueta' => $etiqueta,
            'tipo_direccion' => 'envio',
            'anexa_remision' => (bool) $validated['anexa_remision'],
        ]);
    }
}
