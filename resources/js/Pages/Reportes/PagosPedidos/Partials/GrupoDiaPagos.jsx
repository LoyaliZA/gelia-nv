import React from 'react';
import { ChevronDown } from 'lucide-react';
import PedidoPagoAcordeon from './PedidoPagoAcordeon';
import { cardReportePagos, fmtMxn, fmtPedidosValidados, RADIUS_CONTENEDOR_DIA } from './pagosPedidosStyles';
import { CeldaFinanciera, CeldaIncidencias, GRID_FILA_PAGOS, GRID_FINANCIERO_MOVIL } from './GridFilasPagosPedidos';

export default function GrupoDiaPagos({ grupo, abiertoDefault, cacheDetalle, onCacheDetalle }) {
    const [abierto, setAbierto] = React.useState(abiertoDefault);
    const incidencias = Number(grupo.resumen.observaciones ?? 0);

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
                    <p className="text-base font-semibold theme-text-main m-0 leading-snug">{grupo.fecha_label}</p>
                    <p className="text-xs md:text-[13px] font-medium theme-text-muted m-0 mt-1">
                        {fmtPedidosValidados(grupo.resumen.pedidos)}
                    </p>
                </div>
                <div className={GRID_FINANCIERO_MOVIL}>
                    <CeldaFinanciera label="Venta" valor={fmtMxn(grupo.resumen.monto_venta)} />
                    <CeldaFinanciera label="Pagos" valor={fmtMxn(grupo.resumen.pagos_validos)} />
                    <CeldaFinanciera label="SAF" valor={fmtMxn(grupo.resumen.saf_aplicado)} />
                    <CeldaIncidencias count={incidencias} />
                </div>
            </button>
            {abierto && (
                <div className="p-3 md:p-4 pt-2 space-y-2 bg-black/[0.02]">
                    {grupo.pedidos.map((pedido) => (
                        <PedidoPagoAcordeon
                            key={pedido.cierre_id}
                            pedido={pedido}
                            cacheDetalle={cacheDetalle}
                            onCacheDetalle={onCacheDetalle}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
