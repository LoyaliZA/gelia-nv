import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Settings, Plus, Edit2, Trash2, 
    X, Sparkles, FolderKanban, DollarSign 
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Catalogos({ auth }) {
    const [tabActiva, setTabActiva] = useState('procesos');
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemAEditar, setItemAEditar] = useState(null);
    
    const modalRef = useRef(null);

    const [procesos, setProcesos] = useState([
        { id: 1, nombre: 'Asignación TAG', descripcion: 'Proceso de vinculación de dispositivo' },
        { id: 2, nombre: 'Reactivación', descripcion: 'Reactivación de cuenta suspendida' },
    ]);

    const [tabuladores, setTabuladores] = useState([
        { id: 1, nombre: 'Tarifa Base 2026', monto: '1,500.00' },
        { id: 2, nombre: 'Tarifa Premium', monto: '3,200.00' },
    ]);

    useEffect(() => {
        animate('.tabla-contenido', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 50
        });
    }, [tabActiva]);

    useEffect(() => {
        if (modalAbierto && modalRef.current) {
            animate(modalRef.current, {
                scale: [0.9, 1],
                opacity: [0, 1],
                easing: 'easeOutElastic(1, .8)',
                duration: 800
            });
            animate('.modal-overlay', {
                opacity: [0, 1],
                easing: 'linear',
                duration: 300
            });
        }
    }, [modalAbierto]);

    const abrirModalNuevo = () => {
        setItemAEditar(null);
        setModalAbierto(true);
    };

    const iniciarEdicion = (item) => {
        setItemAEditar(item);
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        if (modalRef.current) {
            animate(modalRef.current, {
                scale: [1, 0.95],
                opacity: [1, 0],
                easing: 'easeInExpo',
                duration: 300
            });
            animate('.modal-overlay', {
                opacity: [1, 0],
                easing: 'linear',
                duration: 300,
                complete: () => {
                    setItemAEditar(null);
                    setModalAbierto(false);
                }
            });
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Catálogos | GELIANV" />

            {/* MODAL 100% CLARO */}
            {modalAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div 
                        className="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm modal-overlay"
                        onClick={cerrarModal}
                    ></div>
                    
                    <div 
                        ref={modalRef}
                        className="bg-white border border-zinc-200 rounded-[3rem] p-8 md:p-10 shadow-2xl max-w-md w-full relative z-10"
                    >
                        <button onClick={cerrarModal} className="absolute top-6 right-6 p-3 bg-zinc-50 rounded-2xl hover:text-pink-500 transition-colors">
                            <X className="w-5 h-5 text-zinc-500" />
                        </button>

                        <form onSubmit={(e) => { e.preventDefault(); cerrarModal(); }} className="space-y-6">
                            <h2 className="text-2xl font-black italic text-zinc-900 uppercase tracking-tighter flex items-center mb-8">
                                <Sparkles className="inline w-6 h-6 mr-3 text-pink-500" /> 
                                {itemAEditar ? 'Editar' : 'Nuevo'} {tabActiva === 'procesos' ? 'Proceso' : 'Tabulador'}
                            </h2>

                            <div className="space-y-3">
                                <label className="text-[10px] font-black uppercase text-zinc-500 ml-2 tracking-widest italic">Nombre_</label>
                                <input 
                                    type="text" 
                                    defaultValue={itemAEditar?.nombre || ''}
                                    placeholder={`Ej. ${tabActiva === 'procesos' ? 'Cambio de Lista' : 'Tarifa Especial'}`} 
                                    className="w-full p-4 bg-zinc-50 border border-zinc-200 rounded-2xl text-zinc-900 font-bold focus:border-pink-500 outline-none transition-all text-sm" 
                                />
                            </div>

                            {tabActiva === 'procesos' ? (
                                <div className="space-y-3">
                                    <label className="text-[10px] font-black uppercase text-zinc-500 ml-2 tracking-widest italic">Descripción_</label>
                                    <input 
                                        type="text" 
                                        defaultValue={itemAEditar?.descripcion || ''}
                                        placeholder="Breve descripción del proceso..." 
                                        className="w-full p-4 bg-zinc-50 border border-zinc-200 rounded-2xl text-zinc-900 font-bold focus:border-pink-500 outline-none transition-all text-sm" 
                                    />
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <label className="text-[10px] font-black uppercase text-zinc-500 ml-2 tracking-widest italic">Monto ($)_</label>
                                    <input 
                                        type="number" 
                                        defaultValue={itemAEditar?.monto?.replace(',', '') || ''}
                                        placeholder="0.00" 
                                        className="w-full p-4 bg-zinc-50 border border-zinc-200 rounded-2xl text-zinc-900 font-bold focus:border-pink-500 outline-none transition-all text-sm" 
                                    />
                                </div>
                            )}

                            <div className="pt-4">
                                <button type="submit" className="w-full py-5 bg-zinc-900 hover:bg-black text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl hover:scale-[1.02]">
                                    Guardar Cambios_
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* VISTA PRINCIPAL 100% CLARA */}
            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-10">
                <header className="space-y-4 fade-up">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Configuración Central</p>
                    </div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase text-zinc-900 leading-tight">
                            ADMINISTRACIÓN DE <span className="text-pink-500">CATÁLOGOS</span>
                        </h1>
                        <button onClick={abrirModalNuevo} className="flex items-center justify-center gap-2 px-8 py-4 bg-zinc-900 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:scale-105 transition-all">
                            <Plus className="w-4 h-4" /> Nuevo Registro_
                        </button>
                    </div>
                </header>

                <div className="flex gap-4 p-2 bg-white border border-zinc-200 rounded-3xl w-fit fade-up shadow-sm">
                    <button 
                        onClick={() => setTabActiva('procesos')}
                        className={`flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all ${tabActiva === 'procesos' ? 'bg-pink-500 text-white shadow-md' : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900'}`}
                    >
                        <FolderKanban className="w-4 h-4" /> Procesos
                    </button>
                    <button 
                        onClick={() => setTabActiva('tabuladores')}
                        className={`flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all ${tabActiva === 'tabuladores' ? 'bg-pink-500 text-white shadow-md' : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-900'}`}
                    >
                        <DollarSign className="w-4 h-4" /> Tabuladores
                    </button>
                </div>

                <div className="bg-white border border-zinc-200 rounded-[3rem] p-8 md:p-12 shadow-sm overflow-hidden fade-up">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-zinc-200">
                                    <th className="pb-6 pl-4 text-[10px] font-black uppercase tracking-widest text-zinc-400">ID_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest text-zinc-400">Nombre_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                        {tabActiva === 'procesos' ? 'Descripción_' : 'Monto_'}
                                    </th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest text-zinc-400 text-right pr-4">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(tabActiva === 'procesos' ? procesos : tabuladores).map((item) => (
                                    <tr key={item.id} className="border-b border-zinc-100 hover:bg-zinc-50 transition-colors tabla-contenido">
                                        <td className="py-6 pl-4 font-bold text-xs text-zinc-400">#{item.id}</td>
                                        <td className="py-6 font-black text-sm text-zinc-900 uppercase">{item.nombre}</td>
                                        <td className="py-6 text-xs font-bold text-zinc-500">
                                            {tabActiva === 'procesos' ? item.descripcion : <span className="font-black italic text-zinc-900">${item.monto}</span>}
                                        </td>
                                        <td className="py-6 pr-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button onClick={() => iniciarEdicion(item)} className="p-3 bg-white border border-zinc-200 hover:border-amber-500 hover:bg-amber-50 rounded-xl transition-all group">
                                                    <Edit2 className="w-4 h-4 text-zinc-400 group-hover:text-amber-500" />
                                                </button>
                                                <button className="p-3 bg-white border border-zinc-200 hover:border-red-500 hover:bg-red-50 rounded-xl transition-all group">
                                                    <Trash2 className="w-4 h-4 text-zinc-400 group-hover:text-red-500" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        
                        {(tabActiva === 'procesos' ? procesos : tabuladores).length === 0 && (
                            <div className="py-16 text-center tabla-contenido">
                                <Settings className="w-12 h-12 text-zinc-300 mx-auto mb-4 opacity-50" />
                                <h3 className="text-lg font-black italic uppercase text-zinc-900">Sin registros</h3>
                                <p className="text-xs font-bold text-zinc-500 mt-2">Agrega tu primer registro usando el botón superior.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}