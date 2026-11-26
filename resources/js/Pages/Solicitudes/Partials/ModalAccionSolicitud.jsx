import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import React from 'react';
import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import CabeceraFlujoSolicitud from '@/Components/Solicitudes/CabeceraFlujoSolicitud';
import AvisoSolicitud from '@/Components/Solicitudes/AvisoSolicitud';
import { ACCION_VISUAL } from './solicitudesStyles';
import PrevisualizacionCancelacionSolicitud from './PrevisualizacionCancelacionSolicitud';

const ACCIONES = {
    pago: { titulo: 'Confirmar pago', ruta: 'solicitudes.confirmar_pago', boton: 'Confirmar pago', texto: 'Registra el monto final cobrado. Si no alcanza el nivel de lista, supervisión recibirá una alerta.' },
    solicitar: { titulo: 'Solicitar cancelación', ruta: 'solicitudes.solicitar_cancelacion', boton: 'Enviar solicitud de cancelación', texto: 'Supervisión deberá confirmar la cancelación. Explica el motivo para que pueda revisarlo.', peligro: true },
    cancelar: { titulo: 'Confirmar cancelación', ruta: 'solicitudes.cancelar', boton: 'Confirmar cancelación', texto: 'Revisa los cambios que se aplicarán al cliente al confirmar.', peligro: true },
    lista: { titulo: 'Confirmar ajuste de lista', ruta: 'solicitudes.confirmar_lista', boton: 'Confirmar ajuste', texto: 'La lista del cliente se ajustará según el pago registrado.' },
    rollback: { titulo: 'Confirmar reversión', ruta: 'solicitudes.confirmar_rollback', boton: 'Confirmar reversión', texto: 'Se revertirán los cambios por vencimiento de pago. Ventas deberá iniciar una nueva solicitud.', peligro: true },
    eliminar: { titulo: 'Eliminar solicitud', ruta: 'solicitudes.destroy', boton: 'Eliminar solicitud', texto: 'El registro se retirará de la bandeja y se conservará un respaldo en la auditoría. Indica por qué lo eliminas.', peligro: true },
};

// Gravedad de cada aviso: peligro para lo que revierte o elimina, aviso para lo que espera a otra persona, info para el resto.
const AVISO_ACCION = {
    pago: { tono: 'info', titulo: 'Registro del pago' },
    solicitar: { tono: 'aviso', titulo: 'Requiere confirmación de supervisión' },
    cancelar: { tono: 'peligro', titulo: 'Esta cancelación revierte cambios' },
    lista: { tono: 'info', titulo: 'Ajuste de lista' },
    rollback: { tono: 'peligro', titulo: 'Esta reversión no se puede deshacer aquí' },
    eliminar: { tono: 'peligro', titulo: 'La solicitud saldrá de la bandeja' },
};

export default function ModalAccionSolicitud({ accion, solicitud, onClose, onProcesando }) {
    const config = ACCIONES[accion];
    const requiereMotivo = accion === 'solicitar' || accion === 'eliminar';
    const esCancelacion = accion === 'solicitar' || accion === 'cancelar';
    const campoMotivo = accion === 'eliminar' ? 'motivo' : 'motivo_cancelacion';
    const inicial = accion === 'pago' ? { modo: 'pago', monto_final_pagado: solicitud.monto_cotizado || '' }
        : requiereMotivo ? { [campoMotivo]: '' } : {};
    const { data, setData, post, put, delete: destroy, processing, errors, isDirty } = useForm(inicial);
    useSolicitudErrores(errors);
    const enviar = (event) => {
        event.preventDefault();
        if (processing) return;
        onProcesando?.(true);
        const guardar = accion === 'eliminar' ? destroy : accion === 'solicitar' ? post : put;
        guardar(route(config.ruta, solicitud.id), { preserveScroll: true, onSuccess: onClose, onFinish: () => onProcesando?.(false) });
    };
    const visual = ACCION_VISUAL[accion] || ACCION_VISUAL.crear;
    return (
        <SolicitudDialog title={config.titulo} onClose={onClose} busy={processing} dirty={isDirty} className="gelia-tag-lista-overlay">
            <form onSubmit={enviar} className="gelia-modal-shell gelia-tag-accion w-full max-w-lg">
                <CabeceraFlujoSolicitud
                    icono={visual.icono}
                    tono={visual.tono}
                    titulo={config.titulo}
                    subtitulo={`FOL-${solicitud.id} · ${solicitud.cliente?.nombre || 'Cliente'}`}
                    closeLabel="Cerrar acción"
                    disabled={processing}
                />
                <div className="gelia-modal-body p-5 sm:p-6 space-y-5">
                    <AvisoSolicitud tono={AVISO_ACCION[accion]?.tono} titulo={AVISO_ACCION[accion]?.titulo}>{config.texto}</AvisoSolicitud>
                    {esCancelacion && <PrevisualizacionCancelacionSolicitud solicitud={solicitud} confirmar={accion === 'cancelar'} />}
                    {accion === 'cancelar' && <AvisoSolicitud tono="neutro" titulo="Motivo de cancelación">{solicitud.motivo_cancelacion || 'Sin motivo registrado.'}</AvisoSolicitud>}
                    {accion === 'pago' && <div className="space-y-2"><label htmlFor="accion-monto" className="theme-label">Monto final cobrado (MXN)</label><input id="accion-monto" name="monto_final_pagado" type="number" inputMode="decimal" min="0" step="0.01" required autoComplete="off" value={data.monto_final_pagado} onChange={e => setData('monto_final_pagado', e.target.value)} disabled={processing} aria-invalid={!!errors.monto_final_pagado} className="theme-input w-full" /></div>}
                    {requiereMotivo && <div className="space-y-2"><label htmlFor="accion-motivo" className="theme-label">Motivo (obligatorio)</label><textarea id="accion-motivo" name={campoMotivo} autoComplete="off" required minLength={10} rows={4} value={data[campoMotivo]} onChange={e => setData(campoMotivo, e.target.value)} disabled={processing} aria-invalid={!!errors[campoMotivo]} className="theme-textarea w-full" placeholder="Describe el motivo con al menos 10 caracteres…" /></div>}
                    {Object.entries(errors).map(([key, error]) => <AvisoSolicitud key={key} tono="peligro" compacto anunciar>{error}</AvisoSolicitud>)}
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions"><button type="button" data-dialog-close disabled={processing} className="theme-btn-secondary">Volver</button><button type="submit" disabled={processing} className={config.peligro ? 'theme-btn-danger' : 'theme-btn-primary'}>{processing && <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}{processing ? 'Guardando…' : config.boton}</button></footer>
            </form>
        </SolicitudDialog>
    );
}
