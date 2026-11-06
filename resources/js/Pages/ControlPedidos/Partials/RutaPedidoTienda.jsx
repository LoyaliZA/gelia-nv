import React from 'react';
import { ArrowRight, Store, Warehouse } from 'lucide-react';

export default function RutaPedidoTienda({ origen = 'CEDIS', destino = 'Por confirmar' }) {
    return <div className="gelia-cedis-ruta" aria-label="Origen y destino del pedido">
        <span className="gelia-cedis-ruta-badge"><Warehouse size={18} aria-hidden /><span><small>Origen</small><strong>{origen}</strong></span></span>
        <ArrowRight size={20} className="shrink-0 theme-text-muted" aria-hidden />
        <span className="gelia-cedis-ruta-badge"><Store size={18} aria-hidden /><span><small>Destino</small><strong>{destino}</strong></span></span>
    </div>;
}
