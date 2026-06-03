import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock, Wrench, CheckCircle2 } from 'lucide-react';

export function totalAlertasActivos(alertas) {
    if (!alertas) return 0;
    return alertas.vencidos.length + alertas.proximos_7.length + alertas.mantenimiento.length;
}

export function totalAlertasResumen(alertasResumen = {}) {
    return (alertasResumen.vencidos || 0) + (alertasResumen.proximos_7 || 0) + (alertasResumen.mantenimiento || 0);
}

export default function ListadoAlertasActivos({ alertas = null, compacto = false }) {
    const total = totalAlertasActivos(alertas);

    const Item = ({ item }) => (
        <Link
            href={route('activos.show', item.id)}
            className={`block rounded-xl hover:bg-black/5 dark:hover:bg-white/5 text-sm border theme-border ${compacto ? 'px-3 py-2' : 'px-3 py-2.5 theme-element'}`}
            onClick={(e) => e.stopPropagation()}
        >
            <span className="font-bold theme-text-main">{item.folio}</span>
            <span className="theme-text-muted"> — {item.nombre}</span>
            {item.fecha && <span className="block text-[10px] theme-text-muted mt-0.5">{item.fecha}</span>}
        </Link>
    );

    if (total === 0) {
        return (
            <div className="flex items-center gap-2 py-8 text-xs theme-text-muted italic justify-center">
                <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                Sin alertas pendientes en tu ámbito.
            </div>
        );
    }

    return (
        <div className="space-y-5">
            {alertas.vencidos.length > 0 && (
                <div>
                    <div className="flex items-center gap-2 text-red-600 text-xs font-black uppercase mb-2">
                        <AlertTriangle className="w-4 h-4" /> Vencidos ({alertas.vencidos.length})
                    </div>
                    <div className="space-y-2">
                        {alertas.vencidos.map((item) => <Item key={`v-${item.id}`} item={item} />)}
                    </div>
                </div>
            )}
            {alertas.proximos_7.length > 0 && (
                <div>
                    <div className="flex items-center gap-2 text-amber-600 text-xs font-black uppercase mb-2">
                        <Clock className="w-4 h-4" /> Próximos 7 días ({alertas.proximos_7.length})
                    </div>
                    <div className="space-y-2">
                        {alertas.proximos_7.map((item) => <Item key={`p-${item.id}`} item={item} />)}
                    </div>
                </div>
            )}
            {alertas.mantenimiento.length > 0 && (
                <div>
                    <div className="flex items-center gap-2 theme-text-main text-xs font-black uppercase mb-2">
                        <Wrench className="w-4 h-4" /> Mantenimiento ({alertas.mantenimiento.length})
                    </div>
                    <div className="space-y-2">
                        {alertas.mantenimiento.map((item) => <Item key={`m-${item.id}-${item.fecha}`} item={item} />)}
                    </div>
                </div>
            )}
        </div>
    );
}
