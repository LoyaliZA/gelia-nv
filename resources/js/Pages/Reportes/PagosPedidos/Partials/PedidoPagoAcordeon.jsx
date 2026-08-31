import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ChevronDown, FileText, ImageIcon } from 'lucide-react';
import ModalVisorArchivo, { payloadArchivoRemision } from '@/Components/ModalVisorArchivo';
import ResumenFinancieroPedido from './ResumenFinancieroPedido';
import ListaExhibicionesPago from './ListaExhibicionesPago';
import DocumentosEvidencias from './DocumentosEvidencias';
import {
    fmtMxn,
    fmtVouchersLabel,
    RADIUS_PEDIDO,
    BTN_ICON,
    BTN_NEUTRAL,
    NOMBRE_CLIENTE,
    FOLIO_META,
    BLOQUE_GAP,
    DETALLE_PAD,
    ACORDEON_CONTENIDO_INNER,
    acordeonContenidoGridClass,
    scrollAlExpandirAcordeon,
} from './pagosPedidosStyles';
import { EtiquetaCoberturaPedido } from './EtiquetaEstadoCategorizado';
import {
    CeldaFinanciera,
    GRID_RESUMEN_PEDIDO,
    PEDIDO_BTN_DOC,
    PEDIDO_CABECERA,
    PEDIDO_CHEVRON,
    PEDIDO_COBERTURA,
    PEDIDO_DOCS,
    PEDIDO_FIN_GRID,
    PEDIDO_TRAIL,
    PEDIDO_IDENTIDAD,
} from './GridFilasPagosPedidos';
import { PieRevisionAdminPedido, useAdminDetalleUpdater } from './AccionesAdminPagos';

function detenerPropagacion(e) {
    e.stopPropagation();
}

