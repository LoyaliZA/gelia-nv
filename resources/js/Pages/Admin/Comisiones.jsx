import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    DollarSign, Edit3, Save, Trash2, 
    CheckCircle, AlertCircle, Info, TrendingUp,
    Briefcase, Sparkles
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Comisiones({ auth, tabulador = [] }) {
    const [editando, setEditando] = useState(null);

    const { data, setData, put, processing, reset } = useForm({
        monto_comision: '',
        activo: true
    });

    useEffect(() => {
        animate('.fade-up', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    const iniciarEdicion = (item) => {
        setEditando(item.id);
        setData({
            monto_comision: item.monto_comision,
            activo: item.activo
        });
    };

    const guardarCambios = (e) => {
        e.preventDefault();
        // Esta ruta debe empatar con el controlador de tu amigo
        put(route('admin.comisiones.update', editando), {
            onSuccess: () => {
                setEditando(null);
                reset();
            }
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Tabulador de Comisiones | GELIANV" />

            <div className="max-w-6xl mx-auto p-6 md:p-12 space-y-12">
                {/* HEADER */}
                <header className="fade-up space-y-4">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Finanzas y Rendimiento</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors">
                        TABULADOR DE <span className="text-pink-500">COMISIONES</span>
                    </h1>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-12">
                    {/* INFORMACIÓN LATERAL */}
                    <div className="fade-up lg:col-span-1 space-y-6">
                        <div className="theme-surface border-2 theme-border rounded-[2.5rem] p-8 shadow-sm relative overflow-hidden">
                            <div className="absolute -top-4 -right-4 opacity-5">
                                <DollarSign className="w-24 h-24 theme-text-main" />
                            </div>
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center mb-4">
                                <Info className="w-5 h-5 mr-2 text-pink-500" />
                                Guía de Pago
                            </h2>
                            <p className="text-[11px] theme-text-muted font-bold leading-relaxed italic uppercase">
                                Los montos aquí definidos se aplicarán automáticamente a cada solicitud finalizada con éxito por las vendedoras[cite: 1, 2].
                            </p>
                        </div>

                        <div className="p-8 border-2 border-emerald-500/10 rounded-[2.5rem] bg-emerald-500/5 space-y-3">
                            <div className="flex items-center gap-2 text-emerald-600">
                                <TrendingUp className="w-4 h-4" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest">Impacto Directo</h4>
                            </div>
                            <p className="text-[10px] text-emerald-700/70 font-bold italic">
                                Ajustar estos valores incentiva la reactivación de clientes heredados y la búsqueda de clientes nuevos.
                            </p>
                        </div>
                    </div>

                    {/* LISTADO DE COMISIONES */}
                    <div className="lg:col-span-3 space-y-6">
                        <div className="grid grid-cols-1 gap-4">
                            {/* Card de ejemplo mapeando el tabulador */}
                            <div className="fade-up theme-surface border-2 theme-border rounded-[2.5rem] p-8 hover:border-pink-500 transition-all group relative overflow-hidden">
                                <div className="flex flex-col md:flex-row justify-between items-center gap-6">
                                    <div className="flex items-center gap-6 flex-1">
                                        <div className="w-14 h-14 theme-element border-2 theme-border rounded-2xl flex items-center justify-center shadow-inner group-hover:bg-zinc-900 dark:group-hover:bg-white transition-colors">
                                            <Briefcase className="w-6 h-6 text-pink-500" />
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">Tipo de Proceso_</p>
                                            <h3 className="text-xl font-black italic theme-text-main uppercase tracking-tighter group-hover:text-pink-500 transition-colors">
                                                Asignar Cliente Nuevo
                                            </h3>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-8">
                                        <div className="text-right">
                                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-1">Monto de Comisión_</p>
                                            {editando === 1 ? (
                                                <div className="flex items-center gap-2">
                                                    <span className="text-2xl font-black italic theme-text-main">$</span>
                                                    <input 
                                                        type="number" 
                                                        value={data.monto_comision}
                                                        onChange={e => setData('monto_comision', e.target.value)}
                                                        className="w-24 p-2 theme-element border-2 border-pink-500 rounded-xl theme-text-main font-black text-xl outline-none"
                                                    />
                                                </div>
                                            ) : (
                                                <p className="text-3xl font-black italic theme-text-main tracking-tighter">
                                                    $150.<span className="text-pink-500 text-sm">00</span>
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {editando === 1 ? (
                                                <button 
                                                    onClick={guardarCambios}
                                                    className="p-4 bg-emerald-500 text-white rounded-2xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-500/20"
                                                >
                                                    <Save className="w-5 h-5" />
                                                </button>
                                            ) : (
                                                <button 
                                                    onClick={() => iniciarEdicion({id: 1, monto_comision: 150, activo: true})}
                                                    className="p-4 theme-element border-2 theme-border rounded-2xl hover:border-pink-500 transition-all group/btn"
                                                >
                                                    <Edit3 className="w-5 h-5 theme-text-muted group-hover/btn:text-pink-500" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="absolute top-0 right-0 p-4 opacity-0 group-hover:opacity-10 transition-opacity">
                                    <Sparkles className="w-12 h-12 text-pink-500" />
                                </div>
                            </div>

                            {/* Aviso de Sincronización */}
                            <div className="fade-up p-6 theme-element border-2 border-dashed theme-border rounded-[2rem] flex items-center gap-4">
                                <CheckCircle className="w-5 h-5 text-emerald-500" />
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted italic">
                                    Valores sincronizados con la tabla <span className="text-pink-500 underline">catalogo_procesos</span>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }

                input::-webkit-outer-spin-button,
                input::-webkit-inner-spin-button {
                    -webkit-appearance: none;
                    margin: 0;
                }
            `}</style>
        </AppLayout>
    );
}