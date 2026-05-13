import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { X, Sparkles, Search, CreditCard, FileSignature, TrendingUp, Upload, Send, AlertTriangle, Users } from 'lucide-react';

export default function ModalFormSolicitud({ onClose, procesos, listas, tiposCliente = [], modoEdicion, solicitudAEditar }) {
    
    const infoClienteInicial = solicitudAEditar?.cliente || null;
    const [infoCliente, setInfoCliente] = useState(infoClienteInicial);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaLista, setAlertaLista] = useState(null);
    const [alertaHeredado, setAlertaHeredado] = useState(false);
    const [previewEvidencia, setPreviewEvidencia] = useState(solicitudAEditar?.evidencia_path ? `/storage/${solicitudAEditar.evidencia_path}` : null);
    
    const temporizadorBusqueda = useRef(null);

    const { data, setData, post, processing, reset } = useForm({
        numero_cliente: solicitudAEditar?.cliente?.numero_cliente || '',
        nombre_cliente: solicitudAEditar?.cliente?.nombre || '',
        monto_cotizado: solicitudAEditar?.monto_cotizado || '',
        catalogo_proceso_id: solicitudAEditar?.catalogo_proceso_id || '',
        catalogo_lista_descuento_id: solicitudAEditar?.catalogo_lista_descuento_id || '',
        catalogo_tipo_cliente_id: solicitudAEditar?.catalogo_tipo_cliente_id || '',
        evidencia: null,
    });

    // LÓGICA REACTIVA: Tipos de Cliente Permitidos
    // Si el cliente es heredado, ocultamos las opciones normales
    const opcionesTipoCliente = infoCliente?.es_heredado 
        ? tiposCliente.filter(t => t.nombre.toUpperCase().includes('HEREDADO')) 
        : tiposCliente.filter(t => !t.nombre.toUpperCase().includes('HEREDADO'));

    useEffect(() => {
        if (infoCliente && data.monto_cotizado && !isNaN(data.monto_cotizado)) {
            const montoHistorico = parseFloat(infoCliente.monto_venta_actual || 0);
            const cotizacion = parseFloat(data.monto_cotizado);
            const totalProyectado = montoHistorico + cotizacion;

            // 1. Obtener la jerarquía de la lista actual del cliente
            const listaActualObj = listas.find(l => l.nombre === infoCliente.lista_actual);
            const montoMinimoListaActual = listaActualObj ? parseFloat(listaActualObj.monto_requerido || listaActualObj.monto_minimo || 0) : 0;

            const listasValidas = listas
                .filter(l => !l.nombre.toUpperCase().includes('COLABORADOR'))
                .sort((a, b) => parseFloat(b.monto_requerido || b.monto_minimo || 0) - parseFloat(a.monto_requerido || a.monto_minimo || 0));

            // 2. Encontrar a qué lista califica según la proyección actual
            const sugerencia = listasValidas.find(l => totalProyectado >= parseFloat(l.monto_requerido || l.monto_minimo || 0));

            // 3. REGLA DE NEGOCIO: Solo sugerir si representa un ascenso (monto mayor a su nivel actual)
            if (sugerencia && parseFloat(sugerencia.monto_requerido || sugerencia.monto_minimo || 0) > montoMinimoListaActual) {
                setData('catalogo_lista_descuento_id', sugerencia.id);
                setAlertaLista({ mensaje: `Califica para ascenso a: ${sugerencia.nombre}` });
            } else {
                // Si la sugerencia es menor o igual, se mantiene en su nivel actual automáticamente
                setData('catalogo_lista_descuento_id', '');
                setAlertaLista(null);
            }
        } else {
            setAlertaLista(null);
            setData('catalogo_lista_descuento_id', '');
        }
    }, [data.monto_cotizado, infoCliente, listas]);

    // ... (Mantén las funciones de imagen y búsqueda intactas)
    const compressToWebp = (file) => {
        return new Promise((resolve) => {
            const reader = new FileReader(); reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image(); img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas'); canvas.width = img.width; canvas.height = img.height;
                    const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
                    canvas.toBlob((blob) => {
                        const newFile = new File([blob], `captura_${Date.now()}.webp`, { type: 'image/webp' });
                        resolve({ file: newFile, preview: canvas.toDataURL('image/webp', 0.8) });
                    }, 'image/webp', 0.8);
                };
            };
        });
    };

    const handlePaste = async (e) => {
        const items = e.clipboardData?.items; if (!items) return;
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                const result = await compressToWebp(item.getAsFile());
                setData('evidencia', result.file); setPreviewEvidencia(result.preview); break;
            }
        }
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const result = await compressToWebp(file); setData('evidencia', result.file); setPreviewEvidencia(result.preview);
        } else if (file) {
            setData('evidencia', file); setPreviewEvidencia(null);
        }
    };

    const fetchClientes = async (term = '') => {
        if (!term) return; setBuscandoCliente(true); setMostrarDropdown(true);
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
        setData('numero_cliente', cliente.numero_cliente); 
        setData('nombre_cliente', cliente.nombre);
        setInfoCliente(cliente); 
        setMostrarDropdown(false); 
        setAlertaHeredado(false);
        // Al cambiar de cliente, vaciamos el tipo para forzar la selección basada en su estatus (Heredado o Normal)
        setData('catalogo_tipo_cliente_id', '');
        // También reiniciamos la lista solicitada para que recalcule
        setData('catalogo_lista_descuento_id', '');
    };

    const guardarSolicitud = (e) => {
        e.preventDefault();
        const procesoSeleccionado = procesos.find(p => p.id == data.catalogo_proceso_id);
        if (infoCliente?.es_heredado && (procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO' || procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA')) {
            setAlertaHeredado(true); return;
        }

        const config = { onSuccess: () => { reset(); onClose(); }, forceFormData: true };

        if (modoEdicion) {
            router.post(route('solicitudes.update', solicitudAEditar.id), { _method: 'put', ...data }, config);
        } else {
            post(route('solicitudes.store'), config);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div onPaste={handlePaste} className="w-full max-w-4xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl transition-all outline-none hover:scale-110 z-50"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-8">
                    <Sparkles className="w-8 h-8 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">{modoEdicion ? 'Reparar Solicitud_' : 'Nueva Solicitud_'}</h2>
                </div>

                {alertaHeredado && (
                    <div className="mb-8 p-5 rounded-2xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-500/10 flex gap-4 items-center animate-fade-in">
                        <AlertTriangle className="w-8 h-8 text-amber-500 shrink-0" />
                        <p className="text-sm font-bold text-amber-700 dark:text-amber-400 m-0 leading-tight">Alerta: Cliente protegido (Heredado). Utiliza los procesos exclusivos para heredados.</p>
                    </div>
                )}

                <form onSubmit={guardarSolicitud} className="grid grid-cols-1 lg:grid-cols-2 gap-10 relative z-10">
                    <div className="space-y-8">
                        
                        {/* Buscador de Cliente */}
                        <div className="space-y-2 relative">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (Buscador)_</label>
                            <div className="relative">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <input type="text" value={data.numero_cliente} onChange={e => manejarBusquedaCliente(e.target.value)} onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }} placeholder="Ingresa nombre o folio..." className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md" disabled={modoEdicion} />
                            </div>
                            {infoCliente && (
                                <div className={`mt-4 p-4 theme-element border ${infoCliente.es_heredado ? 'border-purple-500/50 bg-purple-500/5' : 'theme-border'} rounded-2xl shadow-sm animate-fade-in`}>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mb-1">Titular Seleccionado:</p>
                                            <p className="text-sm font-black theme-text-main italic truncate">{infoCliente.nombre}</p>
                                        </div>
                                        {infoCliente.es_heredado && (
                                            <span className="text-[9px] font-black bg-purple-500 text-white px-2 py-1 rounded-md uppercase tracking-widest shadow-sm">HEREDADO</span>
                                        )}
                                    </div>
                                    <div className="flex gap-2 mt-3"><span className="text-[10px] font-bold bg-[var(--color-primario)] text-white px-3 py-1 rounded-lg uppercase shadow-sm">Lista Actual: {infoCliente.lista_actual || 'Público'}</span></div>
                                </div>
                            )}
                            {mostrarDropdown && !modoEdicion && (
                                <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                    {buscandoCliente ? (<div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando directorio...</div>) : listaClientes.map(c => (<div key={c.id} onClick={() => seleccionarCliente(c)} className="p-4 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer flex justify-between items-center group mb-1 border border-transparent"><p className="text-xs font-black uppercase theme-text-main">{c.numero_cliente} - {c.nombre}</p>{c.es_heredado ? <span className="text-[8px] font-bold bg-purple-500/20 text-purple-500 px-2 py-0.5 rounded uppercase">Heredado</span> : null}</div>))}
                                </div>
                            )}
                        </div>

                        {/* Cotización */}
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cotización Autorizada_</label>
                            <div className="relative">
                                <CreditCard className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <input type="number" step="0.01" required value={data.monto_cotizado} onChange={e => setData('monto_cotizado', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 transition-all shadow-sm" />
                            </div>
                        </div>

                        {/* Proceso y Tipo Cliente en 2 columnas */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Proceso_</label>
                                <div className="relative">
                                    <FileSignature className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                    <select value={data.catalogo_proceso_id} required onChange={e => setData('catalogo_proceso_id', e.target.value)} className="w-full pl-9 pr-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-xs font-bold outline-none appearance-none focus:ring-2 shadow-sm cursor-pointer">
                                        <option value="">Selecciona...</option>
                                        {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                </div>
                            </div>
                            
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Clasificación_</label>
                                <div className="relative">
                                    <Users className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                    <select value={data.catalogo_tipo_cliente_id} onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)} className="w-full pl-9 pr-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-xs font-bold outline-none appearance-none focus:ring-2 shadow-sm cursor-pointer">
                                        <option value="">Asignar Tipo</option>
                                        {opcionesTipoCliente.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* Cambio de Lista */}
                        <div className="space-y-2">
                            <div className="flex justify-between items-end mb-1 px-1">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Lista Solicitada_</label>
                                {alertaLista && <span className="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 animate-pulse">{alertaLista.mensaje}</span>}
                            </div>
                            <div className="relative">
                                <TrendingUp className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <select value={data.catalogo_lista_descuento_id || ''} onChange={e => setData('catalogo_lista_descuento_id', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none focus:ring-2 transition-all shadow-sm cursor-pointer">
                                    <option value="">-- Mantener nivel actual --</option>
                                    {listas.filter(l => !l.nombre.toUpperCase().includes('COLABORADOR')).map(lista => {
                                        const montoHistorico = parseFloat(infoCliente?.monto_venta_actual || 0);
                                        const cotizacion = parseFloat(data.monto_cotizado || 0);
                                        const totalProyectado = montoHistorico + cotizacion;

                                        const listasOrdenadas = [...listas].filter(l => !l.nombre.toUpperCase().includes('COLABORADOR')).sort((a, b) => parseFloat(b.monto_requerido || b.monto_minimo || 0) - parseFloat(a.monto_requerido || a.monto_minimo || 0));
                                        const listaCalificada = listasOrdenadas.find(l => totalProyectado >= parseFloat(l.monto_requerido || l.monto_minimo || 0));

                                        let estaDeshabilitada = false; let textoEstado = '';

                                        if (lista.nombre === infoCliente?.lista_actual) { estaDeshabilitada = true; textoEstado = '(Nivel actual)'; } 
                                        else if (listaCalificada && lista.id !== listaCalificada.id) {
                                            estaDeshabilitada = true;
                                            if (parseFloat(lista.monto_requerido || lista.monto_minimo || 0) > parseFloat(listaCalificada.monto_requerido || listaCalificada.monto_minimo || 0)) {
                                                textoEstado = '(Monto insuficiente)';
                                            } else { textoEstado = '(Excede el límite)'; }
                                        }

                                        return <option key={lista.id} value={lista.id} disabled={estaDeshabilitada}>{lista.nombre} {estaDeshabilitada ? textoEstado : ''}</option>;
                                    })}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Evidencia y Submit */}
                    <div className="space-y-8 flex flex-col justify-between">
                        <div className="space-y-2 h-full flex flex-col">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia / Ticket (Ctrl + V)_</label>
                            <label className="flex-1 flex flex-col items-center justify-center min-h-[250px] border-2 border-dashed theme-border rounded-2xl cursor-pointer theme-element transition-all group overflow-hidden relative">
                                <Upload className="w-12 h-12 mb-4 theme-text-muted z-10" />
                                {previewEvidencia && <img src={previewEvidencia} className="absolute inset-0 w-full h-full object-cover opacity-80" />}
                                <input type="file" className="hidden" accept="image/*,.pdf" onChange={handleFileUpload} />
                            </label>
                        </div>
                        <button type="submit" disabled={processing} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[12px] shadow-xl hover:scale-105 transition-all disabled:opacity-50 outline-none flex justify-center items-center gap-3" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Send className="w-5 h-5" /> {processing ? 'Procesando...' : (modoEdicion ? 'Reenviar Corrección' : 'Transmitir Solicitud')}
                        </button>
                    </div>
                </form>
            </div>
        </div>, document.body
    );
}