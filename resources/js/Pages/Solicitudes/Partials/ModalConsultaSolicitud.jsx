import AvisoSolicitud from '@/Components/Solicitudes/AvisoSolicitud';
import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import React from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquare, Tag, TrendingUp, Send } from 'lucide-react';
import CabeceraFlujoSolicitud from '@/Components/Solicitudes/CabeceraFlujoSolicitud';

export default function ModalConsultaSolicitud({ onClose, solicitud }) {
    const { data, setData, post, processing, isDirty, errors, setError, clearErrors } = useForm({
        consulta_tag: false,
        consulta_lista: false,
        comentario_vendedor: '',
    });

    useSolicitudErrores(errors);
    const enviar = (e) => {
        e.preventDefault();
        if (!data.consulta_tag && !data.consulta_lista) {
            setError('consulta_tag', 'Selecciona TAG, Lista o ambas opciones para enviar la consulta.');
            return;
        }
        clearErrors();
        post(route('solicitudes.consultas.store', solicitud.id), {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
            },
        });
    };

    return (
        <SolicitudDialog className="gelia-tag-lista-overlay" onClose={onClose} busy={processing} title="Consultar TAG y lista" dirty={isDirty}>
            <form onSubmit={enviar} className="gelia-modal-shell w-full max-w-xl">
                <CabeceraFlujoSolicitud
                    icono={MessageSquare}
                    tono="aviso"
                    titulo="Consultar TAG y lista"
                    subtitulo={`FOL-${solicitud.id} · ${solicitud.cliente?.nombre || 'Cliente'}`}
                    closeLabel="Cerrar consulta"
                    disabled={processing}
                />
                <div className="gelia-modal-body p-5 sm:p-6 space-y-5">
                    <AvisoSolicitud tono="info" titulo="Consulta a supervisión">Selecciona qué deseas verificar antes de confirmar el pago.</AvisoSolicitud>

                    <label className={`flex items-center gap-4 p-4 rounded-2xl border cursor-pointer transition-colors ${data.consulta_tag ? 'border-amber-500 bg-amber-500/10' : 'theme-border theme-element'}`}>
                        <input type="checkbox" name="consulta_tag" disabled={processing} checked={data.consulta_tag} onChange={e => { setData('consulta_tag', e.target.checked); clearErrors('consulta_tag'); }} className="w-4 h-4 accent-[var(--color-primario)]" />
                        <Tag className="w-5 h-5 theme-text-aviso" aria-hidden="true" />
                        <div>
                            <p className="text-sm font-medium  theme-text-main">TAG del cliente</p>
                            <p className="text-xs font-medium theme-text-muted">Verificar responsable del TAG</p>
                        </div>
                    </label>

                    <label className={`flex items-center gap-4 p-4 rounded-2xl border cursor-pointer transition-colors ${data.consulta_lista ? 'border-blue-500 bg-blue-500/10' : 'theme-border theme-element'}`}>
                        <input type="checkbox" name="consulta_lista" disabled={processing} checked={data.consulta_lista} onChange={e => { setData('consulta_lista', e.target.checked); clearErrors('consulta_tag'); }} className="w-4 h-4 accent-[var(--color-primario)]" />
                        <TrendingUp className="w-5 h-5 theme-text-info" aria-hidden="true" />
                        <div>
                            <p className="text-sm font-medium  theme-text-main">Lista del cliente</p>
                            <p className="text-xs font-medium theme-text-muted">Verificar lista de descuento actual</p>
                        </div>
                    </label>

                    {Object.values(errors).map((error, index) => <AvisoSolicitud key={index} tono="peligro" compacto anunciar>{error}</AvisoSolicitud>)}
                    <div>
                        <label htmlFor="consulta-comentario" className="text-[10px] font-medium   theme-text-muted mb-2 block">Comentario (opcional)</label>
                        <textarea id="consulta-comentario" name="comentario_vendedor" autoComplete="off" maxLength={1000}
                            value={data.comentario_vendedor}
                            onChange={e => setData('comentario_vendedor', e.target.value)}
                            rows={3}
                            className="w-full theme-element border theme-border rounded-2xl p-4 text-sm font-bold theme-text-main outline-none focus:border-[var(--color-primario)] resize-none"
                            placeholder="Describe tu duda…"
                        />
                    </div>

                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions">
                    <button type="button" data-dialog-close disabled={processing} className="theme-btn-secondary">Cancelar</button>
                    <button type="submit" disabled={processing} className="theme-btn-primary"><Send className="w-4 h-4" aria-hidden="true" />{processing ? 'Enviando…' : 'Enviar consulta'}</button>
                </footer>
            </form>
        </SolicitudDialog>
    );
}
