<?php

namespace App\Services\Clientes\Direcciones;

class NormalizadorDireccion
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function ejecutar(array $datos): array
    {
        $camposTexto = [
            'etiqueta',
            'tipo_direccion',
            'nombre_destinatario',
            'telefono_destinatario',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'municipio',
            'ciudad',
            'estado',
            'pais',
            'referencias',
            'indicaciones_entrega',
        ];

        foreach ($camposTexto as $campo) {
            if (! array_key_exists($campo, $datos) || $datos[$campo] === null) {
                continue;
            }
            $datos[$campo] = $this->normalizarTexto((string) $datos[$campo]);
        }

        if (array_key_exists('codigo_postal', $datos) && $datos['codigo_postal'] !== null) {
            $datos['codigo_postal'] = $this->normalizarCodigoPostal((string) $datos['codigo_postal']);
        }

        return $datos;
    }

    public function representacionComparable(array $datos): string
    {
        $normalizado = $this->ejecutar($datos);

        $piezas = [
            mb_strtolower((string) ($normalizado['calle'] ?? '')),
            mb_strtolower((string) ($normalizado['numero_exterior'] ?? '')),
            mb_strtolower((string) ($normalizado['numero_interior'] ?? '')),
            mb_strtolower((string) ($normalizado['colonia'] ?? '')),
            (string) ($normalizado['codigo_postal'] ?? ''),
            mb_strtolower((string) ($normalizado['nombre_destinatario'] ?? '')),
        ];

        return implode('|', $piezas);
    }

    private function normalizarTexto(string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);

        return $valor;
    }

    private function normalizarCodigoPostal(string $cp): string
    {
        $digitos = preg_replace('/\D+/', '', $cp) ?? '';

        if ($digitos === '') {
            return '';
        }

        if (strlen($digitos) > 5) {
            $digitos = substr($digitos, 0, 5);
        }

        return str_pad($digitos, 5, '0', STR_PAD_LEFT);
    }
}
