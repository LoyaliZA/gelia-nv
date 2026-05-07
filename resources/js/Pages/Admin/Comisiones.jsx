import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation'; // Importación modular corregida
import { 
    DollarSign, Edit3, Save, 
    CheckCircle, Info, TrendingUp,
    Briefcase, Sparkles, X, ShieldCheck
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Comisiones({ auth, tabulador = [] }) {
    const [editando, setEditando] = useState(null);

    const { data, setData, put, processing, reset, errors } = useForm({
        monto_vendedora: '',
        monto_original: '', 
        activo: true
    });

    useEffect(() => {
        animate('.fade-up', {
            translateY: [15, 0],
            opacity: [0, 1],
        }, {
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    const iniciarEdicion = (item) => {
        setEditando(item.id);
        setData({
            monto_vendedora: item.monto_vendedora || item.monto_comision,
            monto_original: item.monto_original || 0,
            activo: item.activo
        });
        
        // Animación sutil al seleccionar
        animate('.panel-edicion', {
            scale: [0.98, 1],
            duration: 400,
            easing: 'easeOutBack'
        });
    };

    const guardarCambios = (e) => {
        e.preventDefault();
        put(route('admin.comisiones.update', editando), {
            onSuccess: () => {
                setEditando(null);
                reset();
            }
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración de Comisiones | GELIANV" />

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12 min-h-screen">
                {/* --- ENCABEZADO --- */}
                <header className="fade-up space-y-4">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 rounded-full transition-colors duration-300" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Configuración Global</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors">
                        TABULADOR DE <span style={{ color: 'var(--color-primario)' }}>COMISIONES</span>
                    </h1>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
                    
                    {/* --- PANEL DE EDICIÓN --- */}
                    <div className="fade-up lg:col-span-1 space-y-6">
                        <div 
                            className="panel-edicion theme-surface border-2 rounded-[2.5rem] p-8 shadow-sm transition-all relative overflow-hidden"
                            style={{ borderColor: editando ? 'var(--color-primario)' : 'var(--theme-border-color)' }}
                        >
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center mb-6">
                                <Edit3 className="w-5 h-5 mr-3" style={{ color: 'var(--color-primario)' }} />
                                {editando ? 'Modificar Valores' : 'Seleccione un Item'}
                            </h2>

                            {editando ? (
                                <form onSubmit={guardarCambios} className="space-y-6 animate-fade-in">
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <label className="text-[10px] font-black uppercase theme-text-muted ml-2 tracking-widest italic">Comisión Vendedora ($)_</label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-500" />
                                                <input 
                                                    type="number" step="0.01"
                                                    value={data.monto_vendedora}
                                                    onChange={e => setData('monto_vendedora', e.target.value)}
                                                    className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none transition-all"
                                                    onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                                    onBlur={(e) => e.target.style.borderColor = ''}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-[10px] font-black uppercase theme-text-muted ml-2 tracking-widest italic flex items-center gap-1">
                                                <ShieldCheck className="w-3 h-3 text-amber-500" /> Comisión Original (Heredados) ($)_
                                            </label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-amber-500" />
                                                <input 
                                                    type="number" step="0.01"
                                                    value={data.monto_original}
                                                    onChange={e => setData('monto_original', e.target.value)}
                                                    className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none transition-all focus:border-amber-500"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-3">
                                        <button 
                                            type="button" 
                                            onClick={() => setEditando(null)}
                                            className="flex-1 py-4 theme-element border-2 theme-border rounded-2xl font-black uppercase text-[10px] tracking-widest theme-text-muted hover:theme-text-main transition-all"
                                        >
                                            Cancelar
                                        </button>
                                        <button 
                                            type="submit" 
                                            disabled={processing}
                                            className="flex-[2] py-4 text-white dark:text-black rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl transition-all disabled:opacity-50"
                                            style={{ backgroundColor: 'var(--color-primario)' }}
                                        >
                                            <Save className="inline w-3.5 h-3.5 mr-2" /> Guardar_
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-center py-12 space-y-4 opacity-40">
                                    <Briefcase className="w-12 h-12 mx-auto theme-text-muted" />
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted leading-relaxed">
                                        Haga clic en el icono de edición de la lista para modificar los montos operativos.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* INFO BOX */}
                        <div className="p-6 theme-element rounded-3xl space-y-4 border-2 theme-border transition-colors duration-300">
                            <div className="flex items-center gap-2" style={{ color: 'var(--color-primario)' }}>
                                <Info className="w-4 h-4" />
                                <p className="text-[9px] font-black uppercase tracking-widest italic">Nota del Sistema_</p>
                            </div>
                            <p className="text-[10px] theme-text-muted font-bold leading-relaxed italic uppercase">
                                Las comisiones se aplican al momento de <strong className="theme-text-main">Aprobar</strong> la solicitud por parte de la encargada de TAGS.
                            </p>
                        </div>
                    </div>

                    {/* --- LISTADO DE PROCESOS --- */}
                    <div className="fade-up lg:col-span-2">
                        <div className="theme-surface border-2 theme-border rounded-[3rem] overflow-hidden shadow-sm transition-colors duration-300">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b-2 theme-border">
                                        <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Proceso / Movimiento_</th>
                                        <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Vendedora Actual_</th>
                                        <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Org. (Heredado)_</th>
                                        <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acción_</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tabulador.map((item) => (
                                        <tr key={item.id} className={`border-b theme-border transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/30 ${editando === item.id ? 'bg-zinc-50 dark:bg-zinc-800/50' : ''}`}>
                                            <td className="p-6">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 theme-element rounded-xl flex items-center justify-center border theme-border" style={{ borderColor: editando === item.id ? 'var(--color-primario)' : '' }}>
                                                        <Sparkles className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-black italic theme-text-main uppercase tracking-tighter">{item.proceso?.nombre || 'Sin nombre'}</p>
                                                        <p className="text-[9px] font-bold theme-text-muted uppercase tracking-widest mt-1">ID Ref: {item.id}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-6 text-center">
                                                <span className="text-sm font-black theme-text-main italic" style={{ color: editando === item.id ? 'var(--color-primario)' : '' }}>
                                                    {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(item.monto_vendedora || item.monto_comision)}
                                                </span>
                                            </td>
                                            <td className="p-6 text-center">
                                                <span className={`text-sm font-black italic ${item.monto_original > 0 ? 'text-amber-500' : 'theme-text-muted opacity-30'}`}>
                                                    {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(item.monto_original || 0)}
                                                </span>
                                            </td>
                                            <td className="p-6 text-center">
                                                <button 
                                                    onClick={() => iniciarEdicion(item)}
                                                    className="p-3 theme-element border-2 theme-border rounded-xl hover:text-white transition-all"
                                                    style={{ 
                                                        borderColor: editando === item.id ? 'var(--color-primario)' : '',
                                                        backgroundColor: editando === item.id ? 'var(--color-primario)' : ''
                                                    }}
                                                >
                                                    <Edit3 className="w-4 h-4" style={{ color: editando === item.id ? (auth.tema_visual?.modo === 'dark' ? '#000' : '#fff') : '' }} />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                :root { --theme-border-color: #f4f4f5; }
                .dark { --theme-border-color: #2A2A2A; }

                .theme-surface { background-color: #ffffff; border-color: var(--theme-border-color); }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: var(--theme-border-color); }
                
                .dark .theme-surface { background-color: #121212; border-color: var(--theme-border-color); }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: var(--theme-border-color); }

                .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
            `}</style>
        </AppLayout>
    );
}