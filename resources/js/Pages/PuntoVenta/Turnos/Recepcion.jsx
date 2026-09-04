import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ShieldOff, Ticket } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '../Resguardos/Partials/SelectorSucursalActivaPdv';
import BandejaColaRecepcionTurno from './Partials/BandejaColaRecepcionTurno';
import FormularioAltaTurno from './Partials/FormularioAltaTurno';
import useAltaTurno from './Partials/useAltaTurno';
import {
    etiquetaEstadoTurno,
    etiquetasPrioridadTurno,
} from './Partials/altaTurnoUtils';
import { badgeEstadoTurno, badgePrioridadTurno } from './Partials/turnosStyles';

export default function Recepcion({
    auth,
    bandeja: bandejaInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    catalogos = {},
}) {
    const puedeAlta = Boolean(permisos.alta);
    const puedeVerBandeja = Boolean(permisos.ver);
    const [senalRefresco, setSenalRefresco] = useState(0);

    const refrescarBandeja = useCallback(() => {
        setSenalRefresco((valor) => valor + 1);
    }, []);

    const {
        enviar,
        enviando,
        error,
        turnoCreado,
        reiniciar,
    } = useAltaTurno({
        onExito: refrescarBandeja,
    });

    return (
        <AppLayout auth={auth}>
            <Head title="Recepción de turnos | Punto de venta" />
            <GeliaPageShell className="max-w-[720px] space-y-5" data-recepcion-turno-root>
                <GeliaTituloCard
                    titulo="Recepción de turnos"
                    subtitulo="Cola, asignados y alta en mostrador"
                    icono={Ticket}
                />

                <SelectorSucursalActivaPdv
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />

                {puedeVerBandeja && (
                    <BandejaColaRecepcionTurno
                        bandeja={bandejaInicial}
                        permisos={permisos}
                        catalogos={catalogos}
                        senalRefresco={senalRefresco}
                        onTurnoDadoDeBaja={refrescarBandeja}
                    />
                )}

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
                            enviando={enviando}
                            error={error}
                            onEnviar={enviar}
                        />
                    )
                ) : !puedeVerBandeja ? (
                    <EstadoSinPermiso />
                ) : null}
            </GeliaPageShell>
        </AppLayout>
    );
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
