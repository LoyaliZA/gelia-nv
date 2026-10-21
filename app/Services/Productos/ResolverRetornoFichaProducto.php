<?php

namespace App\Services\Productos;

use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResolverRetornoFichaProducto
{
    public function __construct(
        private readonly AlcanceAlmacenesService $alcance,
    ) {}

    /**
     * @return array{desde: string, rotulo: string, ruta: string, params: array<string, mixed>}
     */
    public function resolver(Request $request, User $user): array
    {
        $desde = $request->query('desde');
        if ($desde !== null && $desde !== '' && ! in_array($desde, ['productos', 'cobertura'], true)) {
            throw ValidationException::withMessages([
                'desde' => 'El origen del retorno no es válido.',
            ]);
        }

        $sucursalId = $this->entero($request->query('sucursal_id'), 'sucursal_id');
        $almacenId = $this->entero($request->query('almacen_id'), 'almacen_id');
        if ($sucursalId !== null || $almacenId !== null) {
            $this->alcance->validarContextoConsulta($user, $sucursalId, $almacenId);
        }

        $destino = $desde === 'cobertura' || ($desde !== 'productos' && ($sucursalId !== null || $almacenId !== null))
            ? 'cobertura'
            : 'productos';

        if ($destino === 'cobertura') {
            $params = $this->solo($request, ['q', 'asignacion', 'costo', 'activo', 'page']);
            if ($sucursalId !== null) {
                $params['sucursal_id'] = $sucursalId;
            }
            if ($almacenId !== null) {
                $params['almacen_id'] = $almacenId;
            }

            return [
                'desde' => 'cobertura',
                'rotulo' => 'Volver a productos por almacén',
                'ruta' => 'almacenes.cobertura.index',
                'params' => $params,
            ];
        }

        return [
            'desde' => 'productos',
            'rotulo' => 'Volver a productos',
            'ruta' => 'gestion_interna.productos.index',
            'params' => $this->solo($request, ['q', 'sort', 'dir', 'page', 'sin_imagen']),
        ];
    }

    private function entero(mixed $valor, string $campo): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (! is_numeric($valor) || (int) $valor < 1) {
            throw ValidationException::withMessages([
                $campo => 'El identificador no es válido.',
            ]);
        }

        return (int) $valor;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, mixed>
     */
    private function solo(Request $request, array $claves): array
    {
        $params = [];
        foreach ($claves as $clave) {
            $valor = $request->query($clave);
            if ($valor === null || $valor === '') {
                continue;
            }
            if (is_array($valor)) {
                throw ValidationException::withMessages([
                    $clave => 'El parámetro de retorno no es válido.',
                ]);
            }
            $params[$clave] = $valor;
        }

        return $params;
    }
}
