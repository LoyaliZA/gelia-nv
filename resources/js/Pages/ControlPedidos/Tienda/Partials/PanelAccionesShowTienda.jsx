import React from 'react';
import {
    Package, CheckCircle2, Truck, User, Clock,
} from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';

function Meta({ label, children }) {
    return (
        <div>
            <p className="text-xs font-semibold theme-text-muted m-0">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0 mt-0.5 break-words">{children}</p>
        </div>
    );
}

export default function PanelAccionesShowTienda({
    tarea,
    faltantes = [],
    editable,
    enPendiente,
    listaTraslado,
    puedeTomar,
    puedeResponder,
    puedeTrasladar,
    puedeLiberar,
    esMunicipio,
    onTomar,
    onResponder,
    onConfirmarSalida,
    onToggleIncidencia,
    onLiberar,
    modoIncidencia,
}) {
    return (
        <aside className="gelia-tienda-op-panel-acciones space-y-4 self-start">
            <section className={`${geliaCardClass()} p-4 md:p-5 space-y-4`}>
                <h2 className="text-sm font-bold theme-text-main m-0">Resumen</h2>
                <div className="grid grid-cols-1 gap-3">
                    <Meta label="Cliente">{tarea.pedido?.cliente_nombre || '—'}</Meta>
                    <Meta label="Almacén">{tarea.almacen?.nombre || '—'}</Meta>
                    <Meta label="Piezas">{tarea.piezas_solicitadas ?? 0}</Meta>
                    {tarea.responsable?.name && (
                        <Meta label="Responsable">
                            <span className="inline-flex items-center gap-1">
                                <User className="w-3.5 h-3.5" /> {tarea.responsable.name}
                            </span>
                        </Meta>
                    )}
                    {tarea.solicitada_at && (
                        <Meta label="Solicitada">
                            <span className="inline-flex items-center gap-1">
                                <Clock className="w-3.5 h-3.5" /> {formatearFechaNegocio(tarea.solicitada_at)}
                            </span>
                        </Meta>
                    )}
                    {tarea.fecha_limite && (
                        <Meta label="Vencimiento">
                            <span className="text-primario font-bold">
                                {formatearFechaNegocio(tarea.fecha_limite)}
                            </span>
                        </Meta>
                    )}
                </div>
                {faltantes.length > 0 && editable && (
                    <div className="rounded-xl border border-[color-mix(in_srgb,var(--color-peligro)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_6%,transparent)] p-3">
                        <p className="text-xs font-bold theme-text-main m-0 mb-1">Pendiente para enviar</p>
                        <ul className="text-xs theme-text-muted list-disc pl-4 m-0 space-y-0.5">
                            {faltantes.map((f) => <li key={f}>{f}</li>)}
                        </ul>
                    </div>
                )}
            </section>

            <div className={`${geliaCardClass()} p-4 space-y-2`}>
                <p className="text-xs font-semibold theme-text-muted m-0 mb-1">Acciones</p>
                {enPendiente && (
                    <button type="button" className={`${BTN_PRIMARY} w-full min-h-[44px]`} onClick={onTomar}>
                        <Package className="w-4 h-4 inline mr-1" /> Tomar tarea
                    </button>
                )}
                {editable && (
                    <>
                        <button
                            type="button"
                            className={`${BTN_PRIMARY} w-full min-h-[44px]`}
                            disabled={faltantes.length > 0}
                            onClick={onResponder}
                            title={faltantes.length > 0 ? faltantes.join(', ') : undefined}
                        >
                            <CheckCircle2 className="w-4 h-4 inline mr-1" />
                            {tarea.requiere_traslado_cedis
                                ? 'Marcar lista para traslado'
                                : (esMunicipio ? 'Marcar lista para carátula' : 'Responder preparación')}
                        </button>
                        <button
                            type="button"
                            className={`${BTN_SECONDARY} w-full min-h-[44px]`}
                            onClick={onToggleIncidencia}
                        >
                            {modoIncidencia ? 'Ocultar incidencia' : 'Reportar incidencia'}
                        </button>
                    </>
                )}
                {listaTraslado && puedeTrasladar && (
                    <button type="button" className={`${BTN_PRIMARY} w-full min-h-[44px]`} onClick={onConfirmarSalida}>
                        <Truck className="w-4 h-4 inline mr-1" /> Confirmar salida a CEDIS
                    </button>
                )}
                {puedeLiberar && ['RESPONDIDA', 'LIBERACION_SOLICITADA', 'RECIBIDA_CEDIS'].includes(tarea.estado) && (
                    <button type="button" className={`${BTN_SECONDARY} w-full min-h-[44px]`} onClick={onLiberar}>
                        Liberar mercancía
                    </button>
                )}
            </div>
        </aside>
    );
}
