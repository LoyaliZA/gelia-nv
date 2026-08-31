import React from 'react';
import { ImageIcon } from 'lucide-react';
import {
    fmtMxn,
    SEM_BADGE,
    IMPORTE_FIN,
    BTN_NEUTRAL,
    BTN_ICON,
    TABLA_TEXTO,
    TABLA_TH,
    TABLA_META,
} from './pagosPedidosStyles';
import {
    EtiquetaCoberturaExhibicion,
    EtiquetaValidacionPago,
} from './EtiquetaEstadoCategorizado';

export const TH_EXH = `${TABLA_TH} py-3.5 pr-3 text-left`;
export const TD_EXH = `${TABLA_TEXTO} py-4 pr-3 align-middle`;
export const TD_EXH_MULTILINEA = `${TD_EXH} py-4.5 md:py-5`;
export const TR_EXH = 'border-b border-[color-mix(in_srgb,var(--theme-border)_85%,transparent)] min-h-[52px] md:min-h-[58px]';
const META = `block ${TABLA_META} mt-1 first:mt-0`;

export function CeldaPago({ ex }) {
    return (
        <div className="min-w-0">
            <span className={IMPORTE_FIN}>{fmtMxn(ex.monto)}</span>
            <span className={META}>{ex.forma_pago_label || '—'}</span>
        </div>
    );
}

export function CeldaBancoReferencia({ ex }) {
    const reportadoPor = ex.capturado_por || ex.reportado_por;

    return (
        <div className="min-w-0">
            <span className="font-medium">{ex.banco || '—'}</span>
            <span className={`${META} tabular-nums`}>{ex.referencia || '—'}</span>
            {reportadoPor && (
                <span className={META}>Reportado por: {reportadoPor}</span>
            )}
        </div>
    );
}

export function CeldaFechasExhibicion({ ex }) {
    return (
        <div className="min-w-0 space-y-1.5">
            <span className={META}>Movimiento: {ex.fecha_pago_label}</span>
            <span className={META}>Reportado: {ex.capturado_at_label}</span>
            <span className={META}>Validado: {ex.validado_at_label}</span>
            {ex.reportado_posteriormente && (
                <span className={`${SEM_BADGE.advertencia} mt-1`}>Reportado posteriormente</span>
            )}
        </div>
    );
}

function IndicadoresValidacion({ ex }) {
    const items = [];
    if (ex.posible_duplicado) items.push({ label: 'Posible duplicado', cls: SEM_BADGE.advertencia });
    if (ex.sin_voucher) items.push({ label: 'Sin voucher', cls: SEM_BADGE.neutro });
    if (ex.con_saf_relacionado) items.push({ label: 'Con SAF relacionado', cls: SEM_BADGE.info });
    if (items.length === 0) return null;

    return (
        <div className="flex flex-wrap gap-1 mt-1">
            {items.map((i) => (
                <span key={i.label} className={i.cls}>{i.label}</span>
            ))}
        </div>
    );
}

export function CeldaValidacionExhibicion({ ex }) {
    const validadoPor = ex.validado_por || ex.revisado_por;

    return (
        <div className="min-w-0 space-y-1.5">
            <EtiquetaValidacionPago
                estado={ex.estado_revision}
                valor={ex.estado_validacion_label}
            />
            {validadoPor && (
                <span className={META}>Validado por: {validadoPor}</span>
            )}
            {ex.observaciones && (
                <span className={META}>{ex.observaciones}</span>
            )}
            <IndicadoresValidacion ex={ex} />
        </div>
    );
}

export function CeldaCoberturaExhibicion({ ex, exhibiciones }) {
    return <EtiquetaCoberturaExhibicion ex={ex} exhibiciones={exhibiciones} />;
}

export function BotonEvidenciaVoucher({ ex, conEvidencia, onAbrirIndice }) {
    const tieneUrl = Boolean(ex.evidencia?.url);
    const indice = conEvidencia.findIndex((item) => item.id === ex.id);

    return (
        <button
            type="button"
            className={BTN_NEUTRAL}
            disabled={!tieneUrl}
            onClick={() => tieneUrl && onAbrirIndice(indice)}
            title={tieneUrl ? `Ver comprobante — exhibición #${ex.numero_exhibicion}` : 'Sin comprobante adjunto'}
        >
            <ImageIcon className={BTN_ICON} aria-hidden="true" />
            <span className="hidden lg:inline">Ver voucher</span>
        </button>
    );
}
