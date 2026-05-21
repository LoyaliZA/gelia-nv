import React from 'react';
import { Link } from '@inertiajs/react';
import { FileSignature } from 'lucide-react';

export default function ModuleSolicitudes() {
    return (
        <Link 
            href={route('solicitudes.index')} 
            className="theme-element border-2 border-amber-500/20 p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none animate-page-reveal"
        >
            <div className="w-12 h-12 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-center mb-6 transition-colors duration-500 z-10">
                <FileSignature className="w-5 h-5 text-amber-500 transition-colors duration-500" />
            </div>
            <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">Panel Solicitudes</h3>
            <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">Gestión operativa.</p>
        </Link>
    );
}