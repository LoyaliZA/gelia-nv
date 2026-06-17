import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    GELIA_RESPONSIVE_GRID,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA
} from '@/utils/geliaTheme';
import { HelpCircle, Plus, MessageCircle, Send } from 'lucide-react';
import TicketCard from '../Components/TicketCard';
import TicketChatModal from '../Components/TicketChatModal';

export default function Index({ auth, tickets, modoPruebas, modulos, categorias, prioridades }) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [activeTicketChat, setActiveTicketChat] = useState(null);
    const [localTickets, setLocalTickets] = useState(tickets.data || []);

    const markTicketRead = (ticketId) => {
        setLocalTickets((prev) =>
            prev.map((t) => (t.id === ticketId ? { ...t, has_unread: false } : t))
        );
    };

    const markTicketUnread = (ticketId) => {
        setLocalTickets((prev) =>
            prev.map((t) => (t.id === ticketId ? { ...t, has_unread: true } : t))
        );
    };

    useEffect(() => {
        const onNotification = (e) => {
            const payload = e.detail || {};
            if (!payload.ticket_id) return;
            if (!['soporte_respuesta_agente', 'soporte_respuesta_usuario'].includes(payload.tipo)) return;
            if (activeTicketChat?.id === payload.ticket_id) return;
            markTicketUnread(payload.ticket_id);
        };

        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [activeTicketChat?.id]);

    const openTicket = (ticket) => {
        setActiveTicketChat(ticket);
        markTicketRead(ticket.id);
    };

    return (
        <AppLayout>
            <Head title="Mis Tickets de Soporte" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex-1">
                        <GeliaTituloCard
                            eyebrow="Soporte y QA_"
                            title="MIS"
                            titleHighlight="TICKETS"
                            description="Aquí puedes consultar el estado de tus reportes e incidencias. Si tienes un nuevo problema, por favor repórtalo."
                            icon={HelpCircle}
                        />
                    </div>
                    <div className="flex-none">
                        <button 
                            onClick={() => setCreateModalOpen(true)}
                            className={`${THEME_BTN_PRIMARY} flex items-center gap-2 whitespace-nowrap`}
                        >
                            <Plus className="w-4 h-4" />
                            Crear Nuevo Ticket
                        </button>
                    </div>
                </div>

                {modoPruebas && (
                    <div className="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg relative" role="alert">
                        <strong className="font-bold">¡Modo Pruebas Activo! </strong>
                        <span className="block sm:inline">Estás viendo tickets generados automáticamente. La creación de tickets no impactará la base de datos de producción.</span>
                    </div>
                )}

                {localTickets.length === 0 ? (
                    <div className={geliaCardClass('p-12 flex flex-col items-center justify-center text-center')}>
                        <div className="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                            <MessageCircle className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-black uppercase theme-text-main mb-2">No hay tickets activos</h3>
                        <p className="theme-text-muted max-w-sm mb-6">Aún no has reportado ninguna incidencia o solicitud. Estamos aquí para ayudarte cuando lo necesites.</p>
                        <button onClick={() => setCreateModalOpen(true)} className={THEME_BTN_PRIMARY}>
                            Crear mi primer ticket
                        </button>
                    </div>
                ) : (
                    <div className={GELIA_RESPONSIVE_GRID}>
                        {localTickets.map((ticket) => (
                            <TicketCard 
                                key={ticket.id} 
                                ticket={ticket} 
                                onClick={() => openTicket(ticket)} 
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Modal Creación Ticket */}
            {createModalOpen && typeof document !== 'undefined' && ReactDOM.createPortal(
                <CreateTicketModal 
                    modulos={modulos || []} 
                    categorias={categorias || []} 
                    prioridades={prioridades || []}
                    onClose={() => setCreateModalOpen(false)} 
                />,
                document.body
            )}

            {/* Modal Chat de Ticket (Mockup) */}
            {activeTicketChat && typeof document !== 'undefined' && ReactDOM.createPortal(
                <TicketChatModal 
                    ticket={activeTicketChat} 
                    onClose={() => setActiveTicketChat(null)} 
                    onMarkRead={() => markTicketRead(activeTicketChat.id)}
                    auth={auth}
                />,
                document.body
            )}
        </AppLayout>
    );
}

function CreateTicketModal({ modulos, categorias, prioridades, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        titulo: '',
        modulo_id: '',
        categoria_id: '',
        prioridad_id: '',
        descripcion: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('soporte.tickets.store'), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className={`${THEME_MODAL_OVERLAY} z-[1000]`}>
            <div className={`${THEME_MODAL_SHELL} max-w-2xl w-full`}>
                <div className="p-5 border-b theme-border flex justify-between items-center bg-gray-50/50 dark:bg-gray-800/30">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-[var(--color-primario)]/10 text-[var(--color-primario)] flex items-center justify-center">
                            <Plus className="w-5 h-5" />
                        </div>
                        <div>
                            <h2 className="text-lg font-black uppercase theme-text-main leading-tight">Nuevo Ticket</h2>
                            <p className="text-xs theme-text-muted">Reportar incidencia o consulta</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors theme-text-main">
                        ✕
                    </button>
                </div>
                
                <div className="p-6 overflow-y-auto max-h-[70vh]">
                    <form id="create-ticket-form" onSubmit={submit} className="space-y-5">
                        <div>
                            <label className={THEME_LABEL}>Título breve del problema</label>
                            <input type="text" className={THEME_INPUT} value={data.titulo} onChange={e => setData('titulo', e.target.value)} placeholder="Ej. Error al descargar reporte de ventas" required />
                            {errors.titulo && <p className="text-red-500 text-[10px] font-bold uppercase mt-1">{errors.titulo}</p>}
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div className="sm:col-span-2">
                                <label className={THEME_LABEL}>¿En qué módulo ocurre?</label>
                                {modulos.length === 0 ? (
                                    <div className="p-3 mt-1 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
                                        <strong>Acceso Restringido:</strong> No tienes permisos asignados en ningún módulo del sistema, por lo que no puedes generar tickets. Contacta a un administrador.
                                    </div>
                                ) : (
                                    <select className={THEME_SELECT} value={data.modulo_id} onChange={e => setData('modulo_id', e.target.value)} required>
                                        <option value="" disabled>Selecciona un módulo...</option>
                                        {modulos.map(m => (
                                            <option key={m.id} value={m.id}>{m.nombre}</option>
                                        ))}
                                    </select>
                                )}
                                {errors.modulo_id && <p className="text-red-500 text-[10px] font-bold uppercase mt-1">{errors.modulo_id}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <label className={THEME_LABEL}>Categoría del ticket</label>
                                <select className={THEME_SELECT} value={data.categoria_id} onChange={e => setData('categoria_id', e.target.value)} required>
                                    <option value="" disabled>Selecciona una categoría...</option>
                                    {categorias.map(c => (
                                        <option key={c.id} value={c.id}>{c.nombre}</option>
                                    ))}
                                </select>
                                {errors.categoria_id && <p className="text-red-500 text-[10px] font-bold uppercase mt-1">{errors.categoria_id}</p>}
                            </div>

                            <div className="sm:col-span-2 relative group">
                                <label className={`${THEME_LABEL} flex items-center gap-1 cursor-help`}>
                                    Prioridad <HelpCircle className="w-3 h-3 text-gray-400" title="La prioridad final será asignada por soporte" />
                                </label>
                                <select className={THEME_SELECT} value={data.prioridad_id} onChange={e => setData('prioridad_id', e.target.value)} required>
                                    <option value="" disabled>Selecciona una prioridad...</option>
                                    {prioridades.map(p => (
                                        <option key={p.id} value={p.id}>{p.nombre}</option>
                                    ))}
                                </select>
                                <div className="absolute bottom-full left-0 mb-1 hidden group-hover:block bg-gray-800 text-white text-[10px] px-2 py-1 rounded shadow-lg z-10 w-full text-center">
                                    La prioridad final será asignada por soporte
                                </div>
                                {errors.prioridad_id && <p className="text-red-500 text-[10px] font-bold uppercase mt-1">{errors.prioridad_id}</p>}
                            </div>
                        </div>

                        <div>
                            <label className={THEME_LABEL}>Descripción detallada</label>
                            <textarea className={`${THEME_TEXTAREA} min-h-[120px]`} value={data.descripcion} onChange={e => setData('descripcion', e.target.value)} placeholder="Explica paso a paso lo que ocurrió. Si es posible, incluye el mensaje de error exacto." required />
                            {errors.descripcion && <p className="text-red-500 text-[10px] font-bold uppercase mt-1">{errors.descripcion}</p>}
                        </div>
                    </form>
                </div>

                <div className="p-5 border-t theme-border flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/30">
                    <button type="button" onClick={onClose} className={THEME_BTN_SECONDARY}>
                        Cancelar
                    </button>
                    <button type="submit" form="create-ticket-form" disabled={processing || modulos.length === 0} className={`${THEME_BTN_PRIMARY} flex items-center gap-2 ${modulos.length === 0 ? 'opacity-50 cursor-not-allowed' : ''}`}>
                        <Send className="w-4 h-4" />
                        Enviar Ticket
                    </button>
                </div>
            </div>
        </div>
    );
}
