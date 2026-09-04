import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Loader2, Monitor, RefreshCw, ShieldOff, Users } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import TarjetaTurnoVentas from './Partials/TarjetaTurnoVentas';
import useTableroVentas from './Partials/useTableroVentas';
import { badgePrioridadTurno } from './Partials/turnosStyles';
import {
    etiquetasPrioridadDesdeTurno,
    formatearCronometro,
    milisegundosRestantes,
} from './Partials/tableroVentasUtils';

export default function Ventas({
    auth,
    tablero: tableroInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    catalogos = {},
}) {
    const puedeVer = Boolean(permisos.ver);
    const {
        tablero,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    } = useTableroVentas({ tablero: tableroInicial });

    const [mensajeAccion, setMensajeAccion] = useState(null);

    const turnoAsignado = tablero?.turno_asignado ?? null;
    const colaContextual = tablero?.cola_contextual ?? [];
    const servidorAt = ahoraServidor();

    const aplicarRespuestaMutacion = useCallback(() => {
        refrescar({ silencioso: true });
    }, [refrescar]);

    const manejarConflicto = useCallback(async () => {
        setMensajeAccion('Otro terminal modificó el turno. Actualizando tablero…');
        await refrescar({ silencioso: true });
        setMensajeAccion(null);
    }, [refrescar]);

    if (!puedeVer) {
        return (
            <AppLayout auth={auth}>
                <Head title="Tablero ventas | Punto de venta" />
                <GeliaPageShell className="max-w-[720px]">
                    <EstadoSinPermiso />
                </GeliaPageShell>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Tablero ventas | Punto de venta" />
            <GeliaPageShell className="max-w-[720px] space-y-5" data-ventas-tablero-root>
                <GeliaTituloCard
                    titulo="Tablero de ventas"
                    subtitulo="Turno asignado y atención en curso"
                    icono={Monitor}
                />

                <SelectorSucursalActivaPdv
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />

                <div className="flex justify-end">
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

                {cargando && !turnoAsignado && !error && (
                    <div className={`${geliaCardClass()} p-8 text-center`}>
                        <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                        <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando tablero…</p>
                    </div>
                )}

                {!cargando && !turnoAsignado && !error && (
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
                        onError={setMensajeAccion}
                    />
                )}

                {colaContextual.length > 0 && (
                    <section className="space-y-3" aria-labelledby="cola-contextual-titulo">
                        <div className="flex items-center gap-2">
                            <Users className="w-4 h-4 theme-text-muted" aria-hidden />
                            <h2 id="cola-contextual-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                                Reatención pendiente
                            </h2>
                        </div>
                        <ul className="space-y-2 m-0 p-0 list-none">
                            {colaContextual.map((turno) => (
                                <li key={turno.id} className={`${geliaCardClass()} p-4`}>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-lg font-black theme-text-main m-0">{turno.folio}</p>
                                            <p className="text-sm font-semibold theme-text-muted m-0 mt-1">
                                                {turno.snapshot_nombre_llamado}
                                            </p>
                                        </div>
                                        {turno.reatencion_expira_at && (
                                            <p className="text-xs font-bold theme-text-muted m-0">
                                                Ventana: {formatearCronometro(milisegundosRestantes(turno.reatencion_expira_at, servidorAt))}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2 mt-3">
                                        {etiquetasPrioridadDesdeTurno(turno).map((etiqueta) => (
                                            <span
                                                key={etiqueta}
                                                className={`inline-flex px-2 py-1 rounded-lg text-[10px] font-black uppercase ${badgePrioridadTurno(etiqueta)}`}
                                            >
                                                {etiqueta}
                                            </span>
                                        ))}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function EstadoSinPermiso() {
    return (
        <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
            <ShieldOff className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para ver el tablero de ventas</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita el permiso de consulta de turnos a quien administre accesos.
            </p>
        </div>
    );
}

function EstadoVacio() {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-3`}>
            <Monitor className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin turno asignado</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Cuando el sistema asigne un turno aparecerá aquí. El refresco es solo lectura.
            </p>
        </div>
    );
}
