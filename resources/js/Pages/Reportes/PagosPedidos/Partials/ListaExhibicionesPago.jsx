import React, { useState } from 'react';
import { ImageIcon } from 'lucide-react';
import ModalVisorArchivo, { payloadArchivoVoucher } from '@/Components/ModalVisorArchivo';
import {
    badgeCoberturaExhibicion,
    fmtFechaSolo,
    fmtHoraSolo,
    fmtMxn,
    labelRevisionEstado,
    tonoRevisionEstado,
    SECCION_TITULO,
    META_DETALLE,
    IMPORTE_FIN,
} from './pagosPedidosStyles';

const TH = 'text-xs font-semibold uppercase tracking-wide theme-text-main/75 py-3 pr-3 text-left';
const TD = 'text-xs md:text-[13px] theme-text-main py-3 pr-3 align-middle';
const TR = 'border-b border-[color-mix(in_srgb,var(--theme-border)_85%,transparent)] min-h-[44px] md:min-h-[48px]';
const META = `block ${META_DETALLE} leading-snug mt-0.5`;
const BTN_VOUCHER =
    'inline-flex items-center justify-center gap-1.5 min-h-9 px-2.5 rounded-lg border theme-border theme-element text-[11px] md:text-xs font-semibold theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0';

function CeldaReferencia({ ex }) {
    const fecha = fmtFechaSolo(ex.fecha_pago);
    return (
        <div className="min-w-0">
            <span className="font-medium tabular-nums">{ex.referencia || '—'}</span>
            {fecha && <span className={META}>{fecha}</span>}
            {ex.capturado_por && (
                <span className={META}>Capturó: {ex.capturado_por}</span>
            )}
        </div>
    );
}

function CeldaRevision({ ex }) {
    const hora = fmtHoraSolo(ex.revisado_at);
    return (
        <div className="min-w-0">
            <span className={`font-medium ${tonoRevisionEstado(ex.estado_revision)}`}>
                {labelRevisionEstado(ex.estado_revision)}
            </span>
            {(ex.revisado_por || hora) && (
                <span className={META}>
                    {[ex.revisado_por, hora].filter(Boolean).join(' · ')}
                </span>
            )}
            {!ex.revisado_por && !hora && ex.capturado_at && !ex.revisado_at && (
                <span className={META}>Sin revisión</span>
            )}
        </div>
    );
}

function AccionVoucher({ ex, onVer }) {
    const tieneUrl = Boolean(ex.evidencia?.url);

    return (
        <button
            type="button"
            className={BTN_VOUCHER}
            disabled={!tieneUrl}
            onClick={() => tieneUrl && onVer(payloadArchivoVoucher(ex))}
            title={tieneUrl ? `Ver comprobante — exhibición #${ex.numero_exhibicion}` : 'Sin comprobante adjunto'}
        >
            <ImageIcon className="w-4 h-4 shrink-0" aria-hidden="true" />
            <span className="hidden lg:inline">Ver voucher</span>
        </button>
    );
}

function TarjetaExhibicionMovil({ ex, exhibiciones, onVer }) {
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
            <CeldaRevision ex={ex} />
            <AccionVoucher ex={ex} onVer={onVer} />
        </div>
    );
}

export default function ListaExhibicionesPago({ exhibiciones = [] }) {
    const [visor, setVisor] = useState(null);

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
                        onVer={setVisor}
                    />
                ))}
            </div>

            <div className="hidden md:block overflow-x-auto -mx-1 px-1">
                <table className="w-full min-w-[44rem] text-left border-collapse">
                    <thead>
                        <tr className="border-b theme-border">
                            <th className={TH}>#</th>
                            <th className={TH}>Monto</th>
                            <th className={TH}>Forma</th>
                            <th className={TH}>Banco</th>
                            <th className={TH}>Referencia</th>
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
                                        <CeldaRevision ex={ex} />
                                    </td>
                                    <td className={TD}>
                                        <span className={cobertura.badge}>{cobertura.label}</span>
                                    </td>
                                    <td className={`${TD} pr-0`}>
                                        <AccionVoucher ex={ex} onVer={setVisor} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <ModalVisorArchivo
                abierto={Boolean(visor)}
                onCerrar={() => setVisor(null)}
                url={visor?.url}
                mimeType={visor?.mimeType}
                titulo={visor?.titulo}
                subtitulo={visor?.subtitulo}
            />
        </div>
    );
}
