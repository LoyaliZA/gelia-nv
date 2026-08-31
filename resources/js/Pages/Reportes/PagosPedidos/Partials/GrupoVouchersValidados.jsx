import React, { useCallback, useEffect, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import TablaExhibicionesVouchers from './TablaExhibicionesVouchers';
import { cardReportePagos, fmtMxn, RADIUS_CONTENEDOR_DIA, CARD_PAD, FOLIO_META } from './pagosPedidosStyles';
import { CeldaFinanciera, CeldaIncidencias, GRID_FILA_PAGOS, GRID_FINANCIERO_MOVIL } from './GridFilasPagosPedidos';
import { mergeAdminEnExhibiciones } from './accionesAdminPagos';

export default function GrupoVouchersValidados({ grupo, abiertoDefault, auth, onRecargarLista }) {
    const [abierto, setAbierto] = useState(abiertoDefault);
    const [exhibiciones, setExhibiciones] = useState(grupo.exhibiciones ?? []);
    const resumen = grupo.resumen ?? {};

    useEffect(() => {
        setExhibiciones(grupo.exhibiciones ?? []);
    }, [grupo.exhibiciones]);

    const onAdminActualizado = useCallback((resp) => {
        setExhibiciones((prev) => mergeAdminEnExhibiciones(prev, resp));
    }, []);

    const onAdminExito = useCallback((resp) => {
        onAdminActualizado(resp);
        onRecargarLista?.();
    }, [onAdminActualizado, onRecargarLista]);

    return (
        <div className={cardReportePagos('overflow-hidden', RADIUS_CONTENEDOR_DIA)}>
            <button
                type="button"
                className={`${GRID_FILA_PAGOS} ${CARD_PAD} border-b theme-border hover:border-[var(--color-primario)]/30 transition-colors`}
                onClick={() => setAbierto((v) => !v)}
                aria-expanded={abierto}
            >
                <ChevronDown
                    className={`w-5 h-5 shrink-0 self-start mt-0.5 transition-transform ${abierto ? '' : '-rotate-90'}`}
                    style={{ color: 'var(--color-primario)' }}
                />
                <div className="min-w-0">
                    <p className="text-base font-semibold theme-text-main m-0 leading-snug">{grupo.label}</p>
                    <p className={`${FOLIO_META} font-medium m-0 mt-1`}>
                        {resumen.vouchers ?? 0} vouchers · {resumen.pedidos ?? 0} pedidos
                        {resumen.periodo && (
                            <> · {resumen.periodo.desde} — {resumen.periodo.hasta}</>
                        )}
                    </p>
                </div>
                <div className={GRID_FINANCIERO_MOVIL}>
                    <CeldaFinanciera label="Validado" valor={fmtMxn(resumen.total_validado)} />
                    <CeldaFinanciera label="Posterior" valor={String(resumen.reportados_posteriormente ?? 0)} />
                    <CeldaFinanciera label="Duplicados" valor={String(resumen.posibles_duplicados ?? 0)} />
                    <CeldaIncidencias count={resumen.observaciones ?? 0} />
                </div>
            </button>
            {abierto && (
                <div className={`${CARD_PAD} pt-3 bg-black/[0.02]`}>
                    <TablaExhibicionesVouchers
                        exhibiciones={exhibiciones}
                        auth={auth}
                        onActualizado={onAdminExito}
                    />
                </div>
            )}
        </div>
    );
}
