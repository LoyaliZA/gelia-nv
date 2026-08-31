import React, { useState } from 'react';
import { Download, FileText, ZoomIn } from 'lucide-react';
import ModalVisorArchivo, { payloadArchivoRemision } from '@/Components/ModalVisorArchivo';
import { exhibicionesConEvidencia, payloadVisorEnIndice } from '@/utils/visorEvidenciasReporte';
import {
    DETALLE_PAD,
    SECCION_TITULO,
    META_DETALLE,
    RADIUS_PEDIDO_CARD,
    cardReportePagos,
    fmtFechaSolo,
    fmtMxn,
    fmtTamanoArchivo,
    fmtTipoArchivo,
    BTN_NEUTRAL,
    BTN_ICON,
    GRUPO_ACCIONES,
} from './pagosPedidosStyles';
import {
    EtiquetaCoberturaExhibicion,
    EtiquetaValidacionPago,
} from './EtiquetaEstadoCategorizado';

function TarjetaRemision({ remision, folioPedido, folioRemision, onVer }) {
    if (!remision) {
        return (
            <div className={`${cardReportePagos(DETALLE_PAD, RADIUS_PEDIDO_CARD)} h-full`}>
                <h4 className={SECCION_TITULO}>Remisión</h4>
                <p className={`${META_DETALLE} mt-3 m-0`}>Remisión pendiente o no disponible en este cierre.</p>
            </div>
        );
    }

    const tipo = fmtTipoArchivo(remision.mime_type, remision.nombre);
    const tamano = fmtTamanoArchivo(remision.tamano_bytes);
    const fecha = fmtFechaSolo(remision.created_at);
    const meta = [tipo, tamano, fecha].filter(Boolean).join(' · ');
    const puedeAbrir = Boolean(remision.url);

    return (
        <div className={`${cardReportePagos(DETALLE_PAD, RADIUS_PEDIDO_CARD)} h-full flex flex-col`}>
            <h4 className={SECCION_TITULO}>Remisión</h4>

            <div
                className="mt-3 mb-3 rounded-lg border theme-border theme-element flex items-center justify-center h-36 md:h-40"
                style={{ backgroundColor: 'color-mix(in srgb, var(--theme-element-bg) 60%, transparent)' }}
            >
                <FileText className="w-12 h-12 theme-text-muted opacity-70" aria-hidden="true" />
            </div>

            <p className="text-sm font-semibold theme-text-main m-0">Remisión</p>
            {(folioPedido || folioRemision) && (
                <p className="text-xs theme-text-main/80 m-0 mt-1">
                    {folioPedido ? `Pedido ${folioPedido}` : ''}
                    {folioPedido && folioRemision ? ' · ' : ''}
                    {folioRemision ? `Remisión ${folioRemision}` : ''}
                </p>
            )}
            <p className="text-xs theme-text-main/70 m-0 mt-1 truncate" title={remision.nombre}>
                {remision.nombre || 'Documento'}
            </p>
            {meta && <p className={`${META_DETALLE} m-0 mt-1`}>{meta}</p>}

            <div className={`${GRUPO_ACCIONES} mt-auto pt-4`}>
                <button
                    type="button"
                    className={BTN_NEUTRAL}
                    disabled={!puedeAbrir}
                    onClick={() => puedeAbrir && onVer(payloadArchivoRemision(remision, { folio_pedido: folioPedido, folio_remision: folioRemision }))}
                >
                    Ver
                </button>
                {puedeAbrir ? (
                    <a href={remision.url} download={remision.nombre || undefined} className={BTN_NEUTRAL}>
                        <Download className={BTN_ICON} aria-hidden="true" />
                        Descargar
                    </a>
                ) : (
                    <span className={`${META_DETALLE} self-center`}>Requiere permiso de evidencias</span>
                )}
            </div>
        </div>
    );
}

