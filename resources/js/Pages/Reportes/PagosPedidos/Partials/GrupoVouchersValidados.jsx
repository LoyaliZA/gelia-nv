import React from 'react';
import { ChevronDown } from 'lucide-react';
import TablaExhibicionesVouchers from './TablaExhibicionesVouchers';
import { cardReportePagos, fmtMxn, RADIUS_CONTENEDOR_DIA } from './pagosPedidosStyles';
import { CeldaFinanciera, CeldaIncidencias, GRID_FILA_PAGOS, GRID_FINANCIERO_MOVIL } from './GridFilasPagosPedidos';

export default function GrupoVouchersValidados({ grupo, abiertoDefault, auth, onRecargarLista }) {
    const [abierto, setAbierto] = React.useState(abiertoDefault);
    const resumen = grupo.resumen ?? {};

    return (
        <div className={cardReportePagos('overflow-hidden', RADIUS_CONTENEDOR_DIA)}>
            <button
                type="button"
                className={`${GRID_FILA_PAGOS} p-5 md:p-6 border-b theme-border hover:border-[var(--color-primario)]/30 transition-colors`}
                onClick={() => setAbierto((v) => !v)}
                aria-expanded={abierto}
            >
                <ChevronDown
                    className={`w-5 h-5 shrink-0 self-start mt-0.5 transition-transform ${abierto ? '' : '-rotate-90'}`}
                    style={{ color: 'var(--color-primario)' }}
                />
                <div className="min-w-0">
                    <p className="text-base font-semibold theme-text-main m-0 leading-snug">{grupo.label}</p>
                    <p className="text-xs md:text-[13px] font-medium theme-text-muted m-0 mt-1">
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
                <div className="p-3 md:p-4 pt-2 bg-black/[0.02]">
                    <TablaExhibicionesVouchers
                        exhibiciones={grupo.exhibiciones}
                        auth={auth}
                        onRecargarLista={onRecargarLista}
                    />
                </div>
            )}
        </div>
    );
}
