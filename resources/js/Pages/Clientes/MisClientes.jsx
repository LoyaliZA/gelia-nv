import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Users, Plus, X, Hash, User, Calendar, ShieldAlert } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

export default function MisClientes({ auth, clientes }) {
    /*
    |--------------------------------------------------------------------------
    | Validaciones de Seguridad (ABAC)
    |--------------------------------------------------------------------------
    */
    const can = (permiso) => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)');
        return auth?.user?.permissions?.includes(permiso) || isAdmin;
    };

    /*
    |--------------------------------------------------------------------------
    | Estado y Control del Formulario
    |--------------------------------------------------------------------------
    */
    const [modalAbierto, setModalAbierto] = useState(false);
    const [procesandoAccion, setProcesandoAccion] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        numero_cliente: '',
        nombre: '',
    });

    const abrirModal = () => setModalAbierto(true);

    const cerrarModal = () => {
        setModalAbierto(false);
        reset();
        clearErrors();
    };

    const registrarCliente = (e) => {
        e.preventDefault();
        setProcesandoAccion(true);
        post(route('mis_clientes.rapido'), {
            onSuccess: () => cerrarModal(),
            onFinish: () => setProcesandoAccion(false),
            preserveScroll: true,
        });
    };

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */
    const irAPagina = (pagina) => {
        const totalPaginas = clientes.last_page || 1;
        if (pagina < 1 || pagina > totalPaginas) return;
        router.get(route('mis_clientes.index'), { page: pagina }, { preserveState: true, preserveScroll: false });
    };

    /*
    |--------------------------------------------------------------------------
    | Renderizado de la Vista
    |--------------------------------------------------------------------------
    */
    return (
        <AppLayout auth={auth}>
            <Head title="Directorio de Clientes" />
            <GeliaLoader isVisible={procesandoAccion} message="Sincronizando_" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">

                {/* Cabecera Principal (Estilo GELIA) */}
                <header className="animate-page-reveal theme-surface rounded-3xl md:rounded-[2.5rem] p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border theme-border shadow-xl">
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Módulo Operativo</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            MIS <span style={{ color: 'var(--color-primario)' }}>CLIENTES</span>
                        </h1>
                    </div>
                    {can('mis_clientes.gestionar') && (
                        <button onClick={abrirModal} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-5 h-5" /> Alta Rápida
                        </button>
                    )}
                </header>

                {/* Tabla de Datos (Estilo Escritorio) */}
                <div className="animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden bg-white/70 dark:bg-[#121212]/70 backdrop-blur-xl" style={{ animationDelay: '100ms' }}>
                    <div className="overflow-x-auto pb-4 custom-scrollbar">
                        <table className="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr className="border-b theme-border">
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">No. Cliente_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Nombre Completo_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Tipo / Lista Actual_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Fecha de Alta_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!clientes.data || clientes.data.length === 0) ? (
                                    <tr>
                                        <td colSpan="4" className="p-12 text-center text-[11px] font-black uppercase tracking-widest theme-text-muted italic">
                                            Aún no tienes clientes registrados en tu cartera_
                                        </td>
                                    </tr>
                                ) : (
                                    clientes.data.map((cliente) => (
                                        <tr key={cliente.id} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5 group">
                                            <td className="p-6">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">
                                                        {cliente.numero_cliente}
                                                    </span>
                                                    {cliente.es_heredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                                </div>
                                            </td>
                                            <td className="p-6 font-bold text-sm theme-text-main uppercase italic">
                                                {cliente.nombre}
                                            </td>
                                            <td className="p-6">
                                                <div className="inline-block px-3 py-1 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main mb-1">
                                                    {cliente.tipo_cliente ? cliente.tipo_cliente.nombre : 'Público General'}
                                                </div>
                                            </td>
                                            <td className="p-6">
                                                <div className="text-[10px] font-bold theme-text-muted uppercase flex items-center gap-1.5">
                                                    <Calendar className="w-3.5 h-3.5" />
                                                    {new Date(cliente.created_at).toLocaleDateString('es-MX')}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {/* Modal de Registro Rápido */}
            {modalAbierto && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-fade-in" onClick={cerrarModal}>
                    <div className="w-full max-w-lg theme-surface rounded-[2rem] shadow-2xl p-6 md:p-8 modal-pop border theme-border" onClick={(e) => e.stopPropagation()}>



                        <div className="flex justify-between items-center mb-6">
                            <div>
                                <h3 className="text-xl font-black italic tracking-tighter uppercase theme-text-main">
                                    NUEVO <span style={{ color: 'var(--color-primario)' }}>CLIENTE</span>
                                </h3>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mt-1">Sincronización con Wizerp_</p>
                            </div>
                            <button onClick={cerrarModal} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={registrarCliente} className="space-y-5">
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    Número de Cliente (Wizerp)
                                </label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <Hash className="h-4 w-4 theme-text-muted" />
                                    </div>
                                    <input
                                        type="text"
                                        value={data.numero_cliente}
                                        onChange={(e) => setData('numero_cliente', e.target.value)}
                                        className={`w-full pl-11 pr-4 py-3 theme-surface border rounded-xl theme-text-main text-sm font-bold outline-none transition-all shadow-sm ${errors.numero_cliente ? 'border-red-500 focus:ring-1 focus:ring-red-500' : 'theme-border focus:border-[var(--color-primario)]'}`}
                                        placeholder="Ej. 10045"
                                        autoFocus
                                    />
                                </div>
                                {errors.numero_cliente && (
                                    <p className="mt-1.5 text-[10px] font-black uppercase tracking-wide text-red-500 animate-fade-in">
                                        {errors.numero_cliente}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    Nombre Completo
                                </label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <User className="h-4 w-4 theme-text-muted" />
                                    </div>
                                    <input
                                        type="text"
                                        value={data.nombre}
                                        onChange={(e) => setData('nombre', e.target.value)}
                                        className={`w-full pl-11 pr-4 py-3 theme-surface border rounded-xl theme-text-main text-sm font-bold outline-none transition-all shadow-sm ${errors.nombre ? 'border-red-500 focus:ring-1 focus:ring-red-500' : 'theme-border focus:border-[var(--color-primario)]'}`}
                                        placeholder="Nombre del prospecto"
                                    />
                                </div>
                                {errors.nombre && <p className="mt-1.5 text-[10px] font-bold text-red-500 uppercase tracking-wide">{errors.nombre}</p>}
                            </div>

                            <div className="pt-4 flex justify-end gap-3 border-t theme-border mt-6">
                                <button
                                    type="button"
                                    onClick={cerrarModal}
                                    className="px-6 py-3 text-[11px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main theme-element rounded-xl transition-colors border border-transparent hover:border-gray-300 dark:hover:border-gray-600 outline-none"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex items-center gap-2 px-6 py-3 text-white rounded-xl font-black uppercase tracking-widest text-[11px] shadow-lg hover:shadow-xl transition-all disabled:opacity-50"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    {processing ? 'Guardando...' : 'Registrar Cliente'}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            )}
        </AppLayout>
    );
}