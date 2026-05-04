import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { LinkIcon, Copy, CheckCircle, Database, Users, Settings } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function AdminDashboard() {
    const [tabActiva, setTabActiva] = useState('enlaces'); // 'enlaces', 'catalogos', 'usuarios'

    // --- Estado para el Módulo de Enlaces ---
    const [rolSeleccionado, setRolSeleccionado] = useState('Vendedor');
    const [enlaceGenerado, setEnlaceGenerado] = useState('');
    const [copiado, setCopiado] = useState(false);
    const [cargando, setCargando] = useState(false);
    const rolesDisponibles = ['Vendedor', 'Contador', 'Auxiliar', 'Encargado de TAGS'];

    const generarEnlace = async () => {
        setCargando(true);
        setEnlaceGenerado('');
        setCopiado(false);

        try {
            const response = await axios.post(route('registro.generar_enlace'), { role_name: rolSeleccionado });
            setEnlaceGenerado(response.data.enlace);
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

    // --- Componentes Modulares (Internos por ahora para simplicidad, pueden separarse luego) ---
    const ModuloEnlaces = () => (
        <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm max-w-2xl animate-fade-in">
            <h2 className="text-xl font-medium text-black dark:text-white mb-6 flex items-center">
                <LinkIcon className="w-5 h-5 mr-2" style={{ color: 'var(--color-primario)' }} />
                Generar Enlace de Registro
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Genera un enlace válido por 48 horas. La persona que lo use será registrada automáticamente con el rol seleccionado.
            </p>

            <div className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Seleccionar Rol</label>
                    <select 
                        value={rolSeleccionado} 
                        onChange={(e) => setRolSeleccionado(e.target.value)}
                        className="w-full p-3.5 bg-gray-50 dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 focus:border-black text-black dark:text-white"
                    >
                        {rolesDisponibles.map(rol => (
                            <option key={rol} value={rol}>{rol}</option>
                        ))}
                    </select>
                </div>

                <button 
                    onClick={generarEnlace} 
                    disabled={cargando}
                    className="w-full py-3.5 text-white rounded-xl font-medium transition-colors disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario, #000)' }}
                >
                    {cargando ? 'Generando...' : 'Crear Enlace Seguro'}
                </button>

                {enlaceGenerado && (
                    <div className="mt-6 p-4 bg-gray-50 dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl">
                        <label className="block text-xs font-bold text-gray-500 uppercase mb-2">Enlace Generado</label>
                        <div className="flex items-center gap-2">
                            <input type="text" readOnly value={enlaceGenerado} className="flex-1 p-2 text-sm bg-transparent border-none outline-none text-gray-700 dark:text-gray-300 truncate" />
                            <button onClick={copiarAlPortapapeles} className="p-2 bg-white dark:bg-black border border-gray-200 dark:border-[#333] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                {copiado ? <CheckCircle className="w-4 h-4 text-green-500" /> : <Copy className="w-4 h-4 text-black dark:text-white" />}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );

    const ModuloCatalogos = () => (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
            {/* Card para Catálogo de Procesos */}
            <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm">
                <h3 className="text-lg font-medium text-black dark:text-white mb-4 flex items-center">
                    <Settings className="w-5 h-5 mr-2 text-gray-500" />
                    Procesos de Solicitud
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Administra los tipos de solicitudes disponibles (ej: Cambio de lista, Asignar nuevo).</p>
                <button className="px-4 py-2 border border-gray-300 dark:border-[#555] rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#1A1A1A] transition-colors dark:text-white">Gestionar Procesos</button>
            </div>

            {/* Card para Listas de Descuento */}
            <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm">
                <h3 className="text-lg font-medium text-black dark:text-white mb-4 flex items-center">
                    <Database className="w-5 h-5 mr-2 text-gray-500" />
                    Listas de Descuento
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Define los nombres de las listas (Bronce, Plata, Oro) y sus montos mínimos requeridos.</p>
                <button className="px-4 py-2 border border-gray-300 dark:border-[#555] rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#1A1A1A] transition-colors dark:text-white">Gestionar Listas</button>
            </div>
            
             {/* Card para Estados */}
             <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm md:col-span-2">
                <h3 className="text-lg font-medium text-black dark:text-white mb-4 flex items-center">
                    <Settings className="w-5 h-5 mr-2 text-gray-500" />
                    Estados de Solicitud
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Controla los estados por los que transita una solicitud (Pendiente, Verificada, etc).</p>
                <button className="px-4 py-2 border border-gray-300 dark:border-[#555] rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#1A1A1A] transition-colors dark:text-white">Gestionar Estados</button>
            </div>
        </div>
    );

    const ModuloUsuarios = () => (
        <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm animate-fade-in">
             <h2 className="text-xl font-medium text-black dark:text-white mb-6 flex items-center">
                <Users className="w-5 h-5 mr-2 text-gray-500" />
                Gestión de Usuarios
            </h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">Aquí irá la tabla de usuarios registrados en el sistema, donde podrás editar sus roles o darlos de baja.</p>
            {/* Espacio reservado para la tabla de usuarios que implementaremos después */}
        </div>
    );

    return (
        <AppLayout>
            <Head title="Panel de Administrador" />
            
            <div className="mb-8">
                <h1 className="text-3xl font-light tracking-tight text-black dark:text-white">Panel de Administración</h1>
                <p className="text-gray-500 dark:text-gray-400 mt-2">Gestión general del sistema y configuraciones avanzadas.</p>
            </div>

            {/* Pestañas de Navegación */}
            <div className="flex space-x-2 mb-8 border-b border-gray-200 dark:border-[#333] pb-4 overflow-x-auto">
                <button 
                    onClick={() => setTabActiva('enlaces')}
                    className={`px-5 py-2.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap ${tabActiva === 'enlaces' ? 'bg-black text-white dark:bg-white dark:text-black' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#1A1A1A]'}`}
                >
                    <LinkIcon className="inline-block w-4 h-4 mr-2 mb-0.5" /> Enlaces de Registro
                </button>
                <button 
                    onClick={() => setTabActiva('catalogos')}
                    className={`px-5 py-2.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap ${tabActiva === 'catalogos' ? 'bg-black text-white dark:bg-white dark:text-black' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#1A1A1A]'}`}
                >
                    <Database className="inline-block w-4 h-4 mr-2 mb-0.5" /> Catálogos
                </button>
                <button 
                    onClick={() => setTabActiva('usuarios')}
                    className={`px-5 py-2.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap ${tabActiva === 'usuarios' ? 'bg-black text-white dark:bg-white dark:text-black' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-[#1A1A1A]'}`}
                >
                    <Users className="inline-block w-4 h-4 mr-2 mb-0.5" /> Usuarios del Sistema
                </button>
            </div>

            {/* Renderizado Condicional del Contenido */}
            <div className="min-h-[400px]">
                {tabActiva === 'enlaces' && <ModuloEnlaces />}
                {tabActiva === 'catalogos' && <ModuloCatalogos />}
                {tabActiva === 'usuarios' && <ModuloUsuarios />}
            </div>

            <style jsx>{`
                .animate-fade-in {
                    animation: fadeIn 0.4s ease-out forwards;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </AppLayout>
    );
}