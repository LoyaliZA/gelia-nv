import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    Eye, CheckCircle2, AlertTriangle, FileText, Truck, PackageCheck, Scale, History, Undo2, User,
} from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeEmpaqueSemantico,
    badgeEstatusEnvio,
    badgeRetrasoGuia,
    badgesRetrasoSla,
    tieneRetrasoEmpaqueActivo,
    tieneRetrasoRecoleccionActivo,
    badgeConComplementos,
    complementosDe,
    esPedidoEmpacadoCedis,
    etiquetaSucursal,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    BTN_PRIMARY,
    tieneGuiaPdfDisponible,
    cedisConsultaEsRevisionMercancia,
    terminologiaEmpaqueConsultaCedis,
    pedidoRequiereLogistica,
    etiquetaOrigenGuia,
    mostrarNotaCompraCedis,
    nombresDepartamentosVendedor,
} from '../../Partials/pedidosBmaStyles';
import { pedidoEsTrasladoInternoPdv } from '../../Partials/destinoSucursalPedido';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import RutaPedidoTienda from '../../Partials/RutaPedidoTienda';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMarcarEmpacadoBultos from './ModalMarcarEmpacadoBultos';
import { empacarRequiereModalBultos } from '../../Partials/pedidosBmaStyles';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import BotonGuiaPdf from '../../Partials/BotonGuiaPdf';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');
const pdfPedidoDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido')
    || (pedido?.documentos || []).find((d) => d.tipo === 'cotizacion');

