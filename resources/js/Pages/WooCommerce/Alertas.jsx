import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { ArrowLeft, EyeOff } from 'lucide-react';

export default function Alertas({ auth, productosCriticos }) {
    const [selected, setSelected] = useState([]);
    const canEmergencia = auth?.user?.permissions?.includes('woocommerce.emergencia') || auth?.user?.roles?.includes('Super Admin');

    const toggle = (id) => setSelected((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);

    const ocultar = async () => {
        if (!selected.length) return;
        const res = await fetch(route('woocommerce.emergencia.ocultar'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, Accept: 'application/json' },
            body: JSON.stringify({ productos_ids: selected }),
        });
        const data = await res.json();
        alert(data.message || data.error);
        window.location.reload();
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Alertas — Sincronizar Precios" />
            <div className="max-w-[1200px] mx-auto p-4 md:p-8 space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={route('woocommerce.index')} className="p-2 rounded-xl border theme-border"><ArrowLeft className="w-5 h-5 theme-text-muted" /></Link>
                        <h1 className="text-2xl font-black italic uppercase theme-text-main">Alertas de Precios</h1>
                    </div>
                    {canEmergencia && selected.length > 0 && (
                        <button onClick={ocultar} className="px-4 py-2 rounded-xl bg-red-500 text-white text-[10px] font-black uppercase flex items-center gap-2">
                            <EyeOff className="w-4 h-4" /> Ocultar en Woo ({selected.length})
                        </button>
                    )}
                </div>

                <div className={`${geliaCardClass()} p-6`}>
                    <p className="text-xs theme-text-muted mb-4">{productosCriticos.length} productos sin precio normal válido.</p>
                    <div className="overflow-x-auto rounded-2xl border theme-border">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="text-[10px] font-black uppercase theme-text-muted border-b theme-border">
                                    {canEmergencia && <th className="p-3 w-8" />}
                                    <th className="p-3 text-left">SKU</th>
                                    <th className="p-3 text-left">Nombre</th>
                                    <th className="p-3 text-right">Normal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {productosCriticos.map((p) => (
                                    <tr key={p.id} className="border-b theme-border/50">
                                        {canEmergencia && (
                                            <td className="p-3"><input type="checkbox" checked={selected.includes(p.id)} onChange={() => toggle(p.id)} /></td>
                                        )}
                                        <td className="p-3 font-bold">{p.sku}</td>
                                        <td className="p-3 theme-text-muted">{p.nombre}</td>
                                        <td className="p-3 text-right text-red-500 font-bold">{p.precio_normal ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
