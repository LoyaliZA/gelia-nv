import React from 'react';
import { router } from '@inertiajs/react';

export default function TabRecursosCampos({ recursos }) {
    const toggleRecurso = (recurso, campo, valor) => {
        router.put(route('admin.api_externa.recursos.update', recurso.id), {
            activo: campo === 'activo' ? valor : recurso.activo,
            lectura_habilitada: campo === 'lectura_habilitada' ? valor : recurso.lectura_habilitada,
            escritura_habilitada: campo === 'escritura_habilitada' ? valor : recurso.escritura_habilitada,
        }, { preserveScroll: true });
    };

    const toggleCampo = (campo, valor) => {
        router.put(route('admin.api_externa.campos.update', campo.id), {
            habilitado_global: valor,
        }, { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            <p className="text-sm theme-text-muted">
                Controle qué recursos y campos puede exponer la API externa de forma global.
            </p>
            {recursos.map((recurso) => (
                <div key={recurso.id} className="rounded-2xl theme-border border p-5 space-y-4">
                    <div className="flex flex-wrap justify-between gap-4">
                        <div>
                            <h4 className="font-black uppercase">{recurso.nombre}</h4>
                            <p className="text-xs theme-text-muted">{recurso.descripcion}</p>
                            <code className="text-[10px]">{recurso.slug}</code>
                        </div>
                        <div className="flex flex-wrap gap-4 text-xs">
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recurso.activo} onChange={(e) => toggleRecurso(recurso, 'activo', e.target.checked)} /> Activo</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recurso.lectura_habilitada} onChange={(e) => toggleRecurso(recurso, 'lectura_habilitada', e.target.checked)} /> Lectura</label>
                            <label className="flex items-center gap-2"><input type="checkbox" checked={recurso.escritura_habilitada} onChange={(e) => toggleRecurso(recurso, 'escritura_habilitada', e.target.checked)} /> Escritura</label>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="theme-text-muted text-left border-b theme-border">
                                    <th className="py-2 pr-4">Campo</th>
                                    <th className="py-2 pr-4">Etiqueta</th>
                                    <th className="py-2 pr-4">Sensible</th>
                                    <th className="py-2">Expuesto</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recurso.campos.map((campo) => (
                                    <tr key={campo.id} className="border-b theme-border/50">
                                        <td className="py-2 pr-4 font-mono">{campo.slug}</td>
                                        <td className="py-2 pr-4">{campo.etiqueta}</td>
                                        <td className="py-2 pr-4">{campo.es_sensible ? 'Sí' : 'No'}</td>
                                        <td className="py-2">
                                            <input type="checkbox" checked={campo.habilitado_global} onChange={(e) => toggleCampo(campo, e.target.checked)} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ))}
        </div>
    );
}
