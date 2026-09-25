<?php

namespace App\Services\Traspasos;

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class AlmacenesOrigenTraspasoService
{
    public function resolverSucursalSolicitante(User $usuario): Sucursal
    {
        $sucursal = $usuario->sucursalPrincipal();
        if (! $sucursal instanceof Sucursal) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe tener una sucursal operativa asignada (marque una principal en usuarios).',
            ]);
        }

        return $sucursal;
    }

    /**
     * @return Collection<int, Almacen>
     */
    public function almacenesPermitidosParaSucursal(Sucursal $sucursal): Collection
    {
        return Almacen::query()
            ->where('activo', true)
            ->where('visible_en_traspasos', true)
            ->whereHas('sucursalesOrigenTraspaso', fn ($q) => $q->where('sucursales.id', $sucursal->id))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }

    /**
     * @return Collection<int, Almacen>
     */
    public function almacenesPermitidosParaUsuario(User $usuario): Collection
    {
        $sucursal = $this->resolverSucursalSolicitante($usuario);

        return $this->almacenesPermitidosParaSucursal($sucursal);
    }

    public function assertAlmacenPermitido(User $usuario, int $almacenId): Almacen
    {
        $sucursal = $this->resolverSucursalSolicitante($usuario);
        $permitidos = $this->almacenesPermitidosParaSucursal($sucursal);

        if ($permitidos->isEmpty()) {
            throw ValidationException::withMessages([
                'almacen_origen_id' => 'No hay almacenes origen configurados para su sucursal. Contacte a administración.',
            ]);
        }

        /** @var Almacen|null $almacen */
        $almacen = $permitidos->firstWhere('id', $almacenId);
        if (! $almacen) {
            throw ValidationException::withMessages([
                'almacen_origen_id' => 'El almacén origen no está permitido para su sucursal.',
            ]);
        }

        return $almacen;
    }
}
