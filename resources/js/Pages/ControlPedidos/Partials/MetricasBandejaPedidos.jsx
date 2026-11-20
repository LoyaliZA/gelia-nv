import React, { useMemo } from 'react';
import {
    ClipboardList, FileText, Layers, MessageSquare, PackageX, Scale, Send, Trash2, Truck, UserCheck, Warehouse, XCircle,
} from 'lucide-react';
import { gruposBandejas } from './bandejasPedidos';

const ICONO_BANDEJA = {
    PESAJE_PENDIENTE: Scale,
    PESAJE_RESPONDIDO: MessageSquare,
    RECHAZADAS: XCircle,
    OBS_CEDIS: ClipboardList,
    SIN_EXISTENCIA: PackageX,
    PENDIENTE_AUXILIAR: UserCheck,
    EN_CEDIS: Warehouse,
    PENDIENTE_GUIA_CLIENTE: Truck,
    BORRADORES: FileText,
    ENVIADOS: Send,
    TODAS: Layers,
    ELIMINADAS: Trash2,
};

const TONO_ICONO = {
    aviso: 'theme-text-aviso',
    error: 'theme-text-peligro',
    info: 'theme-text-primario',
};

const FOCO = 'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

const formatear = (valor) => new Intl.NumberFormat('es-MX').format(valor ?? 0);

/** Tarjeta de indicador: icono por bandeja, número legible y tono solo si hay pendientes. */
function TarjetaBandeja({ bandeja, conteo, activo, onElegir }) {
    const valor = conteo ?? 0;
    const Icono = ICONO_BANDEJA[bandeja.id] || Layers;
    const tonoIcono = valor > 0 ? TONO_ICONO[bandeja.tono] : null;

    return (
        <button
            type="button"
            aria-pressed={activo}
            data-active={activo ? 'true' : 'false'}
            onClick={() => onElegir(bandeja.id)}
            className={`min-w-0 min-h-[64px] rounded-xl border px-3 py-2.5 text-left flex items-center gap-3 theme-surface-solid theme-no-blur transition-colors ${FOCO} ${
                activo
                    ? 'border-[color:var(--color-primario)] bg-[color:color-mix(in_srgb,var(--color-primario)_10%,var(--theme-surface-solid))] ring-1 ring-[var(--color-primario)]'
                    : 'theme-border hover:border-[color:color-mix(in_srgb,var(--color-primario)_45%,var(--theme-border))]'
            }`}
        >
            <span className="gelia-pedidos-icono !w-9 !h-9" aria-hidden>
                <Icono className={`w-[18px] h-[18px] ${tonoIcono || ''}`} />
            </span>
            <span className="min-w-0 flex flex-col">
                <span className="text-xl font-bold leading-none tabular-nums theme-text-main">{formatear(valor)}</span>
                <span className={`mt-1 text-xs leading-snug ${activo ? 'font-bold theme-text-main' : 'font-semibold theme-text-muted'}`}>
                    {bandeja.label}
                </span>
            </span>
        </button>
    );
}

/** Vista secundaria: pestaña compacta con conteo, para listados de consulta. */
function PestanaBandeja({ bandeja, conteo, activo, onElegir }) {
    const Icono = ICONO_BANDEJA[bandeja.id] || Layers;

    return (
        <button
            type="button"
            aria-pressed={activo}
            data-active={activo ? 'true' : 'false'}
            onClick={() => onElegir(bandeja.id)}
            className={`shrink-0 inline-flex items-center gap-2 min-h-[44px] md:min-h-[36px] px-3 rounded-full border text-sm font-semibold transition-colors ${FOCO} ${
                activo
                    ? 'border-[color:color-mix(in_srgb,var(--color-primario)_55%,var(--theme-border))] bg-[color:color-mix(in_srgb,var(--color-primario)_10%,var(--theme-element-bg))] theme-text-main'
                    : 'theme-border theme-element theme-text-muted hover:theme-text-main'
            }`}
        >
            <Icono className="w-4 h-4 shrink-0" aria-hidden />
            <span className="whitespace-nowrap">{bandeja.label}</span>
            <span className="min-w-[1.5rem] px-1.5 py-0.5 rounded-full text-xs font-bold tabular-nums text-center bg-black/5 dark:bg-white/10 theme-text-muted">
                {formatear(conteo)}
            </span>
        </button>
    );
}

export default function MetricasBandejaPedidos({ metricas = {}, tabActiva, onTabChange, incluirEliminadas = false }) {
    const grupos = useMemo(() => gruposBandejas({ incluirEliminadas }), [incluirEliminadas]);

    return (
        <nav className="gelia-pedidos-bma-metricas min-w-0 space-y-4" aria-label="Bandejas de pedidos">
            {grupos.map((grupo) => {
                const comoTarjetas = grupo.key !== 'cerrados';
                return (
                    <div key={grupo.key} role="group" aria-label={grupo.label} className="min-w-0">
                        <p className="m-0 mb-2 text-xs font-bold uppercase tracking-wide theme-text-muted">{grupo.label}</p>
                        {comoTarjetas ? (
                            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-2 min-w-0">
                                {grupo.bandejas.map((bandeja) => (
                                    <TarjetaBandeja
                                        key={bandeja.id}
                                        bandeja={bandeja}
                                        conteo={metricas?.[bandeja.metrica]}
                                        activo={tabActiva === bandeja.id}
                                        onElegir={onTabChange}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="flex gap-2 overflow-x-auto md:flex-wrap md:overflow-visible pb-1 md:pb-0 -mx-1 px-1 min-w-0">
                                {grupo.bandejas.map((bandeja) => (
                                    <PestanaBandeja
                                        key={bandeja.id}
                                        bandeja={bandeja}
                                        conteo={metricas?.[bandeja.metrica]}
                                        activo={tabActiva === bandeja.id}
                                        onElegir={onTabChange}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </nav>
    );
}
