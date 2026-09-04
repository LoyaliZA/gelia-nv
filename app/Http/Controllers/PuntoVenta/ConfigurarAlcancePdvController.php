<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurarAlcancePdvController extends Controller
{
    public function __invoke(Request $request, ResuelveAlcancePdv $alcance): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $activaId = $alcance->sucursalActivaId($user);
        if ($activaId !== null && ! $request->boolean('elegir')) {
            return redirect()->route('punto_venta.resguardos.index');
        }

        $sucursalesAsignadas = $this->serializarSucursalesAsignadas($user);

        return Inertia::render('PuntoVenta/ConfigurarSucursal', [
            'sucursal_activa' => $this->serializarSucursalActiva($user, $alcance),
            'sucursales_asignadas' => $sucursalesAsignadas,
            'requiere_seleccion' => $activaId === null && count($sucursalesAsignadas) > 1,
            'sin_asignacion' => count($sucursalesAsignadas) === 0,
            'destino' => route('punto_venta.resguardos.index'),
        ]);
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function serializarSucursalActiva(User $user, ResuelveAlcancePdv $alcance): ?array
    {
        $activaId = $alcance->sucursalActivaId($user);
        if ($activaId === null) {
            return null;
        }

        $sucursal = Sucursal::query()->find($activaId, ['id', 'nombre']);
        if ($sucursal === null) {
            return null;
        }

        return [
            'id' => $sucursal->id,
            'nombre' => $sucursal->nombre,
        ];
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function serializarSucursalesAsignadas(User $user): array
    {
        $user->loadMissing('sucursales');

        return $user->sucursales
            ->filter(static fn (Sucursal $sucursal): bool => $sucursal->activo && (bool) $sucursal->pivot->activo)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ])
            ->values()
            ->all();
    }
}
