import React, { useState } from 'react';
import { Package, UserPlus, ArrowRightLeft, Bell, Eye, X, ChevronDown, ChevronUp } from 'lucide-react';
import { getActivosCardClass } from './activosFormStyles';

const GUIA_STORAGE_KEY = 'activos_guia_oculta';

const PASOS = [
    {
        icon: Package,
        titulo: 'Registrar',
        descripcion: 'Crea activos con fotografías y atributos dinámicos según el tipo (equipo TI, licencias, uniformes, etc.).',
    },
    {
        icon: UserPlus,
        titulo: 'Asignar / Transferir',
        descripcion: 'Designa un responsable o transfiere entre departamentos. Cada movimiento queda en la bitácora automáticamente.',
    },
    {
        icon: Bell,
        titulo: 'Mantenimiento y vencimientos',
        descripcion: 'Programa mantenimientos y revisa alertas en el panel lateral y en la campana de notificaciones.',
    },
    {
        icon: Eye,
        titulo: 'Consultar detalle',
        descripcion: 'Abre un activo para ver galería, historial de asignaciones, atributos y acciones según tus permisos.',
    },
];

export default function GuiaVisualActivos({ onOcultar }) {
    const [colapsada, setColapsada] = useState(false);
    const [oculta, setOculta] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(GUIA_STORAGE_KEY) === '1';
    });

    if (oculta) return null;

    const cerrar = () => {
        localStorage.setItem(GUIA_STORAGE_KEY, '1');
        setOculta(true);
        onOcultar?.();
    };

    return (
        <div className={getActivosCardClass({ extra: 'p-4 md:p-6 space-y-4' })} style={{ animationDelay: '150ms' }}>
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="flex items-center gap-3 mb-1">
                        <span className="h-1 w-8 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                        <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Guía del módulo</p>
                    </div>
                    <h2 className="text-lg font-black italic uppercase tracking-tight theme-text-main m-0">Control de Activos</h2>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                    <button type="button" onClick={() => setColapsada((v) => !v)} className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none" title={colapsada ? 'Expandir' : 'Colapsar'}>
                        {colapsada ? <ChevronDown className="w-4 h-4" /> : <ChevronUp className="w-4 h-4" />}
                    </button>
                    <button type="button" onClick={cerrar} className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none" title="Ocultar guía">
                        <X className="w-4 h-4" />
                    </button>
                </div>
            </div>

            {!colapsada && (
                <ol className="space-y-3">
                    {PASOS.map((paso, index) => {
                        const Icon = paso.icon;
                        return (
                            <li key={paso.titulo} className="flex gap-3 rounded-2xl p-3 theme-element border theme-border">
                                <span className="flex items-center justify-center w-8 h-8 rounded-xl shrink-0 text-[10px] font-black text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {index + 1}
                                </span>
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                                        <Icon className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase theme-text-main">{paso.titulo}</span>
                                    </div>
                                    <p className="text-[11px] theme-text-muted leading-relaxed m-0">{paso.descripcion}</p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}

            {!colapsada && (
                <p className="text-[10px] theme-text-muted italic m-0 flex items-center gap-1">
                    <ArrowRightLeft className="w-3 h-3" /> Las transferencias entre departamentos notifican a TI, RH y los usuarios involucrados.
                </p>
            )}
        </div>
    );
}
