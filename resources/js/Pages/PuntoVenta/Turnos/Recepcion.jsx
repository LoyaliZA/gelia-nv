import React from 'react';
import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ShieldOff, Ticket } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import BandejaColaRecepcionTurno from './Partials/BandejaColaRecepcionTurno';
import EncabezadoRecepcionTurno from './Partials/EncabezadoRecepcionTurno';
import FormularioAltaTurno from './Partials/FormularioAltaTurno';
import useAltaTurno from './Partials/useAltaTurno';
import useBandejaRecepcionTurno from './Partials/useBandejaRecepcionTurno';
import ModalErrorAltaTurno from './Partials/ModalErrorAltaTurno';
import {
    etiquetaEstadoTurno,
    etiquetasPrioridadTurno,
} from './Partials/altaTurnoUtils';
import { PDV_VISTA_REALTIME } from '../../../utils/pdvRealtimeMatrix';
import { badgeEstadoTurno, badgePrioridadTurno } from './Partials/turnosStyles';
import PdvAlertProvider, { usePdvAlertContext, usePdvAlertReload } from '../../../Components/PuntoVenta/PdvAlertProvider';

export default function Recepcion({
    auth,
    bandeja: bandejaInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursal_dia: sucursalDia = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    catalogos = {},
}) {
    const puedeAlta = Boolean(permisos.alta);
    const puedeVerBandeja = Boolean(permisos.ver);

    const {
        bandeja,
        cargando: bandejaCargando,
        error: bandejaError,
        refrescar: refrescarBandeja,
    } = useBandejaRecepcionTurno({
        bandeja: bandejaInicial,
        habilitado: puedeVerBandeja,
    });

    const {
        enviar,
        enviando,
        error,
        turnoCreado,
        reiniciar,
        mostrarError,
        limpiarError,
    } = useAltaTurno({
        onExito: () => refrescarBandeja({ silencioso: true }),
        bandeja: puedeVerBandeja ? bandeja : null,
    });

    return (
        <AppLayout auth={auth}>
            <Head title="Recepción de turnos | Punto de venta" />
            <PdvAlertProvider
                sucursalId={sucursalActiva?.id}
                userId={auth?.user?.id}
                habilitado={Boolean(sucursalActiva?.id)}
            >
                <RecepcionRealtimeSync refrescarBandeja={refrescarBandeja} habilitado={puedeVerBandeja} />
                <RecepcionContenido
                    puedeAlta={puedeAlta}
                    puedeVerBandeja={puedeVerBandeja}
                    bandeja={bandeja}
                    bandejaCargando={bandejaCargando}
                    bandejaError={bandejaError}
                    refrescarBandeja={refrescarBandeja}
                    permisos={permisos}
                    catalogos={catalogos}
                    sucursalActiva={sucursalActiva}
                    sucursalDia={sucursalDia}
                    sucursalesAsignadas={sucursalesAsignadas}
                    turnoCreado={turnoCreado}
                    reiniciar={reiniciar}
                    enviar={enviar}
                    enviando={enviando}
                    mostrarError={mostrarError}
                    error={error}
                    limpiarError={limpiarError}
                />
            </PdvAlertProvider>
        </AppLayout>
    );
}

