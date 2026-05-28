import React from 'react';
import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';

export default function ModuleActivos() {
    return (
        <Link
            href={route('activos.index')}
            className="h-full w-full min-h-0 theme-element border-2 border-emerald-500/20 p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none"
        >
            <div className="w-12 h-12 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-center justify-center mb-6 transition-colors duration-500 z-10">
                <Package className="w-5 h-5 text-emerald-500 transition-colors duration-500" />
            </div>
            <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">
                Control de Activos
            </h3>
            <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">
                Inventario, asignaciones y mantenimiento.
            </p>
        </Link>
    );
}
