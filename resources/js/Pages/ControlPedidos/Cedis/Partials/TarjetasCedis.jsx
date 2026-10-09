import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    Eye, CheckCircle2, AlertTriangle, FileText, Truck, PackageCheck, Scale, History, Undo2,
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
    etiquetaAlmacen,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    BTN_PRIMARY,
    tieneGuiaPdfDisponible,
    etiquetaOrigenGuia,
    LABELS_MOTIVO_REPESAJE,
    mostrarNotaCompraCedis,
    nombresDepartamentosVendedor,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMarcarEmpacadoBultos from './ModalMarcarEmpacadoBultos';
import { empacarRequiereModalBultos } from '../../Partials/pedidosBmaStyles';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import BotonGuiaPdf from '../../Partials/BotonGuiaPdf';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');
const pdfPedidoDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido');
const anexosPiezasDe = (pedido) => (pedido?.documentos || []).filter((d) => d.tipo === 'anexo_piezas');

function TarjetaPedido({
    pedido, onVerDetalle, onResponderPesaje, onReportarErrorDatos, onMarcarApartado, onSolicitarConfirmacion, onVerDocumento, onBitacora, puedeReabrir, puedeEnviar,
}) {
    const fase = pedido.estatus?.fase_ciclo;
    const soloMercancia = Boolean(pedido.consulta_cedis_modo_revision_mercancia || pedido.es_consulta_mercancia || pedido.origen?.requiere_logistica === false || (pedido.paqueteria?.categoria === 'local_regional' && pedido.paqueteria?.requiere_peso === false));
    const pendientePesaje = pedido.estatus_envio === 'pendiente_pesaje';
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, { forzarPesaje: true });
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const badgeComp = badgeConComplementos(pedido);
    const complementos = complementosDe(pedido);
    const remision = remisionDe(pedido);
    const pdfPedido = pdfPedidoDe(pedido);
    const anexosPiezas = anexosPiezasDe(pedido);
    const esErrorCedis = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO' && Boolean(puedeEnviar);
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
    const requiereLogistica = pedido.origen?.requiere_logistica ?? true;
    const ringRetraso = pedido.guia_retraso
        || tieneRetrasoEmpaqueActivo(pedido)
        || tieneRetrasoRecoleccionActivo(pedido);

    return (
        <article aria-label={`Pedido ${pedido.folio_remision || pedido.folio}`} className={`${geliaCardClass()} gelia-pedidos-tarjeta p-4 ${esErrorCedis ? 'ring-1 ring-[color:color-mix(in_srgb,var(--color-aviso)_40%,transparent)]' : ''} ${ringRetraso ? 'ring-1 ring-[color:color-mix(in_srgb,var(--color-aviso)_40%,transparent)]' : ''}`}>
            {pedido.origen?.nombre && (
                <p className="gelia-pedidos-tarjeta-origen m-0">
                    Origen: {pedido.origen.nombre}
                </p>
            )}
            <div className="gelia-pedidos-tarjeta-cabecera flex flex-col gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    <BloqueVendedorPedido pedido={pedido} variante="nombre" />
                    {nombresDepartamentosVendedor(pedido.vendedor).length > 0 && (
                        <div className="gelia-pedidos-departamento flex flex-wrap items-center gap-2 mt-3">
                            <span className="text-xs theme-text-muted">Departamento</span>
                            <BloqueVendedorPedido pedido={pedido} variante="etiquetas" className="mt-0" />
                        </div>
                    )}
                </div>
                <div className="gelia-pedidos-estados flex flex-wrap items-start gap-1.5">
                    {!pendientePesaje && (
                        <span className={badgeEmpaque.className} style={badgeEmpaque.style}>{badgeEmpaque.label}</span>
                    )}
                    {badgeEnvio && (
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

            {pendientePesaje && (
                <AvisoOperativoPedido
                    label={pedido.consulta_cedis_modo_revision_mercancia || pedido.es_consulta_mercancia || pedido.origen?.requiere_logistica === false || (pedido.paqueteria?.categoria === 'local_regional' && pedido.paqueteria?.requiere_peso === false) ? 'Consulta mercancía' : 'Consulta de pesaje'}
                    tono="warning"
                    icon={Scale}
                >
                    {pedido.consulta_actualizacion_pendiente || pedido.motivo_repesaje
                        ? `Actualización (${LABELS_MOTIVO_REPESAJE[pedido.motivo_repesaje] || pedido.motivo_repesaje || 'cambio'}). Revise el anexo/PDF y confirme.`
                        : (pedido.consulta_cedis_modo_revision_mercancia || pedido.es_consulta_mercancia || pedido.origen?.requiere_logistica === false || (pedido.paqueteria?.categoria === 'local_regional' && pedido.paqueteria?.requiere_peso === false)
                            ? 'Revise el PDF o foto, registre las piezas, los bultos a preparar (aprox.) y la evidencia final del lote.'
                            : 'Revise el PDF o foto del pedido y registre peso y cajas.')}
                </AvisoOperativoPedido>
            )}

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
                <div>
                    <p className="text-xs font-medium m-0">Cliente</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.cliente?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-xs font-medium m-0">Almacén</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{etiquetaAlmacen(pedido.almacen)}</p>
                </div>
                {requiereLogistica && (
                <>
                <div>
                    <p className="text-xs font-medium m-0">Paquetería</p>
                    <p className="text-xs theme-text-main m-0 mt-0.5 normal-case">{pedido.paqueteria?.nombre || '—'}</p>
                </div>
                <div>
                    <p className="text-xs font-medium m-0">Envíos / Guía</p>
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
                        <Scale className="w-4 h-4" aria-hidden="true" /> {soloMercancia ? 'Responder consulta' : 'Responder pesaje'}
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
                    {pendientePesaje && anexosPiezas.length > 0 && (
                        <BotonAccionCubico
                            icon={FileText}
                            label={anexosPiezas.length > 1 ? `Piezas (${anexosPiezas.length})` : 'Piezas extra'}
                            onClick={() => onVerDocumento(anexosPiezas)}
                            conLabel
                        />
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
                    {onBitacora && (
                        <BotonAccionCubico
                            icon={History}
                            label="Bitácora"
                            onClick={() => onBitacora(pedido)}
                            tone="purple"
                            conLabel
                            className={pendientePesaje && !pdfPedido && anexosPiezas.length === 0 ? 'col-span-2' : ''}
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
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
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
