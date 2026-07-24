import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Save, Key, Settings2, Wifi } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';

export default function ModalConfiguracion({ configuracion, onClose }) {
    const [storeId, setStoreId] = useState(configuracion?.store_id || '');
    const [appId, setAppId] = useState(configuracion?.app_id || '');
    const [accessToken, setAccessToken] = useState('');
    const [scopes, setScopes] = useState(configuracion?.scopes || '');
    const [saving, setSaving] = useState(false);
    const [probando, setProbando] = useState(false);
    const [resultado, setResultado] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const headers = () => ({
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
    });

    const guardar = async (e) => {
        e.preventDefault();
        setSaving(true);
        setResultado(null);
        try {
            const body = {
                store_id: storeId ? Number(storeId) : undefined,
                app_id: appId || undefined,
                scopes: scopes || undefined,
            };
            if (accessToken.trim()) {
                body.access_token = accessToken.trim();
            }
            const res = await fetch(route('tiendanube.configuracion.update'), {
                method: 'PUT',
                headers: headers(),
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo guardar.');
            }
            setResultado({ ok: true, message: data.message });
            setAccessToken('');
            setTimeout(() => onClose(), 600);
        } catch (err) {
            setResultado({ ok: false, message: err.message });
        } finally {
            setSaving(false);
        }
    };

    const probar = async () => {
        setProbando(true);
        setResultado(null);
        try {
            if (accessToken.trim() || storeId) {
                await fetch(route('tiendanube.configuracion.update'), {
                    method: 'PUT',
                    headers: headers(),
                    body: JSON.stringify({
                        store_id: storeId ? Number(storeId) : undefined,
                        app_id: appId || undefined,
                        access_token: accessToken.trim() || undefined,
                    }),
                });
            }
            const res = await fetch(route('tiendanube.configuracion.probar_conexion'), {
                method: 'POST',
                headers: headers(),
                body: '{}',
            });
            const data = await res.json();
            setResultado({
                ok: res.ok && data.success,
                message: data.message || (res.ok ? 'OK' : 'Error'),
            });
        } catch (err) {
            setResultado({ ok: false, message: err.message });
        } finally {
            setProbando(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={probando || saving} message={probando ? 'Probando conexión_' : 'Guardando_'} />
            <div className="w-full max-w-xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto relative">
                <button type="button" onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 mb-8">
                    <Settings2 className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-xl md:text-2xl font-black italic uppercase theme-text-main">Configuración Tiendanube</h2>
                </div>

                <form onSubmit={guardar} className="space-y-4">
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Store ID</label>
                        <input
                            type="number"
                            value={storeId}
                            onChange={(e) => setStoreId(e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                            placeholder="8004291"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">App ID</label>
                        <input
                            type="text"
                            value={appId}
                            onChange={(e) => setAppId(e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                            placeholder="37163"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Access Token</label>
                        <div className="flex items-center theme-element border theme-border rounded-xl px-4">
                            <Key className="w-4 h-4 theme-text-muted shrink-0" />
                            <input
                                type="password"
                                value={accessToken}
                                onChange={(e) => setAccessToken(e.target.value)}
                                placeholder={configuracion?.tiene_token ? 'Dejar vacío para conservar el actual' : 'Pegar token'}
                                className="w-full bg-transparent py-3 px-3 text-sm theme-text-main outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Scopes (opcional)</label>
                        <input
                            type="text"
                            value={scopes}
                            onChange={(e) => setScopes(e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                            placeholder="read_products,write_products,..."
                        />
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3 pt-2">
                        <button
                            type="button"
                            onClick={probar}
                            disabled={probando}
                            className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-black uppercase border theme-border theme-text-main disabled:opacity-50"
                        >
                            <Wifi className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                            Probar conexión
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-xs font-black uppercase text-white disabled:opacity-50"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Save className="w-4 h-4" /> Guardar
                        </button>
                    </div>

                    {resultado && (
                        <div className={`p-3 rounded-xl text-xs font-bold ${
                            resultado.ok
                                ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600'
                                : 'bg-red-500/10 border border-red-500/20 text-red-500'
                        }`}>
                            {resultado.message}
                        </div>
                    )}
                </form>
            </div>
        </div>,
        document.body
    );
}
