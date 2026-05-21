import React from 'react';
import { Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';

export default function ModuleAccesos() {
    return (
        <Link 
            href={route('admin.enlaces')} 
            className="theme-element border-2 theme-border p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none animate-page-reveal"
        >
            <div className="w-12 h-12 theme-element border theme-border rounded-2xl flex items-center justify-center mb-6 transition-colors duration-500 z-10">
                <UserPlus className="w-5 h-5 theme-text-main transition-colors duration-500" />
            </div>
            <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">Generar Accesos</h3>
            <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">Crear enlaces seguros.</p>
        </Link>
    );
}