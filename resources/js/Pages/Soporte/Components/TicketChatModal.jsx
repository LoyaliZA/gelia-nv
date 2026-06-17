import React, { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_SECONDARY, THEME_BTN_PRIMARY, THEME_INPUT } from '@/utils/geliaTheme';
import { Send, MessageCircle, Clock, User, ShieldAlert, AlertCircle, HelpCircle } from 'lucide-react';

function getPrioridadAsignada(ticket) {
    return ticket?.prioridadAsignada || ticket?.prioridad_asignada || null;
}

function getPrioridadAsignadaId(ticket) {
    return ticket?.prioridad_asignada_id ?? getPrioridadAsignada(ticket)?.id ?? '';
}

export default function TicketChatModal({ ticket: initialTicket, onClose, isAgent = false, auth, prioridades = [], estados = [], onMarkRead }) {
    const [ticketData, setTicketData] = useState(initialTicket);
    const [loading, setLoading] = useState(true);
    const [replyMsg, setReplyMsg] = useState('');
    const [sending, setSending] = useState(false);
    const [selectedPriority, setSelectedPriority] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('');
    const [updatingPriority, setUpdatingPriority] = useState(false);
    const [updatingStatus, setUpdatingStatus] = useState(false);
    const [assigning, setAssigning] = useState(false);
    const chatContainerRef = useRef(null);

    const appendInteraccion = useCallback((nueva) => {
        if (!nueva?.id) return;
        setTicketData((prev) => {
            const existing = prev.interacciones || [];
            if (existing.some((i) => i.id === nueva.id)) return prev;
            return { ...prev, interacciones: [...existing, nueva] };
        });
    }, []);

    const refetchTicket = useCallback(async () => {
        const url = isAgent ? `/soporte/agente/tickets/${initialTicket.id}` : `/soporte/mis-tickets/${initialTicket.id}`;
        const res = await axios.get(url);
        setTicketData(res.data);
        setSelectedPriority(String(getPrioridadAsignadaId(res.data) || ''));
        setSelectedStatus(String(res.data.estado_id || ''));
        return res.data;
    }, [initialTicket.id, isAgent]);

    useEffect(() => {
        if (initialTicket.id >= 99990 || typeof window === 'undefined' || !window.Echo) return;

        const channelName = `soporte.ticket.${initialTicket.id}`;

        window.Echo.private(channelName)
            .listen('.interaccion.creada', (event) => {
                appendInteraccion(event.interaccion);
            });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [initialTicket.id, appendInteraccion]);

    useEffect(() => {
        const onNotification = (e) => {
            const payload = e.detail || {};
            if (payload.ticket_id !== initialTicket.id) return;
            if (!['soporte_respuesta_agente', 'soporte_respuesta_usuario'].includes(payload.tipo)) return;
            refetchTicket();
        };

        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [initialTicket.id, refetchTicket]);

    useEffect(() => {
        const fetchTicket = async () => {
            if (initialTicket.id >= 99990) {
                setLoading(false);
                return;
            }

            try {
                const url = isAgent ? `/soporte/agente/tickets/${initialTicket.id}` : `/soporte/mis-tickets/${initialTicket.id}`;
                const res = await axios.get(url);
                setTicketData(res.data);
                setSelectedPriority(String(getPrioridadAsignadaId(res.data) || ''));
                setSelectedStatus(String(res.data.estado_id || ''));
                onMarkRead?.();
            } catch (err) {
                console.error('Error fetching ticket', err);
            } finally {
                setLoading(false);
            }
        };
        fetchTicket();
    }, [initialTicket.id, isAgent, prioridades.length]);

    useEffect(() => {
        if (chatContainerRef.current) {
            chatContainerRef.current.scrollTop = chatContainerRef.current.scrollHeight;
        }
    }, [ticketData?.interacciones]);

    const handleSendReply = async () => {
        if (!replyMsg.trim() || sending || initialTicket.id >= 99990) return;
        setSending(true);
        try {
            const url = isAgent ? `/soporte/agente/tickets/${initialTicket.id}/reply` : `/soporte/mis-tickets/${initialTicket.id}/reply`;
            await axios.post(url, { mensaje: replyMsg });
            setReplyMsg('');
            await refetchTicket();
        } catch (err) {
            console.error('Error sending reply', err);
        } finally {
            setSending(false);
        }
    };

    const handleUpdatePriority = async () => {
        if (!selectedPriority || updatingPriority || initialTicket.id >= 99990) return;
        setUpdatingPriority(true);
        try {
            await axios.post(`/soporte/agente/tickets/${initialTicket.id}/priority`, {
                prioridad_asignada_id: selectedPriority,
            });
            await refetchTicket();
        } catch (err) {
            console.error('Error updating priority', err);
        } finally {
            setUpdatingPriority(false);
        }
    };

    const handleUpdateStatus = async () => {
        if (!selectedStatus || updatingStatus || initialTicket.id >= 99990) return;
        setUpdatingStatus(true);
        try {
            await axios.post(`/soporte/agente/tickets/${initialTicket.id}/status`, {
                estado_id: selectedStatus,
            });
            await refetchTicket();
        } catch (err) {
            console.error('Error updating status', err);
        } finally {
            setUpdatingStatus(false);
        }
    };

    const handleAssignToMe = async () => {
        if (assigning || initialTicket.id >= 99990) return;
        setAssigning(true);
        try {
            await axios.post(`/soporte/agente/tickets/${initialTicket.id}/assign`, {
                asignado_a_id: auth?.user?.id,
            });
            await refetchTicket();
        } catch (err) {
            console.error('Error assigning ticket', err);
        } finally {
            setAssigning(false);
        }
    };

    const prioridadAsignada = getPrioridadAsignada(ticketData);
    const isDemoTicket = initialTicket.id >= 99990;

    return (
        <div className={`${THEME_MODAL_OVERLAY} z-[1000] p-4 md:p-8`}>
            <div className={`${THEME_MODAL_SHELL} max-w-6xl w-full h-full max-h-[90vh]`}>

                <div className="flex flex-shrink-0 items-center justify-between p-4 md:p-5 border-b theme-border theme-surface">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-[var(--color-primario)]/10 text-[var(--color-primario)] flex items-center justify-center">
                            <MessageCircle className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-sm md:text-lg font-black uppercase theme-text-main leading-tight line-clamp-1">
                                #{ticketData.id} - {ticketData.titulo}
                            </h2>
                            <div className="text-xs theme-text-muted flex items-center gap-2 mt-1 flex-wrap">
                                <span className="font-bold">{ticketData.modulo?.nombre}</span>
                                {ticketData.categoria?.nombre && (
                                    <>
                                        <span className="w-1 h-1 rounded-full theme-element border theme-border"></span>
                                        <span>{ticketData.categoria.nombre}</span>
                                    </>
                                )}
                                <span className="w-1 h-1 rounded-full theme-element border theme-border"></span>
                                <span
                                    className="font-bold uppercase tracking-wider"
                                    style={{ color: ticketData.estado?.color || 'var(--theme-text-muted)' }}
                                >
                                    {ticketData.estado?.nombre || 'Pendiente'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onClick={onClose} className="w-8 h-8 rounded-full flex items-center justify-center theme-element hover:border-[var(--color-primario)] border border-transparent transition-colors theme-text-main">
                        ✕
                    </button>
                </div>

                <div className="flex-1 flex flex-col md:flex-row overflow-hidden min-h-0">

                    <div className="flex-1 flex flex-col bg-transparent relative min-w-0">

                        <div ref={chatContainerRef} className="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 scroll-smooth">
                            {loading ? (
                                <div className="flex items-center justify-center h-full">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--color-primario)]"></div>
                                </div>
                            ) : (
                                <>
                                    {isDemoTicket && (
                                        <div className="text-center text-yellow-600 bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg text-xs font-bold">
                                            Ticket de demostración (modo pruebas)
                                        </div>
                                    )}
                                    {(!ticketData.interacciones || ticketData.interacciones.length === 0) ? (
                                        <div className="text-center theme-text-muted py-10">
                                            <MessageCircle className="w-12 h-12 mx-auto mb-3 opacity-20" />
                                            <p className="text-sm">No hay mensajes en este ticket aún.</p>
                                            {ticketData.descripcion && (
                                                <p className="text-xs mt-2 max-w-md mx-auto opacity-70">{ticketData.descripcion}</p>
                                            )}
                                        </div>
                                    ) : (
                                        ticketData.interacciones.map((interaccion) => {
                                            const isMessageFromAgent = interaccion.user_id !== ticketData.user_id;
                                            const amITheAuthor = interaccion.user_id === auth?.user?.id;

                                            return (
                                                <div key={interaccion.id} className={`flex gap-3 max-w-[90%] md:max-w-[80%] ${amITheAuthor ? 'ml-auto flex-row-reverse' : ''}`}>
                                                    <div className={`w-8 h-8 rounded-full flex-shrink-0 overflow-hidden flex items-center justify-center ${isMessageFromAgent ? 'bg-[var(--color-primario)]/10 text-[var(--color-primario)] border border-[var(--color-primario)]/20' : 'theme-element border theme-border'}`}>
                                                        {interaccion.user?.profile_photo_url ? (
                                                            <img src={interaccion.user.profile_photo_url} alt={interaccion.user.name} className="w-full h-full object-cover" />
                                                        ) : (
                                                            isMessageFromAgent ? <ShieldAlert className="w-4 h-4" /> : <User className="w-4 h-4 text-gray-400" />
                                                        )}
                                                    </div>
                                                    <div className="flex flex-col gap-1">
                                                        <div className={`${amITheAuthor ? 'bg-[var(--color-primario)] text-white rounded-tr-none shadow-md' : 'theme-surface theme-text-main rounded-tl-none border theme-border'} p-4 rounded-2xl shadow-sm`}>
                                                            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                                                {interaccion.mensaje}
                                                            </p>
                                                        </div>
                                                        <span className={`text-[10px] font-bold uppercase ${amITheAuthor ? 'theme-text-muted text-right opacity-70' : 'theme-text-muted text-left'}`}>
                                                            {new Date(interaccion.created_at).toLocaleString('es-MX', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })} - {interaccion.user?.name}
                                                        </span>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </>
                            )}
                        </div>

                        <div className="flex-shrink-0 p-4 theme-surface border-t theme-border min-h-[88px] flex flex-col justify-center">
                            <div className="flex items-end gap-2">
                                <div className="flex-1 theme-element border theme-border rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[var(--color-primario)]/50 transition-shadow">
                                    <textarea
                                        className="w-full bg-transparent border-none focus:ring-0 px-4 py-3 text-sm theme-text-main resize-none min-h-[48px] max-h-[150px]"
                                        placeholder={isDemoTicket ? 'Chat deshabilitado en modo pruebas' : 'Escribe tu respuesta aquí...'}
                                        rows="1"
                                        value={replyMsg}
                                        disabled={isDemoTicket}
                                        onChange={e => setReplyMsg(e.target.value)}
                                        onKeyDown={e => {
                                            if (e.key === 'Enter' && !e.shiftKey) {
                                                e.preventDefault();
                                                handleSendReply();
                                            }
                                        }}
                                    />
                                </div>
                                <button
                                    onClick={handleSendReply}
                                    disabled={sending || !replyMsg.trim() || isDemoTicket}
                                    className="w-12 h-12 flex-shrink-0 bg-[var(--color-primario)] text-white rounded-xl flex items-center justify-center hover:scale-105 transition-transform shadow-md disabled:opacity-50 disabled:hover:scale-100">
                                    <Send className={`w-5 h-5 -ml-0.5 mt-0.5 ${sending ? 'animate-pulse' : ''}`} />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="w-full md:w-80 border-t md:border-t-0 md:border-l theme-border theme-surface overflow-y-auto flex-shrink-0 flex flex-col">
                        <div className="p-4 border-b theme-border hidden md:block">
                            <h3 className="font-black uppercase text-xs tracking-widest theme-text-muted">Detalles del Ticket</h3>
                        </div>
                        <div className="p-5 space-y-6 flex-1">

                            <div className="theme-element rounded-xl p-4 border theme-border space-y-4 shadow-sm">
                                <div>
                                    <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-1 flex items-center gap-1">
                                        <User className="w-3 h-3" /> Solicitante
                                    </span>
                                    <div className="flex items-center gap-2">
                                        {ticketData.user?.profile_photo_url && (
                                            <img src={ticketData.user.profile_photo_url} className="w-6 h-6 rounded-full object-cover" alt="" />
                                        )}
                                        <span className="text-sm font-bold theme-text-main">
                                            {ticketData.user?.name || 'Sistema'}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-1 flex items-center gap-1">
                                        <ShieldAlert className="w-3 h-3" /> Agente Asignado
                                    </span>
                                    {ticketData.asignadoA ? (
                                        <div className="flex items-center gap-2">
                                            {ticketData.asignadoA.profile_photo_url && (
                                                <img src={ticketData.asignadoA.profile_photo_url} className="w-6 h-6 rounded-full object-cover" alt="" />
                                            )}
                                            <span className="text-sm font-bold theme-text-main">
                                                {ticketData.asignadoA.name}
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-sm theme-text-muted italic">Sin Asignar</span>
                                    )}
                                    {isAgent && !ticketData.asignadoA && !isDemoTicket && (
                                        <button
                                            onClick={handleAssignToMe}
                                            disabled={assigning}
                                            className="mt-2 text-[10px] font-bold uppercase text-[var(--color-primario)] hover:underline"
                                        >
                                            {assigning ? 'Asignando...' : 'Asignarme este ticket'}
                                        </button>
                                    )}
                                </div>
                                <div>
                                    <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-1 flex items-center gap-1">
                                        <Clock className="w-3 h-3" /> Creado el
                                    </span>
                                    <span className="text-sm font-bold theme-text-main">
                                        {new Date(ticketData.created_at).toLocaleString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                </div>
                                {ticketData.fecha_vencimiento_sla && (
                                    <div>
                                        <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-1 flex items-center gap-1">
                                            <AlertCircle className="w-3 h-3" /> Vence SLA
                                        </span>
                                        <span className="text-sm font-bold theme-text-main">
                                            {new Date(ticketData.fecha_vencimiento_sla).toLocaleString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-2 cursor-help" title="La prioridad final será asignada por soporte">
                                        Prioridad <HelpCircle className="w-3 h-3 text-gray-400 inline-block mb-0.5" />
                                    </span>
                                    {isAgent ? (
                                        <div className="space-y-2">
                                            <select
                                                className={`${THEME_INPUT} text-sm font-bold py-1.5`}
                                                value={selectedPriority}
                                                disabled={isDemoTicket}
                                                onChange={e => setSelectedPriority(e.target.value)}
                                            >
                                                <option value="">Seleccionar Prioridad...</option>
                                                {prioridades.map(p => (
                                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                                ))}
                                            </select>
                                            <button
                                                onClick={handleUpdatePriority}
                                                disabled={updatingPriority || !selectedPriority || selectedPriority === String(getPrioridadAsignadaId(ticketData) || '') || isDemoTicket}
                                                className={`${THEME_BTN_PRIMARY} w-full text-[10px] py-1.5 disabled:opacity-50`}
                                            >
                                                {updatingPriority ? 'Guardando...' : 'Asignar Prioridad'}
                                            </button>
                                        </div>
                                    ) : (
                                        prioridadAsignada ? (
                                            <div className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border theme-border theme-element shadow-sm">
                                                <span className="w-2.5 h-2.5 rounded-full shadow-sm" style={{ backgroundColor: prioridadAsignada.color || 'var(--color-primario)' }}></span>
                                                <span className="text-sm font-black uppercase tracking-wide" style={{ color: prioridadAsignada.color || 'var(--color-primario)' }}>
                                                    {prioridadAsignada.nombre}
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-sm theme-text-muted italic px-3 py-2 theme-element rounded-lg inline-block border theme-border">Bajo revisión</span>
                                        )
                                    )}
                                </div>

                                {isAgent && estados.length > 0 && (
                                    <div>
                                        <span className="block text-[10px] font-black uppercase theme-text-muted opacity-80 mb-2">
                                            Estado
                                        </span>
                                        <div className="space-y-2">
                                            <select
                                                className={`${THEME_INPUT} text-sm font-bold py-1.5`}
                                                value={selectedStatus}
                                                disabled={isDemoTicket}
                                                onChange={e => setSelectedStatus(e.target.value)}
                                            >
                                                <option value="">Seleccionar Estado...</option>
                                                {estados.map(e => (
                                                    <option key={e.id} value={e.id}>{e.nombre}</option>
                                                ))}
                                            </select>
                                            <button
                                                onClick={handleUpdateStatus}
                                                disabled={updatingStatus || !selectedStatus || selectedStatus === String(ticketData.estado_id || '') || isDemoTicket}
                                                className={`${THEME_BTN_SECONDARY} w-full text-[10px] py-1.5 disabled:opacity-50`}
                                            >
                                                {updatingStatus ? 'Guardando...' : 'Actualizar Estado'}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    );
}
