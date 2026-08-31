import React from 'react';
import { Layers } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';

const OPCIONES = [
    { id: 'movimiento', label: 'Fecha del movimiento' },
    { id: 'banco', label: 'Banco' },
    { id: 'forma_pago', label: 'Forma de pago' },
];

export default function SelectorAgrupacionVouchers({ valor, onCambiar }) {
    return (
        <div className={geliaCardClass('p-4 flex flex-col sm:flex-row sm:items-center gap-3')}>
            <div className="flex items-center gap-2 shrink-0">
                <Layers className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                <span className="text-xs font-semibold uppercase tracking-wide theme-text-muted">Agrupar por</span>
            </div>
            <div className="flex flex-wrap gap-2">
                {OPCIONES.map((op) => (
                    <button
                        key={op.id}
                        type="button"
                        onClick={() => op.id !== valor && onCambiar(op.id)}
                        className={[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors',
                            valor === op.id
                                ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]'
                                : 'theme-border theme-element theme-text-main hover:border-[var(--color-primario)]/40',
                        ].join(' ')}
                    >
                        {op.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
