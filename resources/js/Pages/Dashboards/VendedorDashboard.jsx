import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate, createScope } from 'animejs';
import { Users, Briefcase, Sparkles, Send, AlertTriangle, CheckCircle, Clock, XCircle } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function VendedorDashboard({ solicitudes = [], procesos = [] }) {
    const root = useRef(null);
    const scope = useRef(null);

    // Estado para las pestañas
    const [filtroActivo, setFiltroActivo] = useState('TODO');
    
    // Estado simulado del cliente consultado en Wizerp
    const [clienteConsultado, setClienteConsultado] = useState(null);

    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        observaciones_vendedor: '',
    });

    // Simulación de búsqueda de cliente al salir del campo "Número de Cliente"
    const buscarClienteWizerp = () => {
        if (!data.numero_cliente) return;
        
        // Aquí iría la petición real a tu backend (ej. axios.get('/api/wizerp/cliente/...'))
        // Simulación: Si el cliente es "CLI-002", es heredado.
        if (data.numero_cliente === 'CLI-002') {
            setClienteConsultado({ nombre: 'María López', lista: 'Plata', es_heredado: true });
            setData('nombre_cliente', 'María López');
        } else {
            setClienteConsultado({ nombre: 'Juan Pérez', lista: 'Bronce', es_heredado: false });
            setData('nombre_cliente', 'Juan Pérez');
        }
    };

    // Validaciones y Envío
    const handleSubmit = (e) => {
        e.preventDefault();
        clearErrors();

        // Candado de Seguridad: Cliente Heredado vs Reactivación Normal
        const procesoSeleccionado = procesos.find(p => p.id == data.catalogo_proceso_id);
        
        if (clienteConsultado?.es_heredado && procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO') {
            setError('catalogo_proceso_id', 'ALERTA DE SEGURIDAD: Este es un Cliente Heredado. No puedes solicitar una reactivación normal.');
            return; // Bloquea la ejecución y no envía el formulario
        }

        post(route('solicitudes.store'), {
            onSuccess: () => {
                reset();
                setClienteConsultado(null);
            },
        });
    };

    // Animaciones iniciales
    useEffect(() => {
        scope.current = createScope({ root }).add(() => {
            animate('.main-content', {
                translateY: [20, 0], opacity: [0, 1], easing: 'outExpo', duration: 1000,
            });
        });
        return () => scope.current.revert();
    }, []);

    // Filtrado en vivo de las solicitudes
    const solicitudesFiltradas = solicitudes.filter(sol => {
        if (filtroActivo === 'TODO') return true;
        return sol.estado === filtroActivo;
    });

    return (
        <AppLayout>
            <Head title="Panel de Vendedor" />

            <div ref={root}>
                <div className="main-content" style={{ opacity: 0 }}>
                    
                    <div className="mb-10">
                        <h1 className="text-3xl font-light tracking-tight text-black dark:text-white">Mi Panel de Gestión</h1>
                        <p className="text-gray-500 mt-2">Solicita asignación de TAGS y cambios de lista de descuento.</p>
                    </div>

                    {/* Formulario de Solicitud */}
                    <div className="bg-white border border-gray-200 rounded-3xl p-8 shadow-sm mb-12 relative overflow-hidden">
                        <h2 className="text-xl font-medium text-black mb-6 flex items-center">
                            <Sparkles className="w-5 h-5 mr-2" style={{ color: 'var(--color-primario)' }} />
                            Nueva Solicitud
                        </h2>

                        <form onSubmit={handleSubmit} className="space-y-6 max-w-3xl">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Número de Cliente</label>
                                    <input 
                                        type="text" 
                                        value={data.numero_cliente}
                                        onChange={e => setData('numero_cliente', e.target.value)}
                                        onBlur={buscarClienteWizerp}
                                        placeholder="Ej. CLI-002 para simular heredado" 
                                        className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black"
                                    />
                                    {clienteConsultado && (
                                        <p className="text-xs mt-2 text-gray-500">
                                            Lista actual Wizerp: <span className="font-bold text-black">{clienteConsultado.lista}</span>
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Nombre del Cliente</label>
                                    <input 
                                        type="text" 
                                        value={data.nombre_cliente}
                                        onChange={e => setData('nombre_cliente', e.target.value)}
                                        className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black"
                                    />
                                </div>
                            </div>

                            {/* Alerta Visual de Cliente Heredado */}
                            {clienteConsultado?.es_heredado && (
                                <div className="p-4 bg-red-50 border border-red-200 rounded-xl flex items-start">
                                    <AlertTriangle className="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <h4 className="text-sm font-bold text-red-800">Atención: Cliente Heredado detectado.</h4>
                                        <p className="text-xs text-red-600 mt-1">El valor de este cliente es menor según las políticas. Selecciona el proceso correspondiente.</p>
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Monto Cotizado ($)</label>
                                    <input 
                                        type="number" step="0.01"
                                        value={data.monto_cotizado}
                                        onChange={e => setData('monto_cotizado', e.target.value)}
                                        placeholder="0.00" 
                                        className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Proceso a Solicitar</label>
                                    <select 
                                        value={data.catalogo_proceso_id}
                                        onChange={e => setData('catalogo_proceso_id', e.target.value)}
                                        className={`w-full p-3.5 bg-gray-50 border rounded-xl outline-none focus:ring-1 ${errors.catalogo_proceso_id ? 'border-red-500 ring-red-500' : 'border-gray-200 focus:border-black'}`}
                                    >
                                        <option value="">Seleccione una opción...</option>
                                        {procesos.map(proceso => (
                                            <option key={proceso.id} value={proceso.id}>{proceso.nombre}</option>
                                        ))}
                                    </select>
                                    {errors.catalogo_proceso_id && <p className="text-red-600 text-xs mt-2 font-bold">{errors.catalogo_proceso_id}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                                <textarea 
                                    value={data.observaciones_vendedor}
                                    onChange={e => setData('observaciones_vendedor', e.target.value)}
                                    rows="2"
                                    className="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:ring-1 focus:border-black resize-none"
                                ></textarea>
                            </div>

                            <div className="flex justify-end">
                                <button type="submit" disabled={processing} className="px-6 py-3.5 text-white rounded-xl font-medium flex items-center disabled:opacity-50" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {processing ? 'Procesando...' : <><Send className="w-4 h-4 mr-2" /> Enviar Solicitud</>}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Historial de Solicitudes con Pestañas */}
                    <div className="bg-white border border-gray-200 rounded-3xl p-8 shadow-sm">
                        <div className="flex items-center justify-between mb-8 border-b border-gray-100 pb-4">
                            <h2 className="text-xl font-medium text-black">Historial de Solicitudes</h2>
                            
                            {/* Pestañas de filtrado */}
                            <div className="flex space-x-2">
                                {['TODO', 'Pendientes', 'Respondidas', 'Incorrectas'].map(estado => (
                                    <button 
                                        key={estado}
                                        onClick={() => setFiltroActivo(estado)}
                                        className={`px-4 py-2 text-sm font-medium rounded-full transition-colors ${filtroActivo === estado ? 'bg-black text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
                                    >
                                        {estado}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Listado de Solicitudes (Aislado para escalabilidad) */}
                        <div className="space-y-4">
                            {solicitudesFiltradas.length > 0 ? (
                                solicitudesFiltradas.map(sol => (
                                    <div key={sol.id} className="p-4 border border-gray-100 rounded-2xl flex justify-between items-center hover:bg-gray-50 transition-colors">
                                        <div>
                                            <p className="font-medium text-black">{sol.nombre_cliente} <span className="text-gray-400 text-xs ml-2">{sol.numero_cliente}</span></p>
                                            <p className="text-sm text-gray-500 mt-1">{sol.proceso.nombre} • ${sol.monto_cotizado}</p>
                                        </div>
                                        <div className="flex items-center">
                                            {sol.estado === 'Pendientes' && <span className="flex items-center text-yellow-600 text-sm font-medium bg-yellow-50 px-3 py-1 rounded-full"><Clock className="w-4 h-4 mr-1"/> Pendiente</span>}
                                            {sol.estado === 'Respondidas' && <span className="flex items-center text-green-600 text-sm font-medium bg-green-50 px-3 py-1 rounded-full"><CheckCircle className="w-4 h-4 mr-1"/> Respondida</span>}
                                            {sol.estado === 'Incorrectas' && <span className="flex items-center text-red-600 text-sm font-medium bg-red-50 px-3 py-1 rounded-full"><XCircle className="w-4 h-4 mr-1"/> Incorrecta</span>}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-center text-gray-500 py-8">No hay solicitudes en esta categoría.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}