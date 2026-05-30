import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, RefreshCw, Trash2, ShieldOff, Copy, AlertTriangle } from 'lucide-react';

export default function TabAplicaciones({ aplicaciones, credenciales_nuevas, secret_regenerado }) {
    const [editando, setEditando] = useState(null);
    const form = useForm({
        nombre: '',
        descripcion: '',
        ips_permitidas_texto: '',
        limite_por_minuto: 60,
    });

    const editForm = useForm({
        nombre: '',
        descripcion: '',
        activa: true,
        ips_permitidas_texto: '',
        limite_por_minuto: 60,
    });

    const submitNueva = (e) => {
        e.preventDefault();
        const ips = form.data.ips_permitidas_texto
            .split(/[\n,]/)
            .map((s) => s.trim())
            .filter(Boolean);

        router.post(route('admin.api_externa.aplicaciones.store'), {
            nombre: form.data.nombre,
            descripcion: form.data.descripcion,
            ips_permitidas: ips.length ? ips : null,
            limite_por_minuto: form.data.limite_por_minuto,
        }, {
            onSuccess: () => form.reset(),
        });
    };

    const abrirEditar = (app) => {
        setEditando(app.id);
        editForm.setData({
            nombre: app.nombre,
            descripcion: app.descripcion || '',
            activa: app.activa,
            ips_permitidas_texto: (app.ips_permitidas || []).join('\n'),
            limite_por_minuto: app.limite_por_minuto,
        });
    };

    const submitEditar = (appId) => {
        const ips = editForm.data.ips_permitidas_texto
            .split(/[\n,]/)
            .map((s) => s.trim())
            .filter(Boolean);

        editForm.transform(() => ({
            nombre: editForm.data.nombre,
            descripcion: editForm.data.descripcion,
            activa: editForm.data.activa,
            ips_permitidas: ips.length ? ips : null,
            limite_por_minuto: editForm.data.limite_por_minuto,
        }));

        editForm.put(route('admin.api_externa.aplicaciones.update', appId), {
            onSuccess: () => setEditando(null),
        });
    };

    const copiar = (texto) => navigator.clipboard?.writeText(texto);

    return (
        <div className="space-y-8">
            {(credenciales_nuevas || secret_regenerado) && (
                <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 space-y-3">
                    <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-bold text-sm">
                        <AlertTriangle className="w-4 h-4" />
                        Credenciales sensibles — cópielas ahora; no se volverán a mostrar.
                    </div>
                    {credenciales_nuevas && (
                        <div className="space-y-2 text-sm font-mono">
                            <p><strong>App:</strong> {credenciales_nuevas.nombre}</p>
                            <p className="flex items-center gap-2"><strong>Client ID:</strong> {credenciales_nuevas.client_id}
                                <button type="button" onClick={() => copiar(credenciales_nuevas.client_id)} className="p-1 rounded hover:bg-black/10"><Copy className="w-3 h-3" /></button>
                            </p>
                            <p className="flex items-center gap-2"><strong>Client Secret:</strong> {credenciales_nuevas.client_secret}
                                <button type="button" onClick={() => copiar(credenciales_nuevas.client_secret)} className="p-1 rounded hover:bg-black/10"><Copy className="w-3 h-3" /></button>
                            </p>
                        </div>
                    )}
                    {secret_regenerado && (
                        <div className="space-y-2 text-sm font-mono">
                            <p><strong>App:</strong> {secret_regenerado.nombre}</p>
                            <p className="flex items-center gap-2"><strong>Nuevo Secret:</strong> {secret_regenerado.client_secret}
                                <button type="button" onClick={() => copiar(secret_regenerado.client_secret)} className="p-1 rounded hover:bg-black/10"><Copy className="w-3 h-3" /></button>
                            </p>
                        </div>
                    )}
                </div>
            )}

            <form onSubmit={submitNueva} className="grid md:grid-cols-2 gap-4 p-5 rounded-2xl theme-border border">
                <h3 className="md:col-span-2 text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                    <Plus className="w-4 h-4" /> Nueva aplicación
                </h3>
                <input className="rounded-xl px-4 py-3 theme-border border bg-transparent" placeholder="Nombre" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} required />
                <input className="rounded-xl px-4 py-3 theme-border border bg-transparent" placeholder="Límite / min" type="number" min="1" value={form.data.limite_por_minuto} onChange={(e) => form.setData('limite_por_minuto', e.target.value)} />
                <textarea className="md:col-span-2 rounded-xl px-4 py-3 theme-border border bg-transparent" placeholder="Descripción" rows={2} value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} />
                <textarea className="md:col-span-2 rounded-xl px-4 py-3 theme-border border bg-transparent text-xs" placeholder="IPs permitidas (una por línea, vacío = todas)" rows={2} value={form.data.ips_permitidas_texto} onChange={(e) => form.setData('ips_permitidas_texto', e.target.value)} />
                <button type="submit" disabled={form.processing} className="md:col-span-2 py-3 rounded-xl text-white font-black uppercase text-xs tracking-widest" style={{ backgroundColor: 'var(--color-primario)' }}>
                    Crear aplicación
                </button>
            </form>

            <div className="space-y-4">
                {aplicaciones.map((app) => (
                    <div key={app.id} className="rounded-2xl theme-border border p-5 space-y-4">
                        {editando === app.id ? (
                            <div className="grid md:grid-cols-2 gap-3">
                                <input className="rounded-xl px-4 py-2 theme-border border bg-transparent" value={editForm.data.nombre} onChange={(e) => editForm.setData('nombre', e.target.value)} />
                                <input className="rounded-xl px-4 py-2 theme-border border bg-transparent" type="number" value={editForm.data.limite_por_minuto} onChange={(e) => editForm.setData('limite_por_minuto', e.target.value)} />
                                <textarea className="md:col-span-2 rounded-xl px-4 py-2 theme-border border bg-transparent" rows={2} value={editForm.data.descripcion} onChange={(e) => editForm.setData('descripcion', e.target.value)} />
                                <textarea className="md:col-span-2 rounded-xl px-4 py-2 theme-border border bg-transparent text-xs" rows={2} value={editForm.data.ips_permitidas_texto} onChange={(e) => editForm.setData('ips_permitidas_texto', e.target.value)} />
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" checked={editForm.data.activa} onChange={(e) => editForm.setData('activa', e.target.checked)} />
                                    Activa
                                </label>
                                <div className="flex gap-2 md:col-span-2">
                                    <button type="button" onClick={() => submitEditar(app.id)} className="px-4 py-2 rounded-xl text-white text-xs font-bold" style={{ backgroundColor: 'var(--color-primario)' }}>Guardar</button>
                                    <button type="button" onClick={() => setEditando(null)} className="px-4 py-2 rounded-xl theme-border border text-xs">Cancelar</button>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="flex flex-wrap justify-between gap-3">
                                    <div>
                                        <h4 className="font-black uppercase tracking-wide">{app.nombre}</h4>
                                        <p className="text-xs theme-text-muted">{app.descripcion || 'Sin descripción'}</p>
                                        <p className="text-xs font-mono mt-1">ID: {app.client_id}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <span className={`px-3 py-1 rounded-full text-[10px] font-black uppercase ${app.activa ? 'bg-green-500/20 text-green-600' : 'bg-red-500/20 text-red-600'}`}>
                                            {app.activa ? 'Activa' : 'Inactiva'}
                                        </span>
                                        <button type="button" onClick={() => abrirEditar(app)} className="px-3 py-1 rounded-xl theme-border border text-xs">Editar</button>
                                        <button type="button" onClick={() => router.post(route('admin.api_externa.aplicaciones.regenerar_secret', app.id))} className="px-3 py-1 rounded-xl theme-border border text-xs flex items-center gap-1"><RefreshCw className="w-3 h-3" /> Regenerar secret</button>
                                        <button type="button" onClick={() => router.post(route('admin.api_externa.aplicaciones.revocar_tokens', app.id))} className="px-3 py-1 rounded-xl theme-border border text-xs flex items-center gap-1"><ShieldOff className="w-3 h-3" /> Revocar tokens</button>
                                        <button type="button" onClick={() => confirm('¿Eliminar aplicación?') && router.delete(route('admin.api_externa.aplicaciones.destroy', app.id))} className="px-3 py-1 rounded-xl border border-red-500/40 text-red-500 text-xs flex items-center gap-1"><Trash2 className="w-3 h-3" /> Eliminar</button>
                                    </div>
                                </div>
                                <p className="text-[10px] theme-text-muted">Límite: {app.limite_por_minuto}/min · IPs: {(app.ips_permitidas || []).length ? app.ips_permitidas.join(', ') : 'Todas'}</p>
                            </>
                        )}
                    </div>
                ))}
                {!aplicaciones.length && <p className="text-sm theme-text-muted">No hay aplicaciones registradas.</p>}
            </div>
        </div>
    );
}
