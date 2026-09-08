import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Loader2, Headphones, RefreshCw, ShieldOff } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import TarjetaTurnoVentas from './Partials/TarjetaTurnoVentas';
import TarjetaMiAtencion from '../Operacion/Partials/TarjetaMiAtencion';
import useTableroVentas from './Partials/useTableroVentas';
import { mostrarBandejaSinTurno } from '../Operacion/Partials/operacionUtils';
import PdvAlertProvider, { usePdvAlertContext, usePdvAlertReload } from '../../../Components/PuntoVenta/PdvAlertProvider';
import IndicadorConexionTiempoRealPdv from '../../../Components/PuntoVenta/IndicadorConexionTiempoRealPdv';
import { PDV_VISTA_REALTIME } from '../../../utils/pdvRealtimeMatrix';

export default function Ventas({
    auth,
    tablero: tableroInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    catalogos = {},
}) {
    const puedeAtender = Boolean(permisos.atender);
    const {
        tablero,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    } = useTableroVentas({ tablero: tableroInicial });

    const [mensajeAccion, setMensajeAccion] = useState(null);

    const turnoAsignado = tablero?.turno_asignado ?? null;
    const estadoVendedor = tablero?.estado_vendedor ?? null;
    const servidorAt = ahoraServidor();
    const estadoAtencion = {
        estado_vendedor: estadoVendedor,
        cronometro: tablero?.cronometro,
        pausa_motivo: tablero?.pausa_motivo,
    };

    const aplicarRespuestaMutacion = useCallback(() => {
        refrescar({ silencioso: true });
    }, [refrescar]);

    const manejarConflicto = useCallback(async () => {
        setMensajeAccion('Otro terminal modificó el turno. Actualizando atención…');
        await refrescar({ silencioso: true });
        setMensajeAccion(null);
    }, [refrescar]);

    if (!puedeAtender) {
        return (
            <AppLayout auth={auth}>
                <Head title="Mi atención | Punto de venta" />
                <GeliaPageShell className="max-w-[720px]">
                    <EstadoSinPermiso />
                </GeliaPageShell>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Mi atención | Punto de venta" />
            <PdvAlertProvider
                sucursalId={sucursalActiva?.id}
                userId={auth?.user?.id}
                habilitado={Boolean(sucursalActiva?.id)}
            >
                <VentasRealtimeSync refrescar={refrescar} userId={auth?.user?.id} />
                <VentasContenido
                    auth={auth}
                    tablero={tablero}
                    cargando={cargando}
                    error={error}
                    refrescar={refrescar}
                    turnoAsignado={turnoAsignado}
                    estadoVendedor={estadoVendedor}
                    estadoAtencion={estadoAtencion}
                    servidorAt={servidorAt}
                    mensajeAccion={mensajeAccion}
                    aplicarRespuestaMutacion={aplicarRespuestaMutacion}
                    manejarConflicto={manejarConflicto}
                    onError={setMensajeAccion}
                    permisos={permisos}
                    catalogos={catalogos}
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />
            </PdvAlertProvider>
        </AppLayout>
    );
}

function VentasContenido({
    auth,
    tablero,
    cargando,
    error,
    refrescar,
    turnoAsignado,
    estadoVendedor,
    estadoAtencion,
    servidorAt,
    mensajeAccion,
    aplicarRespuestaMutacion,
    manejarConflicto,
    onError,
    permisos,
    catalogos,
    sucursalActiva,
    sucursalesAsignadas,
}) {
    const { estadoConexion, ultimaActualizacionConfirmada } = usePdvAlertContext() ?? {};

    return (
                <GeliaPageShell className="max-w-[720px] space-y-5" data-ventas-tablero-root>
                <GeliaTituloCard
                    title="Mi atención"
                    description="Turno asignado y atención en curso"
                    icon={Headphones}
                />

                <SelectorSucursalActivaPdv
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <IndicadorConexionTiempoRealPdv
                        estadoConexion={estadoConexion}
                        ultimaActualizacion={ultimaActualizacionConfirmada || tablero?.servidor_at || servidorAt}
                    />
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2`}
                        disabled={cargando}
                        onClick={() => refrescar()}
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <RefreshCw className="w-4 h-4" aria-hidden />}
                        Actualizar
                    </button>
                </div>

                {error && (
                    <div className={`${geliaCardClass()} p-4 flex items-start gap-3`}>
                        <AlertTriangle className="w-5 h-5 text-red-500 shrink-0" aria-hidden />
                        <p className="text-sm font-semibold text-red-600 dark:text-red-400 m-0">{error}</p>
                    </div>
                )}

                {mensajeAccion && (
                    <div className={`${geliaCardClass()} p-4`}>
                        <p className="text-sm font-semibold theme-text-muted m-0">{mensajeAccion}</p>
                    </div>
                )}

                {tablero && (
                    <TarjetaMiAtencion
                        estado={estadoAtencion}
                        turnoAsignado={turnoAsignado}
                        nombre={auth?.user?.name}
                        sucursal={sucursalActiva?.nombre}
                        servidorAt={tablero?.servidor_at || servidorAt}
                    />
                )}

                {cargando && !turnoAsignado && !error && (
                    <div className={`${geliaCardClass()} p-8 text-center`}>
                        <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                        <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando atención…</p>
                    </div>
                )}

                {!cargando && !turnoAsignado && !error && mostrarBandejaSinTurno(estadoVendedor) && (
                    <EstadoVacio />
                )}

                {turnoAsignado && (
                    <TarjetaTurnoVentas
                        turno={turnoAsignado}
                        plazos={tablero?.plazos}
                        servidorAt={servidorAt}
                        permisos={permisos}
                        catalogos={catalogos}
                        personasTransferencia={tablero?.personas_transferencia ?? []}
                        onActualizado={aplicarRespuestaMutacion}
                        onConflicto={manejarConflicto}
                        onError={onError}
                    />
                )}
                </GeliaPageShell>
    );
}

function VentasRealtimeSync({ refrescar, userId }) {
    usePdvAlertReload({
        vista: PDV_VISTA_REALTIME.vendedor,
        userId,
        refrescar,
    });
    return null;
}

function EstadoSinPermiso() {
    return (
        <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
            <ShieldOff className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para ver Mi atención</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita el permiso de atender turnos a quien administre accesos.
            </p>
        </div>
    );
}

function EstadoVacio() {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-3`}>
            <Headphones className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin turno asignado</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Cuando el sistema asigne un turno aparecerá aquí. El refresco es solo lectura.
            </p>
        </div>
    );
}