function RecepcionContenido({
    puedeAlta,
    puedeVerBandeja,
    bandeja,
    bandejaCargando,
    bandejaError,
    refrescarBandeja,
    permisos,
    catalogos,
    sucursalActiva,
    sucursalDia,
    sucursalesAsignadas,
    turnoCreado,
    reiniciar,
    enviar,
    enviando,
    mostrarError,
    error,
    limpiarError,
}) {
    const { estadoConexion, ultimaActualizacionConfirmada } = usePdvAlertContext() ?? {};

    return (
        <GeliaPageShell className="max-w-7xl space-y-4" data-recepcion-turno-root>
            <GeliaTituloCard
                title="Recepción de turnos"
                description="Registro y supervisión de fila en mostrador"
                icon={Ticket}
            />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <SelectorSucursalActivaPdv
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />
            </div>

            {puedeVerBandeja && (
                <EncabezadoRecepcionTurno
                    bandeja={bandeja}
                    estadoConexion={estadoConexion}
                    ultimaActualizacion={ultimaActualizacionConfirmada}
                />
            )}

            <div
                className={`grid gap-4 lg:gap-5 ${
                    puedeVerBandeja && (puedeAlta || turnoCreado)
                        ? 'lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]'
                        : ''
                }`}
                data-recepcion-turno-layout
            >
                {(puedeAlta || turnoCreado) && (
                    <div className="min-w-0 order-1">
                        {puedeAlta ? (
                            turnoCreado ? (
                                <ConfirmacionFolio
                                    turno={turnoCreado}
                                    catalogos={catalogos}
                                    onNuevo={reiniciar}
                                />
                            ) : (
                                <FormularioAltaTurno
                                    permisos={permisos}
                                    catalogos={catalogos}
                                    bandeja={bandeja}
                                    sucursalDia={sucursalDia}
                                    enviando={enviando}
                                    onEnviar={enviar}
                                    onMostrarError={mostrarError}
                                />
                            )
                        ) : null}
                    </div>
                )}

                {puedeVerBandeja ? (
                    <div className="min-w-0 order-2">
                        <BandejaColaRecepcionTurno
                            bandeja={bandeja}
                            cargando={bandejaCargando}
                            error={bandejaError}
                            refrescar={refrescarBandeja}
                            permisos={permisos}
                            catalogos={catalogos}
                            onTurnoDadoDeBaja={() => refrescarBandeja({ silencioso: true })}
                        />
                    </div>
                ) : !puedeAlta ? (
                    <EstadoSinPermiso />
                ) : null}
            </div>

            <ModalErrorAltaTurno
                abierto={Boolean(error)}
                mensaje={error}
                onClose={limpiarError}
            />
        </GeliaPageShell>
    );
}

function RecepcionRealtimeSync({ refrescarBandeja, habilitado }) {
    usePdvAlertReload({
        vista: PDV_VISTA_REALTIME.recepcion,
        refrescar: refrescarBandeja,
        habilitado,
    });
    return null;
}

function EstadoSinPermiso() {
    return (
        <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
            <ShieldOff className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
            <p className="text-sm font-bold theme-text-main m-0">Sin permiso para operar recepción de turnos</p>
            <p className="text-xs font-semibold theme-text-muted m-0">
                Solicita permisos de consulta o alta de turnos a quien administre accesos.
            </p>
        </div>
    );
}

function ConfirmacionFolio({ turno, catalogos, onNuevo }) {
    const etiquetas = etiquetasPrioridadTurno(turno);
    const estadoEtiqueta = etiquetaEstadoTurno(turno?.estado, catalogos);
    const enCola = turno?.estado === 'EN_COLA';

    return (
        <div className={`${geliaCardClass()} p-6 space-y-5`}>
            <div className="text-center space-y-2">
                <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500" aria-hidden />
                <h2 className="text-lg font-black uppercase theme-text-main m-0">Turno registrado</h2>
                <p className="text-4xl font-black tracking-wider theme-text-main m-0" aria-live="polite">
                    {turno?.folio}
                </p>
                <p className="text-sm font-semibold theme-text-muted m-0">
                    Folio asignado por el servidor
                </p>
            </div>

            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Nombre para llamado</dt>
                    <dd className="font-bold theme-text-main m-0 mt-1">{turno?.snapshot_nombre_llamado || '—'}</dd>
                </div>
                <div>
                    <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</dt>
                    <dd className="m-0 mt-1">
                        <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badgeEstadoTurno(turno?.estado)}`}>
                            {estadoEtiqueta}
                        </span>
                    </dd>
                </div>
            </dl>

            {etiquetas.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {etiquetas.map((etiqueta) => (
                        <span
                            key={etiqueta}
                            className={`inline-flex px-2.5 py-1 rounded-lg text-[10px] font-black uppercase ${badgePrioridadTurno(etiqueta)}`}
                        >
                            {etiqueta}
                        </span>
                    ))}
                </div>
            )}

            {enCola && (
                <p className="text-xs font-semibold theme-text-muted m-0 flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden />
                    El turno quedó en cola hasta que haya una persona de ventas disponible.
                </p>
            )}

            <button
                type="button"
                onClick={onNuevo}
                className={`${THEME_BTN_PRIMARY} w-full min-h-[48px]`}
            >
                Registrar otro turno
            </button>
        </div>
    );
}