function MiniaturaVoucher({ ex, exhibiciones, conEvidencia, onAbrirIndice }) {
    const esImagen = ex.evidencia?.mime_type?.startsWith('image/');

    return (
        <button
            type="button"
            onClick={() => {
                const idx = conEvidencia.findIndex((item) => item.id === ex.id);
                if (idx >= 0) onAbrirIndice(idx);
            }}
            className="group w-full max-w-[220px] text-left rounded-xl border theme-border theme-element overflow-hidden hover:border-[var(--color-primario)] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
        >
            <div className="relative h-[140px] flex items-center justify-center p-2 bg-[color-mix(in_srgb,var(--theme-element-bg)_55%,transparent)]">
                {esImagen ? (
                    <img
                        src={ex.evidencia.url}
                        alt=""
                        loading="lazy"
                        className="max-w-full max-h-full object-contain"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-1 theme-text-muted">
                        <FileText className="w-10 h-10 opacity-60" />
                        <span className="text-xs font-semibold">PDF</span>
                    </div>
                )}
                <span className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                    <ZoomIn className="w-8 h-8 text-white drop-shadow" aria-hidden="true" />
                </span>
            </div>
            <div className="p-3 space-y-1 border-t theme-border">
                <p className="text-xs font-semibold theme-text-main m-0 tabular-nums">
                    Exhibición {ex.numero_exhibicion} · {fmtMxn(ex.monto)}
                </p>
                <p className={`${META_DETALLE} m-0`}>
                    {ex.forma_pago_label}{ex.banco ? ` · ${ex.banco}` : ''}
                </p>
                <div className="flex flex-wrap gap-3 pt-1">
                    <EtiquetaValidacionPago estado={ex.estado_revision} />
                    <EtiquetaCoberturaExhibicion ex={ex} exhibiciones={exhibiciones} />
                </div>
            </div>
        </button>
    );
}

function ColumnaVouchers({ exhibiciones, todasExhibiciones, onAbrirIndice }) {
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const n = conEvidencia.length;

    return (
        <div className={`${cardReportePagos(DETALLE_PAD, RADIUS_PEDIDO_CARD)} h-full flex flex-col min-w-0`}>
            <h4 className={SECCION_TITULO}>Vouchers ({n})</h4>

            {n === 0 ? (
                <p className={`${META_DETALLE} mt-3 m-0`}>Sin comprobantes adjuntos con acceso de visualización.</p>
            ) : (
                <div className="mt-3 grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-4 justify-items-start">
                    {conEvidencia.map((ex) => (
                        <MiniaturaVoucher
                            key={ex.id}
                            ex={ex}
                            exhibiciones={todasExhibiciones}
                            conEvidencia={conEvidencia}
                            onAbrirIndice={onAbrirIndice}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function DocumentosEvidencias({ exhibiciones = [], documentos = {}, folioPedido, folioRemision }) {
    const [visorIdx, setVisorIdx] = useState(null);
    const [visorRemision, setVisorRemision] = useState(null);
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const visorVoucher = payloadVisorEnIndice(exhibiciones, visorIdx);
    const remision = documentos.remision_vigente;

    return (
        <section className="space-y-4 min-w-0">
            <h3 className={SECCION_TITULO}>Documentos y evidencias</h3>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
                <TarjetaRemision
                    remision={remision}
                    folioPedido={folioPedido}
                    folioRemision={folioRemision}
                    onVer={setVisorRemision}
                />
                <ColumnaVouchers
                    exhibiciones={exhibiciones}
                    todasExhibiciones={exhibiciones}
                    onAbrirIndice={setVisorIdx}
                />
            </div>

            <ModalVisorArchivo
                abierto={visorIdx != null}
                onCerrar={() => setVisorIdx(null)}
                url={visorVoucher?.url}
                mimeType={visorVoucher?.mimeType}
                titulo={visorVoucher?.titulo}
                subtitulo={visorVoucher?.subtitulo}
                metadatos={visorVoucher?.metadatos}
                descargarUrl={visorVoucher?.url}
                indiceActual={visorIdx ?? 0}
                totalItems={conEvidencia.length}
                onAnterior={() => setVisorIdx((i) => Math.max(0, (i ?? 0) - 1))}
                onSiguiente={() => setVisorIdx((i) => Math.min(conEvidencia.length - 1, (i ?? 0) + 1))}
            />

            <ModalVisorArchivo
                abierto={Boolean(visorRemision)}
                onCerrar={() => setVisorRemision(null)}
                url={visorRemision?.url}
                mimeType={visorRemision?.mimeType}
                titulo={visorRemision?.titulo}
                subtitulo={visorRemision?.subtitulo}
                metadatos={visorRemision?.metadatos}
                descargarUrl={visorRemision?.url}
            />
        </section>
    );
}
