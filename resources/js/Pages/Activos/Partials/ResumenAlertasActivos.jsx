import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Wrench, Clock } from 'lucide-react';
import { getActivosCardClass } from './activosFormStyles';

export default function ResumenAlertasActivos({ alertasResumen = {}, alertas = {} }) {
    const items = [
        alertasResumen.vencidos > 0 && {
            key: 'vencidos',
            label: `${alertasResumen.vencidos} vencidos`,
            className: 'border-red-500/30 text-red-700 dark:text-red-300',
            icon: AlertTriangle,
            href: route('activos.alertas'),
        },
        alertasResumen.proximos_7 > 0 && {
            key: 'proximos',
            label: `${alertasResumen.proximos_7} por vencer`,
            className: 'border-amber-500/30 text-amber-700 dark:text-amber-300',
            icon: Clock,
            href: route('activos.alertas'),
        },
        alertasResumen.mantenimiento > 0 && {
            key: 'mantenimiento',
            label: `${alertasResumen.mantenimiento} en mantenimiento`,
            className: 'border-blue-500/30 text-blue-700 dark:text-blue-300',
            icon: Wrench,
            href: route('activos.index', { en_mantenimiento: '1' }),
        },
    ].filter(Boolean);

    if (!items.length) return null;

    return (
        <div className={`lg:hidden ${getActivosCardClass('p-3 overflow-x-auto')}`}>
            <div className="flex gap-2 min-w-max">
                {items.map((item) => {
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={`inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-[10px] font-black uppercase whitespace-nowrap ${item.className}`}
                        >
                            <Icon className="w-3.5 h-3.5 shrink-0" />
                            {item.label}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
