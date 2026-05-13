import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, router } from '@inertiajs/react';
import {
    Clock, Plus, MoreVertical, Edit2, CheckCircle2, AlertOctagon,
    Search, History, CheckSquare, CreditCard, User, Copy, Check, Tag, TrendingUp, ShieldAlert, Users,
    ChevronLeft, ChevronRight
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

// Componentes Modales
import ModalFormSolicitud from './Partials/ModalFormSolicitud';
import ModalRespuestaSolicitud from './Partials/ModalRespuestaSolicitud';
import ModalBitacoraSolicitud from './Partials/ModalBitacoraSolicitud';

const ESTILOS_ADICIONALES = `
    .status-aprobado { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .status-verificado { background-color: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .status-revision { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }
    .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3); }
    .dark .status-incidencia { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    .dark .status-verificado { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border-color: rgba(96, 165, 250, 0.3); }
    .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.3); }

    /* Efecto Sticky para la columna de acciones (Recuperado) */
    .sticky-actions {
        position: sticky;
        right: 0;
        z-index: 30;
        background-color: #ffffff;
    }
    .dark .sticky-actions {
        background-color: #121212;
    }
    .sticky-actions::before {
        content: '';
        position: absolute;
        left: -15px;
        top: 0;
        bottom: 0;
        width: 15px;
        background: linear-gradient(to right, transparent, rgba(0,0,0,0.05));
        pointer-events: none;
    }
    .dark .sticky-actions::before {
        background: linear-gradient(to right, transparent, rgba(0,0,0,0.3));
    }

    /* Animaciones Nativas */
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .animate-page-reveal {
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Paginación */
    .paginacion-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
        font-weight: 900;
        border: 1px solid;
        transition: all 0.15s;
        cursor: pointer;
    }
    .paginacion-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
`;

// =============================================
// MENÚ ACCIONES — Portal (nunca se corta)
// =============================================
const MenuAccionesPortal = ({ menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalForm, setModalRespuesta, setModalBitacora, confirmarPagoManual, can, auth }) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={() => setMenuAbierto(null)}></div>
            <div
                className="fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 w-56 flex flex-col gap-1 backdrop-blur-xl animate-fade-in"
                style={{ top: menuPos.top, left: menuPos.left }}
            >
                {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && (
                    <button onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 dark:hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3"><Edit2 className="w-4 h-4" /> Reparar Solicitud</button>
                )}
                {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id) && !solicitud.pago_confirmado && solicitud.estado?.nombre !== 'Incorrecta' && (
                    <button onClick={() => confirmarPagoManual(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3"><CreditCard className="w-4 h-4" /> Confirmar Pago</button>
                )}
                {can('solicitudes.verificar') && (<button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 3 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors"><CheckSquare className="w-4 h-4" /> Verificado</button>)}
                {can('solicitudes.reportar') && (
                    <>
                        <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors" style={{ color: 'var(--color-primario)' }}><CheckCircle2 className="w-4 h-4" /> Aprobar Proceso</button>
                        <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 4 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors"><AlertOctagon className="w-4 h-4" /> Reportar Error</button>
                    </>
                )}
                {can('configuracion.ver_auditoria') && (<button onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3"><History className="w-4 h-4" /> Ver Bitácora</button>)}
            </div>
        </>,
        document.body
    );
};

// =============================================
// PAGINACIÓN
// =============================================
const Paginacion = ({ solicitudes, onIrAPagina }) => {
    const paginaActual = solicitudes.current_page || 1;
    const totalPaginas = solicitudes.last_page || 1;
    const totalRegistros = solicitudes.total || 0;
    const desde = solicitudes.from || 1;
    const hasta = solicitudes.to || 10;

    if (totalPaginas <= 1) return null;

    const generarPaginas = () => {
        const paginas = [];
        if (totalPaginas <= 7) {
            for (let i = 1; i <= totalPaginas; i++) paginas.push(i);
        } else {
            paginas.push(1);
            if (paginaActual > 3) paginas.push('...');
            for (let i = Math.max(2, paginaActual - 1); i <= Math.min(totalPaginas - 1, paginaActual + 1); i++) {
                paginas.push(i);
            }
            if (paginaActual < totalPaginas - 2) paginas.push('...');
            paginas.push(totalPaginas);
        }
        return paginas;
    };

    const paginas = generarPaginas();

    return (
        <div className="animate-page-reveal theme-surface rounded-[2rem] border theme-border shadow-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4" style={{ animationDelay: '300ms' }}>
            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                Viendo {desde} al {hasta} de {totalRegistros.toLocaleString('es-MX')}
            </span>
            <div className="flex items-center gap-2">
                <button onClick={() => onIrAPagina(paginaActual - 1)} disabled={paginaActual === 1} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]">
                    <ChevronLeft className="w-4 h-4" />
                </button>
                {paginas.map((p, i) =>
                    p === '...' ? (
                        <span key={`dots-${i}`} className="w-10 text-center text-[11px] font-black theme-text-muted">…</span>
                    ) : (
                        <button key={p} onClick={() => onIrAPagina(p)} className={`paginacion-btn theme-border ${p === paginaActual ? '' : 'theme-surface theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'}`} style={p === paginaActual ? { backgroundColor: 'var(--color-primario)', color: '#fff', borderColor: 'var(--color-primario)' } : {}}>
                            {p}
                        </button>
                    )
                )}
                <button onClick={() => onIrAPagina(paginaActual + 1)} disabled={paginaActual === totalPaginas} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]">
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
};

export default function Index({ solicitudes = { total: 0, data: [], current_page: 1, last_page: 1, per_page: 10, from: 1, to: 10 }, procesos = [], listas = [], tipos_cliente = [], auth }) {

    const [modalForm, setModalForm] = useState({ abierto: false, modoEdicion: false, solicitud: null });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });

    const [menuAbierto, setMenuAbierto] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0 });
    const [menuSolicitud, setMenuSolicitud] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');
    const [copiadoId, setCopiadoId] = useState(null);
    const [procesandoAccion, setProcesandoAccion] = useState(false);

    // Validación de permisos robusta
    const can = (permiso) => {
        const roles = auth?.user?.roles || [];
        const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)');
        return auth?.user?.permissions?.includes(permiso) || isAdmin;
    };

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ 
                only: ['solicitudes'], 
                preserveState: true, 
                preserveScroll: true,
                showProgress: false // <-- ESTO EVITARÁ EL PARPADEO CADA 15 SEG.
            });
        }, 15000);
        return () => clearInterval(interval);
    }, []);

    // Cerrar menú al hacer scroll
    useEffect(() => {
        const handleScroll = () => setMenuAbierto(null);
        window.addEventListener('scroll', handleScroll, true);
        return () => window.removeEventListener('scroll', handleScroll, true);
    }, []);

    const copiarAlPortapapeles = (e, texto, id) => {
        e.stopPropagation();
        navigator.clipboard.writeText(texto);
        setCopiadoId(id);
        setTimeout(() => setCopiadoId(null), 2000);
    };

    const confirmarPagoManual = (id) => {
        setMenuAbierto(null);
        setProcesandoAccion(true);
        router.put(route('solicitudes.confirmar_pago', id), {}, { onFinish: () => setProcesandoAccion(false) });
    };

    // Abrir menú calculando posición relativa a la ventana (portal)
    const abrirMenu = (e, solicitud) => {
        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const menuWidth = 224; // w-56 = 224px
        const menuHeight = 220; // altura estimada del menú

        // Calcular si hay espacio abajo o si debe abrirse arriba
        const spaceBelow = window.innerHeight - rect.bottom;
        const openUpward = spaceBelow < menuHeight + 16;

        let top = openUpward ? rect.top - menuHeight - 8 : rect.bottom + 8;
        let left = rect.right - menuWidth;
        if (left < 8) left = 8;

        setMenuPos({ top, left });
        setMenuSolicitud(solicitud);
        setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id);
    };

    const solicitudesFiltradas = (solicitudes.data || []).filter(solicitud => {
        const cumpleTab = tabActiva === 'TODAS' || (tabActiva === 'PENDIENTES' && solicitud.estado?.nombre === 'Pendiente') || (tabActiva === 'RESPONDIDAS' && solicitud.estado?.nombre === 'Respondida') || (tabActiva === 'INCORRECTAS' && solicitud.estado?.nombre === 'Incorrecta');
        const idString = solicitud.id?.toString() || '';
        const clienteNom = solicitud.cliente?.nombre?.toLowerCase() || '';
        const clienteNum = solicitud.cliente?.numero_cliente?.toLowerCase() || '';
        const search = busqueda.toLowerCase();
        return cumpleTab && (idString.includes(search) || clienteNom.includes(search) || clienteNum.includes(search));
    });

    const obtenerEstiloEstado = (nombreEstado) => {
        switch (nombreEstado?.toLowerCase()) {
            case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado' };
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte' };
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada' };
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' };
        }
    };

    const irAPagina = (pagina) => {
        const totalPaginas = solicitudes.last_page || 1;
        if (pagina < 1 || pagina > totalPaginas) return;
        router.get(route('solicitudes.index'), { page: pagina }, { preserveState: true, preserveScroll: false });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes" />
            <style>{ESTILOS_ADICIONALES}</style>
            <GeliaLoader isVisible={procesandoAccion} message="Sincronizando_" />

            {/* Menú de acciones renderizado en portal (nunca se corta) */}
            <MenuAccionesPortal
                menuAbierto={menuAbierto}
                menuSolicitud={menuSolicitud}
                menuPos={menuPos}
                setMenuAbierto={setMenuAbierto}
                setModalForm={setModalForm}
                setModalRespuesta={setModalRespuesta}
                setModalBitacora={setModalBitacora}
                confirmarPagoManual={confirmarPagoManual}
                can={can}
                auth={auth}
            />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">

                {/* Cabecera Principal */}
                <header className="animate-page-reveal theme-surface rounded-3xl md:rounded-[2.5rem] p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border theme-border shadow-xl">
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span>
                        </h1>
                    </div>
                    {can('solicitudes.crear') && (
                        <button onClick={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-5 h-5" /> Nueva Solicitud
                        </button>
                    )}
                </header>

                {/* Filtros y Búsqueda */}
                <div className="animate-page-reveal flex flex-col lg:flex-row gap-4 items-center justify-between" style={{ animationDelay: '100ms' }}>
                    <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto flex">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (
                            <button key={tab} type="button" onClick={() => setTabActiva(tab)} className="gelia-segment-btn px-4 md:px-6 min-w-max flex-1 text-center" data-active={tabActiva === tab}>{tab}</button>
                        ))}
                    </div>
                    <div className="relative w-full lg:w-96">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted" />
                        <input type="text" placeholder="Buscar folio o cliente..." value={busqueda} onChange={e => setBusqueda(e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm" />
                    </div>
                </div>

                {/* ========================================== */}
                {/* VISTA 1: TARJETAS PARA MÓVIL (Pantallas < lg) */}
                {/* ========================================== */}
                <div className="block lg:hidden space-y-4 animate-page-reveal" style={{ animationDelay: '200ms' }}>
                    {solicitudesFiltradas.length === 0 ? (
                        <div className="theme-surface rounded-3xl p-8 text-center border theme-border theme-text-muted font-bold text-sm">
                            No se encontraron solicitudes_
                        </div>
                    ) : (
                        solicitudesFiltradas.map((solicitud) => {
                            const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                            const StatusIcon = estatus.icon;
                            const nombreProceso = solicitud.proceso?.nombre || '';
                            const esTag = nombreProceso.toUpperCase().includes('TAG');
                            const esCambioLista = nombreProceso.toUpperCase().includes('LISTA');
                            const esHeredado = solicitud.cliente?.es_heredado;
                            const objLista = solicitud.lista_descuento || solicitud.listaDescuento;
                            const objTipo = solicitud.tipo_cliente || solicitud.tipoCliente;
                            const vendedoraTag = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Asesor';

                            return (
                                <div key={solicitud.id} className="theme-surface rounded-3xl border theme-border p-5 shadow-lg relative flex flex-col gap-4">
                                    <div className="flex items-start justify-between border-b theme-border pb-3">
                                        <div>
                                            <div className="font-black text-base" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                            <div className="text-[11px] font-bold theme-text-muted mt-0.5 uppercase flex items-center gap-1">
                                                <User className="w-3 h-3" /> {solicitud.vendedor?.name}
                                            </div>
                                        </div>
                                        <div className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border ${estatus.clase}`}>
                                            <StatusIcon className="w-3.5 h-3.5" />
                                            <span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex items-center gap-2 mb-1.5">
                                            <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">
                                                {solicitud.cliente?.numero_cliente || 'N/A'}
                                            </span>
                                            {/* Botón de Copiar agregado en móvil */}
                                            {solicitud.cliente?.numero_cliente && (
                                                <button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">
                                                    {copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}
                                                </button>
                                            )}
                                            {esHeredado && (
                                                <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1">
                                                    <ShieldAlert className="w-3 h-3" /> Heredado
                                                </span>
                                            )}
                                        </div>
                                        <div className="font-bold text-base theme-text-main uppercase italic leading-tight">
                                            {solicitud.cliente?.nombre || 'Nuevo Prospecto'}
                                        </div>
                                    </div>

                                    <div className="bg-black/5 dark:bg-white/5 p-3 rounded-2xl border theme-border flex flex-col gap-2">
                                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main block">
                                            {nombreProceso}
                                        </span>
                                        <div className="flex flex-wrap gap-1.5">
                                            {esCambioLista && objLista && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-blue-500/10 text-blue-500 border border-blue-500/20 flex items-center gap-1"><TrendingUp className="w-3 h-3" /> Ascenso a: {objLista.nombre}</span>}
                                            {esTag && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center gap-1"><Tag className="w-3 h-3" /> TAG: {vendedoraTag}</span>}
                                            {objTipo && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center gap-1"><Users className="w-3 h-3" /> {objTipo.nombre}</span>}
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between pt-2 border-t theme-border">
                                        <div>
                                            <div className="font-black italic theme-text-main text-sm">
                                                {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}
                                            </div>
                                            <div className={`mt-1 flex items-center gap-1 text-[9px] font-black uppercase tracking-widest ${solicitud.pago_confirmado ? 'text-emerald-500' : 'text-amber-500'}`}>
                                                {solicitud.pago_confirmado ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />}
                                                {solicitud.pago_confirmado ? 'Pago Confirmado' : 'Pago Pendiente'}
                                            </div>
                                        </div>

                                        <button onClick={(e) => abrirMenu(e, solicitud)} className="p-2.5 theme-element border theme-border hover:border-[var(--color-primario)] rounded-xl transition-all shadow-sm outline-none">
                                            <MoreVertical className="w-5 h-5 theme-text-main" />
                                        </button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* ======================================================== */}
                {/* VISTA 2: TABLA TRADICIONAL ESCRITORIO (Pantallas >= lg) */}
                {/* ======================================================== */}
                <div className="hidden lg:block animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden bg-white/70 dark:bg-[#121212]/70 backdrop-blur-xl" style={{ animationDelay: '200ms' }}>
                    <div className="overflow-x-auto pb-4 custom-scrollbar">
                        <table className="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr className="border-b theme-border">
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio & Asesor_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Detalles de Operación_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center sticky-actions">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;
                                    const nombreProceso = solicitud.proceso?.nombre || '';
                                    const esTag = nombreProceso.toUpperCase().includes('TAG');
                                    const esCambioLista = nombreProceso.toUpperCase().includes('LISTA');
                                    const esHeredado = solicitud.cliente?.es_heredado;
                                    const objLista = solicitud.lista_descuento || solicitud.listaDescuento;
                                    const objTipo = solicitud.tipo_cliente || solicitud.tipoCliente;
                                    const vendedoraTag = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Asesor';

                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5 group">
                                            <td className="p-6">
                                                <div className="font-black text-sm theme-text-main" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase"><User className="w-3 h-3 inline mr-1" /> {solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="p-6">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
                                                    {/* Botón de copiar ID recuperado para escritorio */}
                                                    {solicitud.cliente?.numero_cliente && (
                                                        <button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">
                                                            {copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}
                                                        </button>
                                                    )}
                                                    {esHeredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                                </div>
                                                <div className="font-bold text-sm theme-text-main uppercase italic truncate max-w-[200px]">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                            </td>
                                            <td className="p-6">
                                                <div className="inline-block px-3 py-1 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main mb-2">{nombreProceso}</div>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {esCambioLista && objLista && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-blue-500/10 text-blue-500 border border-blue-500/20 flex items-center gap-1"><TrendingUp className="w-3 h-3" /> Ascenso a: {objLista.nombre}</span>}
                                                    {esTag && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center gap-1"><Tag className="w-3 h-3" /> TAG: {vendedoraTag}</span>}
                                                    {objTipo && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center gap-1"><Users className="w-3 h-3" /> {objTipo.nombre}</span>}
                                                </div>
                                            </td>
                                            <td className="p-6">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                                <div className={`mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md w-fit border ${solicitud.pago_confirmado ? 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20' : 'text-amber-500 bg-amber-500/10 border-amber-500/20'}`}>
                                                    {solicitud.pago_confirmado ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />} {solicitud.pago_confirmado ? 'Confirmado' : 'Pendiente'}
                                                </div>
                                            </td>
                                            <td className="p-6">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}>
                                                    <StatusIcon className="w-4 h-4" />
                                                    <span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                                </div>
                                            </td>
                                            <td className="p-6 text-center sticky-actions group-hover:bg-slate-50 dark:group-hover:bg-white/5 transition-colors">
                                                <button onClick={(e) => abrirMenu(e, solicitud)} className="p-3 theme-element border theme-border hover:border-[var(--color-primario)] rounded-2xl transition-all shadow-sm outline-none">
                                                    <MoreVertical className="w-5 h-5 theme-text-main" />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Paginación */}
                <Paginacion solicitudes={solicitudes} onIrAPagina={irAPagina} />

            </div>

            {/* Modales - Props corregidas para bitácora */}
            {modalForm.abierto && <ModalFormSolicitud onClose={() => setModalForm({ ...modalForm, abierto: false })} procesos={procesos} listas={listas} tiposCliente={tipos_cliente} modoEdicion={modalForm.modoEdicion} solicitudAEditar={modalForm.solicitud} />}
            {modalRespuesta.abierto && <ModalRespuestaSolicitud onClose={() => setModalRespuesta({ ...modalRespuesta, abierto: false })} solicitud={modalRespuesta.solicitud} estadoId={modalRespuesta.estadoId} />}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} listas={listas} tiposCliente={tipos_cliente} />}
        </AppLayout>
    );
}