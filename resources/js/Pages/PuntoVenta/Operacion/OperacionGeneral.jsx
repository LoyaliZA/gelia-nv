import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Clock, Loader2, RefreshCw, ShieldOff } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import TarjetaMiAtencion from './Partials/TarjetaMiAtencion';
import TarjetaGerenciaOperacion from './Partials/TarjetaGerenciaOperacion';
import EncabezadoGestionVendedores from './Partials/EncabezadoGestionVendedores';
import ListaEquipoGerencia from './Partials/ListaEquipoGerencia';
import useEstadoOperacion from './Partials/useEstadoOperacion';
import useAvisoSucursalToast from './Partials/useAvisoSucursalToast';
import PdvAlertProvider, { usePdvAlertReload } from '../../../Components/PuntoVenta/PdvAlertProvider';
import PdvEncabezadoAlertasPdv from '../../../Components/PuntoVenta/PdvEncabezadoAlertasPdv';
import AbrirPantallaSalaPdv from '../../../Components/PuntoVenta/AbrirPantallaSalaPdv';
import { PDV_VISTA_REALTIME } from '../../../utils/pdvRealtimeMatrix';
import useToastAlCambiar from '../../../hooks/useToastAlCambiar';
import { reportarInfoOperacion, reportarMensajeOperacion } from '../../../utils/geliaToast';

export default function OperacionGeneral({
    auth,
    estado: estadoInicial,
    reatencion: reatencionInicial = [],
    motivos_pausa: motivosPausaIniciales = [],
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    const puedeVer = Boolean(permisos.ver) || Boolean(permisos.equipo_ver);
    const esGerenciaEquipo = Boolean(permisos.equipo_gestionar);
    const mostrarMiAtencion = Boolean(permisos.ver) && !esGerenciaEquipo;
    const puedeVerEquipo = Boolean(permisos.equipo_ver);
    const puedeAsignarReatencion = Boolean(permisos.reatencion_asignar);
    const puedeAbrirPantallaSala = Boolean(permisos.pantalla_sala_abrir);
    const {
        estado,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    } = useEstadoOperacion({ estado: estadoInicial });

    const [motivosPausa, setMotivosPausa] = useState(motivosPausaIniciales);
    const servidorAt = ahoraServidor();
    const reatencion = Array.isArray(estado?.reatencion) ? estado.reatencion : reatencionInicial;

    useAvisoSucursalToast(estado);
    useToastAlCambiar(error, 'error');

    const manejarActualizado = useCallback(async (data) => {
        if (Array.isArray(data?.motivos_pausa)) {
            setMotivosPausa(data.motivos_pausa);
        }
        const nuevo = await refrescar({ silencioso: true });
        if (Array.isArray(nuevo?.motivos_pausa)) {
            setMotivosPausa(nuevo.motivos_pausa);
        }
    }, [refrescar]);

    const manejarConflicto = useCallback(async () => {
        reportarInfoOperacion('Otro terminal modificó el estado. Actualizando…');
        await refrescar({ silencioso: true });
    }, [refrescar]);

    if (!puedeVer) {
        return (
            <AppLayout auth={auth}>
                <Head title="Operación General | Punto de venta" />
                <GeliaPageShell className="max-w-[720px]">
                    <EstadoSinPermiso />
                </GeliaPageShell>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Operación General | Punto de venta" />
            <PdvAlertProvider
                sucursalId={sucursalActiva?.id}
                userId={auth?.user?.id}
                habilitado={Boolean(sucursalActiva?.id)}
            >
                <OperacionRealtimeSync refrescar={manejarActualizado} />
                <GeliaPageShell className="space-y-5" data-operacion-root>
                    <GeliaTituloCard
                        title="Operación General"
                        description="Jornada de sucursal, equipo y acciones gerenciales en un solo lugar"
                        aside={<PdvEncabezadoAlertasPdv />}
                        icon={Clock}
                    >
                        <SelectorSucursalActivaPdv
                            sucursalActiva={sucursalActiva}
                            sucursalesAsignadas={sucursalesAsignadas}
                            variante="compacto"
                        />
                    </GeliaTituloCard>

                    <div className="flex flex-wrap justify-end gap-3">
                        {puedeAbrirPantallaSala && (
                            <AbrirPantallaSalaPdv
                                sucursalActiva={sucursalActiva}
                                sucursalesAsignadas={sucursalesAsignadas}
                                variante="compact"
                            />
                        )}
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

                    {cargando && !estado && !error && (
                        <div className={`${geliaCardClass()} p-8 text-center`}>
                            <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                            <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando operación…</p>
                        </div>
                    )}

                    {estado && (
                        <>
                            {mostrarMiAtencion && (
                                <TarjetaMiAtencion
                                    estado={estado}
                                    servidorAt={servidorAt}
                                    nombre={auth?.user?.name}
                                    sucursal={sucursalActiva?.nombre}
                                />
                            )}

                            <TarjetaGerenciaOperacion
                                estado={estado}
                                permisos={permisos}
                                onActualizado={manejarActualizado}
                                onConflicto={manejarConflicto}
                                onError={reportarMensajeOperacion}
                            />

                            {puedeVerEquipo && (
                                <div className="space-y-5">
                                    <EncabezadoGestionVendedores
                                        resumen={estado.resumen}
                                        clientesEnFila={estado.clientes_en_fila ?? 0}
                                        clientesReatencion={reatencion.length}
                                    />
                                    <ListaEquipoGerencia
                                        equipo={estado.equipo ?? []}
                                        servidorAt={servidorAt}
                                        puedeGestionar={esGerenciaEquipo}
                                        puedeAsignarReatencion={puedeAsignarReatencion}
                                        reatenciones={reatencion}
                                        motivosPausa={motivosPausa}
                                        onActualizado={manejarActualizado}
                                        onConflicto={manejarConflicto}
                                        onError={reportarMensajeOperacion}
                                    />
                                </div>
                            )}
                        </>
                    )}
                </GeliaPageShell>
            </PdvAlertProvider>
        </AppLayout>
    );
}

function OperacionRealtimeSync({ refrescar }) {
    usePdvAlertReload({
        vista: PDV_VISTA_REALTIME.gerencia,
        refrescar,
    });
    return null;
}

function EstadoSinPermiso() {
    return (
        <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
            <ShieldOff className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para ver la operación</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita el permiso de consulta de turnos o de equipo a quien administre accesos.
            </p>
        </div>
    );
}