function TarjetaPedido({
    pedido, onVerDetalle, onResponderPesaje, onReportarErrorDatos, onMarcarApartado, onSolicitarConfirmacion, onVerDocumento, onBitacora, puedeReabrir, puedeEnviar, puedeVerBitacora,
}) {
    const fase = pedido.estatus?.fase_ciclo;
    const soloMercancia = cedisConsultaEsRevisionMercancia(pedido);
    const empaqueConsulta = soloMercancia ? terminologiaEmpaqueConsultaCedis(pedido) : null;
    const pendientePesaje = pedido.estatus_envio === 'pendiente_pesaje';
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, { pedido, forzarPesaje: true });
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const badgeComp = badgeConComplementos(pedido);
    const complementos = complementosDe(pedido);
    const remision = remisionDe(pedido);
    const pdfPedido = pdfPedidoDe(pedido);
    const esErrorCedis = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const revision = pedido.solicitud_preparacion?.revision_disponibilidad;
    const esConsultaDisponibilidad = pedido.solicitud_preparacion?.area_revision === 'control_pedidos';
    const actualizacionMercanciaPendiente = Boolean(pedido.consulta_actualizacion_pendiente || revision?.actualizacion_pendiente
        || (pedido.solicitud_preparacion?.anexo_sin_remision || []).some(a => !a.respondida_at));
    const consultaRespondida = !actualizacionMercanciaPendiente && Boolean(revision?.estado || !pendientePesaje && (pedido.pesaje_respondido_at || pedido.estatus_envio === 'pesaje_listo'));
    const mostrarAvisoConsulta = esConsultaDisponibilidad || pendientePesaje || actualizacionMercanciaPendiente;
    const mensajeConsulta = actualizacionMercanciaPendiente || pendientePesaje && (revision?.estado || pedido.motivo_repesaje)
        ? 'Actualización de consulta solicitada'
        : consultaRespondida ? 'Consulta respondida' : soloMercancia || esConsultaDisponibilidad ? 'Nueva consulta solicitada' : 'Consulta de pesaje solicitada';
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo && !actualizacionMercanciaPendiente;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO' && Boolean(puedeEnviar) && !actualizacionMercanciaPendiente;
    const puedeReabrirEnvio = fase === 'ENVIADO' && Boolean(puedeReabrir);
    const cajasPedido = pedido.cajas || [];
    const cajasPendientesCount = pedido.cajas_pendientes
        ?? cajasPedido.filter((c) => (c.estatus_recoleccion || 'pendiente') === 'pendiente').length;
    const cajasRecolectadasCount = pedido.cajas_recolectadas
        ?? cajasPedido.filter((c) => c.estatus_recoleccion === 'recolectada').length;
    const requiereSeleccionEnvios = cajasPendientesCount > 1;
    const puedeReportarError = ['EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO'].includes(fase) && !pedido.es_resguardo;
    const puedeApartar = Boolean(pedido.es_resguardo) && fase === 'EN_CEDIS' && !pedido.resguardo_apartado_at;
    const tieneGuiaPdf = tieneGuiaPdfDisponible(pedido);
    const requiereLogistica = pedidoRequiereLogistica(pedido);
    const trasladoInterno = pedidoEsTrasladoInternoPdv(pedido);
    const preparacion = pedido.tarea_preparacion_vigente || pedido.tarea_preparacion_respondida || pedido.tarea_preparacion;
    const destino = pedido.sucursal_destino || pedido.sucursalDestino;
    const departamentos = nombresDepartamentosVendedor(pedido.vendedor);
    const ringRetraso = pedido.guia_retraso
        || tieneRetrasoEmpaqueActivo(pedido)
        || tieneRetrasoRecoleccionActivo(pedido);

    return (
        <article aria-label={`Pedido ${pedido.folio_remision || pedido.folio}`} className={`${geliaCardClass()} gelia-pedidos-tarjeta p-4 ${esErrorCedis ? 'ring-1 ring-[color:color-mix(in_srgb,var(--color-aviso)_40%,transparent)]' : ''} ${ringRetraso ? 'ring-1 ring-[color:color-mix(in_srgb,var(--color-aviso)_40%,transparent)]' : ''}`}>
            <header className="gelia-cedis-tipo">
                <div className="gelia-cedis-tipo-identidad"><span>Tipo de pedido</span><strong>{pedido.origen?.nombre || 'Tipo no disponible'}</strong></div>
                {departamentos.length > 0 && <div className="gelia-cedis-tipo-departamento"><span>Departamento</span><strong>{departamentos.join(' · ')}</strong></div>}
            </header>
            {trasladoInterno && <span className="text-xs font-semibold theme-text-info">Traslado interno a punto de venta</span>}
            <div className="gelia-pedidos-tarjeta-cabecera flex flex-col gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="lg" etiquetaPrincipal="Folio" internoCompacto />
                    <p className="gelia-cedis-cliente">Cliente: {pedido.cliente?.nombre || pedido.cliente?.nombre_comercial || pedido.contacto_nombre_snapshot || 'Cliente por confirmar'}</p>
                    <div className="gelia-cedis-solicitud">
                        {pedido.vendedor?.name && <span className="gelia-cedis-solicitante" aria-label={`Solicitó ${pedido.vendedor.name}`}><User size={15} aria-hidden />{pedido.vendedor.name}</span>}
                        <span className="text-xs theme-text-muted">{formatearFechaNegocio(pedido.fecha)}</span>
                    </div>
                </div>
                <div className="gelia-pedidos-estados flex flex-wrap items-start gap-1.5">
                    {!pendientePesaje && badgeEmpaque.label !== '—' && (
                        <span className={badgeEmpaque.className} style={badgeEmpaque.style}>{badgeEmpaque.label}</span>
                    )}
                    {badgeEnvio && (!mostrarAvisoConsulta || !['Consulta respondida', 'Pendiente de respuesta', 'Pendiente de pesaje CEDIS'].includes(badgeEnvio.label)) && (
                        <span className={badgeEnvio.className} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                    )}
                    {badgeComp && (
                        <span className={badgeComp.className} style={badgeComp.style}>{badgeComp.label}</span>
                    )}
                    {badgeRetraso && (
                        <span className={badgeRetraso.className} style={badgeRetraso.style}>{badgeRetraso.label}</span>
                    )}
                    {badgesSla.map((b) => (
                        <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                    ))}
                    {fase === 'PENDIENTE_DE_ENVIO' && cajasPedido.length > 1 && (
                        <span className="text-xs font-semibold px-2 py-0.5 rounded-full gelia-estado-vivo gelia-estado-vivo--aviso">
                            {cajasRecolectadasCount}/{cajasPedido.length} recolectadas
                        </span>
                    )}
                </div>
            </div>

            {mostrarAvisoConsulta && <AvisoOperativoPedido tono={consultaRespondida ? 'success' : 'warning'} icon={consultaRespondida ? CheckCircle2 : Scale} className="gelia-cedis-aviso-consulta">{mensajeConsulta}</AvisoOperativoPedido>}
            {complementos.length > 0 && (
                <div className="rounded-xl border theme-border theme-element p-2.5 space-y-1">
                    <p className="text-xs font-semibold theme-text-info m-0">Complementos</p>
                    {complementos.map((c) => (
                        <p key={c.id} className="text-xs font-bold theme-text-main m-0">
                            {c.folio}{c.folio_remision ? ` · ${c.folio_remision}` : ''}
                        </p>
                    ))}
                </div>
            )}

            {mostrarNotaCompraCedis(fase) && (
                <AvisoOperativoPedido
                    label="Nota de compra en el envío"
                    tono={pedido.anexar_remision ? 'success' : 'warning'}
                    icon={FileText}
                >
                    {pedido.anexar_remision
                        ? 'Incluir nota de compra en el paquete'
                        : 'No incluir nota de compra (dropshipping)'}
                </AvisoOperativoPedido>
            )}

            <div className="gelia-pedidos-tarjeta-datos grid grid-cols-2 gap-x-3 gap-y-3 text-xs font-medium theme-text-muted">
                <div className="col-span-2 gelia-cedis-tarjeta-ruta"><RutaPedidoTienda origen={pedido.almacen?.nombre || pedido.almacen?.codigo || (esConsultaDisponibilidad ? 'CEDIS' : 'Por confirmar')} destino={destino ? etiquetaSucursal(destino) : 'Por confirmar'} /></div>
                {preparacion?.modalidad?.nombre && <div><p className="text-xs font-medium m-0">Preparación</p><p className="text-sm theme-text-main m-0 mt-0.5">{preparacion.modalidad.nombre}</p></div>}
                {!requiereLogistica && pedido.numero_cajas != null && (
                    <div>
                        <p className="text-xs font-medium m-0">{empaqueConsulta?.campoCorto || 'Bultos a preparar'}</p>
                        <p className="text-sm theme-text-main m-0 mt-0.5">{pedido.numero_cajas}</p>
                    </div>
                )}
                {requiereLogistica && (
                <>
                <div>
                    <p className="text-xs font-medium m-0">Paquetería</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.paqueteria?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-xs font-medium m-0">
                        {soloMercancia ? `${empaqueConsulta.campoCorto} / Guía` : 'Envíos / Guía'}
                    </p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">
                        {pedido.numero_cajas ?? '—'} · {etiquetaOrigenGuia(pedido)}
                    </p>
                </div>
                </>
                )}
            </div>
            {requiereLogistica && (() => {
                const dir = pedido.direccion_vigente || pedido.direccionVigente || {};
                const dest = dir.nombre_destinatario;
                const cp = pedido.codigo_postal || dir.codigo_postal;
                if (!dest && !cp) return null;
                return (
                    <p className="text-xs font-bold theme-text-main m-0">
                        {dest || 'Destinatario —'}
                        {cp ? ` · CP ${cp}` : ''}
                    </p>
                );
            })()}

            {esErrorCedis && (pedido.detalle_incidencia_empaque || pedido.detalle_error_datos) && (
                <AvisoOperativoPedido label="Error reportado" tono="danger" icon={AlertTriangle}>
                    {pedido.detalle_incidencia_empaque || pedido.detalle_error_datos}
                </AvisoOperativoPedido>
            )}

            {(fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && pedido.es_resguardo && (
                <AvisoOperativoPedido label="Resguardo" tono="blue" icon={PackageCheck}>
                    {pedido.resguardo_apartado_at
                        ? 'Resguardo apartado — empaque bloqueado'
                        : 'Empaque bloqueado — en resguardo'}
                </AvisoOperativoPedido>
            )}

            {esEmpacado && pedido.empacado_at && (
                <AvisoOperativoPedido label="Empaque" tono="success" icon={CheckCircle2}>
                    Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'}
                    <span className="block text-xs font-medium mt-1">
                        {formatearFechaHoraAuditoria(pedido.empacado_at)}
                    </span>
                </AvisoOperativoPedido>
            )}
            {fase === 'PENDIENTE_GUIA_CLIENTE' && (
                <AvisoOperativoPedido label={etiquetaOrigenGuia(pedido)} tono="info">
                    Esperando guía del cliente (vendedora). Solo lectura hasta que cargue la guía.
                </AvisoOperativoPedido>
            )}


            <div className="gelia-pedidos-tarjeta-acciones pt-2">
                {pendientePesaje && (
                    <button type="button" onClick={() => onResponderPesaje?.(pedido)} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <Scale className="w-4 h-4" aria-hidden="true" /> {pedido.solicitud_preparacion?.area_revision === 'control_pedidos' ? (pedido.solicitud_preparacion?.revision_disponibilidad?.actualizacion_pendiente || (pedido.solicitud_preparacion?.anexo_sin_remision || []).some(a => !a.respondida_at) ? 'Actualización de consulta' : pedido.solicitud_preparacion?.revision_disponibilidad?.estado ? 'Ver disponibilidad' : 'Nueva consulta') : (soloMercancia ? 'Responder consulta' : 'Responder pesaje')}
                    </button>
                )}
                {puedeMarcarEnviado && (
                    <button
                        type="button"
                        onClick={() => (requiereSeleccionEnvios
                            ? onVerDetalle(pedido)
                            : onSolicitarConfirmacion({ accion: 'enviar', pedido }))}
                        className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}
                    >
                        <Truck className="w-4 h-4" />
                        {requiereSeleccionEnvios ? 'Elegir envíos a recolectar' : 'Marcar enviado'}
                    </button>
                )}
                {puedeReabrirEnvio && (
                    <button type="button" onClick={() => onSolicitarConfirmacion({ accion: 'reabrir', pedido })} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <Undo2 className="w-4 h-4" /> Reabrir recolección
                    </button>
                )}
                {puedeEmpacar && (
                    <button type="button" onClick={() => onSolicitarConfirmacion({ accion: 'empacar', pedido })} className={`${pendientePesaje ? 'theme-btn-secondary' : BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <CheckCircle2 className="w-4 h-4" /> {complementos.length ? 'Empacar grupo' : 'Marcar empacado'}
                    </button>
                )}
                {puedeApartar && (
                    <button type="button" onClick={() => onMarcarApartado?.(pedido)} className={`${BTN_PRIMARY} w-full flex items-center justify-center gap-2 text-xs outline-none py-3 min-h-[44px]`}>
                        <PackageCheck className="w-4 h-4" /> Marcar apartado
                    </button>
                )}
                <div className="grid grid-cols-2 gap-2">
                    {pendientePesaje && pdfPedido && (
                        <BotonAccionCubico icon={FileText} label="PDF pedido" onClick={() => onVerDocumento(pdfPedido)} conLabel />
                    )}
                    {esEmpacado && tieneGuiaPdf && (
                        <div className="col-span-2 sm:col-span-1 [&_button]:w-full [&_button]:min-h-[44px]">
                            <BotonGuiaPdf pedido={pedido} onVerPdf={onVerDocumento} compact className="w-full justify-center py-2.5 min-h-[44px]" />
                        </div>
                    )}
                    {remision && (
                        <BotonAccionCubico icon={FileText} label="Remisión" onClick={() => onVerDocumento(remision)} conLabel />
                    )}
                    {puedeReportarError && (
                        <BotonAccionCubico icon={AlertTriangle} label="Reportar error" onClick={() => onReportarErrorDatos?.(pedido)} tone="warn" conLabel className="col-span-2" />
                    )}
                    <BotonAccionCubico icon={Eye} label="Detalle" onClick={() => onVerDetalle(pedido)} conLabel />
                    {puedeVerBitacora && onBitacora && (
                        <BotonAccionCubico
                            icon={History}
                            label="Bitácora"
                            onClick={() => onBitacora(pedido)}
                            tone="purple"
                            conLabel
                        />
                    )}
                </div>
            </div>
        </article>
    );
}

export default function TarjetasCedis({
    pedidos, onVerDetalle, onResponderPesaje, onReportarErrorDatos, onMarcarApartado, onBitacora,
}) {
    const { auth, can = {} } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const puedeVerBitacora = can.bitacora ?? (permisos.includes('control_pedidos.cedis.bitacora') || auth?.user?.roles?.includes('Super Admin'));
    const puedeReabrir = permisos.includes('control_pedidos.reabrir') || auth?.user?.roles?.includes('Super Admin');
    const puedeEnviar = permisos.includes('control_pedidos.cedis.enviar') || auth?.user?.roles?.includes('Super Admin');
    const [confirmacion, setConfirmacion] = useState(null);
    const [modalEmpacarBultos, setModalEmpacarBultos] = useState(null);
    const [docPreview, setDocPreview] = useState(null);

    const abrirDocumento = (docOrDocs, indice = 0) => {
        if (Array.isArray(docOrDocs)) {
            setDocPreview({ documentos: docOrDocs.filter((d) => d?.url), indice });
            return;
        }
        if (docOrDocs?.url) {
            setDocPreview({ documentos: [docOrDocs], indice: 0 });
        }
    };
    const items = pedidos?.data || [];

    const solicitarConfirmacion = (payload) => {
        if (payload?.accion === 'empacar' && empacarRequiereModalBultos(payload.pedido)) {
            setModalEmpacarBultos(payload.pedido);
            return;
        }
        setConfirmacion(payload);
    };

    const ejecutarConfirmacion = () => {
        const { accion, pedido } = confirmacion || {};
        setConfirmacion(null);
        if (!pedido) return;
        if (accion === 'empacar') {
            router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, { preserveScroll: true });
        } else if (accion === 'enviar') {
            router.post(route('control_pedidos.cedis.marcar_enviado', pedido.id), {}, { preserveScroll: true });
        } else if (accion === 'reabrir') {
            router.post(route('control_pedidos.cedis.reabrir_envio', pedido.id), {}, { preserveScroll: true });
        }
    };

    const comps = confirmacion?.accion === 'empacar' ? complementosDe(confirmacion.pedido) : [];
    const cfgConfirm = confirmacion?.accion === 'empacar'
        ? {
            titulo: comps.length ? 'Confirmar empaque del grupo' : 'Confirmar empaque',
            mensaje: comps.length
                ? `Se empacará ${confirmacion.pedido.folio} y ${comps.length} complemento(s).`
                : '¿Confirmar que el pedido fue empacado?',
            etiquetaConfirmar: comps.length ? 'Empacar grupo' : 'Marcar empacado',
            variante: 'primary',
        }
        : confirmacion?.accion === 'enviar'
            ? { titulo: 'Confirmar recolección', mensaje: '¿Confirmas que la paquetería recogió el paquete? Empacado no significa enviado.', etiquetaConfirmar: 'Paquetería recogió', variante: 'primary' }
            : confirmacion?.accion === 'reabrir'
                ? { titulo: 'Reabrir recolección', mensaje: 'El pedido volverá a pendiente de recolección. Solo si la paquetería no recogió.', etiquetaConfirmar: 'Reabrir', variante: 'danger' }
                : null;

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-10 md:p-16 text-center text-sm theme-text-muted font-bold`}>
                Sin pedidos en esta vista
                <p className="text-sm font-normal mt-2 mb-0">Prueba otra búsqueda o cambia el filtro de estado.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
                {items.map((pedido) => (
                    <TarjetaPedido
                        key={pedido.id}
                        pedido={pedido}
                        onVerDetalle={onVerDetalle}
                        onResponderPesaje={onResponderPesaje}
                        onReportarErrorDatos={onReportarErrorDatos}
                        onMarcarApartado={onMarcarApartado}
                        onSolicitarConfirmacion={solicitarConfirmacion}
                        onVerDocumento={abrirDocumento}
                        onBitacora={onBitacora}
                        puedeReabrir={puedeReabrir}
                        puedeEnviar={puedeEnviar}
                        puedeVerBitacora={puedeVerBitacora && pedido.puede_ver_bitacora !== false}
                    />
                ))}
            </div>
            <ModalMarcarEmpacadoBultos
                abierto={Boolean(modalEmpacarBultos)}
                pedido={modalEmpacarBultos}
                onClose={() => setModalEmpacarBultos(null)}
            />
            <ModalConfirmarAccion
                abierto={Boolean(cfgConfirm)}
                titulo={cfgConfirm?.titulo}
                mensaje={cfgConfirm?.mensaje}
                etiquetaConfirmar={cfgConfirm?.etiquetaConfirmar}
                variante={cfgConfirm?.variante}
                onClose={() => setConfirmacion(null)}
                onConfirm={ejecutarConfirmacion}
            />
            <ModalVistaPreviaDocumento
                abierto={Boolean(docPreview?.documentos?.length)}
                documentos={docPreview?.documentos || []}
                indice={docPreview?.indice || 0}
                onClose={() => setDocPreview(null)}
            />
        </div>
    );
}
