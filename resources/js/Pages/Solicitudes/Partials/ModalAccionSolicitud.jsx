import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import React from 'react';
import { useForm } from '@inertiajs/react';
import { Loader2, X } from 'lucide-react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';

const ACCIONES = {
    pago: { titulo: 'Confirmar pago', ruta: 'solicitudes.confirmar_pago', boton: 'Confirmar pago', texto: 'Registra el monto final cobrado. Si no alcanza el nivel de lista, supervisión recibirá una alerta.' },
    solicitar: { titulo: 'Solicitar cancelación', ruta: 'solicitudes.solicitar_cancelacion', boton: 'Enviar solicitud de cancelación', texto: 'Supervisión deberá confirmar la cancelación. Explica el motivo para que pueda revisarlo.', peligro: true },
    cancelar: { titulo: 'Confirmar cancelación', ruta: 'solicitudes.cancelar', boton: 'Confirmar cancelación', texto: 'Se revertirán los cambios al cliente si la solicitud ya fue aprobada.', peligro: true },
    lista: { titulo: 'Confirmar ajuste de lista', ruta: 'solicitudes.confirmar_lista', boton: 'Confirmar ajuste', texto: 'La lista del cliente se ajustará según el pago registrado.' },
    rollback: { titulo: 'Confirmar reversión', ruta: 'solicitudes.confirmar_rollback', boton: 'Confirmar reversión', texto: 'Se revertirán los cambios por vencimiento de pago. Ventas deberá iniciar una nueva solicitud.', peligro: true },
    eliminar: { titulo: 'Eliminar solicitud', ruta: 'solicitudes.destroy', boton: 'Eliminar solicitud', texto: 'El registro se retirará de la bandeja y se conservará un respaldo en la auditoría. Indica por qué lo eliminas.', peligro: true },
};

export default function ModalAccionSolicitud({ accion, solicitud, listas = [], onClose, onProcesando }) {
    const config = ACCIONES[accion];
    const cambioLista = (solicitud.proceso?.nombre || '').toUpperCase().includes('LISTA');
    const referencia = Math.max(Number((solicitud.cliente?.lista_descuento || solicitud.cliente?.listaDescuento)?.monto_requerido || 0), Number((solicitud.lista_descuento || solicitud.listaDescuento)?.monto_requerido || 0));
    const inferiores = listas.filter(l => l.activo !== false && !/COLABORADOR|PLATAFORMAS/i.test(l.nombre) && Number(l.monto_requerido) < referencia).sort((a, b) => Number(b.monto_requerido) - Number(a.monto_requerido));
    const requiereLista = accion === 'solicitar' && cambioLista;
    const requiereMotivo = accion === 'solicitar' || accion === 'eliminar';
    const campoMotivo = accion === 'eliminar' ? 'motivo' : 'motivo_cancelacion';
    const inicial = accion === 'pago' ? { modo: 'pago', monto_final_pagado: solicitud.monto_cotizado || '' }
        : requiereMotivo ? { [campoMotivo]: '', ...(requiereLista ? { catalogo_lista_rebaja_id: '' } : {}) } : {};
    const { data, setData, post, put, delete: destroy, processing, errors, isDirty } = useForm(inicial);
    useSolicitudErrores(errors);
    const enviar = (event) => {
        event.preventDefault();
        if (processing) return;
        onProcesando?.(true);
        const guardar = accion === 'eliminar' ? destroy : accion === 'solicitar' ? post : put;
        guardar(route(config.ruta, solicitud.id), { preserveScroll: true, onSuccess: onClose, onFinish: () => onProcesando?.(false) });
    };
    const listaRebaja = (solicitud.lista_rebaja || solicitud.listaRebaja)?.nombre;
    return (
        <SolicitudDialog title={config.titulo} onClose={onClose} busy={processing} dirty={isDirty} className="gelia-tag-lista-overlay">
            <form onSubmit={enviar} className="gelia-modal-shell gelia-tag-accion w-full max-w-lg">
                <header className="gelia-workflow-header">
                    <div><h2 className="m-0 theme-text-main">{config.titulo}</h2><p className="m-0 mt-1 text-sm theme-text-muted">FOL-{solicitud.id} · {solicitud.cliente?.nombre || 'Cliente'}</p></div>
                    <button type="button" data-dialog-close disabled={processing} className="gelia-workflow-close" aria-label="Cerrar acción"><X className="w-5 h-5" aria-hidden="true" /></button>
                </header>
                <div className="gelia-modal-body p-5 sm:p-6 space-y-5">
                    <p className="m-0 text-sm theme-text-muted leading-relaxed">{config.texto}</p>
                    {accion === 'cancelar' && <section className="gelia-tag-message" data-tone="peligro"><h3>Motivo de cancelación</h3><p>{solicitud.motivo_cancelacion || 'Sin motivo registrado.'}</p>{listaRebaja && <p className="text-sm theme-text-muted">Lista de rebaja: {listaRebaja}</p>}</section>}
                    {accion === 'pago' && <div className="space-y-2"><label htmlFor="accion-monto" className="theme-label">Monto final cobrado (MXN)</label><input id="accion-monto" name="monto_final_pagado" type="number" inputMode="decimal" min="0" step="0.01" required autoComplete="off" value={data.monto_final_pagado} onChange={e => setData('monto_final_pagado', e.target.value)} disabled={processing} aria-invalid={!!errors.monto_final_pagado} className="theme-input w-full" /></div>}
                    {requiereLista && <div className="space-y-2"><label htmlFor="accion-lista" className="theme-label">Lista a la que debe rebajarse el cliente</label><select id="accion-lista" name="catalogo_lista_rebaja_id" required value={data.catalogo_lista_rebaja_id} onChange={e => setData('catalogo_lista_rebaja_id', e.target.value)} disabled={processing} className="theme-select w-full"><option value="">Selecciona una lista inferior…</option>{inferiores.map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}</select>{!inferiores.length && <p role="status" className="text-sm theme-text-peligro">No hay listas inferiores disponibles para este folio.</p>}</div>}
                    {requiereMotivo && <div className="space-y-2"><label htmlFor="accion-motivo" className="theme-label">Motivo (obligatorio)</label><textarea id="accion-motivo" name={campoMotivo} autoComplete="off" required minLength={10} rows={4} value={data[campoMotivo]} onChange={e => setData(campoMotivo, e.target.value)} disabled={processing} aria-invalid={!!errors[campoMotivo]} className="theme-textarea w-full" placeholder="Describe el motivo con al menos 10 caracteres…" /></div>}
                    {Object.entries(errors).map(([key, error]) => <p key={key} role="alert" className="text-sm theme-text-peligro">{error}</p>)}
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions"><button type="button" data-dialog-close disabled={processing} className="theme-btn-secondary">Volver</button><button type="submit" disabled={processing || (requiereLista && !inferiores.length)} className={config.peligro ? 'theme-btn-danger' : 'theme-btn-primary'}>{processing && <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" />}{processing ? 'Guardando…' : config.boton}</button></footer>
            </form>
        </SolicitudDialog>
    );
}
