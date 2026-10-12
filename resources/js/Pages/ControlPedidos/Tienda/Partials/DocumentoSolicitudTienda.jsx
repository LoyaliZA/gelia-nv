import React, { useState } from 'react';
import VisorPdfPaginas from '../../Partials/VisorPdfPaginas';
import { THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import { Expand, FileText } from 'lucide-react';

export default function DocumentoSolicitudTienda({ tareaId, documentos = [] }) {
    const [documentoId, setDocumentoId] = useState(documentos[0]?.id);
    const [ampliado, setAmpliado] = useState(false);
    const documento = documentos.find((d) => d.id === Number(documentoId)) || documentos[0];
    if (!documento) return <div className="gelia-preparacion-vacio"><FileText className="w-6 h-6" aria-hidden="true" /><strong>Sin pedido adjunto</strong><p>El pedido no tiene documento adjunto. Consulte las indicaciones de Ventas; si necesita el desglose, solicítelo antes de confirmar.</p></div>;
    const url = route('control_pedidos.tienda.solicitud.documento', [tareaId, documento.id]);
    const imagen = documento.mime_type?.startsWith('image/') || /\.(png|jpe?g|webp)$/i.test(documento.nombre_original || '');

    return <section className="gelia-preparacion-documento-panel">
        <h4 className="font-semibold theme-text-main m-0">Pedido de referencia</h4>
        <p className="text-xs theme-text-muted">Compare los productos y cantidades con el documento de Ventas.</p>
        {documentos.length > 1 ? <label className={`block ${THEME_LABEL}`}>Documento
            <select className={`${THEME_SELECT} w-full`} value={documento.id} onChange={(e) => setDocumentoId(Number(e.target.value))}>
                {documentos.map((d) => <option key={d.id} value={d.id}>{d.nombre_original || 'Documento del pedido'}</option>)}
            </select>
        </label> : <p className="text-xs theme-text-muted m-0">{documento.nombre_original}</p>}
        <div className="gelia-preparacion-documento-acciones"><button type="button" className={BTN_SECONDARY} onClick={() => setAmpliado(true)}><Expand className="w-4 h-4" aria-hidden="true" />Ampliar pedido</button></div>
        {imagen ? <img src={url} alt={documento.nombre_original || 'Pedido de referencia'} width="800" height="1100" className="w-full h-auto object-contain" />
            : <VisorPdfPaginas key={documento.id} url={url} titulo={documento.nombre_original || 'Pedido'} maxHeight="none" />}
        <ModalVistaPreviaDocumento abierto={ampliado} documento={{ ...documento, url }} onClose={() => setAmpliado(false)} />
    </section>;
}
