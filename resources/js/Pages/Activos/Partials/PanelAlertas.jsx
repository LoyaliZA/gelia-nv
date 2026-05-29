import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock, Wrench, CheckCircle2 } from 'lucide-react';
import { getActivosCardClass } from './activosFormStyles';

export default function PanelAlertas({ alertas = null }) {
    const total = alertas
        ? alertas.vencidos.length + alertas.proximos_7.length + alertas.mantenimiento.length
        : 0;

    const Item = ({ item }) => (
        <Link href={route('activos.show', item.id)} className="block px-3 py-2 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 text-sm">
            <span className="font-bold theme-text-main">{item.folio}</span>
            <span className="theme-text-muted"> — {item.nombre}</span>
            {item.fecha && <span className="block text-[10px] theme-text-muted">{item.fecha}</span>}
        </Link>
    );

    return (
        <div className={getActivosCardClass('p-4 md:p-6 space-y-4')}>
            <p className="text-[10px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                Alertas activas
            </p>

            {total === 0 ? (
                <div className="flex items-center gap-2 py-4 text-xs theme-text-muted italic">
                    <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                    Sin alertas pendientes en tu ámbito.
                </div>
            ) : (
                <>
                    {alertas.vencidos.length > 0 && (
                        <div>
                            <div className="flex items-center gap-2 text-red-600 text-xs font-black uppercase mb-2">
                                <AlertTriangle className="w-4 h-4" /> Vencidos ({alertas.vencidos.length})
                            </div>
                            {alertas.vencidos.slice(0, 5).map((item) => <Item key={`v-${item.id}`} item={item} />)}
                        </div>
                    )}
                    {alertas.proximos_7.length > 0 && (
                        <div>
                            <div className="flex items-center gap-2 text-amber-600 text-xs font-black uppercase mb-2">
                                <Clock className="w-4 h-4" /> Próximos 7 días ({alertas.proximos_7.length})
                            </div>
                            {alertas.proximos_7.slice(0, 5).map((item) => <Item key={`p-${item.id}`} item={item} />)}
                        </div>
                    )}
                    {alertas.mantenimiento.length > 0 && (
                        <div>
                            <div className="flex items-center gap-2 theme-text-main text-xs font-black uppercase mb-2">
                                <Wrench className="w-4 h-4" /> Mantenimiento ({alertas.mantenimiento.length})
                            </div>
                            {alertas.mantenimiento.slice(0, 5).map((item) => <Item key={`m-${item.id}-${item.fecha}`} item={item} />)}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
