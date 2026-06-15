import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Save, CloudUpload } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';

export default function ModalEditarPrecio({ producto, onClose }) {
    const [precioNormal, setPrecioNormal] = useState(String(producto.precio_normal ?? 0));
    const [precioRebajado, setPrecioRebajado] = useState(String(producto.precio_rebajado ?? 0));
    const [errorMsg, setErrorMsg] = useState(null);
    const [guardando, setGuardando] = useState(false);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const guardar = async (e) => {
        e.preventDefault();
        setErrorMsg(null);
        setGuardando(true);

        try {
            const response = await fetch(route('woocommerce.productos.update', producto.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    precio_normal: parseFloat(precioNormal),
                    precio_rebajado: parseFloat(precioRebajado),
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error al sincronizar con WooCommerce.');
            }

            onClose();
            router.reload({ only: ['productos'] });
        } catch (err) {
            setErrorMsg(err.message);
        } finally {
            setGuardando(false);
        }
    };

    const modal = (
        <div className="fixed inset-0 z-[210] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={guardando} message="Sincronizando con WooCommerce_" />

            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-6 md:p-8 relative">
                <button type="button" onClick={onClose} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 mb-6">
                    <CloudUpload className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main">Editar precio</h2>
                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-1">SKU: {producto.sku}</p>
                    </div>
                </div>

                {errorMsg && (
                    <div className="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-bold">
                        {errorMsg}
                    </div>
                )}

                <form onSubmit={guardar} className="space-y-4">
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Precio normal</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={precioNormal}
                            onChange={(e) => setPrecioNormal(e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Precio rebajado</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={precioRebajado}
                            onChange={(e) => setPrecioRebajado(e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main"
                            required
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                        <button type="button" onClick={onClose} className="px-5 py-2.5 rounded-xl text-xs font-black uppercase border theme-border theme-text-main">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={guardando}
                            className="px-5 py-2.5 rounded-xl text-xs font-black uppercase text-white flex items-center gap-2 disabled:opacity-50"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Save className="w-4 h-4" /> Subir a WooCommerce
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );

    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
