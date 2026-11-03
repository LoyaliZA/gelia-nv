<?php

namespace App\Support\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class RespuestaPaginada
{
    public static function porPagina(Request $request, int $defecto = 15, int $maximo = 50): int
    {
        $valor = (int) $request->query('per_page', $defecto);

        return min(max($valor, 1), $maximo);
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public static function meta(LengthAwarePaginator $paginado): array
    {
        return [
            'current_page' => $paginado->currentPage(),
            'last_page' => $paginado->lastPage(),
            'per_page' => $paginado->perPage(),
            'total' => $paginado->total(),
        ];
    }
}
