import React from 'react';
import { Key, ShieldCheck, Check, ChevronRight } from 'lucide-react';

export default function PermisosAtomicos({ data, setData, roles, todosLosPermisos }) {
    
    // Evaluamos la herencia directamente dentro del componente
    const permisoHeredado = (permisoName) => {
        const asignados = data.roles_asignados || [];
        return (roles || [])
            .filter(r => asignados.includes(r.name))
            .some(r => (r.permissions || []).some(p => p.name === permisoName));
    };

    // Agrupamos los permisos recibidos
    const permisosAgrupados = (todosLosPermisos || []).reduce((acc, p) => {
        const modulo = p?.name?.split('.')[0] || 'Otros';
        if (!acc[modulo]) acc[modulo] = [];
        acc[modulo].push(p);
        return acc;
    }, {});

    // Manejador del click
    const togglePermisoIndividual = (permisoName) => {
        if (permisoHeredado(permisoName)) return; // Bloqueo si es heredado
        
        const actuales = data.permisos_individuales || [];
        const nuevos = actuales.includes(permisoName)
            ? actuales.filter(item => item !== permisoName)
            : [...actuales, permisoName];
            
        // Usamos la función setData de Inertia (pasada como propiedad desde la vista padre)
        setData('permisos_individuales', nuevos);
    };

    if (!todosLosPermisos || todosLosPermisos.length === 0) return null;

    return (
        <div>
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                <Key className="w-4 h-4 text-red-500" /> Permisos Atómicos
            </h3>
            <p className="text-[10px] theme-text-muted mb-4 font-bold tracking-widest">
                INDICADORES: <span className="text-blue-500 mx-1">AZUL</span> herencia de rol. <span className="text-orange-500 mx-1">NARANJA</span> excepción directa.
            </p>
            
            <div className="space-y-2">
                {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                    <details key={modulo} className="group theme-element rounded-2xl overflow-hidden border theme-border">
                        <summary className="p-4 cursor-pointer flex justify-between items-center select-none outline-none">
                            <div className="flex items-center gap-3">
                                <ShieldCheck className="w-4 h-4 theme-text-muted" />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main italic">Módulo: {modulo}</span>
                            </div>
                            <ChevronRight className="w-4 h-4 group-open:rotate-90 transition-transform duration-300 theme-text-muted" />
                        </summary>
                        <div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 border-t theme-border">
                            {(permisosDeModulo || []).map(permiso => {
                                const isHeredado = permisoHeredado(permiso.name);
                                const isDirecto = (data.permisos_individuales || []).includes(permiso.name);
                                return (
                                    <button 
                                        key={permiso.id} 
                                        type="button" 
                                        disabled={isHeredado}
                                        onClick={() => togglePermisoIndividual(permiso.name)} 
                                        className={`flex justify-between items-center px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all ${isHeredado ? 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400 opacity-90' : isDirecto ? 'border-orange-500 bg-orange-500/10 text-orange-600' : 'theme-border theme-text-muted hover:border-gray-400'}`}
                                    >
                                        <span>{permiso.name.split('.')[1]?.replace('_', ' ') || permiso.name}</span>
                                        {isHeredado ? <ShieldCheck className="w-3 h-3" /> : isDirecto && <Check className="w-3 h-3" />}
                                    </button>
                                );
                            })}
                        </div>
                    </details>
                ))}
            </div>
        </div>
    );
}