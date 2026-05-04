import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Settings, Plus, Edit2, Trash2, 
    X, Sparkles, FolderKanban, DollarSign 
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Catalogos({ auth }) {
    const [tabActiva, setTabActiva] = useState('procesos'); // 'procesos' | 'tabuladores'
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemAEditar, setItemAEditar] = useState(null);

    // Datos de prueba (mientras conectas tu backend)
    const [procesos, setProcesos] = useState([
        { id: 1, nombre: 'Asignación TAG', descripcion: 'Proceso de vinculación de dispositivo' },
        { id: 2, nombre: 'Reactivación', descripcion: 'Reactivación de cuenta suspendida' },
    ]);

    const [tabuladores, setTabuladores] = useState([
        { id: 1, nombre: 'Tarifa Base 2026', monto: '1,500.00' },
        { id: 2, nombre: 'Tarifa Premium', monto: '3,200.00' },
    ]);

    useEffect(() => {
        animate('.tab-content', {
            translateY: [10, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 500
        });
    }, [tabActiva]);

    const abrirModalNuevo = () => {
        setItemAEditar(null);
        setModalAbierto(true);
    };

    const iniciarEdicion = (item) => {
        setItemAEditar(item);
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setItemAEditar(null);
        setModalAbierto(false);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Catálogos | GELIANV" />

            {/* MODAL DE CREACIÓN / EDICIÓN */}
            {modalAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center theme-overlay p-4 transition-all">
                    <div className="theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-10 shadow-2xl max-w-md w-full relative tab-content m-auto">
                        
                        <button onClick={cerrarModal} className="absolute top-6 right-6 p-3 theme-element theme-hover-bg theme-text-muted hover:text-pink-500 rounded-2xl transition-all z-20">
                            <X className="w-5 h-5" />
                        </button>

                        <form onSubmit={(e) => { e.preventDefault(); cerrarModal(); }} className="space-y-6">
                            <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center mb-8">
                                <Sparkles className="inline w-6 h-6 mr-3 text-pink-500" /> 
                                {itemAEditar ? 'Editar' : 'Nuevo'} {tabActiva === 'procesos' ? 'Proceso' : 'Tabulador'}
                            </h2>

                            <div className="space-y-3">
                                <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Nombre_</label>
                                <input 
                                    type="text" 
                                    defaultValue={itemAEditar?.nombre || ''}
                                    placeholder={`Ej. ${tabActiva === 'procesos' ? 'Cambio de Lista' : 'Tarifa Especial'}`} 
                                    className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" 
                                />
                            </div>

                            {tabActiva === 'procesos' ? (
                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Descripción_</label>
                                    <input 
                                        type="text" 
                                        defaultValue={itemAEditar?.descripcion || ''}
                                        placeholder="Breve descripción del proceso..." 
                                        className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" 
                                    />
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Monto ($)_</label>
                                    <input 
                                        type="number" 
                                        defaultValue={itemAEditar?.monto?.replace(',', '') || ''}
                                        placeholder="0.00" 
                                        className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" 
                                    />
                                </div>
                            )}

                            <div className="pt-4">
                                <button type="submit" className="w-full py-5 bg-pink-600 hover:bg-pink-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20">
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* VISTA PRINCIPAL */}
            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-10">
                
                <header className="space-y-4 tab-content">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Configuración Central</p>
                    </div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                            ADMINISTRACIÓN DE <span className="text-pink-500">CATÁLOGOS</span>
                        </h1>
                        <button onClick={abrirModalNuevo} className="flex items-center justify-center gap-2 px-8 py-4 bg-pink-600 hover:bg-pink-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20">
                            <Plus className="w-4 h-4" /> Nuevo Registro
                        </button>
                    </div>
                </header>

                {/* SISTEMA DE PESTAÑAS (TABS) */}
                <div className="flex gap-4 p-2 theme-surface border theme-border rounded-3xl w-fit tab-content">
                    <button 
                        onClick={() => setTabActiva('procesos')}
                        className={`flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all ${tabActiva === 'procesos' ? 'bg-zinc-900 dark:bg-white text-white dark:text-black shadow-md' : 'theme-text-muted hover:theme-text-main hover:theme-element'}`}
                    >
                        <FolderKanban className="w-4 h-4" /> Procesos
                    </button>
                    <button 
                        onClick={() => setTabActiva('tabuladores')}
                        className={`flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all ${tabActiva === 'tabuladores' ? 'bg-zinc-900 dark:bg-white text-white dark:text-black shadow-md' : 'theme-text-muted hover:theme-text-main hover:theme-element'}`}
                    >
                        <DollarSign className="w-4 h-4" /> Tabuladores
                    </button>
                </div>

                {/* TABLA DE CONTENIDO */}
                <div className="tab-content theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Nombre_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                        {tabActiva === 'procesos' ? 'Descripción_' : 'Monto_'}
                                    </th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right pr-4">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(tabActiva === 'procesos' ? procesos : tabuladores).map((item) => (
                                    <tr key={item.id} className="border-b theme-border theme-hover-bg transition-colors">
                                        <td className="py-6 pl-4 font-bold text-xs theme-text-muted">#{item.id}</td>
                                        <td className="py-6 font-bold text-sm theme-text-main">{item.nombre}</td>
                                        <td className="py-6 text-xs font-bold theme-text-muted">
                                            {tabActiva === 'procesos' ? item.descripcion : <span className="font-black italic theme-text-main">${item.monto}</span>}
                                        </td>
                                        <td className="py-6 pr-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button onClick={() => iniciarEdicion(item)} className="p-3 theme-element theme-hover-bg rounded-xl transition-colors">
                                                    <Edit2 className="w-4 h-4 text-amber-500" />
                                                </button>
                                                <button className="p-3 theme-element theme-hover-bg rounded-xl transition-colors">
                                                    <Trash2 className="w-4 h-4 text-red-500" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        
                        {(tabActiva === 'procesos' ? procesos : tabuladores).length === 0 && (
                            <div className="py-16 text-center">
                                <Settings className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                <h3 className="text-lg font-black italic uppercase theme-text-main">Sin registros</h3>
                                <p className="text-xs font-bold theme-text-muted mt-2">Agrega tu primer registro usando el botón superior.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* ESTILOS MAESTROS - Igual que en solicitudes para garantizar contraste */}
            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-hover-bg:hover { background-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                .theme-overlay { background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-hover-bg:hover { background-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }
                .dark .theme-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
            `}</style>
        </AppLayout>
    );
}