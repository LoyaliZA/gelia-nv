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
                    <Tag key={key} className="gelia-solicitudes-indicador" data-tone={tone}
                        {...(Tag === 'button' ? { type: 'button', onClick: () => onSeleccionar(tab), 'aria-label': `${label}: ${metricas[key] ?? 0}. Ver solicitudes` } : {})}>
                        <div className="flex items-center justify-between gap-2"><span className="text-sm theme-text-muted">{label}</span><Icon className="gelia-indicador-icon w-4 h-4 shrink-0" aria-hidden="true" /></div>
                        <div className="flex items-end justify-between gap-2 mt-2"><strong className="text-2xl font-semibold tabular-nums theme-text-main">{new Intl.NumberFormat('es-MX').format(metricas[key] ?? 0)}</strong>{Tag === 'button' && <ArrowUpRight className="w-4 h-4 theme-text-muted" aria-hidden="true" />}</div>
                    </Tag>
                );
            })}
        </section>
    );
}
