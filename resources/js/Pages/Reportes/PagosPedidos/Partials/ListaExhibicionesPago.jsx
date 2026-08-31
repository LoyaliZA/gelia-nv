import React, { useState } from 'react';
import { ImageIcon } from 'lucide-react';
import ModalVisorArchivo, { payloadArchivoVoucher } from '@/Components/ModalVisorArchivo';
import { exhibicionesConEvidencia, payloadVisorEnIndice } from '@/utils/visorEvidenciasReporte';
import {
    badgeCoberturaExhibicion,
    fmtMxn,
    labelRevisionEstado,
    tonoRevisionEstado,
    SECCION_TITULO,
    META_DETALLE,
    IMPORTE_FIN,
    SEM_BADGE,
} from './pagosPedidosStyles';

const TH = 'text-xs font-semibold uppercase tracking-wide theme-text-main/75 py-3 pr-3 text-left';
const TD = 'text-xs md:text-[13px] theme-text-main py-3 pr-3 align-middle';
const TR = 'border-b border-[color-mix(in_srgb,var(--theme-border)_85%,transparent)] min-h-[44px] md:min-h-[48px]';
const META = `block ${META_DETALLE} leading-snug mt-0.5`;
const BTN_VOUCHER =
    'inline-flex items-center justify-center gap-1.5 min-h-9 px-2.5 rounded-lg border theme-border theme-element text-[11px] md:text-xs font-semibold theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0';

function CeldaReferencia({ ex }) {
    return (
        <div className="min-w-0">
            <span className="font-medium tabular-nums">{ex.referencia || '—'}</span>
            {ex.capturado_por && (
                <span className={META}>Reportado por: {ex.capturado_por}</span>
            )}
        </div>
    );
}

function CeldaFechas({ ex }) {
    return (
        <div className="min-w-0 space-y-0.5">
            <span className={META}>
                Movimiento: {ex.fecha_pago_label}
            </span>
            <span className={META}>
                Reportado: {ex.capturado_at_label}
            </span>
            <span className={META}>
                Validado: {ex.validado_at_label}
            </span>
            {ex.reportado_posteriormente && (
                <span className={`${SEM_BADGE.advertencia} mt-1`}>Reportado posteriormente</span>
            )}
        </div>
    );
}

function CeldaRevision({ ex }) {
    return (
        <div className="min-w-0">
            <span className={`font-medium ${tonoRevisionEstado(ex.estado_revision)}`}>
                {labelRevisionEstado(ex.estado_revision)}
            </span>
            {ex.revisado_por && (
                <span className={META}>Revisado por: {ex.revisado_por}</span>
            )}
        </div>
    );
}

function AccionVoucher({ ex, conEvidencia, onAbrirIndice }) {
    const tieneUrl = Boolean(ex.evidencia?.url);
    const indice = conEvidencia.findIndex((item) => item.id === ex.id);

    return (
        <button
            type="button"
            className={BTN_VOUCHER}
            disabled={!tieneUrl}
            onClick={() => tieneUrl && onAbrirIndice(indice)}
            title={tieneUrl ? `Ver comprobante — exhibición #${ex.numero_exhibicion}` : 'Sin comprobante adjunto'}
        >
            <ImageIcon className="w-4 h-4 shrink-0" aria-hidden="true" />
            <span className="hidden lg:inline">Ver voucher</span>
        </button>
    );
}

