import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Shield, Trash2, MessageCircle, AlertTriangle, Eye, Loader2, ArrowLeft } from 'lucide-react';
import { useModal } from '@/Layouts/AppLayout';
import axios from 'axios';

// Helper for dates
const formatDate = (dateString, options) => {
    if (!dateString) return '';
    return new Intl.DateTimeFormat('es-MX', options).format(new Date(dateString));
};

function LoadingSpinner({ className = "w-6 h-6" }) {
    return <Loader2 className={`animate-spin ${className} text-[var(--color-primario)]`} />;
}

function MonitoreoContent({ permisoEliminar, usuarios = [] }) {
    const { openModal, closeModal } = useModal();
    
    // Lista de conversaciones
    const [conversaciones, setConversaciones] = useState([]);
    const [nextPageUrl, setNextPageUrl] = useState(route('admin.mensajeria_monitoreo.conversaciones'));
    const [loadingConversaciones, setLoadingConversaciones] = useState(false);
    const [q, setQ] = useState('');
    const [filtroUserId, setFiltroUserId] = useState('');

    // Conversación activa
    const [activa, setActiva] = useState(null);
    const [mensajes, setMensajes] = useState([]);
    const [nextCursor, setNextCursor] = useState(null);
    const [loadingMensajes, setLoadingMensajes] = useState(false);
    const observerRef = useRef(null);

    const loadConversaciones = useCallback(async (reset = false) => {
        if (loadingConversaciones) return;
        setLoadingConversaciones(true);

        const params = new URLSearchParams();
        if (q) params.append('q', q);
        if (filtroUserId) params.append('user_id', filtroUserId);

        const url = reset 
            ? `${route('admin.mensajeria_monitoreo.conversaciones')}?${params.toString()}` 
            : nextPageUrl;

        if (!url) {
            setLoadingConversaciones(false);
            return;
        }

        try {
            const res = await axios.get(url);
            setConversaciones(prev => reset ? res.data.data : [...prev, ...res.data.data]);
            setNextPageUrl(res.data.next_page_url);
        } catch (error) {
            console.error(error);
        } finally {
            setLoadingConversaciones(false);
        }
    }, [nextPageUrl, q, loadingConversaciones]);

    useEffect(() => {
        const timer = setTimeout(() => {
            loadConversaciones(true);
        }, 500);
        return () => clearTimeout(timer);
    }, [q, filtroUserId]);

    const selectConversacion = async (conv) => {
        setActiva(conv);
        setMensajes([]);
        setNextCursor(null);
        await loadMensajes(conv.id, null);
    };

    const loadMensajes = async (convId, cursor) => {
        setLoadingMensajes(true);
        try {
            const url = route('admin.mensajeria_monitoreo.mensajes', convId) + (cursor ? `?cursor=${encodeURIComponent(cursor)}` : '');
            const res = await axios.get(url);
            
            // Los mensajes vienen del más nuevo al más viejo en order, pero el servicio los invierte para el chat
            // Invertimos la lógica para mostrarlos como historial de chat,
            // pero como los cargamos hacia arriba, hay que anexarlos al principio
            setMensajes(prev => cursor ? [...res.data.mensajes, ...prev] : res.data.mensajes);
            setNextCursor(res.data.next_cursor);
        } catch (error) {
            console.error(error);
        } finally {
            setLoadingMensajes(false);
        }
    };

    // Intersection Observer for infinite scroll on messages
    const topElementRef = useRef(null);

    useEffect(() => {
        if (!topElementRef.current || !nextCursor || loadingMensajes || !activa) return;

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                loadMensajes(activa.id, nextCursor);
            }
        }, { threshold: 0.1 });

        observer.observe(topElementRef.current);
        return () => observer.disconnect();
    }, [nextCursor, loadingMensajes, activa]);

    const handleDeleteConversacion = (conv) => {
        openModal(
            <div className="flex flex-col items-center text-center p-4">
                <div className="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <AlertTriangle className="w-8 h-8 text-red-600" />
                </div>
                <h3 className="text-xl font-bold mb-2 theme-text-main">¿Eliminar Conversación?</h3>
                <p className="text-sm theme-text-muted mb-6">
                    Se marcará la conversación como eliminada. Esta acción borrará el historial para los participantes.
                </p>
                <div className="flex gap-3 w-full">
                    <button
                        onClick={closeModal}
                        className="flex-1 py-3 px-4 rounded-xl font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={async () => {
                            try {
                                await axios.delete(route('admin.mensajeria_monitoreo.conversaciones.destroy', conv.id));
                                setConversaciones(prev => prev.filter(c => c.id !== conv.id));
                                if (activa?.id === conv.id) setActiva(null);
                                closeModal();
                                window.dispatchEvent(new CustomEvent('gelia-toast', { detail: { mensaje: 'Conversación eliminada' } }));
                            } catch (error) {
                                console.error(error);
                            }
                        }}
                        className="flex-1 py-3 px-4 rounded-xl font-medium bg-red-600 hover:bg-red-700 text-white transition-colors"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        );
    };

    const handleDeleteMensaje = (msj) => {
        openModal(
            <div className="flex flex-col items-center text-center p-4">
                <div className="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                    <AlertTriangle className="w-8 h-8 text-red-600" />
                </div>
                <h3 className="text-xl font-bold mb-2 theme-text-main">¿Eliminar Mensaje?</h3>
                <p className="text-sm theme-text-muted mb-6">
                    Se borrará el mensaje seleccionado del historial de la conversación.
                </p>
                <div className="flex gap-3 w-full">
                    <button
                        onClick={closeModal}
                        className="flex-1 py-3 px-4 rounded-xl font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={async () => {
                            try {
                                await axios.delete(route('admin.mensajeria_monitoreo.mensajes.destroy', msj.id));
                                setMensajes(prev => prev.filter(m => m.id !== msj.id));
                                closeModal();
                                window.dispatchEvent(new CustomEvent('gelia-toast', { detail: { mensaje: 'Mensaje eliminado' } }));
                            } catch (error) {
                                console.error(error);
                            }
                        }}
                        className="flex-1 py-3 px-4 rounded-xl font-medium bg-red-600 hover:bg-red-700 text-white transition-colors"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title="Monitoreo de Mensajería" />
            
            <div className="h-[calc(100dvh-6rem)] sm:h-auto sm:min-h-screen p-4 sm:p-6 lg:p-8 flex flex-col max-w-7xl mx-auto gap-6 animate-fade-in">
                
                <header className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-2">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 rounded-xl bg-[color-mix(in_srgb,var(--color-primario)_15%,transparent)] flex items-center justify-center">
                                <Shield className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-black theme-text-main tracking-tight">
                                Monitoreo de Mensajería
                            </h1>
                        </div>
                        <p className="text-sm font-medium theme-text-muted max-w-2xl">
                            Visualiza y gestiona las conversaciones para mantener el orden del sistema.
                        </p>
                    </div>
                </header>

                <div className="flex-1 flex flex-col md:flex-row gap-6 min-h-0 h-full">
                    
                    {/* Panel Izquierdo: Lista de Conversaciones */}
                    <div className={`w-full md:w-1/3 flex flex-col min-h-0 theme-surface theme-border rounded-3xl overflow-hidden border shadow-sm ${activa ? 'hidden md:flex' : 'flex'}`}>
                        <div className="p-4 border-b theme-border shrink-0 flex flex-col gap-3">
                            <input 
                                type="text"
                                placeholder="Buscar conversación..."
                                className="w-full px-4 py-2.5 rounded-xl border theme-border theme-surface text-sm theme-text-main outline-none focus:ring-2"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                value={q}
                                onChange={e => setQ(e.target.value)}
                            />
                            <select
                                className="w-full px-4 py-2.5 rounded-xl border theme-border theme-surface text-sm theme-text-main outline-none focus:ring-2 appearance-none"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                value={filtroUserId}
                                onChange={e => setFiltroUserId(e.target.value)}
                            >
                                <option value="">Todos los usuarios</option>
                                {usuarios.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        
                        <div className="flex-1 overflow-y-auto p-2 custom-scrollbar">
                            {conversaciones.length === 0 && !loadingConversaciones && (
                                <div className="text-center p-8 text-sm theme-text-muted">
                                    No hay conversaciones que mostrar.
                                </div>
                            )}

                            {conversaciones.map(conv => {
                                let tituloChat = conv.nombre;
                                let isGroup = conv.tipo === 'grupo';

                                if (!isGroup) {
                                    const participantes = conv.usuarios || [];
                                    if (filtroUserId) {
                                        const other = participantes.find(u => u.id != filtroUserId);
                                        tituloChat = other ? `Chat con ${other.name}` : 'Chat Directo';
                                    } else {
                                        tituloChat = `Chat: ${participantes.map(u => u.name).join(' y ')}`;
                                    }
                                } else {
                                    tituloChat = `[Grupo] ${tituloChat || 'Sin Nombre'}`;
                                }

                                return (
                                    <div 
                                        key={conv.id}
                                        onClick={() => selectConversacion(conv)}
                                        className={`flex items-center gap-3 p-3 rounded-2xl cursor-pointer transition-colors mb-1 group ${activa?.id === conv.id ? 'bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'hover:bg-black/5 dark:hover:bg-white/5'}`}
                                    >
                                        <div className="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-800 shrink-0 overflow-hidden flex items-center justify-center font-bold theme-text-main">
                                            {conv.foto ? (
                                                <img src={`/storage/${conv.foto}`} alt="" className="w-full h-full object-cover" />
                                            ) : (
                                                tituloChat?.charAt(0).toUpperCase() || 'C'
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex justify-between items-center mb-0.5">
                                                <h4 className="font-bold text-sm theme-text-main truncate pr-2">{tituloChat}</h4>
                                            <span className="text-[10px] theme-text-muted shrink-0">
                                                {conv.ultimo_mensaje_at ? formatDate(conv.ultimo_mensaje_at, { day: '2-digit', month: 'short' }) : ''}
                                            </span>
                                        </div>
                                        <p className="text-xs theme-text-muted truncate">
                                            {conv.ultimo_mensaje_preview || 'Sin mensajes'}
                                        </p>
                                    </div>
                                    {permisoEliminar && (
                                        <button 
                                            onClick={(e) => { e.stopPropagation(); handleDeleteConversacion(conv); }}
                                            className="opacity-0 group-hover:opacity-100 p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all"
                                            title="Eliminar Conversación"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                        )}
                                    </div>
                                );
                            })}

                            {loadingConversaciones && (
                                <div className="flex justify-center p-4">
                                    <LoadingSpinner />
                                </div>
                            )}

                            {nextPageUrl && !loadingConversaciones && (
                                <button 
                                    onClick={() => loadConversaciones(false)}
                                    className="w-full py-2 text-xs font-bold theme-text-muted hover:theme-text-main transition-colors text-center mt-2"
                                >
                                    Cargar más...
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Panel Derecho: Mensajes */}
                    <div className={`w-full md:w-2/3 flex flex-col min-h-0 theme-surface theme-border rounded-3xl overflow-hidden border shadow-sm ${!activa ? 'hidden md:flex items-center justify-center bg-black/5 dark:bg-white/5' : 'flex'}`}>
                        {!activa ? (
                            <div className="flex flex-col items-center gap-4 opacity-50">
                                <MessageCircle className="w-16 h-16 theme-text-muted" />
                                <p className="text-sm font-medium theme-text-main">Selecciona una conversación</p>
                            </div>
                        ) : (
                            <>
                                {/* Cabecera Chat */}
                                <div className="px-4 py-3 border-b theme-border flex items-center justify-between bg-black/5 dark:bg-white/5 shrink-0">
                                    <div className="flex items-center gap-3">
                                        <button 
                                            className="md:hidden p-2 rounded-xl hover:bg-black/10 dark:hover:bg-white/10"
                                            onClick={() => setActiva(null)}
                                        >
                                            <ArrowLeft className="w-5 h-5 theme-text-main" />
                                        </button>
                                        <div className="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center font-bold theme-text-main overflow-hidden">
                                            {activa.foto ? (
                                                <img src={`/storage/${activa.foto}`} className="w-full h-full object-cover" alt="" />
                                            ) : (
                                                activa.nombre?.charAt(0).toUpperCase() || 'C'
                                            )}
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-sm theme-text-main">
                                                {activa.tipo === 'grupo' ? `[Grupo] ${activa.nombre || 'Sin Nombre'}` : 
                                                    (filtroUserId 
                                                        ? `Chat con ${activa.usuarios?.find(u => u.id != filtroUserId)?.name || 'Directo'}`
                                                        : `Chat: ${activa.usuarios?.map(u => u.name).join(' y ') || 'Directo'}`)
                                                }
                                            </h3>
                                            <p className="text-xs theme-text-muted">{activa.mensajes_count || 0} mensajes</p>
                                        </div>
                                    </div>
                                    {permisoEliminar && (
                                        <button 
                                            onClick={() => handleDeleteConversacion(activa)}
                                            className="flex items-center gap-2 px-3 py-1.5 text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 rounded-xl transition-colors"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                            <span className="hidden sm:inline">Eliminar Chat</span>
                                        </button>
                                    )}
                                </div>

                                {/* Mensajes Scroll */}
                                <div className="flex-1 overflow-y-auto p-4 custom-scrollbar flex flex-col gap-4">
                                    {nextCursor && (
                                        <div ref={topElementRef} className="flex justify-center p-4">
                                            {loadingMensajes ? <LoadingSpinner /> : <span className="text-xs theme-text-muted font-medium">Cargando historial...</span>}
                                        </div>
                                    )}

                                    {mensajes.length === 0 && !loadingMensajes && (
                                        <div className="text-center p-8 text-sm theme-text-muted flex-1 flex items-center justify-center">
                                            La conversación está vacía.
                                        </div>
                                    )}

                                    {mensajes.map((msj, index) => {
                                        const dateCurrent = new Date(msj.created_at);
                                        const datePrev = index > 0 ? new Date(mensajes[index-1].created_at) : null;
                                        const showDate = index === 0 || 
                                            dateCurrent.toDateString() !== datePrev?.toDateString();

                                        return (
                                            <React.Fragment key={msj.id}>
                                                {showDate && (
                                                    <div className="flex justify-center my-4">
                                                        <span className="px-3 py-1 text-[10px] font-bold uppercase tracking-wider bg-black/5 dark:bg-white/10 theme-text-muted rounded-full">
                                                            {formatDate(msj.created_at, { day: '2-digit', month: 'long', year: 'numeric' })}
                                                        </span>
                                                    </div>
                                                )}

                                                <div className="flex gap-3 max-w-[85%] group">
                                                    <div className="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-800 shrink-0 mt-1 overflow-hidden">
                                                        {msj.user?.foto_perfil ? (
                                                            <img src={`/storage/${msj.user.foto_perfil}`} className="w-full h-full object-cover" alt=""/>
                                                        ) : (
                                                            <div className="w-full h-full flex justify-center items-center font-bold text-xs theme-text-main">
                                                                {msj.user?.name?.charAt(0).toUpperCase()}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-col items-start min-w-0">
                                                        <div className="flex items-baseline gap-2 mb-1">
                                                            <span className="text-xs font-bold theme-text-main truncate max-w-[150px] sm:max-w-[200px]">
                                                                {msj.user?.name}
                                                            </span>
                                                            <span className="text-[10px] theme-text-muted">
                                                                {formatDate(msj.created_at, { hour: '2-digit', minute: '2-digit' })}
                                                            </span>
                                                        </div>
                                                        <div className="bg-black/5 dark:bg-white/5 rounded-2xl rounded-tl-sm px-4 py-2 text-sm theme-text-main whitespace-pre-wrap break-words">
                                                            {msj.tipo === 'texto' ? msj.contenido : `[${msj.tipo.toUpperCase()}]`}
                                                        </div>
                                                    </div>

                                                    {permisoEliminar && (
                                                        <button 
                                                            onClick={() => handleDeleteMensaje(msj)}
                                                            className="opacity-0 group-hover:opacity-100 self-center p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all shrink-0 ml-2"
                                                            title="Eliminar Mensaje"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </React.Fragment>
                                        );
                                    })}
                                </div>
                            </>
                        )}
                    </div>

                </div>

            </div>
        </>
    );
}

export default function MonitoreoMensajeria(props) {
    return (
        <AppLayout>
            <MonitoreoContent {...props} />
        </AppLayout>
    );
}
