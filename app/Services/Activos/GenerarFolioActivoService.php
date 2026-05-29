<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\Departamento;

class GenerarFolioActivoService
{
    private const CATEGORIA_PREFIJOS = [
        'fisico' => 'FIS',
        'tecnologico' => 'TEC',
        'intangible' => 'INT',
        'vestimenta' => 'VES',
    ];

    public function ejecutar(CatalogoTipoActivo $tipo, Departamento $departamento): string
    {
        $year = now()->format('Y');
        $prefijoCategoria = self::CATEGORIA_PREFIJOS[$tipo->categoria] ?? 'ACT';
        $prefijoDepartamento = $this->codigoDepartamento($departamento);
        $prefijo = "{$prefijoCategoria}-{$prefijoDepartamento}-{$year}";

        $ultimo = Activo::withTrashed()
            ->where('folio', 'like', "{$prefijo}-%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('folio');

        $numero = 1;
        if ($ultimo && preg_match('/-' . preg_quote($year, '/') . '-(\d+)$/', $ultimo, $matches)) {
            $numero = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefijo, $numero);
    }

    public function codigoDepartamento(Departamento $departamento): string
    {
        if (!empty($departamento->codigo)) {
            return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $departamento->codigo), 0, 6));
        }

        $palabras = preg_split('/\s+/', trim($departamento->nombre)) ?: [];
        if (count($palabras) >= 2) {
            $iniciales = '';
            foreach ($palabras as $palabra) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
            }

            return substr($iniciales, 0, 6) ?: 'GEN';
        }

        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $departamento->nombre));

        return substr($limpio, 0, 3) ?: 'GEN';
    }
}
