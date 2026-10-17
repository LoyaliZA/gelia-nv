<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\Almacen;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;

class ListarAlmacenesTool implements HerramientaGeliaAi
{
    private const MAX = 40;

    public function __construct(
        private AlcanceAlmacenesService $alcance,
        private BuscarProductoInventarioTool $inventario,
    ) {}

    public function name(): string
    {
        return 'listar_almacenes';
    }

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Lista los almacenes activos a los que la persona usuaria tiene acceso operable (código, nombre, tipo, sucursal). Úsala para resolver o aclarar qué almacén mencionó.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filtro' => ['type' => 'string', 'maxLength' => 60, 'description' => 'Texto opcional para filtrar por código o nombre'],
                    ],
                ],
            ],
        ];
    }

    public function disponiblePara(User $user): bool
    {
        return $this->inventario->disponiblePara($user);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function ejecutar(User $user, array $args, array $archivos = []): array
    {
        if (! $this->disponiblePara($user)) {
            return ['ok' => false, 'error_code' => 'sin_permiso', 'error' => 'Sin permiso para consultar almacenes.'];
        }

        $filtro = mb_strtolower(trim((string) ($args['filtro'] ?? '')));
        $almacenes = $this->alcance->almacenesOperables($user)
            ->filter(fn (Almacen $a) => $filtro === ''
                || str_contains(mb_strtolower((string) $a->codigo), $filtro)
                || str_contains(mb_strtolower((string) $a->nombre), $filtro))
            ->values();

        $items = $almacenes->take(self::MAX)->map(fn (Almacen $a) => [
            'codigo' => $a->codigo,
            'nombre' => $a->nombre,
            'tipo' => $a->tipoAlmacen?->nombre,
            'sucursal' => $a->sucursal?->nombre,
        ])->all();

        $out = ['ok' => true, 'n' => count($items), 'total' => $almacenes->count(), 'items' => $items];
        if ($almacenes->count() > self::MAX) {
            $out['aviso'] = 'Lista recortada; usa filtro para acotar.';
        }
        if ($items === [] && $filtro === '') {
            $out['aviso'] = 'Sin sucursales operables asignadas: no hay almacenes en el alcance de la persona usuaria.';
        }

        return $out;
    }
}
