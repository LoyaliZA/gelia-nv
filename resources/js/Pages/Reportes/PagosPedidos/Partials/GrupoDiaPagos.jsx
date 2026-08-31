import React, { useEffect, useRef } from 'react';
import { ChevronDown } from 'lucide-react';
import PedidoPagoAcordeon from './PedidoPagoAcordeon';
import {
    cardReportePagos,
    fmtMxn,
    fmtPedidosValidados,
    RADIUS_CONTENEDOR_DIA,
    CARD_PAD,
    BLOQUE_GAP,
    FOLIO_META,
    ACORDEON_CONTENIDO_INNER,
    acordeonContenidoGridClass,
    scrollAlExpandirAcordeon,
} from './pagosPedidosStyles';
import { CeldaFinanciera, CeldaIncidencias, GRID_FILA_PAGOS, GRID_FINANCIERO_MOVIL } from './GridFilasPagosPedidos';

export default function GrupoDiaPagos({ grupo, abiertoDefault = false, auth, cacheDetalle, onCacheDetalle, onRecargarLista }) {
    const [abierto, setAbierto] = React.useState(abiertoDefault);
    const [cierreAbiertoId, setCierreAbiertoId] = React.useState(null);
    const cardRef = useRef(null);
    const abiertoPrevioRef = useRef(abiertoDefault);
    const incidencias = Number(grupo.resumen.observaciones ?? 0);

    useEffect(() => {
        if (abierto && !abiertoPrevioRef.current) {
            scrollAlExpandirAcordeon(cardRef.current);
        }
        abiertoPrevioRef.current = abierto;
    }, [abierto]);

    const toggleGrupo = () => {
        setAbierto((v) => {
            if (v) setCierreAbiertoId(null);
            return !v;
        });
    };

    const onPedidoAbiertoChange = (cierreId, nextAbierto) => {
        setCierreAbiertoId(nextAbierto ? cierreId : null);
    };

    return (
        <div ref={cardRef} className={cardReportePagos('overflow-hidden scroll-mt-24', RADIUS_CONTENEDOR_DIA)}>
            <button
                type="button"
                className={`${GRID_FILA_PAGOS} ${CARD_PAD} border-b theme-border hover:border-[var(--color-primario)]/30 transition-colors`}
                onClick={toggleGrupo}
                aria-expanded={abierto}
            >
                <ChevronDown
                    className={`w-5 h-5 shrink-0 self-start mt-0.5 transition-transform duration-300 ease-out ${abierto ? '' : '-rotate-90'}`}
                    style={{ color: 'var(--color-primario)' }}
                />
                <div className="min-w-0">
                    <p className="text-base font-semibold theme-text-main m-0 leading-snug">{grupo.fecha_label}</p>
                    <p className={`${FOLIO_META} font-medium m-0 mt-1`}>
                        {fmtPedidosValidados(grupo.resumen.pedidos)}
                    </p>
                </div>
                <div className={GRID_FINANCIERO_MOVIL}>
                    <CeldaFinanciera label="Remisiones" valor={fmtMxn(grupo.resumen.total_remisiones)} />
                    <CeldaFinanciera label="SAF" valor={fmtMxn(grupo.resumen.saf_aplicado)} />
                    <CeldaFinanciera label="Pagos" valor={fmtMxn(grupo.resumen.pagos_validos)} />
                    <CeldaIncidencias count={incidencias} />
                </div>
            </button>
            <div
                className={acordeonContenidoGridClass(abierto)}
                aria-hidden={!abierto}
            >
                <div className={ACORDEON_CONTENIDO_INNER}>
                    <div className={`${CARD_PAD} pt-3 ${BLOQUE_GAP} bg-black/[0.02]`}>
                        {grupo.pedidos.map((pedido) => (
                            <PedidoPagoAcordeon
                                key={pedido.cierre_id}
                                pedido={pedido}
                                auth={auth}
                                cacheDetalle={cacheDetalle}
                                onCacheDetalle={onCacheDetalle}
                                onRecargarLista={onRecargarLista}
                                abierto={cierreAbiertoId === pedido.cierre_id}
                                onAbiertoChange={(nextAbierto) => onPedidoAbiertoChange(pedido.cierre_id, nextAbierto)}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