export default function PedidoPagoAcordeon({
    pedido,
    auth,
    cacheDetalle,
    onCacheDetalle,
    onRecargarLista,
    abierto = false,
    onAbiertoChange,
}) {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [irAVouchers, setIrAVouchers] = useState(false);
    const [visorRemision, setVisorRemision] = useState(null);
    const [pendienteRemision, setPendienteRemision] = useState(false);
    const pedidoRef = useRef(null);
    const documentosRef = useRef(null);
    const abiertoPrevioRef = useRef(abierto);
    const detalle = cacheDetalle[pedido.cierre_id];
    const aplicarMergeAdmin = useAdminDetalleUpdater(onCacheDetalle, pedido.cierre_id);
    const adminResumen = pedido.admin_resumen;
    const adminResumenLabel = pedido.admin_resumen_label;
    const exhibicionesRevisadas = pedido.admin_exhibiciones_revisadas;
    const exhibicionesTotal = pedido.admin_exhibiciones_total;
    const exhibicionesPendientes = pedido.admin_exhibiciones_pendientes;
    const adminRevisadoPor = pedido.admin_revisado_por;
    const adminRevisadoAt = pedido.admin_revisado_at;
    const pedidoTieneError = Boolean(pedido.admin_pedido_error_reportado_at);

    const cargarDetalle = useCallback(async ({ force = false } = {}) => {
        if (!force && (detalle || cargando)) return;
        setCargando(true);
        setError(null);
        try {
            const res = await fetch(route('reportes.pagos_pedidos.detalle', { cierre: pedido.cierre_id }), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('No se pudo cargar el detalle');
            const data = await res.json();
            onCacheDetalle(pedido.cierre_id, data);
        } catch (e) {
            setError(e.message);
        } finally {
            setCargando(false);
        }
    }, [cargando, detalle, onCacheDetalle, pedido.cierre_id]);

    const onAdminActualizado = useCallback((resp) => {
        aplicarMergeAdmin(resp);
        onRecargarLista?.();
        if (abierto) {
            cargarDetalle({ force: true });
        }
    }, [aplicarMergeAdmin, onRecargarLista, abierto, cargarDetalle]);

    const expandir = useCallback(() => {
        onAbiertoChange?.(true);
        cargarDetalle();
    }, [cargarDetalle, onAbiertoChange]);

    const toggle = useCallback(() => {
        if (abierto) {
            onAbiertoChange?.(false);
            return;
        }
        expandir();
    }, [abierto, expandir, onAbiertoChange]);

    useEffect(() => {
        if (abierto && !abiertoPrevioRef.current) {
            scrollAlExpandirAcordeon(pedidoRef.current);
        }
        abiertoPrevioRef.current = abierto;
    }, [abierto]);

    useEffect(() => {
        if (!irAVouchers || !detalle || !abierto) return;
        documentosRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setIrAVouchers(false);
    }, [irAVouchers, detalle, abierto]);

    useEffect(() => {
        if (!pendienteRemision || !detalle) return;
        const payload = payloadArchivoRemision(detalle.documentos?.remision_vigente);
        if (payload) setVisorRemision(payload);
        setPendienteRemision(false);
    }, [pendienteRemision, detalle]);

    const remisionDoc = detalle?.documentos?.remision_vigente ?? null;
    const remisionUrl = remisionDoc?.url ?? null;
    const vouchersCount = Number(pedido.vouchers_count ?? 0);
    const vouchersLabel = fmtVouchersLabel(vouchersCount);

    const onVouchers = () => {
        if (!pedido.tiene_vouchers) return;
        setIrAVouchers(true);
        if (!abierto) expandir();
        else if (detalle) documentosRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const onRemision = () => {
        if (!pedido.tiene_remision) return;
        const payload = payloadArchivoRemision(remisionDoc);
        if (payload) {
            setVisorRemision(payload);
            return;
        }
        setPendienteRemision(true);
        if (!abierto) expandir();
        else cargarDetalle();
    };

    const numExhibiciones = Number(pedido.num_exhibiciones ?? 0);
    const metaAtencion = [
        pedido.pedido_fecha_label || null,
        pedido.vendedor ? `Atendido por: ${pedido.vendedor}` : null,
        pedido.departamento || null,
    ].filter(Boolean);
    if (numExhibiciones > 0) {
        metaAtencion.push(
            `${numExhibiciones.toLocaleString('es-MX')} exhibición${numExhibiciones === 1 ? '' : 'es'}`,
        );
    }
    const metaAtencionTexto = metaAtencion.join(' · ');

    return (
        <div
            ref={pedidoRef}
            className={[
                'overflow-hidden border theme-border transition-colors scroll-mt-24',
                RADIUS_PEDIDO,
                abierto
                    ? 'border-l-[3px] border-l-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_5%,var(--theme-element-bg))]'
                    : 'theme-element',
            ].join(' ')}
        >
            <div
                role="button"
                tabIndex={0}
                aria-expanded={abierto}
                aria-label={abierto ? 'Contraer pedido' : 'Expandir pedido'}
                onClick={toggle}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                }}
                className={`${GRID_RESUMEN_PEDIDO} ${PEDIDO_CABECERA}`}
            >
                <div className={PEDIDO_CHEVRON} aria-hidden="true">
                    <ChevronDown
                        className={`w-[18px] h-[18px] transition-transform duration-300 ease-out ${abierto ? '' : '-rotate-90'}`}
                        style={{ color: 'var(--color-primario)' }}
                    />
                </div>

                <div className={PEDIDO_IDENTIDAD}>
                    <p className={`${NOMBRE_CLIENTE} m-0 truncate leading-snug`}>
                        {pedido.cliente?.nombre || '—'}
                    </p>
                    <p className={`${FOLIO_META} m-0 leading-snug truncate`}>
                        {pedido.folio_remision ? (
                            <>
                                <span className="font-medium">Remisión </span>
                                <span className="font-mono tabular-nums">{pedido.folio_remision}</span>
                            </>
                        ) : (
                            <span className="font-medium">Sin remisión</span>
                        )}
                        <span className="mx-1.5" aria-hidden="true">·</span>
                        <span className="font-medium">Folio</span>{' '}
                        <span className="font-mono">{pedido.folio || '—'}</span>
                    </p>
                    {metaAtencionTexto && (
                        <p className={`${FOLIO_META} m-0 truncate leading-snug`}>
                            {metaAtencionTexto}
                        </p>
                    )}
                </div>

                <div
                    className={PEDIDO_FIN_GRID}
                    onClick={detenerPropagacion}
                    onKeyDown={detenerPropagacion}
                >
                    <CeldaFinanciera label="Total remisión" valor={fmtMxn(pedido.total_pedido)} />
                    <CeldaFinanciera label="SAF aplicado" valor={fmtMxn(pedido.saf_aplicado)} />
                    <CeldaFinanciera label="Pagos válidos" valor={fmtMxn(pedido.pagos_validos)} />
                    <CeldaFinanciera
                        label="Exhibiciones"
                        valor={numExhibiciones.toLocaleString('es-MX')}
                    />
                </div>

                <div
                    className={PEDIDO_TRAIL}
                    onClick={detenerPropagacion}
                    onKeyDown={detenerPropagacion}
                >
                    <div className={PEDIDO_DOCS}>
                        <button
                            type="button"
                            className={PEDIDO_BTN_DOC}
                            disabled={!pedido.tiene_vouchers}
                            title={
                                pedido.tiene_vouchers
                                    ? `Ver ${vouchersLabel.toLowerCase()} adjuntos`
                                    : 'Sin comprobantes'
                            }
                            aria-label={
                                pedido.tiene_vouchers
                                    ? `Ver ${vouchersLabel.toLowerCase()} adjuntos`
                                    : 'Sin comprobantes'
                            }
                            onClick={onVouchers}
                        >
                            <ImageIcon className={BTN_ICON} aria-hidden="true" />
                            <span>{vouchersLabel}</span>
                        </button>
                        <button
                            type="button"
                            className={PEDIDO_BTN_DOC}
                            disabled={!pedido.tiene_remision}
                            title={
                                !pedido.tiene_remision
                                    ? 'Sin remisión'
                                    : remisionUrl
                                        ? 'Abrir remisión'
                                        : 'Cargando documento de remisión…'
                            }
                            aria-label={
                                !pedido.tiene_remision
                                    ? 'Sin remisión'
                                    : 'Abrir remisión'
                            }
                            onClick={onRemision}
                        >
                            <FileText className={BTN_ICON} aria-hidden="true" />
                            <span>Remisión</span>
                        </button>
                    </div>

                    <div className={PEDIDO_COBERTURA}>
                        <EtiquetaCoberturaPedido pedido={pedido} className="items-end" />
                    </div>
                </div>
            </div>

            <PieRevisionAdminPedido
                auth={auth}
                cierreId={pedido.cierre_id}
                adminResumen={adminResumen}
                adminResumenLabel={adminResumenLabel}
                adminRevisadoPor={adminRevisadoPor}
                adminRevisadoAt={adminRevisadoAt}
                pedidoTieneError={pedidoTieneError}
                exhibicionesRevisadas={exhibicionesRevisadas}
                exhibicionesTotal={exhibicionesTotal}
                exhibicionesPendientes={exhibicionesPendientes}
                onActualizado={onAdminActualizado}
                onRecargarLista={onRecargarLista}
            />

            <div
                className={acordeonContenidoGridClass(abierto)}
                aria-hidden={!abierto}
            >
                <div className={ACORDEON_CONTENIDO_INNER}>
                    <div className={`${DETALLE_PAD} pb-5 ${BLOQUE_GAP} border-t theme-border pt-4 md:pt-5`}>
                        {cargando && (
                            <p className="text-xs font-medium theme-text-muted animate-pulse m-0">Cargando detalle…</p>
                        )}
                        {error && (
                            <div className="flex flex-wrap items-center gap-3">
                                <p className="text-sm text-red-600 m-0">{error}</p>
                                <button type="button" onClick={cargarDetalle} className={BTN_NEUTRAL}>Reintentar</button>
                            </div>
                        )}
                        {detalle && (
                            <>
                                <ResumenFinancieroPedido cierre={detalle.cierre} financiero={detalle.financiero} />
                                <ListaExhibicionesPago
                                    exhibiciones={detalle.exhibiciones}
                                    auth={auth}
                                    cierreId={pedido.cierre_id}
                                    pedidoTieneError={Boolean(detalle?.cierre?.admin_pedido_error_reportado_at ?? pedido.admin_pedido_error_reportado_at)}
                                    onAdminActualizado={onAdminActualizado}
                                    onRecargarLista={onRecargarLista}
                                />
                                <div ref={documentosRef}>
                                    <DocumentosEvidencias
                                        exhibiciones={detalle.exhibiciones}
                                        documentos={detalle.documentos}
                                        folioPedido={detalle.cierre?.folio}
                                        folioRemision={detalle.cierre?.folio_remision}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
            <ModalVisorArchivo
                abierto={Boolean(visorRemision)}
                onCerrar={() => setVisorRemision(null)}
                url={visorRemision?.url}
                mimeType={visorRemision?.mimeType}
                titulo={visorRemision?.titulo}
                subtitulo={visorRemision?.subtitulo}
            />
        </div>
    );
}
