import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Save, Key, Settings2, Check, Wifi } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';

export default function ModalConfiguracion({ configuracion, margenes, users, onClose }) {
    const { data, setData, processing, errors } = useForm({
        store_url: configuracion?.store_url || '',
        iva: configuracion?.iva || 1.16,
        consumer_key: '',
        consumer_secret: '',
        notified_users: configuracion?.notified_user_ids || [],
        mapeo_precios: {
            sku: configuracion?.mapeo_precios?.sku || 'SKU',
            precio_base: configuracion?.mapeo_precios?.precio_base || 'Plataformas',
        },
        margenes: margenes.reduce((acc, m) => ({
            ...acc,
            [m.id]: { rebaja: m.multiplicador_rebaja, normal: m.multiplicador_normal },
        }), {}),
    });

    const [busqueda, setBusqueda] = useState('');
    const [probandoConexion, setProbandoConexion] = useState(false);
    const [resultadoConexion, setResultadoConexion] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const probarConexion = async () => {
        setProbandoConexion(true);
        setResultadoConexion(null);

        try {
            const response = await fetch(route('woocommerce.configuracion.probar_conexion'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    store_url: data.store_url,
                    consumer_key: data.consumer_key || undefined,
                    consumer_secret: data.consumer_secret || undefined,
                }),
            });

            const result = await response.json();
            setResultadoConexion({
                ok: response.ok && result.success,
                message: result.message || (response.ok ? 'Conexión exitosa.' : 'Error de conexión.'),
            });
        } catch (err) {
            setResultadoConexion({ ok: false, message: err.message });
        } finally {
            setProbandoConexion(false);
        }
    };

    const toggleUser = (userId) => {
        const current = [...data.notified_users];
        setData('notified_users', current.includes(userId) ? current.filter((id) => id !== userId) : [...current, userId]);
    };

    const submit = async (e) => {
        e.preventDefault();
        const response = await fetch(route('woocommerce.configuracion.update'), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify(data),
        });
        if (response.ok) onClose();
    };

    const filteredUsers = users.filter((u) =>
        u.name.toLowerCase().includes(busqueda.toLowerCase()) || u.email.toLowerCase().includes(busqueda.toLowerCase())
    );

    const modal = (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={probandoConexion} message="Probando conexión API_" />
            <div className="w-full max-w-4xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto custom-scrollbar relative">
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-8">
                    <Settings2 className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-xl md:text-2xl font-black italic uppercase theme-text-main">Configuración WooCommerce</h2>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div className="md:col-span-2">
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">URL de la Tienda</label>
                        <input type="url" value={data.store_url} onChange={(e) => setData('store_url', e.target.value)}
                            placeholder="https://tutienda.com" className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main" />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Consumer Key</label>
                        <div className="flex items-center theme-element border theme-border rounded-xl px-4">
                            <Key className="w-4 h-4 theme-text-muted shrink-0" />
                            <input type="password" value={data.consumer_key} onChange={(e) => setData('consumer_key', e.target.value)}
                                placeholder="Dejar en blanco para conservar..." className="w-full bg-transparent py-3 px-3 text-sm theme-text-main outline-none" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Consumer Secret</label>
                        <div className="flex items-center theme-element border theme-border rounded-xl px-4">
                            <Key className="w-4 h-4 theme-text-muted shrink-0" />
                            <input type="password" value={data.consumer_secret} onChange={(e) => setData('consumer_secret', e.target.value)}
                                placeholder="Dejar en blanco para conservar..." className="w-full bg-transparent py-3 px-3 text-sm theme-text-main outline-none" />
                        </div>
                    </div>

                    <div className="md:col-span-2 flex flex-col sm:flex-row sm:items-center gap-3">
                        <button
                            type="button"
                            onClick={probarConexion}
                            disabled={probandoConexion}
                            className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-black uppercase border theme-border theme-text-main hover:bg-black/5 dark:hover:bg-white/5 disabled:opacity-50"
                        >
                            <Wifi className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                            Probar conexión API
                        </button>
                        <p className="text-[10px] theme-text-muted font-bold">
                            Usa los valores del formulario; si dejas key/secret vacíos, se prueban las credenciales guardadas.
                        </p>
                    </div>

                    {resultadoConexion && (
                        <div className={`md:col-span-2 p-3 rounded-xl text-xs font-bold ${
                            resultadoConexion.ok
                                ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600'
                                : 'bg-red-500/10 border border-red-500/20 text-red-500'
                        }`}>
                            {resultadoConexion.message}
                        </div>
                    )}

                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">IVA Divisor</label>
                        <input type="number" step="0.01" value={data.iva} onChange={(e) => setData('iva', parseFloat(e.target.value))}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main" />
                    </div>

                    <div className="md:col-span-2">
                        <h3 className="text-xs font-black uppercase theme-text-main mb-3">Mapeo de precios (Excel)</h3>
                        <p className="text-[10px] theme-text-muted font-bold mb-3">
                            Nombres de columna predeterminados para auto-mapear en la sincronización de precios.
                        </p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Columna SKU</label>
                                <input
                                    type="text"
                                    value={data.mapeo_precios.sku}
                                    onChange={(e) => setData('mapeo_precios', { ...data.mapeo_precios, sku: e.target.value })}
                                    placeholder="SKU"
                                    className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Columna Precio base</label>
                                <input
                                    type="text"
                                    value={data.mapeo_precios.precio_base}
                                    onChange={(e) => setData('mapeo_precios', { ...data.mapeo_precios, precio_base: e.target.value })}
                                    placeholder="Plataformas"
                                    className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="md:col-span-2">
                        <h3 className="text-xs font-black uppercase theme-text-main mb-3">Márgenes por Rango</h3>
                        <div className="space-y-2">
                            {margenes.map((m) => (
                                <div key={m.id} className="flex items-center gap-4 p-3 theme-element border theme-border rounded-xl text-xs">
                                    <span className="flex-1 font-bold theme-text-muted">
                                        ${m.precio_min} — {m.precio_max >= 99999 ? '∞' : `$${m.precio_max}`}
                                    </span>
                                    <label className="flex items-center gap-1 theme-text-main font-bold">Rebaja
                                        <input type="number" step="0.01" value={data.margenes[m.id]?.rebaja ?? m.multiplicador_rebaja}
                                            onChange={(e) => setData('margenes', { ...data.margenes, [m.id]: { ...data.margenes[m.id], rebaja: parseFloat(e.target.value) } })}
                                            className="w-16 bg-white dark:bg-zinc-900 border theme-border rounded px-2 py-1 text-center theme-text-main focus:ring-1 focus:ring-[var(--color-primario)]" />
                                    </label>
                                    <label className="flex items-center gap-1 theme-text-main font-bold">Normal
                                        <input type="number" step="0.01" value={data.margenes[m.id]?.normal ?? m.multiplicador_normal}
                                            onChange={(e) => setData('margenes', { ...data.margenes, [m.id]: { ...data.margenes[m.id], normal: parseFloat(e.target.value) } })}
                                            className="w-16 bg-white dark:bg-zinc-900 border theme-border rounded px-2 py-1 text-center theme-text-main focus:ring-1 focus:ring-[var(--color-primario)]" />
                                    </label>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="md:col-span-2">
                        <h3 className="text-xs font-black uppercase theme-text-main mb-2">Usuarios Notificados</h3>
                        <input type="text" placeholder="Buscar usuario..." value={busqueda} onChange={(e) => setBusqueda(e.target.value)}
                            className="w-full mb-3 px-4 py-2 text-sm theme-surface border theme-border rounded-lg theme-text-main" />
                        <div className="space-y-1 max-h-[200px] overflow-y-auto border theme-border rounded-xl p-2">
                            {filteredUsers.map((user) => {
                                const selected = data.notified_users.includes(user.id);
                                return (
                                    <div key={user.id} onClick={() => toggleUser(user.id)}
                                        className={`flex items-center gap-3 p-2 rounded-lg cursor-pointer ${selected ? 'bg-black/5 dark:bg-white/5' : ''}`}>
                                        <div className={`w-4 h-4 rounded border flex items-center justify-center ${selected ? '' : 'border-gray-400'}`}
                                            style={selected ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}>
                                            {selected && <Check className="w-3 h-3 text-white" />}
                                        </div>
                                        <div><span className="text-xs font-bold theme-text-main">{user.name}</span><br /><span className="text-[10px] theme-text-muted">{user.email}</span></div>
                                    </div>
                                );
                            })}
                        </div>
                        {errors.notified_users && <p className="text-red-500 text-xs mt-1">{errors.notified_users}</p>}
                    </div>

                    <div className="md:col-span-2 flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onClose} className="px-6 py-3 rounded-xl text-xs font-black uppercase border theme-border theme-text-main">Cancelar</button>
                        <button type="submit" disabled={processing} className="px-6 py-3 rounded-xl text-xs font-black uppercase text-white flex items-center gap-2 disabled:opacity-50"
                            style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Save className="w-4 h-4" /> {processing ? 'Guardando...' : 'Guardar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
