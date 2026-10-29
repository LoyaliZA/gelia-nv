<?php

namespace App\Http\Controllers\PuntoVenta\Reportes;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Reportes\ConsultarAuditoriaOperacionPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Reportes\ConsultarLineaTiempoAuditoriaOperacionPdvService;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaOperacionPdvController extends Controller
{
    public function __invoke(
        ConsultarAuditoriaOperacionPdvRequest $request,
        ConsultarLineaTiempoAuditoriaOperacionPdvService $linea,
        ResuelveAlcancePdv $alcance,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $filtrosConsulta = $request->filtrosConsulta();
        $sucursalId = $request->filled('sucursal_id') ? (int) $request->input('sucursal_id') : null;

        return Inertia::render('PuntoVenta/Reportes/AuditoriaOperacion', [
            'filtros' => [
                'sucursal_id' => $sucursalId ? (string) $sucursalId : '',
                'user_id' => $request->filled('user_id') ? (string) $request->input('user_id') : '',
                'desde' => (string) $request->input('desde', ''),
                'hasta' => (string) $request->input('hasta', ''),
            ],
            'catalogos' => [
                'sucursales' => $this->sucursales($alcance),
                'personas' => $sucursalId ? $this->personas($sucursalId) : [],
            ],
            'linea' => $filtrosConsulta === null ? [] : $linea->ejecutar($filtrosConsulta),
            'consulta_completa' => $filtrosConsulta !== null,
            'vistas_disponibles' => $this->vistasDisponibles($user, $alcance),
        ]);
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function sucursales(ResuelveAlcancePdv $alcance): array
    {
        return Sucursal::query()
            ->whereIn('id', $alcance->idsSucursalesElegibles())
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(static fn (Sucursal $sucursal): array => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function personas(int $sucursalId): array
    {
        return User::query()
            ->where(function ($query) use ($sucursalId): void {
                $query->whereHas('sucursales', fn ($sucursales) => $sucursales->where('sucursales.id', $sucursalId))
                    ->orWhereHas('jornadasPdv', fn ($jornadas) => $jornadas->where('sucursal_id', $sucursalId));
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $persona): array => [
                'id' => $persona->id,
                'nombre' => $persona->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{resguardos: bool, turnos_operacion: bool, conjunto: bool, auditoria_operacion: bool}
     */
    private function vistasDisponibles(User $user, ResuelveAlcancePdv $alcance): array
    {
        $resguardos = $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)
            && $alcance->tieneAlcanceGlobal($user);
        $turnos = $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER);

        return [
            'resguardos' => $resguardos,
            'turnos_operacion' => $turnos,
            'conjunto' => $resguardos && $turnos,
            'auditoria_operacion' => $user->hasRole('Super Admin'),
        ];
    }
}
