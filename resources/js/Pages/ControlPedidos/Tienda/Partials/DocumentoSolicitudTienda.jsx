import React, { useState } from 'react';
import VisorPdfPaginas from '../../Partials/VisorPdfPaginas';
import { THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';

export default function DocumentoSolicitudTienda({ tareaId, documentos = [] }) {
    const [documentoId, setDocumentoId] = useState(documentos[0]?.id);
    const documento = documentos.find((d) => d.id === Number(documentoId)) || documentos[0];
    if (!documento) return <p className="text-sm theme-text-muted">El pedido no tiene documento adjunto.</p>;
    const url = route('control_pedidos.tienda.solicitud.documento', [tareaId, documento.id]);
    const imagen = documento.mime_type?.startsWith('image/') || /\.(png|jpe?g|webp)$/i.test(documento.nombre_original || '');

    return <section className="theme-element border theme-border rounded-xl p-3 space-y-3 xl:sticky xl:top-0">
        <h3 className="text-sm font-bold theme-text-main m-0">Pedido de referencia</h3>
        {documentos.length > 1 ? <label className={`block ${THEME_LABEL}`}>Documento
            <select className={`${THEME_SELECT} w-full`} value={documento.id} onChange={(e) => setDocumentoId(Number(e.target.value))}>
                {documentos.map((d) => <option key={d.id} value={d.id}>{d.nombre_original || 'Documento del pedido'}</option>)}
            </select>
        </label> : <p className="text-xs theme-text-muted m-0">{documento.nombre_original}</p>}
        {imagen ? <img src={url} alt={documento.nombre_original || 'Pedido de referencia'} className="w-full max-h-[65dvh] object-contain" />
            : <VisorPdfPaginas key={documento.id} url={url} titulo={documento.nombre_original || 'Pedido'} maxHeight="65dvh" />}
    </section>;
}
