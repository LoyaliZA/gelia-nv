import React from 'react';
import { FileText } from 'lucide-react';
import { BTN_SECONDARY } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import VisorPdfPaginas from './VisorPdfPaginas';
import useDispositivoCampo from '../../Activos/Partials/useDispositivoCampo';

/** Referencia visible mientras vendedores revisan la respuesta de CEDIS. */
export default function ReferenciaPedidoCompartido({ pedido, onVerGaleria, id, descripcion = 'Compara las piezas solicitadas con la revisión y las evidencias de CEDIS.' }) {
    const { esCampo, esMovil } = useDispositivoCampo();
    const documentos = (pedido?.documentos || []).filter((d) => d.tipo === 'pdf_pedido' || d.tipo === 'anexo_piezas');
    const principal = documentos.find((d) => d.tipo === 'pdf_pedido');
    const esImagen = principal?.mime_type?.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(principal?.nombre_original || '');
    const ver = (doc) => onVerGaleria?.(documentos, Math.max(0, documentos.indexOf(doc)));
    return (
        <section id={id} tabIndex={-1} className="gelia-pedidos-seccion space-y-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="gelia-pedidos-seccion-titulo">Pedido compartido</h3>
                {principal?.url && <button type="button" onClick={() => ver(principal)} className={`${BTN_SECONDARY} inline-flex items-center gap-1.5 text-xs`}><FileText className="w-4 h-4" aria-hidden="true" /> Ampliar</button>}
            </div>
            <p className="text-xs theme-text-muted m-0">{descripcion}</p>
            {principal?.url ? (
                esImagen ? <button type="button" onClick={() => ver(principal)} aria-label="Ampliar foto del pedido" className="w-full rounded-xl overflow-hidden border theme-border theme-element"><img src={principal.url} alt={principal.nombre_original || 'Pedido compartido'} width="480" height="640" className="w-full max-h-[55dvh] object-contain" /></button>
                    : (esCampo || esMovil) ? <VisorPdfPaginas url={principal.url} titulo={principal.nombre_original || 'Pedido compartido'} maxHeight="45dvh" />
                        : <iframe src={principal.url} title={principal.nombre_original || 'Pedido compartido'} className="w-full rounded-xl border theme-border bg-white" style={{ height: 'min(55dvh, 520px)' }} />
            ) : <p className="text-sm theme-text-muted m-0 p-4 rounded-xl border theme-border theme-element">El pedido no tiene PDF ni foto adjuntos.</p>}
            {documentos.filter((doc) => doc !== principal).length > 0 && <div className="space-y-2"><p className="text-xs theme-text-muted m-0">Piezas adicionales</p><div className="flex flex-wrap gap-2">{documentos.filter((doc) => doc !== principal).map((doc, index) => <MiniaturaDocumento key={doc.id || index} documento={doc} onVer={ver} />)}</div></div>}
        </section>
    );
}
