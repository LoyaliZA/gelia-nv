import React from 'react';
import { CheckCircle2, Clock } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { OPCIONES_TIPO_REPORTE } from '../../../../utils/reportesPagosTipoReporte';

const cardBase = geliaCardClass('p-4 md:p-5 text-left transition-all border');

export default function SelectorTipoReportePagos({ tipoActivo, onCambiar }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
            {OPCIONES_TIPO_REPORTE.map((opcion) => {
                const activo = tipoActivo === opcion.id;
                const proximamente = !opcion.disponible;

                return (
                    <button
                        key={opcion.id}
                        type="button"
                        aria-pressed={activo}
                        onClick={() => {
                            if (opcion.id !== tipoActivo) onCambiar(opcion.id);
                        }}
                        className={[
                            cardBase,
                            activo
                                ? 'border-[var(--color-primario)] ring-2 ring-[color-mix(in_srgb,var(--color-primario)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-primario)_6%,var(--theme-element-bg))]'
                                : 'theme-border hover:border-[var(--color-primario)]/40',
                            'cursor-pointer',
                        ].join(' ')}
                    >
                        <div className="flex items-start justify-between gap-3 mb-2">
                            <h2 className="text-sm md:text-base font-semibold theme-text-main m-0 leading-snug">
                                {opcion.titulo}
                            </h2>
                            {activo ? (
                                <CheckCircle2 className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                            ) : proximamente ? (
                                <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border theme-border theme-text-muted shrink-0">
                                    <Clock className="w-3 h-3" aria-hidden />
                                    Próximamente
                                </span>
                            ) : null}
                        </div>
                        <p className="text-xs md:text-sm theme-text-muted m-0 leading-relaxed">
                            {opcion.descripcion}
                        </p>
                    </button>
                );
            })}
        </div>
    );
}
