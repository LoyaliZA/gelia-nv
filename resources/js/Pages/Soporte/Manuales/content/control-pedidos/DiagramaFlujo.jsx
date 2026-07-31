import React from 'react';
import { geliaCardClass } from '@/utils/geliaTheme';

export default function DiagramaFlujo({ flujo }) {
    const camino = flujo?.camino_feliz || [];
    const ramas = flujo?.ramas || [];

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                {flujo?.titulo || 'Diagrama de flujo'}
            </h2>

            <div className="flex flex-col gap-2">
                {camino.map((paso, i) => (
                    <div key={`${paso.fase}-${i}`} className="flex items-stretch gap-3">
                        <div className="flex flex-col items-center w-6 shrink-0">
                            <span
                                className="w-3 h-3 rounded-full border-2 shrink-0 mt-1.5"
                                style={{ borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' }}
                            />
                            {i < camino.length - 1 && (
                                <span className="w-0.5 flex-1 min-h-[1.25rem] opacity-20 bg-[var(--color-primario)]" />
                            )}
                        </div>
                        <div className={geliaCardClass('flex-1 p-3 md:p-4 mb-1')}>
                            <p
                                className="text-[10px] font-black uppercase tracking-[0.15em] m-0 mb-1"
                                style={{ color: 'var(--color-primario)' }}
                            >
                                {String(paso.fase).replaceAll('_', ' ')}
                            </p>
                            <p className="text-xs theme-text-muted m-0 leading-relaxed">
                                <span className="font-bold theme-text-main">{paso.quien}: </span>
                                {paso.accion}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            {ramas.length > 0 && (
                <div className="space-y-3">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">
                        Ramas y excepciones
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {ramas.map((r) => (
                            <div key={r.nombre} className={geliaCardClass('p-4')}>
                                <p className="text-[11px] font-black uppercase tracking-wider theme-text-main m-0 mb-2">
                                    {r.nombre}
                                </p>
                                <p className="text-xs theme-text-muted m-0 leading-relaxed">{r.detalle}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
