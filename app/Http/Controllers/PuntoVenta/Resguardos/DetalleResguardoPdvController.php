<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaDetalleResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\CorreccionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DetalleResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaDetalleResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
    ): Response|JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('PuntoVenta/Resguardos/Show', [
            'resguardo' => $payload['resguardo'],
            'timeline' => $payload['timeline'],
            'catalogos' => fn () => [
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
                'eventos' => EtiquetasResguardoPdv::eventos(),
                'tipos_incidencia' => EtiquetasResguardoPdv::tiposIncidencia(),
                'estados_incidencia' => EtiquetasResguardoPdv::estadosIncidencia(),
                'tipos_bulto' => EtiquetasResguardoPdv::tiposBulto(),
                'condiciones_bulto' => EtiquetasResguardoPdv::condicionesBulto(),
                'tipos_correccion' => CorreccionResguardoPdv::etiquetas(),
            ],
            'almacenes' => fn () => $this->serializarAlmacenes($resguardo, $user, $alcance),
            'permisos' => fn () => [
                'ver_etiquetas' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER),
                'recibir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR),
                'entregar' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
                'incidencia_folio' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO),
                'incidencia_dano' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO),
                'incidencia_faltante' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE),
                'autorizar_incidencia' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA),
                'confirmar_devolucion' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION),
                'reponer_vencido' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO),
                'corregir' => $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR),
            ],
        ]);
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string}>
     */
    private function serializarAlmacenes(ResguardoPdv $resguardo, User $user, ResuelveAlcancePdv $alcance): array
    {
        if (! $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO)) {
            return [];
        }

        return Almacen::query()
            ->where('sucursal_id', $resguardo->sucursal_id)
            ->where('activo', true)
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (Almacen $almacen) => [
                'id' => $almacen->id,
                'codigo' => $almacen->codigo,
                'nombre' => $almacen->nombre,
            ])
            ->values()
            ->all();
    }
}
