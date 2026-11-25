import React from 'react';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import { revisionesVisiblesDeConsulta } from './cambiosDisponibilidad';

export default function AlternativasDisponibilidadCedis({ pedido, onVer, puedeVerDocumentos = true }) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    return <ResumenCambiosDisponibilidad solicitud={solicitud} documentos={pedido.documentos || []} revisionesVisibles={revisionesVisiblesDeConsulta(pedido, solicitud)} vista="revision" onVer={onVer} puedeVerDocumentos={puedeVerDocumentos} />;
}
