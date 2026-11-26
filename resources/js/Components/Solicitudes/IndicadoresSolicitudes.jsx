import React from 'react';
import { ArrowUpRight, CheckCircle2, Clock3, FilePenLine, TriangleAlert } from 'lucide-react';

export default function IndicadoresSolicitudes({ metricas = {}, borradores = false, onSeleccionar }) {
    const items = [
        ...(borradores ? [{ key: 'borradores', label: 'Borradores', tab: 'BORRADORES', icon: FilePenLine, tone: 'neutro' }] : []),
        { key: 'pendientes', label: 'Pendientes', tab: 'PENDIENTES', icon: Clock3, tone: 'aviso' },
        { key: 'respondidas_hoy', label: 'Respondidas hoy', icon: CheckCircle2, tone: 'exito' },
        { key: 'incorrectas', label: 'Requieren corrección', tab: 'INCORRECTAS', icon: TriangleAlert, tone: 'peligro' },
    ];
    return (
        <section className="gelia-solicitudes-indicadores" aria-label="Resumen de solicitudes">
            {items.map(({ key, label, tab, icon: Icon, tone }) => {
                const Tag = tab && onSeleccionar ? 'button' : 'div';
                return (
                    <Tag key={key} className="gelia-solicitudes-indicador gelia-tag-kpi" data-tone={tone}
                        {...(Tag === 'button' ? { type: 'button', onClick: () => onSeleccionar(tab), 'aria-label': `${label}: ${metricas[key] ?? 0}. Ver solicitudes` } : {})}>
                        <span className="gelia-tag-kpi-icono" aria-hidden="true"><Icon className="h-5 w-5" /></span>
                        <span className="min-w-0 text-left">
                            <span className="block text-xs font-medium theme-text-muted">{label}</span>
                            <strong className="mt-0.5 block text-2xl font-semibold tabular-nums leading-none theme-text-main">{new Intl.NumberFormat('es-MX').format(metricas[key] ?? 0)}</strong>
                        </span>
                        {Tag === 'button' && <ArrowUpRight className="ml-auto h-4 w-4 shrink-0 theme-text-muted" aria-hidden="true" />}
                    </Tag>
                );
            })}
        </section>
    );
}
