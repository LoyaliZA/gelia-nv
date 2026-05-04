import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    User, Mail, Phone, Calendar, Camera, 
    Palette, Layout, Save, Sparkles, 
    ShieldCheck, Smartphone, Moon, Sun
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Perfil({ auth }) {
    const { data, setData, post, processing } = useForm({
        name: auth.user?.name || '',
        email: auth.user?.email || '',
        // Campos que empatan con la migración de tu amigo
        apellido_paterno: auth.user?.apellido_paterno || '',
        telefono: auth.user?.telefono || '',
        foto_perfil: null
    });

    useEffect(() => {
        animate('.fade-up', {
            translateY: [20, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 700,
            delay: (el, i) => i * 100
        });
    }, []);

    return (
        <AppLayout auth={auth}>
            <Head title="Mi Perfil | GELIANV" />

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                {/* HEADER DE PERFIL */}
                <header className="fade-up flex flex-col md:flex-row items-center gap-8 border-b theme-border pb-12">
                    <div className="relative group">
                        <div className="w-32 h-32 rounded-[2.5rem] overflow-hidden border-4 border-pink-500 shadow-2xl">
                            {auth.user?.foto_perfil ? (
                                <img src={auth.user.foto_perfil} alt="Perfil" className="w-full h-full object-cover" />
                            ) : (
                                <div className="w-full h-full theme-element flex items-center justify-center">
                                    <User className="w-12 h-12 theme-text-muted" />
                                </div>
                            )}
                        </div>
                        <button className="absolute -bottom-2 -right-2 p-3 bg-zinc-900 text-white rounded-2xl hover:bg-pink-500 transition-colors shadow-xl">
                            <Camera className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="text-center md:text-left space-y-2">
                        <div className="flex items-center justify-center md:justify-start gap-3 text-pink-500">
                            <ShieldCheck className="w-4 h-4" />
                            <p className="text-[10px] font-black uppercase tracking-widest italic">Identidad Verificada_</p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                            {auth.user?.name || 'Usuario'} <span className="text-pink-500">GELIANV</span>
                        </h1>
                        <p className="theme-text-muted font-bold italic uppercase text-xs tracking-widest">
                            Miembro desde: {auth.user?.created_at ? new Date(auth.user.created_at).toLocaleDateString() : '2026'}
                        </p>
                    </div>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    
                    {/* COLUMNA 1: DATOS PERSONALES */}
                    <div className="fade-up lg:col-span-2 space-y-8">
                        <div className="theme-surface border-2 theme-border rounded-[3rem] p-10 shadow-sm space-y-8">
                            <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                <User className="w-6 h-6 mr-3 text-pink-500" />
                                Información General
                            </h2>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted ml-2 italic">Nombre(s)_</label>
                                    <div className="relative">
                                        <User className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                        <input type="text" value={data.name} className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none focus:border-pink-500 transition-all" />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted ml-2 italic">Apellido Paterno_</label>
                                    <input type="text" value={data.apellido_paterno} className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none focus:border-pink-500 transition-all" />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted ml-2 italic">Email Institucional_</label>
                                    <div className="relative">
                                        <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                        <input type="email" value={data.email} readOnly className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-muted font-bold cursor-not-allowed opacity-60" />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted ml-2 italic">Teléfono / WhatsApp_</label>
                                    <div className="relative">
                                        <Smartphone className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                        <input type="text" value={data.telefono} className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none focus:border-pink-500 transition-all" />
                                    </div>
                                </div>
                            </div>

                            <button className="py-4 px-10 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all hover:scale-105 flex items-center gap-3">
                                <Save className="w-4 h-4" /> Guardar Cambios
                            </button>
                        </div>
                    </div>

                    {/* COLUMNA 2: PERSONALIZACIÓN (EL FUTURO) */}
                    <div className="fade-up lg:col-span-1 space-y-8">
                        <div className="theme-surface border-2 border-pink-500/30 rounded-[3rem] p-10 shadow-sm relative overflow-hidden group">
                            <div className="absolute -top-10 -right-10 opacity-5 group-hover:rotate-12 transition-transform duration-700">
                                <Palette className="w-48 h-48 theme-text-main" />
                            </div>

                            <div className="relative z-10 space-y-8">
                                <header>
                                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                        <Palette className="w-5 h-5 mr-3 text-pink-500" />
                                        Interfaz Visual
                                    </h2>
                                    <p className="text-[9px] font-bold theme-text-muted italic uppercase mt-1">Personalización de entorno activo_</p>
                                </header>

                                <div className="space-y-6">
                                    {/* MODO DE COLOR */}
                                    <div className="space-y-3">
                                        <p className="text-[10px] font-black uppercase theme-text-muted italic ml-1">Esquema de Color_</p>
                                        <div className="grid grid-cols-2 gap-3">
                                            <button className="flex items-center justify-center gap-2 p-4 theme-element border-2 border-pink-500 rounded-2xl theme-text-main font-bold transition-all">
                                                <Moon className="w-4 h-4" /> Dark
                                            </button>
                                            <button className="flex items-center justify-center gap-2 p-4 theme-element border-2 theme-border rounded-2xl theme-text-muted font-bold hover:border-pink-500 transition-all">
                                                <Sun className="w-4 h-4" /> Light
                                            </button>
                                        </div>
                                    </div>

                                    {/* SELECCIÓN DE COLOR ACENTO (A FUTURO) */}
                                    <div className="space-y-3">
                                        <p className="text-[10px] font-black uppercase theme-text-muted italic ml-1">Color de Acento (Core)_</p>
                                        <div className="flex gap-3">
                                            <button className="w-8 h-8 rounded-full bg-pink-500 ring-4 ring-pink-500/20"></button>
                                            <button className="w-8 h-8 rounded-full bg-blue-500 opacity-50 hover:opacity-100 transition-opacity"></button>
                                            <button className="w-8 h-8 rounded-full bg-emerald-500 opacity-50 hover:opacity-100 transition-opacity"></button>
                                            <button className="w-8 h-8 rounded-full bg-amber-500 opacity-50 hover:opacity-100 transition-opacity"></button>
                                        </div>
                                    </div>

                                    <div className="p-6 theme-element border-2 border-dashed theme-border rounded-3xl">
                                        <div className="flex items-center gap-2 text-pink-500 mb-2">
                                            <Sparkles className="w-4 h-4" />
                                            <p className="text-[9px] font-black uppercase tracking-widest italic">Próximamente_</p>
                                        </div>
                                        <p className="text-[10px] theme-text-muted font-bold leading-relaxed italic uppercase">
                                            Podrás subir fondos personalizados y cambiar el estilo de las tarjetas.
                                        </p>
                                    </div>
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
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
            `}</style>
        </AppLayout>
    );
}