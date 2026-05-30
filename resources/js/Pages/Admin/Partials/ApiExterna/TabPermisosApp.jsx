import React, { useState } from 'react';
import { router } from '@inertiajs/react';

export default function TabPermisosApp({ aplicaciones, recursos }) {
    const [appSeleccionada, setAppSeleccionada] = useState(aplicaciones[0]?.id ?? '');

    const app = aplicaciones.find((a) => String(a.id) === String(appSeleccionada));

    const togglePermiso = (permiso, campo, valor) => {
        router.put(route('admin.api_externa.permisos.update', permiso.id), {
            puede_leer: campo === 'puede_leer' ? valor : permiso.puede_leer,
            puede_escribir: campo === 'puede_escribir' ? valor : permiso.puede_escribir,
            activo: campo === 'activo' ? valor : permiso.activo,
        }, { preserveScroll: true });
    };

    const toggleCampoApp = (campo, valor) => {
        router.put(route('admin.api_externa.aplicacion_campos.update', campo.id), {
            habilitado: valor,
        }, { preserveScroll: true });
    };

    if (!aplicaciones.length) {
        return <p className="text-sm theme-text-muted">Cree una aplicación primero.</p>;
    }

    return (
        <div className="space-y-6">
            <select
                className="rounded-xl px-4 py-3 theme-border border bg-transparent text-sm max-w-md"
                value={appSeleccionada}
                onChange={(e) => setAppSeleccionada(e.target.value)}
            >
                {aplicaciones.map((a) => (
                    <option key={a.id} value={a.id}>{a.nombre}</option>
                ))}
            </select>

            {app && (
                <>
                    <div className="rounded-2xl theme-border border p-5 space-y-3">
                        <h4 className="font-black uppercase text-sm">Permisos por recurso</h4>
                        {(app.permisos || []).map((permiso) => (
                            <div key={permiso.id} className="flex flex-wrap items-center gap-4 py-2 border-b theme-border/50 text-xs">
                                <span className="font-bold min-w-[120px]">{permiso.recurso_nombre}</span>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={permiso.activo} onChange={(e) => togglePermiso(permiso, 'activo', e.target.checked)} /> Activo</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={permiso.puede_leer} onChange={(e) => togglePermiso(permiso, 'puede_leer', e.target.checked)} /> Leer</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={permiso.puede_escribir} onChange={(e) => togglePermiso(permiso, 'puede_escribir', e.target.checked)} /> Escribir</label>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-2xl theme-border border p-5 space-y-3">
                        <h4 className="font-black uppercase text-sm">Campos visibles para esta app</h4>
                        {recursos.map((recurso) => {
                            const camposApp = (app.campos || []).filter((c) => c.recurso_slug === recurso.slug);
                            if (!camposApp.length) return null;
                            return (
                                <div key={recurso.id} className="space-y-2">
                                    <p className="text-xs font-bold uppercase theme-text-muted">{recurso.nombre}</p>
                                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        {camposApp.map((campo) => (
                                            <label key={campo.id} className="flex items-center gap-2 text-xs p-2 rounded-lg theme-border border">
                                                <input type="checkbox" checked={campo.habilitado} onChange={(e) => toggleCampoApp(campo, e.target.checked)} />
                                                {campo.campo_etiqueta}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}
