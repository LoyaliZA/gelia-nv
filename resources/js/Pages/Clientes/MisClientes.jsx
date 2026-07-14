import React, { useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { Users, Plus, X, Hash, User, Calendar, ShieldAlert, MapPin, Link2, Copy } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import { geliaCardClass, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../utils/geliaTheme';
import { soloDigitosNumeroCliente } from '../../utils/numeroClienteInput';

export default function MisClientes({ auth, clientes }) {
    /*
    |--------------------------------------------------------------------------
    | Validaciones de Seguridad (ABAC)
    |--------------------------------------------------------------------------
    */
    const { flash } = usePage().props;
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

    const generarEnlace = (clienteId) => {
        setProcesandoAccion(true);
        router.post(route('admin.clientes.direcciones.enlace', clienteId), {}, {
            preserveScroll: true,
            onFinish: () => setProcesandoAccion(false),
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
                <header className={`${geliaCardClass()} p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6`}>
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

                {flash?.enlace_direccion_url && (
                    <div className={`${geliaCardClass()} p-4 flex flex-wrap items-center gap-3`}>
                        <p className="flex-1 min-w-0 text-sm break-all theme-text-main m-0 font-mono">{flash.enlace_direccion_url}</p>
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 px-3 py-2 rounded-xl border theme-border text-[10px] font-black uppercase tracking-widest"
                            onClick={() => navigator.clipboard?.writeText(flash.enlace_direccion_url)}
                        >
                            <Copy className="w-3.5 h-3.5" /> Copiar
                        </button>
                    </div>
                )}

                {/* Tabla de Datos (Estilo Escritorio) */}
                <div className={`${geliaCardClass()} overflow-hidden`} style={{ animationDelay: '100ms' }}>
                    <div className="overflow-x-auto pb-4 custom-scrollbar">
                        <table className="w-full text-left border-collapse min-w-[800px]">
                            {/* Cabeceras actualizadas */}
                            <thead>
                                <tr className="border-b theme-border">
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">No. Cliente_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Nombre Completo_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Nivel / Lista_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Monto Actual_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Fecha de Alta_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Direcciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!clientes.data || clientes.data.length === 0) ? (
                                    <tr>
                                        <td colSpan="6" className="p-12 text-center text-[11px] font-black uppercase tracking-widest theme-text-muted italic">
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
                                            
                                            {/* Modificación: Renderizado dinámico de la lista de descuento */}
                                            <td className="p-6">
                                                <div className="inline-block px-3 py-1 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main mb-1">
                                                    {cliente.lista_descuento ? cliente.lista_descuento.nombre : 'Público General'}
                                                </div>
                                                {cliente.tipo && (
                                                    <div className="text-[8px] font-bold text-gray-400 uppercase mt-1">
                                                        {cliente.tipo.nombre}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Modificación: Formateo nativo de divisa para el monto de venta */}
                                            <td className="p-6">
                                                <span className="text-sm font-black theme-text-main">
                                                    {new Intl.NumberFormat('es-MX', { 
                                                        style: 'currency', 
                                                        currency: 'MXN' 
                                                    }).format(cliente.monto_venta_actual || 0)}
                                                </span>
                                            </td>

                                            <td className="p-6">
                                                <div className="text-[10px] font-bold theme-text-muted uppercase flex items-center gap-1.5">
                                                    <Calendar className="w-3.5 h-3.5" />
                                                    {new Date(cliente.created_at).toLocaleDateString('es-MX')}
                                                </div>
                                            </td>

                                            <td className="p-6">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {can('clientes.direcciones.ver') && (
                                                        <a
                                                            href={route('admin.clientes.direcciones.index', cliente.id)}
                                                            className="relative inline-flex items-center gap-1.5 px-3 py-2 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main hover:scale-105 transition-transform"
                                                            title="Ver direcciones"
                                                        >
                                                            <MapPin className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} />
                                                            {cliente.direcciones_activas_count || 0}
                                                            {cliente.solicitudes_direccion_pendientes > 0 && (
                                                                <span className="absolute -top-1 -right-1 min-w-[14px] h-3.5 px-1 rounded-full bg-amber-500 text-[8px] text-white font-black flex items-center justify-center">
                                                                    {cliente.solicitudes_direccion_pendientes}
                                                                </span>
                                                            )}
                                                        </a>
                                                    )}
                                                    {can('clientes.direcciones.generar_enlace') && (
                                                        <button
                                                            type="button"
                                                            onClick={() => generarEnlace(cliente.id)}
                                                            className="inline-flex items-center gap-1 px-3 py-2 rounded-xl border theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main transition-colors"
                                                            title="Generar enlace de dirección"
                                                        >
                                                            <Link2 className="w-3.5 h-3.5" /> Enlace
                                                        </button>
                                                    )}
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
                <div className={`${THEME_MODAL_OVERLAY} z-[100]`} onClick={cerrarModal}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg modal-pop p-6 md:p-8`} onClick={(e) => e.stopPropagation()}>



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
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        autoComplete="off"
                                        value={data.numero_cliente}
                                        onChange={(e) => setData('numero_cliente', soloDigitosNumeroCliente(e.target.value))}
                                        onPaste={(e) => {
                                            e.preventDefault();
                                            const texto = e.clipboardData?.getData('text') ?? '';
                                            setData('numero_cliente', soloDigitosNumeroCliente(texto));
                                        }}
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