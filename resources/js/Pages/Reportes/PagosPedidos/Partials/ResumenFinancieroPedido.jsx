import React from 'react';
import {
    SEM_BADGE,
    SEM_TEXTO,
    SECCION_TITULO,
    ETIQUETA_FIN,
    IMPORTE_FIN,
    META_DETALLE,
    cardReportePagos,
    fmtFechaHora,
    fmtMxn,
    lineaResultadoCobertura,
    RADIUS_PEDIDO_CARD,
    DETALLE_PAD,
} from './pagosPedidosStyles';

const TONO_VALOR = {
    exito: SEM_TEXTO.exito,
    advertencia: SEM_TEXTO.advertencia,
    critico: SEM_TEXTO.critico,
    info: SEM_TEXTO.info,
};

function FilaConciliacion({ label, valor, tono, destacado = false, resta = false }) {
    const valorFmt = typeof valor === 'string' ? valor : (resta ? `−${fmtMxn(valor)}` : fmtMxn(valor));

    return (
        <div className="flex items-baseline justify-between gap-4 py-2 border-b border-[color-mix(in_srgb,var(--theme-border)_80%,transparent)] last:border-b-0">
            <span className={`${ETIQUETA_FIN} shrink-0 ${destacado ? 'font-semibold theme-text-main' : ''}`}>
                {label}
            </span>
            <span
                className={[
                    IMPORTE_FIN,
                    'text-right',
                    tono ? TONO_VALOR[tono] : '',
                ].filter(Boolean).join(' ')}
            >
                {valorFmt}
            </span>
        </div>
    );
}

function BloqueConciliacion({ titulo, children }) {
    return (
        <div className={cardReportePagos(DETALLE_PAD, RADIUS_PEDIDO_CARD)}>
            <h4 className={`${SECCION_TITULO} mb-3 pb-2 border-b theme-border`}>
                {titulo}
            </h4>
            <div>{children}</div>
        </div>
    );
}

export default function ResumenFinancieroPedido({ cierre, financiero }) {
    const resultado = lineaResultadoCobertura(financiero);
    const validador = cierre.validado_por?.name || '—';

    return (
        <div className="space-y-4">
            <header className="min-w-0">
                <div className="flex flex-wrap items-center gap-2 mb-1.5">
                    <h3 className={SECCION_TITULO}>Cierre financiero</h3>
                    <span className={SEM_BADGE.info}>Versión {cierre.version}</span>
                </div>
                <p className={`${META_DETALLE} m-0 leading-relaxed`}>
                    Validado por {validador}
                    {' · '}
                    {fmtFechaHora(cierre.validado_at)}
                    {' · '}
                    Tolerancia aplicada: {fmtMxn(financiero.tolerancia_aplicada)}
                </p>
            </header>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <BloqueConciliacion titulo="Composición del pedido">
                    <FilaConciliacion label="Mercancía" valor={financiero.monto_venta} />
                    <FilaConciliacion label="Envío" valor={financiero.monto_envio} />
                    <FilaConciliacion label="Seguro" valor={financiero.monto_seguro} />
                    <FilaConciliacion label="Total del pedido" valor={financiero.total_pedido} destacado />
                </BloqueConciliacion>

                <BloqueConciliacion titulo="Cobertura del pago">
                    <FilaConciliacion label="SAF aplicado" valor={financiero.saf_aplicado} resta />
                    <FilaConciliacion label="Total a cobrar" valor={financiero.total_a_cobrar} destacado />
                    <FilaConciliacion label="Pagos válidos" valor={financiero.pagos_validos} />
                    <FilaConciliacion
                        label={resultado.label}
                        valor={resultado.texto}
                        tono={resultado.tono}
                        destacado
                    />
                </BloqueConciliacion>
            </div>
        </div>
    );
}
