import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import axios from 'axios';
import {
    Clock, Sparkles, Send, ShieldCheck, Info, Plus, MoreVertical, Edit2,
    CheckCircle2, XCircle, FileText, X, AlertOctagon, Search, History,
    CheckSquare, CreditCard, Upload, FileSignature, AlertTriangle, User,
    TrendingUp, UserCheck, Copy, Check
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

const ESTILOS_GLOBALES = `
    .status-aprobado { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .status-verificado { background-color: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .status-revision { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }
    .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3); }
    .dark .status-incidencia { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    .dark .status-verificado { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border-color: rgba(96, 165, 250, 0.3); }
    .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.3); }
`;

export default function Index({ solicitudes = { total: 0, data: [] }, procesos = [], listas = [], tipos_cliente = [], auth, filtros = {} }) {

    const [modalAbierto, setModalAbierto] = useState(false);
    const [modoEdicion, setModoEdicion] = useState(false);
    const [solicitudAEditar, setSolicitudAEditar] = useState(null);

    const [modalRespuestaAbierto, setModalRespuestaAbierto] = useState(false);
    const [modalBitacoraAbierto, setModalBitacoraAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');
    const [solicitudAuditada, setSolicitudAuditada] = useState(null);
    const [copiadoId, setCopiadoId] = useState(null);

    const [infoCliente, setInfoCliente] = useState(null);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaHeredado, setAlertaHeredado] = useState(false);
    const [alertaLista, setAlertaLista] = useState(null);
    const [previewEvidencia, setPreviewEvidencia] = useState(null);
    const [previewEvidenciaRespuesta, setPreviewEvidenciaRespuesta] = useState(null);

    const temporizadorBusqueda = useRef(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        catalogo_tipo_cliente_id: '',
        evidencia: null,
    });

    const formRespuesta = useForm({
        solicitud_id: null,
        catalogo_estado_solicitud_id: '',
        motivo: '',
        evidencia_respuesta: null,
        _method: 'put',
    });

    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['solicitudes'], preserveState: true, preserveScroll: true });
        }, 10000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => { animate('.page-reveal-solicitudes', { translateY: [15, 0], opacity: [0, 1] }, { easing: 'easeOutExpo', duration: 600, delay: (el, i) => i * 80 }); }, []);

    useEffect(() => {
        if (infoCliente && data.monto_cotizado && !isNaN(data.monto_cotizado)) {
            const montoHistorico = parseFloat(infoCliente.monto_venta_actual || 0);
            const cotizacion = parseFloat(data.monto_cotizado);
            const totalProyectado = montoHistorico + cotizacion;
            const listaSugerida = listas.find(l => totalProyectado >= parseFloat(l.monto_minimo) && totalProyectado <= parseFloat(l.monto_maximo));
            if (listaSugerida && listaSugerida.nombre !== infoCliente.lista_actual) {
                setAlertaLista({ total: totalProyectado, lista: listaSugerida.nombre, mensaje: `La cotización proyecta un acumulado de $${totalProyectado.toLocaleString('es-MX')}. Sugerencia: Ascenso a ${listaSugerida.nombre}.` });
            } else { setAlertaLista(null); }
        } else { setAlertaLista(null); }
    }, [data.monto_cotizado, infoCliente, listas]);

    const compressToWebp = (file) => {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    canvas.toBlob((blob) => {
                        const newFile = new File([blob], `captura_${Date.now()}.webp`, { type: 'image/webp' });
                        resolve({ file: newFile, preview: canvas.toDataURL('image/webp', 0.8) });
                    }, 'image/webp', 0.8);
                };
            };
        });
    };

    const handlePaste = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                const result = await compressToWebp(item.getAsFile());
                setData('evidencia', result.file); setPreviewEvidencia(result.preview); break;
            }
        }
    };

    const handlePasteRespuesta = async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                const result = await compressToWebp(item.getAsFile());
                formRespuesta.setData('evidencia_respuesta', result.file); setPreviewEvidenciaRespuesta(result.preview); break;
            }
        }
    };

    const handleFileUpload = async (e, isRespuesta = false) => {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const result = await compressToWebp(file);
            if (isRespuesta) { formRespuesta.setData('evidencia_respuesta', result.file); setPreviewEvidenciaRespuesta(result.preview); }
            else { setData('evidencia', result.file); setPreviewEvidencia(result.preview); }
        } else if (file) {
            if (isRespuesta) { formRespuesta.setData('evidencia_respuesta', file); setPreviewEvidenciaRespuesta(null); }
            else { setData('evidencia', file); setPreviewEvidencia(null); }
        }
    };

    // --- CORRECCIÓN: Botón de copiar robusto ---
    const copiarAlPortapapeles = (e, texto, id) => {
        e.stopPropagation(); // Evita que se disparen clics de otros elementos

        if (navigator.clipboard && window.isSecureContext) {
            // Navegadores modernos con HTTPS o Localhost
            navigator.clipboard.writeText(texto);
        } else {
            // Fallback para IPs locales o HTTP sin SSL
            const textArea = document.createElement("textarea");
            textArea.value = texto;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (error) {
                console.error('Error al copiar:', error);
            }
            textArea.remove();
        }

        setCopiadoId(id);
        setTimeout(() => setCopiadoId(null), 2000);
    };

    const fetchClientes = async (term = '') => {
        if (!term) return;
        setBuscandoCliente(true); setMostrarDropdown(true);
        try { const response = await axios.get(`/api/clientes?q=${term}`); setListaClientes(response.data); }
        catch (error) { setListaClientes([]); } finally { setBuscandoCliente(false); }
    };

    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor); setInfoCliente(null); setAlertaHeredado(false); setAlertaLista(null);
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);
        if (valor.trim() === '') { setMostrarDropdown(false); setListaClientes([]); return; }
        temporizadorBusqueda.current = setTimeout(() => { fetchClientes(valor); }, 400);
    };

    const seleccionarCliente = (cliente) => {
        setData('numero_cliente', cliente.numero_cliente); setData('nombre_cliente', cliente.nombre);
        setInfoCliente(cliente); setMostrarDropdown(false); setAlertaHeredado(false);
    };

    const abrirCrear = () => {
        reset(); setModoEdicion(false); setSolicitudAEditar(null); setInfoCliente(null); setPreviewEvidencia(null); setModalAbierto(true);
    };

    const abrirEditar = (solicitud) => {
        setMenuAbierto(null); setModoEdicion(true); setSolicitudAEditar(solicitud); setInfoCliente(solicitud.cliente);
        setData({
            numero_cliente: solicitud.cliente?.numero_cliente || '',
            nombre_cliente: solicitud.cliente?.nombre || '',
            monto_cotizado: solicitud.monto_cotizado,
            catalogo_proceso_id: solicitud.catalogo_proceso_id,
            catalogo_tipo_cliente_id: solicitud.catalogo_tipo_cliente_id || '',
            evidencia: null,
        });
        setPreviewEvidencia(solicitud.evidencia_path ? `/storage/${solicitud.evidencia_path}` : null);
        setModalAbierto(true);
    };

    const guardarSolicitud = (e) => {
        e.preventDefault();
        const procesoSeleccionado = procesos.find(p => p.id == data.catalogo_proceso_id);

        if (infoCliente?.es_heredado && (procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO' || procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA')) {
            setAlertaHeredado(true);
            return;
        }

        const config = { onSuccess: () => { setModalAbierto(false); reset(); setPreviewEvidencia(null); setInfoCliente(null); } };

        if (modoEdicion) {
            // SOLUCIÓN: Usamos router.post para construir el payload exacto
            // Inyectamos _method: 'put' junto con todo el estado 'data'
            router.post(route('solicitudes.update', solicitudAEditar.id), {
                _method: 'put',
                ...data
            }, config);
        } else {
            post(route('solicitudes.store'), config);
        }
    };

    const iniciarRespuesta = (solicitud, estadoId) => { formRespuesta.setData({ solicitud_id: solicitud.id, catalogo_estado_solicitud_id: estadoId, motivo: '', evidencia_respuesta: null, _method: 'put' }); setPreviewEvidenciaRespuesta(null); setMenuAbierto(null); setModalRespuestaAbierto(true); };
    const enviarRespuesta = (e) => { e.preventDefault(); formRespuesta.post(route('solicitudes.actualizar_estado', formRespuesta.data.solicitud_id), { onSuccess: () => { setModalRespuestaAbierto(false); formRespuesta.reset(); } }); };
    const confirmarPagoManual = (id) => { setMenuAbierto(null); router.put(route('solicitudes.confirmar_pago', id)); };

    const solicitudesFiltradas = (solicitudes.data || []).filter(solicitud => { const cumpleTab = tabActiva === 'TODAS' || (tabActiva === 'PENDIENTES' && solicitud.estado?.nombre === 'Pendiente') || (tabActiva === 'RESPONDIDAS' && solicitud.estado?.nombre === 'Respondida') || (tabActiva === 'INCORRECTAS' && solicitud.estado?.nombre === 'Incorrecta'); const idString = solicitud.id ? solicitud.id.toString() : ''; const nombreCliente = solicitud.cliente?.nombre || ''; const numeroCliente = solicitud.cliente?.numero_cliente || ''; const cumpleBusqueda = busqueda === '' || idString.includes(busqueda) || nombreCliente.toLowerCase().includes(busqueda.toLowerCase()) || numeroCliente.includes(busqueda); return cumpleTab && cumpleBusqueda; });
    const obtenerEstiloEstado = (nombreEstado) => { switch (nombreEstado?.toLowerCase()) { case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado (TAGS)' }; case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte (Error)' }; case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada (Auxiliar)' }; default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' }; } };
    const activeCardClass = "page-reveal-solicitudes theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300 border theme-border shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)] bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px]";

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes | GELIANV" />
            <style>{ESTILOS_GLOBALES}</style>
            <GeliaLoader isVisible={processing || formRespuesta.processing} message="Sincronizando Matriz_" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6`}>
                    <div>
                        <div className="flex items-center space-x-3 mb-2"><span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span><p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Central Infrastructure</p></div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0 p-0">PANEL DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span></h1>
                    </div>
                    {can('solicitudes.crear') && (
                        <button onClick={abrirCrear} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto outline-none" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-5 h-5" /> Nueva Solicitud</button>
                    )}
                </header>

                <div className="page-reveal-solicitudes flex flex-col lg:flex-row gap-6 items-start lg:items-center justify-between">
                    <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (
                            <button key={tab} type="button" onClick={() => setTabActiva(tab)} className="gelia-segment-btn px-6 min-w-max" data-active={tabActiva === tab}>{tab}</button>
                        ))}
                    </div>
                    <div className="relative w-full lg:w-96 shrink-0">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                        <input type="text" placeholder="Buscar folio o No. cliente..." value={busqueda} onChange={e => setBusqueda(e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md" onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                    </div>
                </div>

                <div className={`${activeCardClass} p-2 sm:p-8 overflow-hidden w-full`}>
                    <div className="overflow-x-auto custom-scrollbar pb-32">
                        <table className="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio & Asesor_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Proceso Solicitado_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                    <th className="pb-6 pt-4 pr-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.length === 0 ? (
                                    <tr><td colSpan="6" className="text-center py-16 text-zinc-500 font-bold uppercase text-[11px] tracking-widest italic">No se encontraron registros en esta frecuencia.</td></tr>
                                ) : solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;
                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5">
                                            <td className="py-6 pl-6">
                                                <div className="font-black text-sm theme-text-main drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase truncate max-w-[150px]"><User className="w-3 h-3 inline mr-1 opacity-70" /> {solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="py-6 min-w-[250px] pr-4">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">
                                                        {solicitud.cliente?.numero_cliente || 'N/A'}
                                                    </span>
                                                    {solicitud.cliente?.numero_cliente && (
                                                        <button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">
                                                            {copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}
                                                        </button>
                                                    )}
                                                </div>
                                                <div className="font-bold text-sm theme-text-main uppercase italic truncate lg:max-w-xs">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                            </td>
                                            <td className="py-6 pr-4">
                                                <div className="inline-block px-3 py-1.5 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main shadow-sm truncate max-w-[200px]" title={solicitud.proceso?.nombre}>
                                                    {solicitud.proceso?.nombre}
                                                </div>
                                            </td>
                                            <td className="py-6 pr-4">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                                {solicitud.pago_confirmado ? (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md w-fit border border-emerald-500/20"><CheckCircle2 className="w-3 h-3" /> Pago Confirmado</div>
                                                ) : (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-500 bg-amber-500/10 px-2 py-1 rounded-md w-fit border border-amber-500/20"><Clock className="w-3 h-3" /> Pago Pendiente</div>
                                                )}
                                            </td>
                                            <td className="py-6 pr-4">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}><StatusIcon className="w-4 h-4" /><span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span></div>
                                            </td>
                                            <td className="py-6 pr-6 text-center relative">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-3 theme-element border theme-border hover:border-[var(--color-primario)] rounded-2xl transition-all shadow-sm outline-none"><MoreVertical className="w-5 h-5 theme-text-main" /></button>

                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-40" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-16 top-4 theme-surface border theme-border shadow-2xl rounded-2xl p-2 z-50 w-56 flex flex-col gap-1 text-left animate-fade-in backdrop-blur-xl">

                                                            {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && (
                                                                <button onClick={() => abrirEditar(solicitud)} className="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 dark:hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-b theme-border mb-1 pb-3">
                                                                    <Edit2 className="w-4 h-4" /> Reparar Solicitud
                                                                </button>
                                                            )}

                                                            {/* CAMBIO AQUÍ: Ahora revisa si tiene el permiso de confirmar pago */}
                                                            {/* Lógica segura: El dueño o un Admin pueden confirmar, pero NUNCA si la solicitud es 'Incorrecta' */}
                                                            {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id) &&
                                                                !solicitud.pago_confirmado &&
                                                                solicitud.estado?.nombre !== 'Incorrecta' && (
                                                                    <button onClick={() => confirmarPagoManual(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-b theme-border mb-1 pb-3">
                                                                        <CreditCard className="w-4 h-4" /> Confirmar Pago
                                                                    </button>
                                                                )}
                                                            {can('solicitudes.verificar') && (<button onClick={() => iniciarRespuesta(solicitud, 3)} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors"><CheckSquare className="w-4 h-4" /> Verificado</button>)}
                                                            {can('solicitudes.reportar') && (
                                                                <>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 2)} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors" style={{ color: 'var(--color-primario)' }}><CheckCircle2 className="w-4 h-4" /> Aprobar Proceso</button>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 4)} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors"><AlertOctagon className="w-4 h-4" /> Reportar Error</button>
                                                                </>
                                                            )}
                                                            {can('configuracion.ver_auditoria') && (<button onClick={() => { setSolicitudAuditada(solicitud); setMenuAbierto(null); setModalBitacoraAbierto(true); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-t theme-border mt-1 pt-3"><History className="w-4 h-4" /> Ver Bitácora</button>)}
                                                        </div>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* --- MODAL NUEVA/EDITAR SOLICITUD --- */}
            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div onPaste={handlePaste} className="w-full max-w-4xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setModalAbierto(false)} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl transition-all outline-none hover:scale-110 z-50"><X className="w-5 h-5" /></button>
                        <div className="flex items-center gap-3 mb-8"><Sparkles className="w-8 h-8 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} /><h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">{modoEdicion ? 'Reparar Solicitud_' : 'Nueva Solicitud_'}</h2></div>

                        {alertaHeredado && (<div className="mb-8 p-5 rounded-2xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-500/10 flex gap-4 items-center animate-fade-in"><AlertTriangle className="w-8 h-8 text-amber-500 shrink-0" /><p className="text-sm font-bold text-amber-700 dark:text-amber-400 m-0 leading-tight">Alerta: Cliente protegido (Heredado).</p></div>)}
                        {alertaLista && (<div className="mb-8 p-5 rounded-2xl border-2 border-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 flex gap-4 items-center animate-fade-in"><TrendingUp className="w-8 h-8 text-emerald-500 shrink-0" /><div><p className="text-xs font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest leading-none mb-1">Análisis de Sistema_</p><p className="text-sm font-bold text-emerald-800 dark:text-emerald-300 m-0 leading-tight">{alertaLista.mensaje}</p></div></div>)}

                        <form onSubmit={guardarSolicitud} className="grid grid-cols-1 lg:grid-cols-2 gap-10 relative z-10">
                            <div className="space-y-8">
                                <div className="space-y-2 relative">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (Buscador)_</label>
                                    <div className="relative">
                                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input type="text" value={data.numero_cliente} onChange={e => manejarBusquedaCliente(e.target.value)} onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }} placeholder="Ingresa nombre o folio..." className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md" disabled={modoEdicion} />
                                    </div>
                                    {infoCliente && (
                                        <div className="mt-4 p-4 theme-element border theme-border rounded-2xl shadow-sm animate-fade-in">
                                            <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mb-1">Titular Seleccionado:</p>
                                            <p className="text-sm font-black theme-text-main italic truncate">{infoCliente.nombre}</p>
                                            <div className="flex gap-2 mt-3">
                                                <span className="text-[10px] font-bold bg-[var(--color-primario)] text-white px-3 py-1 rounded-lg uppercase shadow-sm">Lista: {infoCliente.lista_actual || infoCliente.lista_descuento?.nombre}</span>
                                            </div>
                                        </div>
                                    )}
                                    {mostrarDropdown && !modoEdicion && (
                                        <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                            {buscandoCliente ? (<div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando directorio...</div>) : listaClientes.map(c => (<div key={c.id} onClick={() => seleccionarCliente(c)} className="p-4 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer flex justify-between items-center group mb-1 border border-transparent"><p className="text-xs font-black uppercase theme-text-main">{c.numero_cliente} - {c.nombre}</p></div>))}
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-2"><label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cotización Autorizada_</label><div className="relative"><CreditCard className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" /><input type="number" step="0.01" required value={data.monto_cotizado} onChange={e => setData('monto_cotizado', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 transition-all shadow-sm" /></div></div>
                                <div className="space-y-2"><label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Proceso a Ejecutar_</label><div className="relative"><FileSignature className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" /><select value={data.catalogo_proceso_id} required onChange={e => setData('catalogo_proceso_id', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none focus:ring-2 transition-all shadow-sm cursor-pointer"><option value="">-- Seleccionar proceso --</option>{procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}</select></div></div>
                                <div className="space-y-2"><label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipo de Cliente (Asignación)_</label><div className="relative"><UserCheck className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" /><select value={data.catalogo_tipo_cliente_id} onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none focus:ring-2 transition-all shadow-sm cursor-pointer"><option value="">-- Mantener tipo actual --</option>{tipos_cliente.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}</select></div></div>
                            </div>
                            <div className="space-y-8 flex flex-col justify-between">
                                <div className="space-y-2 h-full flex flex-col"><label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia / Ticket (Ctrl + V soportado)_</label><label className="flex-1 flex flex-col items-center justify-center min-h-[250px] border-2 border-dashed theme-border rounded-2xl cursor-pointer theme-element transition-all group overflow-hidden relative"><Upload className="w-12 h-12 mb-4 theme-text-muted z-10" />{previewEvidencia && <img src={previewEvidencia} className="absolute inset-0 w-full h-full object-cover opacity-80" />}<input type="file" className="hidden" accept="image/*,.pdf" onChange={(e) => handleFileUpload(e, false)} /></label></div>
                                <button type="submit" disabled={processing} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[12px] shadow-xl hover:scale-105 transition-all disabled:opacity-50 outline-none flex justify-center items-center gap-3" style={{ backgroundColor: 'var(--color-primario)' }}><Send className="w-5 h-5" /> {processing ? 'Procesando...' : (modoEdicion ? 'Reenviar Corrección' : 'Transmitir Solicitud')}</button>
                            </div>
                        </form>
                    </div>
                </div>, document.body
            )}

            {/* --- MODAL RESPONDER CON CAPTURA --- */}
            {modalRespuestaAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalRespuestaAbierto(false)}>
                    <div onPaste={handlePasteRespuesta} className="w-full max-w-3xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 flex flex-col relative modal-pop" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setModalRespuestaAbierto(false)} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none hover:scale-110"><X className="w-5 h-5" /></button>
                        <div className="flex items-center gap-3 mb-8"><Edit2 className="w-8 h-8" style={{ color: 'var(--color-primario)' }} /><h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">Actualizar Estado_</h2></div>

                        <form onSubmit={enviarRespuesta} className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div className="space-y-8">
                                <div className="p-5 rounded-2xl border flex items-start gap-4" style={{ backgroundColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)', borderColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'rgba(239, 68, 68, 0.3)' : 'rgba(52, 211, 153, 0.3)' }}>
                                    {formRespuesta.data.catalogo_estado_solicitud_id === 4 ? <AlertOctagon className="w-6 h-6 text-red-500 mt-0.5" /> : <Info className="w-6 h-6 text-emerald-500 mt-0.5" />}
                                    <div><p className="text-sm font-black uppercase" style={{ color: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? '#ef4444' : '#10b981' }}>{formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'Reporte de Error' : 'Aprobación'}</p></div>
                                </div>
                                <div className="space-y-2"><label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Observaciones / Motivo</label><textarea required={formRespuesta.data.catalogo_estado_solicitud_id === 4} rows="6" value={formRespuesta.data.motivo} onChange={e => formRespuesta.setData('motivo', e.target.value)} className="w-full p-5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 shadow-sm resize-none"></textarea></div>
                            </div>
                            <div className="space-y-2 flex flex-col">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia de Respuesta (Ctrl+V)_</label>
                                <label className="flex-1 flex flex-col items-center justify-center border-2 border-dashed theme-border rounded-2xl cursor-pointer theme-element relative overflow-hidden">
                                    <Upload className="w-10 h-10 mb-3 theme-text-muted z-10" />
                                    {previewEvidenciaRespuesta && <img src={previewEvidenciaRespuesta} className="absolute inset-0 w-full h-full object-cover opacity-80" />}
                                    <p className="text-[10px] font-bold theme-text-main uppercase z-10 bg-white/50 dark:bg-black/50 px-3 py-1.5 rounded-lg border theme-border backdrop-blur-sm">Adjuntar Captura</p>
                                    <input type="file" className="hidden" accept="image/*" onChange={(e) => handleFileUpload(e, true)} />
                                </label>
                                <button type="submit" disabled={formRespuesta.processing} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[12px] hover:scale-105 transition-all mt-4" style={{ backgroundColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? '#ef4444' : 'var(--color-primario)' }}>{formRespuesta.processing ? 'Registrando...' : 'Confirmar Acción'}</button>
                            </div>
                        </form>
                    </div>
                </div>, document.body
            )}

            {/* --- MODAL REDISEÑADO DE BITÁCORA --- */}
            {modalBitacoraAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalBitacoraAbierto(false)}>
                    <div className="w-full max-w-6xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh]" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setModalBitacoraAbierto(false)} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none hover:scale-110 z-10"><X className="w-5 h-5" /></button>

                        <div className="flex items-center gap-4 mb-8 shrink-0 border-b theme-border pb-6">
                            <History className="w-10 h-10 text-purple-500 drop-shadow-sm" />
                            <div>
                                <h2 className="text-3xl font-black italic theme-text-main uppercase tracking-tighter m-0">Expediente de Auditoría_</h2>
                                <p className="text-xs font-bold theme-text-muted uppercase tracking-widest mt-1">Folio: FOL-{solicitudAuditada?.id}</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 flex-1 overflow-hidden">
                            {/* Lado Izquierdo: Solicitud Original */}
                            <div className="theme-element border theme-border rounded-3xl p-8 overflow-y-auto custom-scrollbar">
                                <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-6">Datos Originales (Vendedora)</h3>
                                <div className="space-y-6">
                                    <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cliente</p><p className="text-base font-black theme-text-main">{solicitudAuditada?.cliente?.nombre}</p></div>
                                    <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Proceso Solicitado</p><p className="text-base font-black theme-text-main">{solicitudAuditada?.proceso?.nombre}</p></div>
                                    <div><p className="text-[10px] font-bold theme-text-muted uppercase mb-1">Cotización Inicial</p><p className="text-base font-black theme-text-main">${solicitudAuditada?.monto_cotizado}</p></div>
                                    <div>
                                        <p className="text-[10px] font-bold theme-text-muted uppercase mb-3">Evidencia Adjunta</p>
                                        {solicitudAuditada?.evidencia_path ? (
                                            <a href={`/storage/${solicitudAuditada.evidencia_path}`} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-2xl border theme-border hover:ring-2 transition-all h-56">
                                                <img src={`/storage/${solicitudAuditada.evidencia_path}`} className="w-full h-full object-cover hover:scale-105 transition-transform" />
                                            </a>
                                        ) : (<p className="text-sm font-bold theme-text-muted italic">Sin evidencia inicial.</p>)}
                                    </div>
                                </div>
                            </div>

                            {/* Lado Derecho: Timeline de Respuestas */}
                            <div className="overflow-y-auto custom-scrollbar relative px-6 py-4 before:absolute before:inset-0 before:ml-6 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-purple-300 dark:before:via-purple-900/50 before:to-transparent">
                                <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-8 ml-8 relative z-10 theme-surface inline-block pr-4">Línea de Tiempo Operativa</h3>
                                <div className="space-y-8">
                                    {solicitudAuditada?.auditorias?.map((registro, idx) => (
                                        <div key={idx} className="relative flex flex-col ml-10">
                                            <div className="absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface bg-purple-500 text-white flex items-center justify-center shadow-md z-10"><ShieldCheck className="w-4 h-4" /></div>
                                            <div className="theme-element border theme-border p-6 rounded-3xl shadow-sm">
                                                <div className="flex justify-between items-center mb-3"><span className="font-black text-xs text-purple-600 dark:text-purple-400 uppercase tracking-widest">{registro.usuario?.name}</span><span className="text-[10px] font-bold theme-text-muted">{new Date(registro.created_at).toLocaleString()}</span></div>
                                                <p className="text-sm font-black theme-text-main">Pasó a estado: <span className="italic">{registro.estado_nuevo?.nombre}</span></p>
                                                {registro.motivo_reporte && (<div className="mt-4 p-4 theme-surface rounded-2xl border theme-border"><p className="text-xs font-bold theme-text-main m-0 uppercase tracking-widest leading-relaxed">Nota: {registro.motivo_reporte}</p></div>)}
                                            </div>
                                        </div>
                                    ))}
                                    {solicitudAuditada?.evidencia_respuesta_path && (
                                        <div className="relative flex flex-col ml-10 mt-8">
                                            <div className="absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface bg-emerald-500 text-white flex items-center justify-center z-10"><CheckCircle2 className="w-4 h-4" /></div>
                                            <div className="theme-element border border-emerald-500/30 p-6 rounded-3xl shadow-sm">
                                                <h4 className="text-xs font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-4">Evidencia de Respuesta (Encargada)</h4>
                                                <a href={`/storage/${solicitudAuditada.evidencia_respuesta_path}`} target="_blank" rel="noreferrer" className="block rounded-2xl overflow-hidden border theme-border h-40 relative group">
                                                    <img src={`/storage/${solicitudAuditada.evidencia_respuesta_path}`} className="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                                    <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-sm font-bold uppercase tracking-widest backdrop-blur-sm">Ver Completa</div>
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>, document.body
            )}

        </AppLayout>
    );
}