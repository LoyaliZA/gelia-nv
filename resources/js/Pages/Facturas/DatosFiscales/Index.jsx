import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Database, ArrowLeft, Save, X } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { BTN_SECONDARY, FACTURA_ACCENT } from '../Partials/facturasStyles';

function ModalEditarFiscal({ cliente, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        rfc: cliente.rfc || '',
        codigo_postal: cliente.codigo_postal || '',
        regimen_fiscal: cliente.regimen_fiscal || '',
        correo_electronico: cliente.correo_electronico || '',
        uso_factura: cliente.uso_factura || '',
        nombre_razon_social: cliente.nombre_razon_social || '',
    });

    const guardar = (e) => {
        e.preventDefault();
        put(route('facturas.datos_fiscales.update', cliente.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const campos = [
        ['rfc', 'RFC'],
        ['codigo_postal', 'Código Postal'],
        ['regimen_fiscal', 'Régimen Fiscal'],
        ['correo_electronico', 'Correo Electrónico'],
        ['uso_factura', 'Uso de Factura'],
        ['nombre_razon_social', 'Nombre / Razón Social'],
    ];

    return (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-lg theme-surface border theme-border rounded-2xl p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted rounded-xl outline-none"><X className="w-5 h-5" /></button>
                <h3 className="text-lg font-black theme-text-main uppercase mb-1">Datos Fiscales</h3>
                <p className="text-xs font-bold theme-text-muted mb-6">{cliente.numero_cliente} — {cliente.nombre}</p>
                <form onSubmit={guardar} className="space-y-4">
                    {campos.map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <label className="text-[9px] font-black uppercase theme-text-muted tracking-widest">{label}</label>
                            <input
                                value={data[key]}
                                onChange={e => setData(key, e.target.value)}
                                className="w-full px-3 py-2.5 theme-surface border theme-border rounded-xl text-sm font-bold theme-text-main outline-none"
                            />
                            {errors[key] && <p className="text-xs text-red-500">{errors[key]}</p>}
                        </div>
                    ))}
                    <button type="submit" disabled={processing} className="w-full py-3 rounded-xl text-white font-black uppercase text-xs outline-none flex items-center justify-center gap-2" style={{ backgroundColor: FACTURA_ACCENT }}>
                        <Save className="w-4 h-4" /> {processing ? 'Guardando…' : 'Guardar'}
                    </button>
                </form>
            </div>
        </div>
    );
}

export default function DatosFiscalesIndex({ clientes, filtros }) {
    const [editando, setEditando] = useState(null);

    return (
        <AppLayout>
            <Head title="Datos Fiscales de Clientes" />
            <div className="max-w-6xl mx-auto px-4 py-8 space-y-6">
                <div className="flex items-center gap-4">
                    <Link href={route('facturas.index')} className={BTN_SECONDARY}>
                        <ArrowLeft className="w-4 h-4" /> Facturas
                    </Link>
                    <div className="flex items-center gap-2">
                        <Database className="w-6 h-6" style={{ color: FACTURA_ACCENT }} />
                        <h1 className="text-2xl font-black italic theme-text-main uppercase m-0">Datos Fiscales_</h1>
                    </div>
                </div>

                <div className="theme-surface border theme-border rounded-2xl overflow-hidden">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="theme-element border-b theme-border">
                                <th className="px-4 py-3 text-[9px] font-black uppercase theme-text-muted">Cliente</th>
                                <th className="px-4 py-3 text-[9px] font-black uppercase theme-text-muted">RFC</th>
                                <th className="px-4 py-3 text-[9px] font-black uppercase theme-text-muted hidden md:table-cell">Razón Social</th>
                                <th className="px-4 py-3 text-[9px] font-black uppercase theme-text-muted hidden lg:table-cell">Correo</th>
                                <th className="px-4 py-3 w-24" />
                            </tr>
                        </thead>
                        <tbody>
                            {(clientes.data || []).map(c => (
                                <tr key={c.id} className="border-b theme-border last:border-b-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                    <td className="px-4 py-3">
                                        <p className="text-xs font-black theme-text-main m-0">{c.numero_cliente}</p>
                                        <p className="text-[10px] theme-text-muted m-0 truncate max-w-[140px]">{c.nombre}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs font-mono font-bold theme-text-main">{c.rfc || '—'}</td>
                                    <td className="px-4 py-3 text-xs font-bold theme-text-main hidden md:table-cell truncate max-w-[180px]">{c.nombre_razon_social || '—'}</td>
                                    <td className="px-4 py-3 text-xs theme-text-muted hidden lg:table-cell truncate max-w-[160px]">{c.correo_electronico || '—'}</td>
                                    <td className="px-4 py-3">
                                        <button type="button" onClick={() => setEditando(c)} className="text-[10px] font-black uppercase outline-none hover:underline" style={{ color: FACTURA_ACCENT }}>
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {clientes?.links && <GeliaPaginacion paginacion={clientes} />}
            </div>

            {editando && <ModalEditarFiscal cliente={editando} onClose={() => setEditando(null)} />}
        </AppLayout>
    );
}
