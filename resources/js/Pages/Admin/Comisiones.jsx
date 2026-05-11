import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    DollarSign, Edit3, Save, 
    Info, Briefcase, Sparkles, 
    ShieldCheck, TrendingUp, X
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Comisiones({ auth, tabulador = [] }) {
    const [editando, setEditando] = useState(null);

    const { data, setData, put, processing, reset } = useForm({
        monto_vendedora: '',
        monto_original: '', 
        activo: true
    });

    useEffect(() => {
        animate('.page-reveal-comisiones', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 80
        });
    }, []);

    const iniciarEdicion = (item) => {
        setEditando(item.id);
        setData({
            monto_vendedora: item.monto_vendedora || item.monto_comision,
            monto_original: item.monto_original || 0,
            activo: item.activo
        });
        
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
            <Head title="Tabulador de Comisiones | GELIANV" />

            <div className="max-w-7xl mx-auto p-4 md:p-8 space-y-8">
                
                {/* --- HEADER GELIA --- */}
                <div className="page-reveal-comisiones flex flex-col md:flex-row justify-between items-start md:items-center gap-6 theme-surface border-2 theme-border p-6 md:p-10 rounded-[2rem] md:rounded-[3rem] shadow-sm">
                    <div>
                        <div className="flex items-center space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Configuración Financiera</p>
                        </div>
                        <h1 className="text-3xl md:text-4xl font-black theme-text-main flex items-center gap-3 italic uppercase tracking-tighter m-0">
                            <TrendingUp className="w-8 h-8 md:w-10 md:h-10 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            TABULADOR DE <span style={{ color: 'var(--color-primario)' }}>COMISIONES</span>
                        </h1>
                        <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-2 opacity-80 max-w-2xl">
                            Ajuste de parámetros económicos por cada proceso y movimiento operativo dentro de la matriz.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    
                    {/* --- PANEL LATERAL DE EDICIÓN --- */}
                    <div className="page-reveal-comisiones lg:col-span-1 space-y-6">
                        <div 
                            className="panel-edicion theme-surface border-2 theme-border rounded-[2.5rem] p-8 shadow-sm transition-all relative overflow-hidden"
                            style={{ borderColor: editando ? 'var(--color-primario)' : '' }}
                        >
                            <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 border-b theme-border pb-4 mb-8">
                                <Edit3 className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> 
                                {editando ? 'Modificar Matriz' : 'Selección Activa_'}
                            </h2>

                            {editando ? (
                                <form onSubmit={guardarCambios} className="space-y-8 animate-fade-in">
                                    <div className="space-y-6">
                                        <div className="space-y-2">
                                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Comisión Vendedora ($)</label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-500" />
                                                <input 
                                                    type="number" step="0.01" required
                                                    value={data.monto_vendedora}
                                                    onChange={e => setData('monto_vendedora', e.target.value)}
                                                    className="w-full pl-12 p-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 focus:ring-[var(--color-primario)]"
                                                    onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                                    onBlur={(e) => e.target.style.borderColor = ''}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1 flex items-center gap-1.5">
                                                <ShieldCheck className="w-3 h-3 text-amber-500" /> Comisión Heredada ($)
                                            </label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-amber-500" />
                                                <input 
                                                    type="number" step="0.01" required
                                                    value={data.monto_original}
                                                    onChange={e => setData('monto_original', e.target.value)}
                                                    className="w-full pl-12 p-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 focus:ring-amber-500"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-3">
                                        <button 
                                            type="submit" disabled={processing}
                                            className="w-full py-4 text-white dark:text-black rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50"
                                            style={{ backgroundColor: 'var(--color-primario)' }}
                                        >
                                            <Save className="inline w-4 h-4 mr-2" /> {processing ? 'Transmitiendo...' : 'Actualizar Valores'}
                                        </button>
                                        <button 
                                            type="button" onClick={() => { setEditando(null); reset(); }}
                                            className="w-full py-4 theme-element border theme-border rounded-2xl font-black uppercase text-[10px] tracking-widest theme-text-muted hover:theme-text-main transition-all flex items-center justify-center gap-2"
                                        >
                                            <X className="w-3.5 h-3.5" /> Cancelar Transacción
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="text-center py-16 space-y-4 opacity-30 italic">
                                    <Briefcase className="w-16 h-16 mx-auto theme-text-muted" />
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted px-6 leading-relaxed">
                                        Active el protocolo de edición sobre un proceso de la tabla para reajustar los montos.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* INFO BOX */}
                        <div className="p-6 theme-element border theme-border rounded-[2rem] shadow-sm flex flex-col gap-3 relative overflow-hidden">
                             <div className="absolute inset-0 opacity-[0.03] pointer-events-none">
                                <Info className="w-24 h-24 absolute -right-4 -bottom-4" />
                            </div>
                            <div className="flex items-center gap-2 pb-2 border-b theme-border" style={{ color: 'var(--color-primario)' }}>
                                <Info className="w-4 h-4" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest">Información Crítica</h4>
                            </div>
                            <p className="text-[11px] theme-text-muted font-bold leading-relaxed italic uppercase tracking-tighter">
                                Los cambios aplicados se verán reflejados <span className="theme-text-main font-black">únicamente en solicitudes futuras</span>. La aprobación de TAGS congela el monto al momento de la firma.
                            </p>
                        </div>
                    </div>

                    {/* --- TABLA DE PROCESOS --- */}
                    <div className="page-reveal-comisiones lg:col-span-2">
                        <div className="theme-surface border-2 theme-border rounded-[3rem] overflow-hidden shadow-sm">
                            <div className="overflow-x-auto custom-scrollbar">
                                <table className="w-full text-left border-collapse min-w-[600px]">
                                    <thead>
                                        <tr className="border-b-2 theme-border">
                                            <th className="p-8 text-[10px] font-black uppercase tracking-widest theme-text-muted">Concepto Operativo_</th>
                                            <th className="p-8 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Vendedora_</th>
                                            <th className="p-8 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Heredados_</th>
                                            <th className="p-8 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acción_</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tabulador.map((item) => (
                                            <tr 
                                                key={item.id} 
                                                className={`border-b theme-border transition-all duration-300 hover:bg-black/5 dark:hover:bg-white/5 ${editando === item.id ? 'bg-zinc-50 dark:bg-zinc-900' : ''}`}
                                            >
                                                <td className="p-8">
                                                    <div className="flex items-center gap-4">
                                                        <div className={`w-12 h-12 theme-element rounded-[1rem] flex items-center justify-center border transition-all duration-500 shadow-sm ${editando === item.id ? 'border-[var(--color-primario)]' : 'theme-border'}`}>
                                                            <Sparkles className="w-5 h-5" style={{ color: editando === item.id ? 'var(--color-primario)' : 'var(--theme-text-muted)' }} />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-black italic theme-text-main uppercase tracking-tighter leading-none">{item.proceso?.nombre || 'PROCESO SIN IDENTIFICAR'}</p>
                                                            <p className="text-[9px] font-black theme-text-muted uppercase tracking-widest mt-2 bg-black/5 dark:bg-white/5 w-fit px-2 py-0.5 rounded-md border theme-border">REFERENCIA: {item.id}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="p-8 text-center">
                                                    <span className="text-sm font-black theme-text-main italic block" style={{ color: editando === item.id ? 'var(--color-primario)' : '' }}>
                                                        {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(item.monto_vendedora || item.monto_comision)}
                                                    </span>
                                                </td>
                                                <td className="p-8 text-center">
                                                    <span className={`text-sm font-black italic block ${item.monto_original > 0 ? 'text-amber-500' : 'theme-text-muted opacity-30'}`}>
                                                        {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(item.monto_original || 0)}
                                                    </span>
                                                </td>
                                                <td className="p-8 text-center">
                                                    <button 
                                                        onClick={() => iniciarEdicion(item)}
                                                        className="p-4 theme-element border-2 theme-border rounded-2xl hover:text-white transition-all shadow-sm active:scale-90"
                                                        style={{ 
                                                            borderColor: editando === item.id ? 'var(--color-primario)' : '',
                                                            backgroundColor: editando === item.id ? 'var(--color-primario)' : ''
                                                        }}
                                                    >
                                                        <Edit3 className="w-5 h-5" style={{ color: editando === item.id ? (auth.tema_visual?.modo === 'dark' ? '#000' : '#fff') : 'var(--theme-text-main)' }} />
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
            </div>

            <style>{`
                .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                @keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
                
                .custom-scrollbar::-webkit-scrollbar { height: 6px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
                .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); }
            `}</style>
        </AppLayout>
    );
}