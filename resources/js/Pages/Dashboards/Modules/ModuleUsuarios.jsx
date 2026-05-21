import React from 'react';
import { Link } from '@inertiajs/react';
import { Users } from 'lucide-react';

export default function ModuleUsuarios() {
    return (
        <Link 
            href={route('admin.usuarios')} 
            className="theme-element border-2 p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none animate-page-reveal"
            style={{ borderColor: 'var(--color-primario)' }}
        >
            <div className="w-12 h-12 border border-transparent rounded-2xl flex items-center justify-center mb-6 transition-colors duration-500 z-10" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                <Users className="w-5 h-5 transition-colors duration-500" style={{ color: 'var(--color-primario)' }} />
            </div>
            <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">Control de Usuarios</h3>
            <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">Administrar roles y personal.</p>
        </Link>
    );
}