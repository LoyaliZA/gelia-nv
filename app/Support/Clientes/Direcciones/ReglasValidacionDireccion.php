<?php

namespace App\Support\Clientes\Direcciones;

use Illuminate\Validation\Validator;

/**
 * Reglas compartidas para catálogo / pedido / formulario público.
 *
 * domicilio_irregular=true: admite “domicilio conocido” / calle sin nombre;
 * exige referencias + estado + municipio o ciudad.
 */
class ReglasValidacionDireccion
{
    public const REFERENCIAS_MIN = 10;

    /**
     * Campos de domicilio/destinatario para alta/edición interna (nombre_destinatario único).
     *
     * @return array<string, list<string|\Illuminate\Validation\Rules\RequiredIf>>
     */
    public static function internas(bool $irregular = false): array
    {
        $req = $irregular ? 'nullable' : 'required';

        return [
            'domicilio_irregular' => ['nullable', 'boolean'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:30'],
            'calle' => [$req, 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:30'],
            'numero_interior' => ['nullable', 'string', 'max:30'],
            'colonia' => [$req, 'string', 'max:255'],
            'codigo_postal' => $irregular
                ? ['nullable', 'string', 'regex:/^\d{5}$/']
                : ['required', 'string', 'regex:/^\d{5}$/'],
            'municipio' => $irregular ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'referencias' => $irregular
                ? ['required', 'string', 'min:'.self::REFERENCIAS_MIN, 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'indicaciones_entrega' => ['nullable', 'string', 'max:2000'],
            'etiqueta' => ['nullable', 'string', 'max:100'],
            'tipo_direccion' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Misma matriz de domicilio que internas, sin nombre_destinatario
     * (el público usa nombres/apellidos por separado).
     *
     * @return array<string, list<string>>
     */
    public static function domicilioPublico(bool $irregular = false): array
    {
        $req = $irregular ? 'nullable' : 'required';

        return [
            'domicilio_irregular' => ['nullable', 'boolean'],
            'calle' => [$req, 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:30'],
            'numero_interior' => ['nullable', 'string', 'max:30'],
            'colonia' => [$req, 'string', 'max:255'],
            'codigo_postal' => $irregular
                ? ['nullable', 'string', 'regex:/^\d{5}$/']
                : ['required', 'string', 'regex:/^\d{5}$/'],
            'municipio' => $irregular ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'referencias' => $irregular
                ? ['required', 'string', 'min:'.self::REFERENCIAS_MIN, 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'indicaciones_entrega' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * En irregular: exigir municipio o ciudad.
     */
    public static function afterIrregular(Validator $validator, bool $irregular): void
    {
        if (! $irregular) {
            return;
        }

        $validator->after(function (Validator $validator) {
            $municipio = trim((string) ($validator->getData()['municipio'] ?? ''));
            $ciudad = trim((string) ($validator->getData()['ciudad'] ?? ''));
            if ($municipio === '' && $ciudad === '') {
                $validator->errors()->add('municipio', 'Indique municipio o ciudad.');
                $validator->errors()->add('ciudad', 'Indique municipio o ciudad.');
            }
        });
    }
}
