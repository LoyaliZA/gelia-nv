import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { animate } from 'animejs';
import { 
    Link as LinkIcon, 
    Copy, 
    CheckCircle, 
    Sparkles, 
    ShieldCheck, 
    Clock, 
    ChevronDown 
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Enlaces({ auth }) {
    const [rolSeleccionado, setRolSeleccionado] = useState('Vendedor');
    const [enlaceGenerado, setEnlaceGenerado] = useState('');
    const [copiado, setCopiado] = useState(false);
    const [cargando, setCargando] = useState(false);
    
    const rolesDisponibles = ['Vendedor', 'Contador', 'Auxiliar', 'Encargado de TAGS'];

    useEffect(() => {
        animate('.tab-content', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600
        });
    }, []);

    const generarEnlace = async () => {
        setCargando(true);
        setEnlaceGenerado('');
        setCopiado(false);

        try {
            const response = await axios.post(route('registro.generar_enlace'), { role_name: rolSeleccionado });
            setEnlaceGenerado(response.data.enlace);
            
            setTimeout(() => {
                animate('.result-box', {
                    scale: [0.95, 1],
                    opacity: [0, 1],
                    duration: 400,
                    easing: 'easeOutBack'
                });
            }, 50);
        } catch (error) {
            console.error("Error generando el enlace:", error);
            alert("Hubo un error al generar el enlace.");
        } finally {
            setCargando(false);
        }
    };

    const copiarAlPortapapeles = () => {
        navigator.clipboard.writeText(enlaceGenerado);
        setCopiado(true);
        setTimeout(() => setCopiado(false), 3000);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Generación de Accesos | GELIANV" />
            
            <div className="max-w-4xl mx-auto p-6 md:p-12 space-y-12">
                {/* HEADER */}
                <header className="space-y-4 tab-content">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Protocolo de Seguridad</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                        GENERACIÓN DE <span className="text-pink-500">ACCESOS</span>
                    </h1>
                    <p className="theme-text-muted font-bold italic text-base max-w-2xl">
                        Crea enlaces criptográficos seguros para el registro de nuevos colaboradores.
                    </p>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-5 gap-12 items-start tab-content">
                    {/* PANEL PRINCIPAL */}
                    <div className="lg:col-span-3 theme-surface border-2 theme-border rounded-[3rem] p-10 shadow-sm relative overflow-hidden group/card hover:border-pink-500 transition-all duration-300">
                        <div className="absolute top-0 right-0 p-8 opacity-5">
                            <ShieldCheck className="w-32 h-32 text-pink-500" />
                        </div>

                        <div className="relative z-10 space-y-8">
                            <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                <LinkIcon className="w-6 h-6 mr-3 text-pink-500" />
                                Enlace de Registro
                            </h2>

                            <div className="space-y-6">
                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Seleccionar Rol de Usuario_</label>
                                    
                                    {/* SELECT CON INDICADOR VISUAL */}
                                    <div className="relative group/select">
                                        <select 
                                            value={rolSeleccionado} 
                                            onChange={(e) => setRolSeleccionado(e.target.value)}
                                            className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none appearance-none cursor-pointer transition-all pr-12"
                                        >
                                            {rolesDisponibles.map(rol => (
                                                <option key={rol} value={rol} className="theme-option">
                                                    {rol.toUpperCase()}
                                                </option>
                                            ))}
                                        </select>
                                        <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-400 group-hover/select:text-pink-500 transition-colors">
                                            <ChevronDown className="w-5 h-5" />
                                        </div>
                                    </div>
                                </div>

                                <button 
                                    onClick={generarEnlace} 
                                    disabled={cargando}
                                    className="w-full py-5 bg-pink-600 hover:bg-pink-500 text-white rounded-3xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20 disabled:opacity-50 flex items-center justify-center gap-3"
                                >
                                    {cargando ? <Clock className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                                    {cargando ? 'Generando Token...' : 'Crear Enlace Seguro'}
                                </button>
                            </div>

                            {enlaceGenerado && (
                                <div className="result-box mt-10 p-6 theme-element border-2 theme-border rounded-3xl space-y-4">
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-pink-500 italic">Token Criptográfico Generado_</label>
                                    <div className="flex items-center gap-3">
                                        <input 
                                            type="text" 
                                            readOnly 
                                            value={enlaceGenerado} 
                                            className="flex-1 p-3 bg-transparent border-none outline-none theme-text-main font-bold text-xs truncate" 
                                        />
                                        <button 
                                            onClick={copiarAlPortapapeles} 
                                            className="p-4 theme-surface border theme-border rounded-2xl hover:border-pink-500 transition-all group/copy"
                                            title="Copiar enlace"
                                        >
                                            {copiado ? <CheckCircle className="w-5 h-5 text-emerald-500" /> : <Copy className="w-5 h-5 theme-text-muted group-hover/copy:text-pink-500" />}
                                        </button>
                                    </div>
                                    <p className="text-[9px] font-black uppercase text-emerald-500 italic flex items-center gap-1">
                                        <ShieldCheck className="w-3 h-3" /> Enlace válido por 48 horas bajo firma digital.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* SIDEBAR INFORMATIVO */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="p-8 theme-element border-2 theme-border rounded-[2.5rem] shadow-sm">
                            <div className="flex items-center gap-2 mb-4">
                                <ShieldCheck className="w-4 h-4 theme-text-main" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main">Seguridad Firmada</h4>
                            </div>
                            <p className="text-xs theme-text-muted leading-relaxed font-bold italic">
                                Estos enlaces utilizan URLs firmadas. Si el enlace es alterado incluso en un solo carácter, Laravel invalidará el acceso automáticamente.
                            </p>
                        </div>

                        <div className="p-8 border-2 border-amber-500/10 rounded-[2.5rem] bg-amber-500/5">
                            <div className="flex items-center gap-2 mb-4">
                                <Clock className="w-4 h-4 text-amber-500" />
                                <h4 className="text-[10px] font-black uppercase tracking-widest text-amber-500">Expiración</h4>
                            </div>
                            <p className="text-xs text-amber-600/80 leading-relaxed font-bold italic">
                                Al expirar el tiempo, deberás generar un nuevo token. No se pueden reutilizar enlaces caducados.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                /* Estilos Base */
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                
                select { -webkit-appearance: none; -moz-appearance: none; appearance: none; }
                .theme-option { background-color: white; color: #18181b; }

                /* Modo Oscuro */
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }
                .dark .theme-option { background-color: #1A1A1A; color: white; }
            `}</style>
        </AppLayout>
    );
}