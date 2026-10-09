import usePedidoDialog from './usePedidoDialog';
import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import { X, User, MapPin, ClipboardCheck, Package, FileText } from 'lucide-react';
import {
    badgeEstatusPedido,
    badgeResguardoApartado,
    etiquetaEstatusPedido,
    pedidoRequiereLogistica,
    formatearMoneda,
    etiquetaAlmacen,
    etiquetaSucursal,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    tieneGuiaLista,
    badgeGuiaLista,
    badgeEstadoFisico,
    badgesRetrasoSla,
    etiquetaOrigenGuia,
    etiquetaEnvio,
    LABEL_NOTA_COMPRA_CAMPO,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import SeccionGuiaRastreo from './SeccionGuiaRastreo';
import SeccionRevisionFisicaPedido from './SeccionRevisionFisicaPedido';
import DireccionPedidoResumen from './DireccionPedidoResumen';
import { codigoDireccionCliente } from './codigoDireccionCliente';
import ModalCambiarDireccion from './ModalCambiarDireccion';
import NavegacionPedido from './NavegacionPedido';
import ReferenciaPedidoCompartido from './ReferenciaPedidoCompartido';

const Campo = ({ label, value }) => (
    <div className="gelia-pedidos-campo">
        <dt>{label}</dt>
        <dd>{value ?? '—'}</dd>
    </div>
);

export default function ModalDetallePedido({ abierto, onClose, pedido }) {
    const [docPreview, setDocPreview] = useState(null);
    const [cambiarDir, setCambiarDir] = useState(false);
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const puedeAtender = Boolean(pedido?.puede_mutar)
        || Number(pedido?.vendedor_id) === Number(auth?.user?.id);

    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose });

    if (!abierto || !pedido) return null;

    const opcionesEstatus = {
        esResguardo: pedido.es_resguardo,
        requiereLogistica: pedidoRequiereLogistica(pedido),
    };
    const badge = badgeEstatusPedido(pedido.estatus, opcionesEstatus);
    const guiaLista = tieneGuiaLista(pedido);
    const badgeGuia = badgeGuiaLista();
    const badgeApartado = pedido.resguardo_apartado_at ? badgeResguardoApartado() : null;
    const docsSinGuia = (pedido.documentos || []).filter((d) => !['guia', 'pdf_pedido', 'anexo_piezas'].includes(d.tipo));
    const evidenciasApartado = (pedido.documentos || []).filter((d) => d.tipo === 'evidencia_apartado');
    const badgeFisico = pedido.estado_fisico_general ? badgeEstadoFisico(pedido.estado_fisico_general) : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const snap = pedido.direccion_vigente || pedido.direccionVigente;

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    {...dialog}
                    aria-labelledby="detalle-pedido-titulo"
                    className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace gelia-pedidos-detalle max-w-6xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <header className="gelia-pedidos-detalle-cabecera border-b theme-border">
                        <div className="gelia-pedidos-detalle-identidad min-w-0">
                            <h2 id="detalle-pedido-titulo" className="text-xs font-semibold theme-text-muted m-0 mb-1">Detalle del pedido</h2>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                        </div>
                        <div className="gelia-pedidos-detalle-cliente min-w-0">
                            <p className="text-sm font-semibold theme-text-main m-0 break-words">{pedido.cliente?.nombre || 'Sin cliente'}</p>
                            {pedido.cliente?.numero_cliente && <p className="text-xs theme-text-muted m-0 mt-1">Cliente {pedido.cliente.numero_cliente}</p>}
                            {pedido.vendedor?.name && (
                                <p className="text-xs theme-text-muted mt-1 m-0 flex items-start gap-1.5">
                                    <User className="w-3.5 h-3.5 mt-0.5 shrink-0" aria-hidden="true" /> <span>Capturado por: {pedido.vendedor.name}</span>
                                </p>
                            )}
                        </div>
                        <div className="gelia-pedidos-detalle-estados flex flex-wrap items-center gap-2 min-w-0">
                                <span className={badge.className} style={badge.style}>{badge.label}</span>
                                {guiaLista && (
                                    <span className={badgeGuia.className}>{badgeGuia.label}</span>
                                )}
                                {badgeApartado && (
                                    <span className={badgeApartado.className} style={badgeApartado.style}>{badgeApartado.label}</span>
                                )}
                                {badgeFisico && (
                                    <span className={badgeFisico.className} style={badgeFisico.style}>{badgeFisico.label}</span>
                                )}
                                {badgesSla.map((b) => (
                                    <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                                ))}
                                {pedido.tiene_observaciones_fisicas && (
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold gelia-estado-vivo gelia-estado-vivo--aviso">
                                        Observaciones CEDIS
                                    </span>
                                )}
                        </div>
                        <button type="button" onClick={onClose} className="gelia-pedidos-detalle-cerrar p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0 inline-flex items-center justify-center" aria-label="Cerrar detalle">
                            <X className="w-5 h-5" aria-hidden="true" />
                        </button>
                        {pedido.motivo_rechazo && (
                            <p className="gelia-pedidos-detalle-rechazo text-sm theme-text-peligro font-semibold m-0">Motivo rechazo: {pedido.motivo_rechazo}</p>
                        )}
                    </header>
                    <NavegacionPedido secciones={[
                        { id: 'detalle-respuesta', label: 'Respuesta CEDIS' },
                        { id: 'detalle-compartido', label: 'Pedido compartido' },
                        { id: 'detalle-datos', label: 'Datos del pedido' },
                        { id: 'detalle-envio', label: 'Entrega' },
                        { id: 'detalle-documentos', label: 'Documentos' },
                    ]} />
                    <div className="gelia-modal-body p-4 md:p-6">
                        <div className="gelia-pedidos-detalle-grid">
                        <aside className="gelia-pedidos-referencia">
                            <ReferenciaPedidoCompartido id="detalle-compartido" pedido={pedido} onVerGaleria={(documentos, indice) => setDocPreview({ documentos, indice })} />
                        </aside>
                        <div className="min-w-0 space-y-4">
                        <section id="detalle-respuesta" tabIndex={-1} className="space-y-3">
                            <dl className="gelia-pedidos-resumen">
                                <div><dt>Respuesta CEDIS</dt><dd>{pedido.pesaje_respondido_at ? 'Recibida' : (pedido.resguardo_apartado_at ? 'Apartado confirmado' : 'Pendiente')}</dd></div>
                                <div><dt>{pedidoRequiereLogistica(pedido) ? 'Peso cobrado' : 'Bultos aproximados'}</dt><dd>{pedidoRequiereLogistica(pedido) ? `${pedido.peso_cobrado_guia_kg ?? '—'} kg` : (pedido.numero_cajas ?? '—')}</dd></div>
                                <div><dt>Total a cobrar</dt><dd>{formatearMoneda(pedido.total_a_cobrar)}</dd></div>
                            </dl>
                        <SeccionRevisionFisicaPedido
                            pedido={pedido}
                            onVerDoc={setDocPreview}
                            onVerGaleria={(documentos, indice) => setDocPreview({ documentos, indice })}
                            puedeAtender={puedeAtender}
                            puedeCancelar={Boolean(pedido?.puede_cancelar)}
                        />

                            {!pedido.estado_fisico_general && !(pedido.revisiones_producto || pedido.revisionesProducto || []).length && !pedido.pesaje_respondido_at && (
                                <p className="text-sm theme-text-muted m-0 px-1">La revisión y sus evidencias aparecerán aquí cuando CEDIS registre su respuesta.</p>
                            )}
                        </section>
                        <section id="detalle-datos" tabIndex={-1} className="gelia-pedidos-seccion space-y-4">
                        <h3 className="gelia-pedidos-seccion-titulo flex items-center gap-2"><ClipboardCheck className="w-4 h-4 theme-text-muted" aria-hidden="true" /> Datos del pedido</h3>
                        <dl className="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-5">
                            <Campo label="Número de pedido" value={pedido.folio_remision} />
                            <Campo label="Número de remisión" value={pedido.numero_remision} />
                            <Campo label="Cliente" value={`${pedido.cliente?.numero_cliente || ''} — ${pedido.cliente?.nombre || ''}`} />
                            <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
                            <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                            <Campo label="Estado" value={etiquetaEstatusPedido(pedido.estatus, opcionesEstatus)} />
                            <Campo label="Tipo de pedido" value={pedido.origen?.nombre} />
                            <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                            {(pedido.sucursal_destino || pedido.sucursal_destino_id) && (
                                <Campo
                                    label="Sucursal destino"
                                    value={etiquetaSucursal(pedido.sucursal_destino || {
                                        id: pedido.sucursal_destino_id,
                                        nombre: pedido.sucursal_destino_nombre,
                                        codigo: pedido.sucursal_destino_codigo,
                                    })}
                                />
                            )}
                            <Campo
                                label="Pagos / bancos"
                                value={(pedido.fuentes_pago?.length
                                    ? pedido.fuentes_pago
                                    : (pedido.banco?.nombre ? [pedido.banco.nombre] : [])
                                ).join(', ') || '—'}
                            />
                            <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                            <Campo label="Peso vol." value={pedido.peso_volumetrico_kg != null ? `${pedido.peso_volumetrico_kg} kg` : null} />
                            <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                            <Campo label="Peso cobrado guía" value={pedido.peso_cobrado_guia_kg != null ? `${pedido.peso_cobrado_guia_kg} kg` : null} />
                            <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                            <Campo label="Origen de la guía" value={etiquetaOrigenGuia(pedido)} />
                            <Campo label="Tipo guía" value={pedido.tipo_guia?.nombre} />
                            <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                            <Campo label="Resguardo" value={pedido.es_resguardo ? 'Sí' : 'No'} />
                            <Campo
                                label="Apartado CEDIS"
                                value={pedido.resguardo_apartado_at
                                    ? formatearFechaHoraAuditoria(pedido.resguardo_apartado_at)
                                    : (pedido.es_resguardo ? 'Pendiente' : '—')}
                            />
                            <Campo label={LABEL_NOTA_COMPRA_CAMPO} value={pedido.anexar_remision ? 'Sí' : 'No'} />
                            <Campo label="C.P." value={pedido.codigo_postal} />
                            <Campo label="Total a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} />
                        </dl>
                        </section>
                        {(pedido.cajas || []).length > 0 && (
                            <section className="gelia-pedidos-seccion space-y-3">
                                <h3 className="gelia-pedidos-seccion-titulo flex items-center gap-2"><Package className="w-4 h-4 theme-text-muted" aria-hidden="true" /> Envíos y pesaje</h3>
                                {[...(pedido.cajas || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                    <div key={c.id || idx} className="space-y-1">
                                        <p className="text-xs font-semibold theme-text-main m-0">
                                            {etiquetaEnvio(idx, c)}
                                            <span className="ml-2 font-bold theme-text-muted">
                                                {(c.estatus_recoleccion || 'pendiente') === 'recolectada' ? 'Recolectada' : 'Pendiente'}
                                            </span>
                                        </p>
                                        <div className="grid grid-cols-2 gap-1 text-xs">
                                            <p className="m-0 theme-text-muted font-bold">Largo: <span className="theme-text-main">{c.largo != null ? `${c.largo} cm` : '—'}</span></p>
                                            <p className="m-0 theme-text-muted font-bold">Ancho: <span className="theme-text-main">{c.ancho != null ? `${c.ancho} cm` : '—'}</span></p>
                                            <p className="m-0 theme-text-muted font-bold">Alto: <span className="theme-text-main">{c.alto != null ? `${c.alto} cm` : '—'}</span></p>
                                            <p className="m-0 theme-text-muted font-bold">Vol.: <span className="theme-text-main">{c.peso_volumetrico_kg != null ? `${c.peso_volumetrico_kg} kg` : '—'}</span></p>
                                            <p className="m-0 theme-text-muted font-bold">Real: <span className="theme-text-main">{c.peso_real_kg != null ? `${c.peso_real_kg} kg` : '—'}</span></p>
                                            <p className="m-0 theme-text-muted font-bold">Cobrado: <span className="theme-text-main">{c.peso_cobrado_kg != null ? `${c.peso_cobrado_kg} kg` : '—'}</span></p>
                                            {(c.numero_rastreo || pedido.numero_rastreo) && (
                                                <p className="m-0 theme-text-muted font-bold col-span-2">
                                                    Guía: <span className="theme-text-main">{c.numero_rastreo || pedido.numero_rastreo}</span>
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </section>
                        )}
                        <section id="detalle-envio" tabIndex={-1} className="gelia-pedidos-seccion space-y-4">
                            <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} />
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="gelia-pedidos-seccion-titulo">Entrega y dirección</h3>
                                {can('control_pedidos.direccion.cambiar') && (
                                    <button
                                        type="button"
                                        className={`${BTN_SECONDARY} text-xs py-1.5 px-2 inline-flex items-center gap-1`}
                                        onClick={() => setCambiarDir(true)}
                                    >
                                        <MapPin className="w-3.5 h-3.5" /> Cambiar
                                    </button>
                                )}
                            </div>
                            <DireccionPedidoResumen
                                direccion={snap}
                                domicilioLegacy={pedido.domicilio_entrega}
                                codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(pedido.cliente?.numero_cliente, snap?.numero_direccion)}
                            />
                            {pedido.envia_a_otra_persona && (
                                <dl><Campo label="Destinatario alterno" value={pedido.envia_otra_persona} /></dl>
                            )}
                            <dl><Campo label="Comentarios" value={pedido.comentarios_drive} /></dl>
                        </section>
                        <section id="detalle-documentos" tabIndex={-1} className="gelia-pedidos-seccion space-y-3">
                        <h3 className="gelia-pedidos-seccion-titulo flex items-center gap-2"><FileText className="w-4 h-4 theme-text-muted" aria-hidden="true" /> Documentos y apartado</h3>
                        {!docsSinGuia.filter((d) => d.tipo !== 'evidencia_condicion').length && !pedido.detalle_resguardo_apartado && <p className="text-sm theme-text-muted m-0">Sin documentos adicionales.</p>}
                        {pedido.detalle_resguardo_apartado && (
                            <div className="mt-4 p-3 rounded-xl gelia-estado-vivo gelia-estado-vivo--info">
                                <p className="text-xs font-semibold theme-text-muted m-0">Nota de apartado CEDIS</p>
                                <p className="text-sm font-bold theme-text-main m-0 mt-1">{pedido.detalle_resguardo_apartado}</p>
                            </div>
                        )}
                        {evidenciasApartado.length > 0 && (
                            <div className="mt-4">
                                <p className="text-xs font-semibold theme-text-muted m-0 mb-2">Evidencia de apartado</p>
                                <div className="flex flex-wrap gap-2">
                                    {evidenciasApartado.map((doc) => (
                                        <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                    ))}
                                </div>
                            </div>
                        )}
                        {docsSinGuia.filter((d) => d.tipo !== 'evidencia_apartado' && d.tipo !== 'evidencia_condicion').length > 0 && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {docsSinGuia.filter((d) => d.tipo !== 'evidencia_apartado' && d.tipo !== 'evidencia_condicion').map((doc) => (
                                    <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                ))}
                            </div>
                        )}
                        </section>
                    </div>
                        </div>
                        </div>
                </div>
            </div>
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview?.documentos ? null : docPreview} documentos={docPreview?.documentos} indice={docPreview?.indice || 0} onClose={() => setDocPreview(null)} />
            <ModalCambiarDireccion abierto={cambiarDir} onClose={() => setCambiarDir(false)} pedido={pedido} />
        </>,
        document.body
    );
}
