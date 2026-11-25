import React from 'react';
import { FileText } from 'lucide-react';
import { MiniaturaDocumento } from '../ModalVistaPreviaDocumento';

export default function DetallePedidoSeccion({
    id,
    icon: Icono,
    titulo,
    children,
    className = '',
    extra = null,
}) {
    return (
        <section id={id} tabIndex={id ? -1 : undefined} className={`gelia-pedidos-seccion space-y-4 ${className}`.trim()}>
            {(titulo || extra) && (
                <div className="flex items-center justify-between gap-2">
                    {titulo ? (
                        <h3 className="gelia-pedidos-seccion-titulo">
                            {Icono ? (
                                <span className="gelia-pedidos-seccion-icono">
                                    <Icono aria-hidden="true" />
                                </span>
                            ) : null}
                            {titulo}
                        </h3>
                    ) : <span />}
                    {extra}
                </div>
            )}
            {children}
        </section>
    );
}

export function DetallePedidoDocumentos({
    pedido,
    onVerDoc,
    ocultarApartadoCedis = false,
}) {
    const docsSinGuia = (pedido.documentos || []).filter((d) => !['guia', 'pdf_pedido', 'cotizacion', 'anexo_piezas'].includes(d.tipo));
    const evidenciasApartado = ocultarApartadoCedis
        ? []
        : docsSinGuia.filter((d) => d.tipo === 'evidencia_apartado');
    const otros = docsSinGuia.filter((d) => d.tipo !== 'evidencia_apartado' && d.tipo !== 'evidencia_condicion');
    const notaApartado = !ocultarApartadoCedis && pedido.detalle_resguardo_apartado;
    const vacio = !otros.length && !evidenciasApartado.length && !notaApartado;

    return (
        <DetallePedidoSeccion id="detalle-documentos" icon={FileText} titulo="Documentos">
            {vacio && <p className="text-sm theme-text-muted m-0">Sin documentos adicionales.</p>}
            {notaApartado && (
                <div className="p-3 rounded-xl gelia-estado-vivo gelia-estado-vivo--info">
                    <p className="text-xs font-medium theme-text-muted m-0">Nota de apartado CEDIS</p>
                    <p className="text-sm font-semibold theme-text-main m-0 mt-1">{pedido.detalle_resguardo_apartado}</p>
                </div>
            )}
            {evidenciasApartado.length > 0 && (
                <div>
                    <p className="text-xs font-medium theme-text-muted m-0 mb-2">Evidencia de apartado</p>
                    <div className="flex flex-wrap gap-2">
                        {evidenciasApartado.map((doc) => (
                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={onVerDoc} />
                        ))}
                    </div>
                </div>
            )}
            {otros.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {otros.map((doc) => (
                        <MiniaturaDocumento key={doc.id} documento={doc} onVer={onVerDoc} />
                    ))}
                </div>
            )}
        </DetallePedidoSeccion>
    );
}
