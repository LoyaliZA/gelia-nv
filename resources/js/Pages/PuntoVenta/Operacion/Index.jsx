import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Clock, Loader2, RefreshCw, ShieldOff, Users } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import ListaEquipoOperativo from './Partials/ListaEquipoOperativo';
import TarjetaEstadoJornada from './Partials/TarjetaEstadoJornada';
import TarjetaGerenciaOperacion from './Partials/TarjetaGerenciaOperacion';
import useEstadoOperacion from './Partials/useEstadoOperacion';
import { mensajeAvisoSucursal } from './Partials/operacionUtils';

export default function Index({
    auth,
    estado: estadoInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    const puedeVer = Boolean(permisos.ver);
    const {
        estado,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    } = useEstadoOperacion({ estado: estadoInicial });

    const [mensajeAccion, setMensajeAccion] = useState(null);
    const servidorAt = ahoraServidor();
    const avisoSucursal = mensajeAvisoSucursal(estado);

    const manejarActualizado = useCallback(async () => {
        await refrescar({ silencioso: true });
    }, [refrescar]);

    const manejarConflicto = useCallback(async () => {
        setMensajeAccion('Otro terminal modificó el estado. Actualizando…');
        await refrescar({ silencioso: true });
        setMensajeAccion(null);
    }, [refrescar]);

    if (!puedeVer) {
        return (
            <AppLayout auth={auth}>
                <Head title="Operación | Punto de venta" />
                <GeliaPageShell className="max-w-[720px]">
                    <EstadoSinPermiso />
                </GeliaPageShell>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Operación | Punto de venta" />
            <GeliaPageShell className="max-w-[720px] space-y-5" data-operacion-root>
                <GeliaTituloCard
                    titulo="Operación de piso"
                    subtitulo="Jornada, pausa y estado de sucursal"
                    icono={Clock}
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

                {avisoSucursal && (
                    <div className={`${geliaCardClass()} p-4 border-l-4 border-amber-500`}>
                        <p className="text-sm font-semibold text-amber-800 dark:text-amber-200 m-0">{avisoSucursal}</p>
                    </div>
                )}

                {cargando && !estado && !error && (
                    <div className={`${geliaCardClass()} p-8 text-center`}>
                        <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                        <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando operación…</p>
                    </div>
                )}

                {estado && (
                    <>
                        {(permisos.jornada_abrir || permisos.jornada_cerrar || permisos.pausa) && (
                            <TarjetaEstadoJornada
                                estado={estado}
                                permisos={permisos}
                                servidorAt={servidorAt}
                                onActualizado={manejarActualizado}
                                onConflicto={manejarConflicto}
                                onError={setMensajeAccion}
                            />
                        )}

                        <TarjetaGerenciaOperacion
                            estado={estado}
                            permisos={permisos}
                            onActualizado={manejarActualizado}
                            onConflicto={manejarConflicto}
                            onError={setMensajeAccion}
                        />

                        <section className="space-y-3" aria-labelledby="equipo-operacion-titulo">
                            <div className="flex items-center gap-2">
                                <Users className="w-4 h-4 theme-text-muted" aria-hidden />
                                <h2 id="equipo-operacion-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                                    Equipo en sucursal
                                </h2>
                            </div>
                            <p className="text-xs font-semibold theme-text-muted m-0">
                                Solo lectura. La disponibilidad de chat no sustituye la jornada PDV.
                            </p>
                            <ListaEquipoOperativo equipo={estado.equipo ?? []} />
                        </section>
                    </>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function EstadoSinPermiso() {
    return (
        <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
            <ShieldOff className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para ver la operación</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita el permiso de consulta de turnos a quien administre accesos.
            </p>
        </div>
    );
}
