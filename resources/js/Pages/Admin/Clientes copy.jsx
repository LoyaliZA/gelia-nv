import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Users, Upload, Search, Filter, 
    FileSpreadsheet, AlertTriangle, TrendingUp, 
    ChevronDown, Download, CheckCircle, ShieldAlert 
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Clientes({ auth, clientes = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [dragActive, setDragActive] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        archivo: null,
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

    const handleUpload = (e) => {
        e.preventDefault();
        // Lógica para enviar el Excel al controlador que tu amigo empata
        post(route('admin.clientes.importar'), {
            onSuccess: () => {
                reset();
                alert('Base de datos actualizada correctamente');
            }
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Clientes | GELIANV" />

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                {/* HEADER */}
                <header className="fade-up space-y-4">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Base de Datos Wizerp</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors">
                        SISTEMA DE <span className="text-pink-500">CLIENTES</span>
                    </h1>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
                    
                    {/* PANEL DE CARGA MASIVA */}
                    <div className="fade-up lg:col-span-1 space-y-6">
                        <div className={`theme-surface border-2 ${dragActive ? 'border-pink-500' : 'theme-border'} rounded-[2.5rem] p-8 shadow-sm transition-all relative overflow-hidden`}>
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center mb-6">
                                <Upload className="w-5 h-5 mr-3 text-pink-500" />
                                Carga Masiva
                            </h2>
                            
                            <form onSubmit={handleUpload} className="space-y-6">
                                <div 
                                    className="border-2 border-dashed theme-border rounded-3xl p-10 flex flex-col items-center justify-center text-center space-y-4 hover:bg-pink-500/5 transition-colors cursor-pointer group"
                                    onDragOver={() => setDragActive(true)}
                                    onDragLeave={() => setDragActive(false)}
                                >
                                    <div className="w-16 h-16 theme-element rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <FileSpreadsheet className="w-8 h-8 theme-text-muted group-hover:text-pink-500" />
                                    </div>
                                    <div>
                                        <p className="text-xs font-black theme-text-main uppercase">Suelte el archivo aquí_</p>
                                        <p className="text-[10px] theme-text-muted italic mt-1 uppercase font-bold">Formatos: .xlsx, .csv</p>
                                    </div>
                                    <input 
                                        type="file" 
                                        className="hidden" 
                                        onChange={e => setData('archivo', e.target.files[0])}
                                    />
                                    <button type="button" className="text-[9px] font-black uppercase tracking-widest text-pink-500 underline">O examinar archivos</button>
                                </div>

                                {data.archivo && (
                                    <div className="flex items-center gap-3 p-4 theme-element rounded-2xl border-2 border-emerald-500/20">
                                        <CheckCircle className="w-4 h-4 text-emerald-500" />
                                        <span className="text-[10px] font-bold theme-text-main truncate">{data.archivo.name}</span>
                                    </div>
                                )}

                                <button 
                                    disabled={processing || !data.archivo}
                                    className="w-full py-4 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all disabled:opacity-30"
                                >
                                    {processing ? 'Sincronizando...' : 'Actualizar Base de Datos'}
                                </button>
                            </form>

                            <div className="mt-8 p-6 theme-element rounded-3xl space-y-4">
                                <div className="flex items-center gap-2 text-amber-500">
                                    <AlertTriangle className="w-4 h-4" />
                                    <p className="text-[9px] font-black uppercase tracking-widest italic">Nota de Auditoría_</p>
                                </div>
                                <p className="text-[10px] theme-text-muted font-bold leading-relaxed italic">
                                    El sistema detectará automáticamente cambios en el monto de venta y registrará la diferencia en el historial.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* LISTADO DE CLIENTES */}
                    <div className="fade-up lg:col-span-2 space-y-6">
                        <div className="flex flex-col md:flex-row gap-4 items-center justify-between theme-surface border-2 theme-border p-4 rounded-[2rem] shadow-sm">
                            <div className="relative w-full">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                <input 
                                    type="text" 
                                    placeholder="Buscar por número o nombre de cliente..." 
                                    value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    className="w-full pl-12 pr-4 py-3 theme-element border theme-border rounded-xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder text-xs"
                                />
                            </div>
                        </div>

                        <div className="space-y-4">
                            {/* Card de ejemplo (Luego se mapeará con la prop clientes) */}
                            <div className="theme-surface border-2 theme-border p-6 rounded-[2rem] flex flex-col md:flex-row items-center justify-between gap-6 hover:border-pink-500 transition-all group">
                                <div className="flex items-center gap-6 w-full md:w-auto">
                                    <div className="w-16 h-16 theme-element border-2 theme-border rounded-2xl flex items-center justify-center font-black italic theme-text-main">
                                        #4521
                                    </div>
                                    <div>
                                        <h3 className="text-xl font-black italic theme-text-main uppercase tracking-tighter">Papelería La Choca</h3>
                                        <div className="flex items-center gap-3 mt-1">
                                            <span className="px-2 py-0.5 bg-blue-500/10 text-blue-500 text-[9px] font-black uppercase tracking-widest rounded-md">Lista Plata</span>
                                            <span className="flex items-center gap-1 text-[10px] font-bold theme-text-muted">
                                                <TrendingUp className="w-3 h-3 text-emerald-500" /> $45,200.00 MXN
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 w-full md:w-auto justify-end">
                                    <div className="p-4 theme-element rounded-2xl flex flex-col items-end">
                                        <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Estado Heredado_</p>
                                        <p className="text-[10px] font-black text-amber-500 uppercase italic">Protegido</p>
                                    </div>
                                    <button className="p-4 theme-element border-2 theme-border rounded-2xl hover:border-pink-500 transition-all">
                                        <Download className="w-5 h-5 theme-text-muted" />
                                    </button>
                                </div>
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
                .theme-placeholder::placeholder { color: #a1a1aa; }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }
            `}</style>
        </AppLayout>
    );
}