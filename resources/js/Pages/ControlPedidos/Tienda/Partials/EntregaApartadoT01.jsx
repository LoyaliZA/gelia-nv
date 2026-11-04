import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import VigenciaApartadoTienda from '../../Partials/VigenciaApartadoTienda';
import GaleriaEvidenciasPedido from '../../Partials/GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';

export default function EntregaApartadoT01({ tarea, auth, onEnviar, onDirtyChange }) {
    const [receptor, setReceptor] = useState('');
    const [remision, setRemision] = useState('');
    const [foto, setFoto] = useState(null);
    const [ticket, setTicket] = useState(null);
    const [fotoPreviews, setFotoPreviews] = useState([]);
    const [ticketPreviews, setTicketPreviews] = useState([]);
    const [vista, setVista] = useState(null);
    const urls = useRef([]);
    urls.current = [...fotoPreviews, ...ticketPreviews];
    useEffect(() => () => urls.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const [pagado, setPagado] = useState(false);
    const [motivo, setMotivo] = useState('');
    const [liberar, setLiberar] = useState(false);
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => { onDirtyChange?.(Boolean(receptor || remision || foto || ticket || pagado || motivo)); }, [receptor, remision, foto, ticket, pagado, motivo, onDirtyChange]);
    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);
    const apartado = tarea.apartado;
    const modalidadConfirmada = tarea.pedido?.cobertura_tienda?.tipo === 'apartado';
    const finalizado = ['ENTREGADA', 'DEVUELTA_ANAQUEL'].includes(apartado?.estado);
    const autorizado = Number(tarea.responsable?.id) === Number(auth?.user?.id)
        && (auth?.user?.roles?.includes('Super Admin') || (auth?.user?.permissions || []).some(p => ['control_pedidos.tienda.responder', 'control_pedidos.tienda.apartado.separar'].includes(p)));
    const enviar = (entregar) => {
        if (ocupado || !autorizado || finalizado) return;
        setError('');
        if (entregar && (!pagado || !remision.trim() || !receptor.trim() || !foto)) {
            setError('Confirme el pago recibido en PDV, indique receptor y número de remisión, y adjunte la fotografía de entrega.'); return;
        }
        if (!entregar && !motivo.trim()) { setError('Indique el motivo para liberar el apartado.'); return; }
        const datos = new FormData();
        datos.append('version', apartado.version);
        if (entregar) {
            datos.append('pago_confirmado', '1'); datos.append('receptor', receptor.trim()); datos.append('numero_remision', remision.trim());
            datos.append('foto', foto); if (ticket) datos.append('ticket', ticket);
        } else datos.append('motivo', motivo.trim());
        setOcupado(true);
        const opciones = { forceFormData: true, preserveScroll: true, onFinish: () => setOcupado(false), onError: errores => setError(Object.values(errores).flat().join(' ')) };
        const url = route(entregar ? 'control_pedidos.tienda.apartado.entregar_pdv' : 'control_pedidos.tienda.apartado.liberar_pdv', tarea.id);
        if (onEnviar) onEnviar(url, datos, opciones);
        else router.post(url, datos, opciones);
    };
    return <section className="registrar-tienda-panel space-y-4" aria-label="Pago y entrega en tienda">
        <div className="registrar-tienda-seccion-titulo"><Banknote size={18} aria-hidden="true" /><h3>Pago y entrega en tienda</h3></div>
        <p className="text-sm m-0 theme-text-main">{apartado?.estado === 'ENTREGADA' ? `Pedido entregado · Remisión ${apartado.folio_operacion || 'registrada'} · ${apartado.receptor_nombre || ''}` : apartado?.estado === 'DEVUELTA_ANAQUEL' ? 'Apartado liberado. La vendedora recibió el aviso.' : 'Se paga en tienda. El cobro y la emisión de remisión se realizan en PDV.'}</p>
        {modalidadConfirmada && <VigenciaApartadoTienda venceAt={apartado?.vence_at} finalizado={finalizado || Boolean(apartado?.pago_confirmado_at)} />}
        {error && <p className="text-sm theme-text-peligro m-0" role="alert">{error}</p>}
        {!finalizado && !modalidadConfirmada && <p className="text-sm theme-text-muted m-0">Espere a que la vendedora confirme Pago al recoger.</p>}
        {!finalizado && autorizado && modalidadConfirmada && <fieldset disabled={ocupado} className="min-w-0 grid gap-4">
            <label className="flex gap-2 items-start text-sm"><input type="checkbox" className="gelia-checkbox mt-0.5" checked={pagado} onChange={e => setPagado(e.target.checked)} />Confirmo que PDV recibió el pago y se puede entregar el pedido.</label>
            <div className="grid sm:grid-cols-2 gap-3">
                <label className={THEME_LABEL}>Quién recibe<input className={`${THEME_INPUT} w-full mt-1`} maxLength={160} value={receptor} onChange={e => setReceptor(e.target.value)} /></label>
                <label className={THEME_LABEL}>Número de remisión PDV<input className={`${THEME_INPUT} w-full mt-1`} maxLength={100} value={remision} onChange={e => setRemision(e.target.value)} /></label>
            </div>
            <GaleriaEvidenciasPedido id="t01-foto-entrega" label="Fotografía de entrega" obligatorio soloImagenes maxArchivos={1} disabled={ocupado}
                archivos={foto ? [foto] : []} previews={fotoPreviews} onChange={(archivos, previews) => { setFoto(archivos[0] || null); setFotoPreviews(previews); }} onVer={(documentos, indice) => setVista({ documentos, indice })} />
            <GaleriaEvidenciasPedido id="t01-ticket-remision" label="Ticket de remisión (opcional)" maxArchivos={1} disabled={ocupado}
                archivos={ticket ? [ticket] : []} previews={ticketPreviews} onChange={(archivos, previews) => { setTicket(archivos[0] || null); setTicketPreviews(previews); }} onVer={(documentos, indice) => setVista({ documentos, indice })} />
            {tarea.pedido?.motivo_bloqueo_entrega && <p className="text-sm theme-text-aviso m-0" role="status">{tarea.pedido.motivo_bloqueo_entrega}</p>}
            <button type="button" className={`${BTN_PRIMARY} min-h-[44px]`} disabled={Boolean(tarea.pedido?.motivo_bloqueo_entrega)} onClick={() => enviar(true)}>Confirmar entrega</button>
            {!apartado?.pago_confirmado_at && !pagado && <div className="border-t theme-border pt-4 grid gap-3">
                <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} aria-expanded={liberar} onClick={() => setLiberar(!liberar)}>El cliente no recogió · Liberar apartado</button>
                {liberar && <><label className={THEME_LABEL}>Motivo de liberación<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} rows={2} value={motivo} onChange={e => setMotivo(e.target.value)} /></label>
                    <p className="text-sm theme-text-muted m-0">Confirme después de devolver las piezas a su ubicación. Esta acción libera el apartado y avisa a la vendedora.</p>
                    <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} onClick={() => enviar(false)}>Confirmar liberación y avisar a Ventas</button></>}
            </div>}
        </fieldset>}
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} documentos={vista?.documentos || []} indice={vista?.indice || 0} onClose={() => setVista(null)} />
    </section>;
}
