import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import React from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2, Send, X } from 'lucide-react';
import SolicitudDialog from './SolicitudDialog';
import AdjuntoRespuesta, { useAdjuntoRespuesta } from './AdjuntoRespuesta';

export default function ModalRespuestaComercial({ onClose, onExito, solicitud, estadoId, esReporteError = false, esVerificacion = false, operativa = false }) {
    const esError = esReporteError || (operativa && Number(estadoId) === 4);
    const verificar = esVerificacion || (operativa && Number(estadoId) === 3);
    const accion = esError ? 'Reportar error' : verificar ? 'Verificar solicitud' : 'Aprobar solicitud';
    const { data, setData, post, processing, errors, isDirty } = useForm({
        ...(operativa ? {} : { solicitud_id: solicitud.id }),
        catalogo_estado_solicitud_id: estadoId || '', motivo: '', evidencia_respuesta: null, _method: 'put',
    });
    const attachment = useAdjuntoRespuesta(data.evidencia_respuesta, (file) => setData('evidencia_respuesta', file));
    const busy = processing || attachment.busy;
    useSolicitudErrores(errors);
    const enviar = (event) => {
        event.preventDefault();
        if (busy) return;
        post(route(operativa ? 'cancelaciones_cotizaciones.actualizar_estado' : 'solicitudes.actualizar_estado', solicitud.id), {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { onExito?.(); onClose(); },
        });
    };
    const tienda = solicitud.compra_en_tienda || solicitud.compra_en_tienda_solo_tag;
    return (
        <SolicitudDialog className={operativa ? '' : 'gelia-tag-lista-overlay'} onClose={onClose} busy={busy} dirty={isDirty} title={accion}>
            <form onSubmit={enviar} onPaste={attachment.onPaste} className="gelia-modal-shell gelia-respuesta-comercial w-full max-w-4xl">
                <header className="gelia-workflow-header">
                    <div className="min-w-0">
                        <h2 className="theme-text-main m-0">{accion}</h2>
                        <p className="text-sm theme-text-muted mt-1 mb-0">FOL-{solicitud.id} · {solicitud.proceso?.nombre}</p>
                    </div>
                    <button type="button" data-dialog-close disabled={busy} className="gelia-workflow-close" aria-label="Cerrar respuesta"><X className="w-5 h-5" aria-hidden="true" /></button>
                </header>
                <div className="gelia-modal-body p-5 sm:p-6 space-y-5">
                    <dl className="gelia-respuesta-contexto">
                        <div><dt>Cliente</dt><dd>{solicitud.cliente?.nombre || 'Sin cliente'}{solicitud.cliente?.numero_cliente ? ` · ${solicitud.cliente.numero_cliente}` : ''}</dd></div>
                        <div><dt>Solicitada por</dt><dd>{solicitud.vendedor?.name || 'Sin asignar'}</dd></div>
                    </dl>
                    {solicitud.observaciones_vendedor && <div className="gelia-respuesta-nota"><h3 className="text-sm font-medium m-0 theme-text-main">Nota de la solicitud</h3><p className="text-sm whitespace-pre-wrap break-words theme-text-main mt-2 mb-0">{solicitud.observaciones_vendedor}</p></div>}
                    <div className={`gelia-respuesta-aviso ${esError ? 'gelia-respuesta-aviso-error' : ''}`}>
                        {esError && <AlertTriangle className="w-5 h-5 shrink-0" aria-hidden="true" />}
                        <p className="m-0 text-sm">{esError ? 'La solicitud quedará marcada como incorrecta. Explica qué debe corregirse para que pueda reenviarse.' : verificar ? 'La solicitud quedará verificada. Puedes agregar una nota o evidencia de tu revisión.' : tienda && !operativa ? 'Al aprobar, la solicitud concluye para ventas y queda pendiente de verificación, sin confirmar pago.' : 'La respuesta y la evidencia estarán disponibles para quien creó la solicitud.'}</p>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div className="space-y-2">
                            <label htmlFor="respuesta-motivo" className="theme-label">{esError ? 'Qué debe corregirse (obligatorio)' : 'Mensaje para quien solicitó (opcional)'}</label>
                            <textarea id="respuesta-motivo" name="motivo" autoComplete="off" required={esError} disabled={processing} rows={6} value={data.motivo} onChange={(event) => setData('motivo', event.target.value)} aria-invalid={!!errors.motivo} aria-describedby={errors.motivo ? 'respuesta-motivo-error' : undefined} className="theme-textarea w-full" placeholder={esError ? 'Indica el dato incorrecto y cómo corregirlo…' : 'Agrega detalles útiles de la resolución…'} />
                            {errors.motivo && <p id="respuesta-motivo-error" role="alert" className="text-sm theme-text-peligro">{errors.motivo}</p>}
                        </div>
                        <AdjuntoRespuesta file={data.evidencia_respuesta} attachment={attachment} error={errors.evidencia_respuesta} disabled={processing} />
                    </div>
                    {Object.entries(errors).filter(([key]) => !['motivo', 'evidencia_respuesta'].includes(key)).map(([key, mensaje]) => <p key={key} role="alert" className="text-sm theme-text-peligro">{mensaje}</p>)}
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions">
                    <button type="button" data-dialog-close disabled={busy} className="theme-btn-secondary">Cancelar</button>
                    <button type="submit" disabled={busy} aria-busy={busy} className={esError ? 'theme-btn-danger' : 'theme-btn-primary'}>
                        {busy ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : <Send className="w-4 h-4" aria-hidden="true" />}{processing ? 'Enviando…' : attachment.busy ? 'Preparando evidencia…' : accion}
                    </button>
                </footer>
            </form>
        </SolicitudDialog>
    );
}
