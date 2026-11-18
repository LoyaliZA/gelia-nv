import React from 'react';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';

export default function AlternativasDisponibilidadCedis({ pedido, onVer, puedeVerDocumentos = true }) {
    return <ResumenCambiosDisponibilidad solicitud={pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {}} documentos={pedido.documentos || []} onVer={onVer} puedeVerDocumentos={puedeVerDocumentos} />;
}
