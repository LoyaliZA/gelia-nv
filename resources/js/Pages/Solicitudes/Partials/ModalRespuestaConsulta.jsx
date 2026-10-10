import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import React from 'react';
import { useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, Send, X, XCircle } from 'lucide-react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import AdjuntoRespuesta, { useAdjuntoRespuesta } from '@/Components/Solicitudes/AdjuntoRespuesta';

export default function ModalRespuestaConsulta({ onClose, solicitud, consulta }) {
    const { data, setData, post, processing, errors, isDirty } = useForm({
        respuesta_positiva: true, comentario_encargada: '', evidencia_respuesta: null, _method: 'put',
    });
    const attachment = useAdjuntoRespuesta(data.evidencia_respuesta, (file) => setData('evidencia_respuesta', file));
    const busy = processing || attachment.busy;
    const temas = [consulta?.consulta_tag && 'TAG', consulta?.consulta_lista && 'lista'].filter(Boolean).join(' y ');
    useSolicitudErrores(errors);
    const enviar = (event) => {
        event.preventDefault();
        if (busy) return;
        post(route('solicitudes.consultas.responder', [solicitud.id, consulta.id]), { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };
    return (
        <SolicitudDialog className="gelia-tag-lista-overlay" title="Responder consulta de TAG y lista" onClose={onClose} busy={busy} dirty={isDirty}>
            <form onSubmit={enviar} onPaste={attachment.onPaste} className="gelia-modal-shell gelia-respuesta-comercial w-full max-w-2xl">
                <header className="gelia-workflow-header">
                    <div><h2 className="theme-text-main m-0">Responder consulta</h2><p className="text-sm theme-text-muted mt-1 mb-0">FOL-{solicitud.id} · {temas}</p></div>
                    <button type="button" data-dialog-close disabled={busy} className="gelia-workflow-close" aria-label="Cerrar consulta"><X className="w-5 h-5" aria-hidden="true" /></button>
                </header>
                <div className="gelia-modal-body p-5 space-y-5">
                    <div className="gelia-respuesta-nota">
                        <p className="text-sm font-medium theme-text-main m-0">{solicitud.cliente?.nombre || 'Cliente'} · {consulta.vendedor?.name || solicitud.vendedor?.name || 'Ventas'}</p>
                        <p className="text-sm theme-text-main mt-2 mb-0 whitespace-pre-wrap break-words">{consulta.comentario_vendedor || `Revisión de ${temas}.`}</p>
                    </div>
                    <fieldset disabled={processing} className="border-0 p-0 m-0 space-y-2">
                        <legend className="theme-label mb-2">Resultado de la revisión</legend>
                        <div className="grid grid-cols-2 gap-3">
                            {[true, false].map((valor) => (
                                <label key={String(valor)} className={`gelia-respuesta-opcion ${data.respuesta_positiva === valor ? 'gelia-respuesta-opcion-activa' : ''}`}>
                                    <input type="radio" name="respuesta_positiva" checked={data.respuesta_positiva === valor} onChange={() => setData('respuesta_positiva', valor)} />
                                    {valor ? <CheckCircle2 className="w-4 h-4" aria-hidden="true" /> : <XCircle className="w-4 h-4" aria-hidden="true" />}{valor ? 'Confirmar' : 'Rechazar'}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                    <div className="space-y-2">
                        <label htmlFor="consulta-respuesta-mensaje" className="theme-label">Comentario para ventas (opcional)</label>
                        <textarea id="consulta-respuesta-mensaje" name="comentario_encargada" autoComplete="off" rows={3} maxLength={1000} value={data.comentario_encargada} disabled={processing} onChange={(event) => setData('comentario_encargada', event.target.value)} className="theme-textarea w-full" placeholder="Explica el resultado y el siguiente paso…" />
                    </div>
                    <AdjuntoRespuesta file={data.evidencia_respuesta} attachment={attachment} error={errors.evidencia_respuesta} disabled={processing} />
                    {Object.entries(errors).filter(([key]) => key !== 'evidencia_respuesta').map(([key, error]) => <p key={key} role="alert" className="text-sm theme-text-peligro">{error}</p>)}
                    <p className="text-xs theme-text-muted m-0">Ventas podrá consultar esta respuesta y la evidencia desde la solicitud.</p>
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions">
                    <button type="button" data-dialog-close disabled={busy} className="theme-btn-secondary">Cancelar</button>
                    <button type="submit" disabled={busy} aria-busy={busy} className="theme-btn-primary">{busy ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Send className="w-4 h-4" aria-hidden="true" />}{processing ? 'Enviando…' : 'Enviar respuesta'}</button>
                </footer>
            </form>
        </SolicitudDialog>
    );
}
