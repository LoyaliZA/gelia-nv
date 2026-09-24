<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ConsultaDetalleResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\CorreccionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DetalleResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ConsultaDetalleResguardoPdvService $consulta,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): JsonResponse|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $payload = $consulta->obtener($user, $resguardo);
        $meta = $this->metaDetalle($resguardo, $user, $alcance, $autorizacion);

        if ($request->expectsJson()) {
            return response()->json(array_merge($payload, $meta, [
                'modo_auditoria' => $autorizacion->soloHistorialEntregados($user),
            ]));
        }

        $rutaListado = $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)
            ? 'punto_venta.resguardos.index'
            : 'punto_venta.resguardos.entregados.index';

        return redirect()->route($rutaListado, [
            'detalle' => $resguardo->id,
        ]);
    }

    /**
     * @return array{catalogos: array<string, mixed>, almacenes: list<array{id: int, codigo: string, nombre: string}>, permisos: array<string, bool>}
     */
    private function metaDetalle(
        ResguardoPdv $resguardo,
        User $user,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): array {
        $soloHistorial = $autorizacion->soloHistorialEntregados($user);
        $puedeOperar = ! $soloHistorial && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);
        $puedeVerEtiquetas = $puedeOperar
            || ($soloHistorial && $resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO);

        return [
            'catalogos' => [
                'estados' => EtiquetasResguardoPdv::estados(),
                'antiguedades' => EtiquetasResguardoPdv::antiguedades(),
                'eventos' => EtiquetasResguardoPdv::eventos(),
                'tipos_incidencia' => EtiquetasResguardoPdv::tiposIncidencia(),
                'estados_incidencia' => EtiquetasResguardoPdv::estadosIncidencia(),
                'tipos_bulto' => EtiquetasResguardoPdv::tiposBulto(),
                'condiciones_bulto' => EtiquetasResguardoPdv::condicionesBulto(),
                'tipos_correccion' => CorreccionResguardoPdv::etiquetas(),
            ],
            'almacenes' => $this->serializarAlmacenes($resguardo, $user, $alcance),
            'permisos' => [
                'ver_etiquetas' => $puedeVerEtiquetas,
                'recibir' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR),
                'entregar' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR),
                'confirmar_custodia' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA),
                'incidencia_folio' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO),
                'incidencia_dano' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO),
                'incidencia_faltante' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE),
                'autorizar_incidencia' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA),
                'confirmar_devolucion' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION),
                'reponer_vencido' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO),
                'corregir' => $puedeOperar && $alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR),
            ],
        ];
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
