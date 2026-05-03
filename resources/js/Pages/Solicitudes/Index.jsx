import React, { useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate, createScope } from 'animejs';
import { Users, Briefcase, Sparkles, Send } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ solicitudes, procesos }) {
    const root = useRef(null);
    const scope = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        observaciones_vendedor: '',
    });

    useEffect(() => {
        scope.current = createScope({ root }).add(() => {
            // Animación de entrada general
            animate('.main-content', {
                translateY: [20, 0],
                opacity: [0, 1],
                easing: 'outExpo',
                duration: 1000,
            });

            // Animación de los números de las tarjetas
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(el => {
                const targetValue = parseInt(el.getAttribute('data-target') || 0);
                animate(el, {
                    innerHTML: [0, targetValue],
                    easing: 'outExpo',
                    round: 1,
                    duration: 1500,
                    delay: 200
                });
            });
        });

        return () => scope.current.revert();
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('solicitudes.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout>
            <Head title="Panel de Solicitudes" />

            <div ref={root}>
                <div className="main-content" style={{ opacity: 0 }}>
                    {/* Cabecera */}
                    <div className="mb-10">
                        <h1 className="text-3xl font-light tracking-tight text-black dark:text-white">Panel de Solicitudes</h1>
                        <p className="text-gray-500 dark:text-gray-400 mt-2">Gestión de tags y cambios de lista.</p>
                    </div>

                    {/* Tarjetas de Estadísticas */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                        <div className="stats-card p-6 bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-2xl transition-all duration-300">
                            <div className="flex justify-between items-start mb-6">
                                <div className="p-3 bg-gray-50 dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-xl">
                                    <Users className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                </div>
                            </div>
                            <h3 className="text-4xl font-light text-black dark:text-white mb-1 stat-number" data-target={solicitudes?.total || 0}>0</h3>
                            <p className="text-sm text-gray-400 font-medium uppercase tracking-wider">Mis Solicitudes</p>
                        </div>

                        <div className="stats-card p-6 bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-2xl transition-all duration-300">
                            <div className="flex justify-between items-start mb-6">
                                <div className="p-3 bg-gray-50 dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#2A2A2A] rounded-xl">
                                    <Briefcase className="w-6 h-6 text-black dark:text-white" />
                                </div>
                            </div>
                            <h3 className="text-4xl font-light text-black dark:text-white mb-1 stat-number" data-target="15">0</h3>
                            <p className="text-sm text-gray-400 font-medium uppercase tracking-wider">Pendientes de revisión</p>
                        </div>
                    </div>

                    {/* Formulario de Nueva Solicitud */}
                    <div className="bg-white dark:bg-[#141414] border border-gray-200 dark:border-[#2A2A2A] rounded-3xl p-8 shadow-sm">
                        <h2 className="text-xl font-medium text-black dark:text-white mb-6 flex items-center">
                            <Sparkles className="w-5 h-5 mr-2" style={{ color: 'var(--color-primario)' }} />
                            Nueva Solicitud de Tag
                        </h2>

                        <form onSubmit={handleSubmit} className="space-y-6 max-w-2xl">
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Número de Cliente (Opcional)</label>
                                    <input 
                                        type="text" 
                                        value={data.numero_cliente}
                                        onChange={e => setData('numero_cliente', e.target.value)}
                                        placeholder="Ej. CLI-001" 
                                        className="w-full p-3.5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 text-black dark:text-white"
                                    />
                                    {errors.numero_cliente && <p className="text-red-500 text-xs mt-1">{errors.numero_cliente}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nombre del Cliente</label>
                                    <input 
                                        type="text" 
                                        value={data.nombre_cliente}
                                        onChange={e => setData('nombre_cliente', e.target.value)}
                                        placeholder="Nombre completo" 
                                        className="w-full p-3.5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 text-black dark:text-white"
                                    />
                                    {errors.nombre_cliente && <p className="text-red-500 text-xs mt-1">{errors.nombre_cliente}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Monto Cotizado ($)</label>
                                    <input 
                                        type="number" 
                                        step="0.01"
                                        value={data.monto_cotizado}
                                        onChange={e => setData('monto_cotizado', e.target.value)}
                                        placeholder="0.00" 
                                        className="w-full p-3.5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 text-black dark:text-white"
                                    />
                                    {errors.monto_cotizado && <p className="text-red-500 text-xs mt-1">{errors.monto_cotizado}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Proceso a Solicitar</label>
                                    <select 
                                        value={data.catalogo_proceso_id}
                                        onChange={e => setData('catalogo_proceso_id', e.target.value)}
                                        className="w-full p-3.5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 text-black dark:text-white"
                                    >
                                        <option value="">Seleccione una opción...</option>
                                        {procesos?.map(proceso => (
                                            <option key={proceso.id} value={proceso.id}>
                                                {proceso.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.catalogo_proceso_id && <p className="text-red-500 text-xs mt-1 font-bold">{errors.catalogo_proceso_id}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Observaciones</label>
                                <textarea 
                                    value={data.observaciones_vendedor}
                                    onChange={e => setData('observaciones_vendedor', e.target.value)}
                                    rows="3"
                                    className="w-full p-3.5 bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] rounded-xl outline-none focus:ring-1 text-black dark:text-white resize-none"
                                ></textarea>
                                {errors.observaciones_vendedor && <p className="text-red-500 text-xs mt-1">{errors.observaciones_vendedor}</p>}
                            </div>

                            <div className="flex justify-end">
                                <button 
                                    type="submit" 
                                    disabled={processing}
                                    className="px-6 py-3.5 text-white rounded-xl font-medium transition-colors flex justify-center items-center disabled:opacity-50"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    {processing ? 'Procesando...' : (
                                        <>
                                            <Send className="w-4 h-4 mr-2" /> Enviar Solicitud
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}