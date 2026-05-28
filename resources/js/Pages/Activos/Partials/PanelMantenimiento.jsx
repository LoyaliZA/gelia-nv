import React from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle, Wrench } from 'lucide-react';
import { BTN_PRIMARY_CLASS, LABEL_CLASS, getActivosCardClass } from './activosFormStyles';

const TIPO_LABELS = { preventivo: 'Preventivo', correctivo: 'Correctivo', garantia: 'Garantía' };
const ESTADO_LABELS = { programado: 'Programado', en_proceso: 'En proceso', completado: 'Completado', cancelado: 'Cancelado' };

export default function PanelMantenimiento({ activo, canCambiarEstado, onProgramar }) {
    const activoMantenimiento = activo.mantenimiento_activo?.[0] || activo.mantenimientoActivo?.[0];
    const historial = activo.mantenimientos || [];

    const completar = (mantenimiento) => {
        if (!confirm('¿Marcar este mantenimiento como completado?')) return;
        router.post(route('activos.mantenimiento.completar', [activo.id, mantenimiento.id]), {}, { preserveScroll: true });
    };

    return (
        <div className={getActivosCardClass({ extra: 'p-6 space-y-4' })} style={{ animationDelay: '200ms' }}>
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-muted flex items-center gap-2">
                    <Wrench className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                    Mantenimiento
                </h2>
                {canCambiarEstado && activo.estado !== 'baja' && activo.estado !== 'mantenimiento' && (
                    <button type="button" onClick={onProgramar} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>
                        Programar
                    </button>
                )}
            </div>

            {activoMantenimiento ? (
                <div className="rounded-xl p-4 theme-element border theme-border space-y-2">
                    <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 rounded-lg text-[10px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            {ESTADO_LABELS[activoMantenimiento.estado] || activoMantenimiento.estado}
                        </span>
                        <span className="px-2 py-1 rounded-lg text-[10px] font-black uppercase theme-element border theme-border">
                            {TIPO_LABELS[activoMantenimiento.tipo] || activoMantenimiento.tipo}
                        </span>
                    </div>
                    {activoMantenimiento.fecha_programada && (
                        <p className="text-xs theme-text-muted">Programado: {String(activoMantenimiento.fecha_programada).substring(0, 10)}</p>
                    )}
                    {activoMantenimiento.proveedor && <p className="text-sm theme-text-main">Proveedor: {activoMantenimiento.proveedor}</p>}
                    {activoMantenimiento.descripcion && <p className="text-xs theme-text-muted">{activoMantenimiento.descripcion}</p>}
                    {canCambiarEstado && ['programado', 'en_proceso'].includes(activoMantenimiento.estado) && (
                        <button type="button" onClick={() => completar(activoMantenimiento)} className={`${BTN_PRIMARY_CLASS} mt-2 text-[10px]`} style={{ backgroundColor: 'var(--color-primario)' }}>
                            <CheckCircle className="w-3 h-3" /> Completar mantenimiento
                        </button>
                    )}
                </div>
            ) : (
                <p className="text-sm theme-text-muted italic">Sin mantenimiento activo.</p>
            )}

            {historial.length > 0 && (
                <div className="space-y-2 pt-2 border-t theme-border">
                    <p className={LABEL_CLASS}>Historial</p>
                    {historial.slice(0, 5).map((m) => (
                        <div key={m.id} className="text-xs theme-text-muted border-b theme-border pb-2 last:border-0">
                            <span className="font-bold theme-text-main">{TIPO_LABELS[m.tipo]}</span>
                            {' · '}{ESTADO_LABELS[m.estado]}
                            {m.fecha_programada && ` · ${String(m.fecha_programada).substring(0, 10)}`}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