function TarjetaExhibicionMovil({ ex, exhibiciones, conEvidencia, onAbrirIndice }) {
    const cobertura = badgeCoberturaExhibicion(ex, exhibiciones);

    return (
        <div className={`${TR} p-4 rounded-lg border theme-border theme-element space-y-3`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-semibold uppercase theme-text-muted m-0">
                        Exhibición #{ex.numero_exhibicion}
                    </p>
                    <p className="text-sm font-semibold theme-text-main tabular-nums m-0 mt-1">
                        {fmtMxn(ex.monto)}
                    </p>
                </div>
                <span className={cobertura.badge}>{cobertura.label}</span>
            </div>
            <p className="text-xs md:text-[13px] theme-text-main m-0">
                {ex.forma_pago_label} · {ex.banco || '—'}
            </p>
            <CeldaReferencia ex={ex} />
            <CeldaFechas ex={ex} />
            <CeldaRevision ex={ex} />
            <AccionVoucher ex={ex} conEvidencia={conEvidencia} onAbrirIndice={onAbrirIndice} />
        </div>
    );
}

export default function ListaExhibicionesPago({ exhibiciones = [] }) {
    const [visorIdx, setVisorIdx] = useState(null);
    const conEvidencia = exhibicionesConEvidencia(exhibiciones);
    const visor = payloadVisorEnIndice(exhibiciones, visorIdx);

    if (!exhibiciones.length) {
        return <p className="text-xs md:text-[13px] theme-text-muted m-0">Sin exhibiciones registradas.</p>;
    }

    return (
        <div className="space-y-3 min-w-0">
            <p className={SECCION_TITULO}>Exhibiciones de pago</p>

            <div className="space-y-3 md:hidden">
                {exhibiciones.map((ex) => (
                    <TarjetaExhibicionMovil
                        key={ex.id}
                        ex={ex}
                        exhibiciones={exhibiciones}
                        conEvidencia={conEvidencia}
                        onAbrirIndice={setVisorIdx}
                    />
                ))}
            </div>

            <div className="hidden md:block overflow-x-auto -mx-1 px-1">
                <table className="w-full min-w-[52rem] text-left border-collapse">
                    <thead>
                        <tr className="border-b theme-border">
                            <th className={TH}>#</th>
                            <th className={TH}>Monto</th>
                            <th className={TH}>Forma</th>
                            <th className={TH}>Banco</th>
                            <th className={TH}>Referencia</th>
                            <th className={TH}>Fechas</th>
                            <th className={TH}>Revisión</th>
                            <th className={TH}>Cobertura</th>
                            <th className={`${TH} pr-0`}>Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        {exhibiciones.map((ex) => {
                            const cobertura = badgeCoberturaExhibicion(ex, exhibiciones);
                            return (
                                <tr key={ex.id} className={TR}>
                                    <td className={`${TD} font-mono tabular-nums`}>{ex.numero_exhibicion}</td>
                                    <td className={`${TD} ${IMPORTE_FIN}`}>{fmtMxn(ex.monto)}</td>
                                    <td className={TD}>{ex.forma_pago_label}</td>
                                    <td className={TD}>{ex.banco || '—'}</td>
                                    <td className={TD}>
                                        <CeldaReferencia ex={ex} />
                                    </td>
                                    <td className={TD}>
                                        <CeldaFechas ex={ex} />
                                    </td>
                                    <td className={TD}>
                                        <CeldaRevision ex={ex} />
                                    </td>
                                    <td className={TD}>
                                        <span className={cobertura.badge}>{cobertura.label}</span>
                                    </td>
                                    <td className={`${TD} pr-0`}>
                                        <AccionVoucher ex={ex} conEvidencia={conEvidencia} onAbrirIndice={setVisorIdx} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <ModalVisorArchivo
                abierto={visorIdx != null}
                onCerrar={() => setVisorIdx(null)}
                url={visor?.url}
                mimeType={visor?.mimeType}
                titulo={visor?.titulo}
                subtitulo={visor?.subtitulo}
                metadatos={visor?.metadatos}
                indiceActual={visorIdx ?? 0}
                totalItems={conEvidencia.length}
                onAnterior={() => setVisorIdx((i) => Math.max(0, (i ?? 0) - 1))}
                onSiguiente={() => setVisorIdx((i) => Math.min(conEvidencia.length - 1, (i ?? 0) + 1))}
            />
        </div>
    );
}
