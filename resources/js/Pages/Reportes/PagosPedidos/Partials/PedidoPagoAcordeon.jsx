import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ChevronDown, FileText, ImageIcon } from 'lucide-react';
import ModalVisorArchivo, { payloadArchivoRemision } from '@/Components/ModalVisorArchivo';
import ResumenFinancieroPedido from './ResumenFinancieroPedido';
import ListaExhibicionesPago from './ListaExhibicionesPago';
import DocumentosEvidencias from './DocumentosEvidencias';
import { badgeCobertura, fmtMxn, fmtVouchersLabel, LABEL_COBERTURA, RADIUS_PEDIDO } from './pagosPedidosStyles';
import {
    CeldaFinanciera,
    GRID_FILA_PEDIDO,
    PEDIDO_BADGE,
    PEDIDO_BTN_DOC,
    PEDIDO_CABECERA,
    PEDIDO_CHEVRON,
    PEDIDO_DOCS,
    PEDIDO_FIN_GRID,
    PEDIDO_IDENTIDAD,
} from './GridFilasPagosPedidos';

function detenerPropagacion(e) {
    e.stopPropagation();
}

export default function PedidoPagoAcordeon({ pedido, cacheDetalle, onCacheDetalle }) {
    const [abierto, setAbierto] = useState(false);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [irAVouchers, setIrAVouchers] = useState(false);
    const [visorRemision, setVisorRemision] = useState(null);
    const [pendienteRemision, setPendienteRemision] = useState(false);
    const documentosRef = useRef(null);
    const detalle = cacheDetalle[pedido.cierre_id];

    const cargarDetalle = useCallback(async () => {
        if (detalle || cargando) return;
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

    const expandir = useCallback(() => {
        setAbierto(true);
        cargarDetalle();
    }, [cargarDetalle]);

    const toggle = useCallback(() => {
        if (abierto) {
            setAbierto(false);
            return;
        }
        expandir();
    }, [abierto, expandir]);

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

    const diferencia = Number(pedido.diferencia || 0);
    const excedente = Number(pedido.excedente || 0);
    const diferenciaFmt = fmtMxn(diferencia);
    const diferenciaPendiente = diferencia > 0.005;
    const diferenciaCero = diferencia <= 0.005 && excedente <= 0.005;
    const tonoDiferencia = diferenciaCero
        ? 'exito'
        : diferenciaPendiente || excedente > 0.005
            ? 'advertencia'
            : null;

    return (
        <div
            className={[
                'overflow-hidden border theme-border transition-colors',
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
                className={`${GRID_FILA_PEDIDO} ${PEDIDO_CABECERA}`}
            >
                <div className={PEDIDO_CHEVRON} aria-hidden="true">
                    <ChevronDown
                        className={`w-[18px] h-[18px] transition-transform ${abierto ? '' : '-rotate-90'}`}
                        style={{ color: 'var(--color-primario)' }}
                    />
                </div>

                <div className={PEDIDO_IDENTIDAD}>
                    <p className="text-sm font-semibold theme-text-main m-0 leading-snug truncate">
                        {pedido.folio_remision ? (
                            <>
                                <span className="text-[11px] font-medium theme-text-muted">Remisión </span>
                                <span className="font-mono tabular-nums">{pedido.folio_remision}</span>
                            </>
                        ) : (
                            <span className="text-xs font-medium theme-text-muted">Sin remisión</span>
                        )}
                    </p>
                    <p className="text-xs theme-text-muted m-0 leading-snug truncate">
                        <span className="font-medium">Folio:</span>{' '}
                        <span className="font-mono">{pedido.folio || '—'}</span>
                    </p>
                    <p className="text-sm font-semibold theme-text-main m-0 mt-1 truncate leading-snug">
                        {pedido.cliente?.nombre || '—'}
                    </p>
                    <p className="text-xs theme-text-muted m-0 truncate leading-snug">
                        <span className="font-medium">Atendió:</span> {pedido.vendedor || '—'}
                        {pedido.departamento ? ` · ${pedido.departamento}` : ''}
                    </p>
                </div>

                <div className={PEDIDO_FIN_GRID}>
                    <CeldaFinanciera label="Total del pedido" valor={fmtMxn(pedido.total_pedido)} />
                    <CeldaFinanciera label="A cobrar" valor={fmtMxn(pedido.total_a_cobrar)} />
                    <CeldaFinanciera label="Pagado" valor={fmtMxn(pedido.pagos_validos)} />
                    <CeldaFinanciera
                        label="Diferencia"
                        valor={diferenciaFmt}
                        tono={tonoDiferencia}
                        confirmado={diferenciaCero}
                    />
                </div>

                <div
                    className={PEDIDO_DOCS}
                    onClick={detenerPropagacion}
                    onKeyDown={detenerPropagacion}
                >
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
                        <ImageIcon className="w-[18px] h-[18px] shrink-0" aria-hidden="true" />
                        <span className="hidden lg:inline">{vouchersLabel}</span>
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
                        <FileText className="w-[18px] h-[18px] shrink-0" aria-hidden="true" />
                        <span className="hidden lg:inline">Remisión</span>
                    </button>
                </div>

                <span className={`${badgeCobertura(pedido.estado_cobertura)} ${PEDIDO_BADGE}`}>
                    {LABEL_COBERTURA[pedido.estado_cobertura] || pedido.estado_cobertura}
                </span>
            </div>

            {abierto && (
                <div className="px-4 md:px-5 pb-5 space-y-4 border-t theme-border pt-4 md:pt-5">
                    {cargando && (
                        <p className="text-xs font-medium theme-text-muted animate-pulse m-0">Cargando detalle…</p>
                    )}
                    {error && (
                        <div className="flex flex-wrap items-center gap-3">
                            <p className="text-sm text-red-600 m-0">{error}</p>
                            <button type="button" onClick={cargarDetalle} className="theme-btn-secondary theme-btn-secondary--compact text-xs">Reintentar</button>
                        </div>
                    )}
                    {detalle && (
                        <>
                            <ResumenFinancieroPedido cierre={detalle.cierre} financiero={detalle.financiero} />
                            <ListaExhibicionesPago exhibiciones={detalle.exhibiciones} />
                            <div ref={documentosRef}>
                                <DocumentosEvidencias
                                    exhibiciones={detalle.exhibiciones}
                                    documentos={detalle.documentos}
                                />
                            </div>
                        </>
                    )}
                </div>
            )}
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
