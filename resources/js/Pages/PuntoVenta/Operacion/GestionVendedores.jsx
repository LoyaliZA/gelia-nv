import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Loader2, RefreshCw, ShieldOff, Users } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import EncabezadoGestionVendedores from './Partials/EncabezadoGestionVendedores';
import ListaEquipoGerencia from './Partials/ListaEquipoGerencia';
import PanelReatencionGerencia from './Partials/PanelReatencionGerencia';
import useEstadoOperacion from './Partials/useEstadoOperacion';
import PdvAlertProvider, { usePdvAlertContext, usePdvAlertReload } from '../../../Components/PuntoVenta/PdvAlertProvider';
import AbrirPantallaSalaPdv from '../../../Components/PuntoVenta/AbrirPantallaSalaPdv';
import { PDV_VISTA_REALTIME } from '../../../utils/pdvRealtimeMatrix';

export default function GestionVendedores({
    auth,
    estado: estadoInicial,
    reatencion: reatencionInicial = [],
    motivos_pausa: motivosPausaIniciales = [],
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    const puedeVer = Boolean(permisos.equipo_ver);
    const puedeGestionar = Boolean(permisos.equipo_gestionar);
    const puedeAsignarReatencion = Boolean(permisos.reatencion_asignar);
    const puedeAbrirPantallaSala = Boolean(permisos.pantalla_sala_abrir);
    const {
        estado,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    } = useEstadoOperacion({
        estado: estadoInicial,
        datosRoute: 'punto_venta.operacion.vendedores.datos',
    });

    const [mensajeAccion, setMensajeAccion] = useState(null);
    const [motivosPausa, setMotivosPausa] = useState(motivosPausaIniciales);
    const servidorAt = ahoraServidor();
    const reatencion = Array.isArray(estado?.reatencion) ? estado.reatencion : reatencionInicial;

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
        setMensajeAccion('Otro terminal modificó el estado. Actualizando…');
        await refrescar({ silencioso: true });
        setMensajeAccion(null);
    }, [refrescar]);

    if (!puedeVer) {
        return (
            <AppLayout auth={auth}>
                <Head title="Gestión de vendedores | Punto de venta" />
                <GeliaPageShell>
                    <EstadoSinPermiso />
                </GeliaPageShell>
            </AppLayout>
        );
    }

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de vendedores | Punto de venta" />
            <PdvAlertProvider
                sucursalId={sucursalActiva?.id}
                userId={auth?.user?.id}
                habilitado={Boolean(sucursalActiva?.id)}
                mostrarPreferencias={false}
            >
                <GestionVendedoresRealtimeSync refrescar={manejarActualizado} />
                <GestionVendedoresContenido
                    estado={estado}
                    cargando={cargando}
                    error={error}
                    mensajeAccion={mensajeAccion}
                    servidorAt={servidorAt}
                    puedeGestionar={puedeGestionar}
                    puedeAsignarReatencion={puedeAsignarReatencion}
                    puedeAbrirPantallaSala={puedeAbrirPantallaSala}
                    reatencion={reatencion}
                    motivosPausa={motivosPausa}
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                    onRefrescar={() => refrescar()}
                    onActualizado={manejarActualizado}
                    onConflicto={manejarConflicto}
                    onError={setMensajeAccion}
                />
            </PdvAlertProvider>
        </AppLayout>
    );
}

function GestionVendedoresContenido({
    estado,
    cargando,
    error,
    mensajeAccion,
    servidorAt,
    puedeGestionar,
    puedeAsignarReatencion = false,
    puedeAbrirPantallaSala = false,
    reatencion = [],
    motivosPausa = [],
    sucursalActiva,
    sucursalesAsignadas,
    onRefrescar,
    onActualizado,
    onConflicto,
    onError,
}) {
    const { estadoConexion, ultimaActualizacionConfirmada } = usePdvAlertContext() ?? {};

    return (
        <GeliaPageShell className="space-y-5" data-gestion-vendedores-root>
            <GeliaTituloCard
                title="Gestión de vendedores"
                description="Estado del equipo, jornadas y acciones gerenciales en la sucursal activa"
                icon={Users}
            />

            <SelectorSucursalActivaPdv
                sucursalActiva={sucursalActiva}
                sucursalesAsignadas={sucursalesAsignadas}
            />

            {puedeAbrirPantallaSala && (
                <div className="flex justify-end">
                    <AbrirPantallaSalaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compact"
                    />
                </div>
            )}

            <div className="flex justify-end">
                <button
                    type="button"
                    className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2`}
                    disabled={cargando}
                    onClick={onRefrescar}
                >
                    {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <RefreshCw className="w-4 h-4" aria-hidden />}
                    Actualizar
                </button>
            </div>

            {error && (
                <div className={`${geliaCardClass()} p-4 flex items-start gap-3`}>
                    <ShieldOff className="w-5 h-5 text-red-500 shrink-0" aria-hidden />
                    <p className="text-sm font-semibold text-red-600 dark:text-red-400 m-0">{error}</p>
                </div>
            )}

            {mensajeAccion && (
                <div className={`${geliaCardClass()} p-4`}>
                    <p className="text-sm font-semibold theme-text-muted m-0">{mensajeAccion}</p>
                </div>
            )}

            {cargando && !estado && !error && (
                <div className={`${geliaCardClass()} p-8 text-center`}>
                    <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                    <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando equipo…</p>
                </div>
            )}

            {estado && (
                <div className={`grid gap-5 ${puedeAsignarReatencion ? 'lg:grid-cols-[minmax(0,1fr)_minmax(260px,320px)]' : ''}`}>
                    <div className="space-y-5 min-w-0">
                        <EncabezadoGestionVendedores
                            resumen={estado.resumen}
                            clientesEnFila={estado.clientes_en_fila ?? 0}
                            estadoConexion={estadoConexion}
                            ultimaActualizacion={ultimaActualizacionConfirmada || servidorAt}
                        />
                        <ListaEquipoGerencia
                            equipo={estado.equipo ?? []}
                            servidorAt={servidorAt}
                            puedeGestionar={puedeGestionar}
                            motivosPausa={motivosPausa}
                            onActualizado={onActualizado}
                            onConflicto={onConflicto}
                            onError={onError}
                        />
                    </div>
                    {puedeAsignarReatencion && (
                        <PanelReatencionGerencia
                            reatenciones={reatencion}
                            servidorAt={servidorAt}
                            puedeAsignar={puedeAsignarReatencion}
                            onActualizado={onActualizado}
                            onConflicto={onConflicto}
                            onError={onError}
                        />
                    )}
                </div>
            )}
        </GeliaPageShell>
    );
}

function GestionVendedoresRealtimeSync({ refrescar }) {
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
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para consultar el equipo</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita el permiso de consulta de equipo en piso a quien administre accesos.
            </p>
        </div>
    );
}
