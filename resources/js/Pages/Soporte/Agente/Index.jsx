import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import {
    geliaCardClass,
    THEME_INPUT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    GELIA_RESPONSIVE_GRID,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL
} from '@/utils/geliaTheme';
import { Settings, Shield, Clock, List as ListIcon, AlertCircle, CheckCircle2, PlayCircle, Users, MessageCircle } from 'lucide-react';
import TicketCard from '../Components/TicketCard';
import TicketChatModal from '../Components/TicketChatModal';

export default function AgenteIndex({ auth, tickets, modoPruebas, configuracion, modulos, categorias, prioridades, estados, permisos_disponibles = [] }) {
    const [configModalOpen, setConfigModalOpen] = useState(false);
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
        if (typeof window !== 'undefined' && window.Echo) {
            const channel = window.Echo.private('soporte.agentes')
                .listen('.App\\Events\\Soporte\\TicketCreatedEvent', (e) => {
                    setLocalTickets((prev) => [{ ...e.ticket, has_unread: true }, ...prev]);
                });

            return () => {
                channel.stopListening('.App\\Events\\Soporte\\TicketCreatedEvent');
            };
        }
    }, []);

    useEffect(() => {
        const onNotification = (e) => {
            const payload = e.detail || {};
            if (!payload.ticket_id) return;
            if (!['soporte_respuesta_agente', 'soporte_respuesta_usuario', 'soporte_ticket_nuevo'].includes(payload.tipo)) return;
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

    const canManageConfig = !!configuracion;

    return (
        <AppLayout>
            <Head title="Dashboard de Soporte" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex-1">
                        <GeliaTituloCard
                            eyebrow="Soporte y QA_"
                            title="GESTIÓN DE"
                            titleHighlight="TICKETS"
                            description="Monitorea y atiende las solicitudes de los usuarios de la plataforma."
                            icon={Shield}
                        />
                    </div>
                    {canManageConfig && (
                        <div className="flex-none">
                            <button 
                                onClick={() => setConfigModalOpen(true)}
                                className={`${THEME_BTN_SECONDARY} flex items-center gap-2 whitespace-nowrap bg-white/50 dark:bg-gray-800/50 hover:bg-white dark:hover:bg-gray-800 transition-colors shadow-sm`}
                            >
                                <Settings className="w-4 h-4" />
                                Configuración
                            </button>
                        </div>
                    )}
                </div>

                {modoPruebas && (
                    <div className="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg relative" role="alert">
                        <strong className="font-bold">¡Modo Pruebas Activo! </strong>
                        <span className="block sm:inline">Estás viendo tickets simulados para pruebas de UI. Ningún ticket creado ahora afectará SLA ni BD real.</span>
                    </div>
                )}

                {localTickets.length === 0 ? (
                    <div className={geliaCardClass('p-12 flex flex-col items-center justify-center text-center')}>
                        <div className="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                            <MessageCircle className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-black uppercase theme-text-main mb-2">No hay tickets activos</h3>
                        <p className="theme-text-muted max-w-sm mb-6">Actualmente no hay solicitudes de soporte pendientes.</p>
                    </div>
                ) : (
                    <div className={GELIA_RESPONSIVE_GRID}>
                        {localTickets.map((ticket) => (
                            <TicketCard 
                                key={ticket.id} 
                                ticket={ticket} 
                                onClick={() => openTicket(ticket)} 
                                isAgent={true}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Modal Chat de Ticket (Mockup) */}
            {activeTicketChat && typeof document !== 'undefined' && ReactDOM.createPortal(
                <TicketChatModal 
                    ticket={activeTicketChat} 
                    onClose={() => setActiveTicketChat(null)} 
                    isAgent={true}
                    prioridades={prioridades}
                    estados={estados}
                    onMarkRead={() => markTicketRead(activeTicketChat.id)}
                    auth={auth}
                />,
                document.body
            )}

            {/* Modal de Configuración Global mediante Portal */}
            {canManageConfig && configModalOpen && typeof document !== 'undefined' && ReactDOM.createPortal(
                <ConfigModal 
                    onClose={() => setConfigModalOpen(false)} 
                    configuracion={configuracion} 
                    modulos={modulos} 
                    categorias={categorias} 
                    prioridades={prioridades} 
                    estados={estados} 
                    permisos={permisos_disponibles}
                />,
                document.body
            )}
        </AppLayout>
    );
}

import { Search } from 'lucide-react';

// Componente del Modal Separado para mantener orden
function ConfigModal({ onClose, configuracion, modulos, categorias, prioridades, estados, permisos }) {
    const [activeTab, setActiveTab] = useState('sla');
    const [permSearch, setPermSearch] = useState('');

    const { data, setData, post, processing } = useForm({
        horario_inicio: configuracion.horario_inicio ? configuracion.horario_inicio.substring(0, 5) : '09:00',
        horario_fin: configuracion.horario_fin ? configuracion.horario_fin.substring(0, 5) : '17:00',
        mensaje_fuera_horario: configuracion.mensaje_fuera_horario || '',
        hora_notificacion_diaria: configuracion.hora_notificacion_diaria ? configuracion.hora_notificacion_diaria.substring(0, 5) : '09:30',
        modo_pruebas: configuracion.modo_pruebas || false,
    });

    const submitConfig = (e) => {
        e.preventDefault();
        post(route('soporte.admin.config.update'), {
            preserveScroll: true,
        });
    };

    // Modal anidado para editar un catálogo específico
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [modalType, setModalType] = useState('');
    const [editId, setEditId] = useState(null);
    const [catData, setCatData] = useState({});

    const openEditModal = (tipo, item = null) => {
        setModalType(tipo);
        setEditId(item ? item.id : null);
        setCatData(item ? { ...item } : { nombre: '', activo: true, permiso_requerido: '', color: '#000000', tiempo_respuesta_horas: 24 });
        setPermSearch('');
        setEditModalOpen(true);
    };

    const saveCatalogo = (e) => {
        e.preventDefault();
        const routeName = editId ? route('soporte.admin.config.catalogos.update', { tipo: modalType, id: editId }) : route('soporte.admin.config.catalogos.store', { tipo: modalType });
        const method = editId ? 'put' : 'post';
        router[method](routeName, catData, {
            preserveScroll: true,
            onSuccess: () => setEditModalOpen(false),
        });
    };

    const renderCatalogoSection = (titulo, tipo, items) => (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="font-black uppercase tracking-tight theme-text-main">{titulo}</h3>
                <button onClick={() => openEditModal(tipo)} className="text-xs font-bold uppercase text-[var(--color-primario)] hover:underline">
                    + Agregar
                </button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {items.map(item => (
                    <div key={item.id} className={geliaCardClass('p-4 flex items-center justify-between hover:border-[var(--color-primario)] transition-colors')}>
                        <div className="flex items-center gap-3">
                            <div className={`w-3 h-3 rounded-full ${item.activo ? 'bg-green-500' : 'bg-red-500'}`}></div>
                            <span className="font-semibold text-sm theme-text-main">{item.nombre}</span>
                        </div>
                        <button onClick={() => openEditModal(tipo, item)} className="text-[10px] uppercase font-bold text-gray-500 hover:text-[var(--color-primario)]">
                            Editar
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );

    return (
        <div className={`${THEME_MODAL_OVERLAY} z-[999]`}>
            <div className={`${THEME_MODAL_SHELL} max-w-5xl w-full h-[85vh]`}>
                {/* Header */}
                <div className="flex items-center justify-between p-4 md:p-6 border-b theme-border bg-gray-50/50 dark:bg-gray-800/30">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl theme-element flex items-center justify-center">
                            <Settings className="w-5 h-5 theme-text-main" />
                        </div>
                        <div>
                            <h2 className="text-lg font-black uppercase theme-text-main">Ajustes de Soporte</h2>
                            <p className="text-xs theme-text-muted">SLA, Permisos y Catálogos</p>
                        </div>
                    </div>
                    <button onClick={onClose} className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors theme-text-main">
                        ✕
                    </button>
                </div>

                {/* Body with Sidebar Tabs */}
                <div className="flex-1 flex flex-col md:flex-row overflow-hidden">
                    {/* Sidebar Tabs */}
                    <div className="w-full md:w-64 border-r theme-border bg-white dark:bg-gray-900/20 overflow-y-auto p-4 space-y-2 flex-shrink-0">
                        <button onClick={() => setActiveTab('sla')} className={`w-full text-left px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors ${activeTab === 'sla' ? 'bg-[var(--color-primario)] text-white shadow-md' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                            <Clock className="w-4 h-4 inline-block mr-2" />
                            Reglas y SLA
                        </button>
                        <button onClick={() => setActiveTab('modulos')} className={`w-full text-left px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors ${activeTab === 'modulos' ? 'bg-[var(--color-primario)] text-white shadow-md' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                            <Shield className="w-4 h-4 inline-block mr-2" />
                            Módulos
                        </button>
                        <button onClick={() => setActiveTab('categorias')} className={`w-full text-left px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors ${activeTab === 'categorias' ? 'bg-[var(--color-primario)] text-white shadow-md' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                            <ListIcon className="w-4 h-4 inline-block mr-2" />
                            Categorías
                        </button>
                        <button onClick={() => setActiveTab('prioridades')} className={`w-full text-left px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors ${activeTab === 'prioridades' ? 'bg-[var(--color-primario)] text-white shadow-md' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                            <AlertCircle className="w-4 h-4 inline-block mr-2" />
                            Prioridades
                        </button>
                        <button onClick={() => setActiveTab('estados')} className={`w-full text-left px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-wider transition-colors ${activeTab === 'estados' ? 'bg-[var(--color-primario)] text-white shadow-md' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>
                            <CheckCircle2 className="w-4 h-4 inline-block mr-2" />
                            Estados
                        </button>
                    </div>

                    {/* Content Area */}
                    <div className="flex-1 overflow-y-auto p-4 md:p-8 bg-gray-50/30 dark:bg-gray-900/10">
                        {activeTab === 'sla' && (
                            <form onSubmit={submitConfig} className="space-y-6 max-w-2xl">
                                <div className={geliaCardClass('p-6 space-y-6')}>
                                    <h3 className="font-black uppercase theme-text-main border-b theme-border pb-3">Horarios Hábiles</h3>
                                    <div className="grid grid-cols-2 gap-6">
                                        <div>
                                            <label className={THEME_LABEL}>Apertura</label>
                                            <input type="time" className={THEME_INPUT} value={data.horario_inicio} onChange={e => setData('horario_inicio', e.target.value)} required />
                                        </div>
                                        <div>
                                            <label className={THEME_LABEL}>Cierre</label>
                                            <input type="time" className={THEME_INPUT} value={data.horario_fin} onChange={e => setData('horario_fin', e.target.value)} required />
                                        </div>
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Mensaje Automático (Fuera de Horario)</label>
                                        <textarea className={`${THEME_INPUT} min-h-[100px]`} value={data.mensaje_fuera_horario} onChange={e => setData('mensaje_fuera_horario', e.target.value)} placeholder="Se enviará a los usuarios que generen un ticket fuera del horario configurado."></textarea>
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Notificación Diaria (Agentes)</label>
                                        <input type="time" className={THEME_INPUT} value={data.hora_notificacion_diaria} onChange={e => setData('hora_notificacion_diaria', e.target.value)} required />
                                    </div>
                                </div>

                                <div className={geliaCardClass('p-6')}>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <label className={`${THEME_LABEL} mb-1 flex items-center gap-2`}>
                                                <PlayCircle className="w-4 h-4 text-purple-500" />
                                                Modo de Pruebas
                                            </label>
                                            <p className="text-xs theme-text-muted">Genera tickets ficticios en UI para simular el tablero.</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" className="sr-only peer" checked={data.modo_pruebas} onChange={e => setData('modo_pruebas', e.target.checked)} />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[var(--color-primario)]"></div>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                                    Guardar Preferencias
                                </button>
                            </form>
                        )}

                        {activeTab === 'modulos' && renderCatalogoSection('Módulos de Sistema', 'modulos', modulos)}
                        {activeTab === 'categorias' && renderCatalogoSection('Categorías', 'categorias', categorias)}
                        {activeTab === 'prioridades' && renderCatalogoSection('Prioridades SLA', 'prioridades', prioridades)}
                        {activeTab === 'estados' && renderCatalogoSection('Estados', 'estados', estados)}
                    </div>
                </div>
            </div>

            {/* Modal de Edición de Catálogo anidado */}
            {editModalOpen && (
                <div className={`${THEME_MODAL_OVERLAY} z-[1000]`}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md w-full`}>
                        <div className="p-5 border-b theme-border flex justify-between">
                            <h3 className="text-lg font-black uppercase theme-text-main">
                                {editId ? 'Editar' : 'Nuevo'} {modalType}
                            </h3>
                            <button onClick={() => setEditModalOpen(false)} className="text-gray-500 hover:text-gray-800">✕</button>
                        </div>
                        <div className="p-5">
                            <form onSubmit={saveCatalogo} className="space-y-4">
                                <div>
                                    <label className={THEME_LABEL}>Nombre</label>
                                    <input type="text" className={THEME_INPUT} value={catData.nombre} onChange={e => setCatData({...catData, nombre: e.target.value})} required />
                                </div>
                                
                                {modalType === 'modulos' && (
                                    <div className="flex flex-col h-full max-h-[300px]">
                                        <label className={THEME_LABEL}>Permiso Requerido (Opcional)</label>
                                        <div className="flex items-center gap-2 mb-2">
                                            <div className="relative flex-1">
                                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                                <input 
                                                    type="text" 
                                                    className={`${THEME_INPUT} pl-9`}
                                                    placeholder="Buscar permiso..."
                                                    value={permSearch}
                                                    onChange={e => setPermSearch(e.target.value)}
                                                />
                                            </div>
                                            {catData.permiso_requerido && (
                                                <button 
                                                    type="button"
                                                    onClick={() => setCatData({...catData, permiso_requerido: ''})}
                                                    className="text-xs font-bold text-red-500 hover:underline flex-shrink-0"
                                                >
                                                    Limpiar Selección
                                                </button>
                                            )}
                                        </div>
                                        <div className="flex-1 overflow-y-auto border theme-border rounded-lg p-2 theme-element space-y-4">
                                            {Object.entries(
                                                permisos.reduce((acc, perm) => {
                                                    if (!permSearch || perm.toLowerCase().includes(permSearch.toLowerCase())) {
                                                        const group = perm.split('.')[0];
                                                        if (!acc[group]) acc[group] = [];
                                                        acc[group].push(perm);
                                                    }
                                                    return acc;
                                                }, {})
                                            ).map(([group, groupPerms]) => (
                                                <div key={group}>
                                                    <div className="text-[10px] font-black uppercase text-gray-500 mb-2 border-b theme-border pb-1">
                                                        Módulo: {group}
                                                    </div>
                                                    <div className="space-y-1">
                                                        {groupPerms.map(perm => (
                                                            <label key={perm} className="flex items-start gap-2 p-2 rounded-lg hover:bg-[var(--color-primario)]/5 cursor-pointer transition-colors border border-transparent hover:border-[var(--color-primario)]/20">
                                                                <input 
                                                                    type="radio" 
                                                                    name="permiso_requerido"
                                                                    checked={catData.permiso_requerido === perm}
                                                                    onChange={() => setCatData({...catData, permiso_requerido: perm})}
                                                                    className="mt-0.5 text-[var(--color-primario)] focus:ring-[var(--color-primario)]"
                                                                />
                                                                <span className={`text-xs break-all ${catData.permiso_requerido === perm ? 'font-bold text-[var(--color-primario)]' : 'theme-text-main'}`}>
                                                                    {perm}
                                                                </span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                            {(!permisos || permisos.length === 0) && (
                                                <p className="text-xs text-gray-400 p-4 text-center">No hay permisos cargados.</p>
                                            )}
                                        </div>
                                    </div>
                                )}
                                
                                {modalType === 'prioridades' && (
                                    <div>
                                        <label className={THEME_LABEL}>Tiempo de Respuesta (Horas)</label>
                                        <input type="number" min="1" className={THEME_INPUT} value={catData.tiempo_respuesta_horas || ''} onChange={e => setCatData({...catData, tiempo_respuesta_horas: e.target.value})} required />
                                    </div>
                                )}
                                
                                {modalType === 'estados' && (
                                    <div>
                                        <label className={THEME_LABEL}>Color (HEX)</label>
                                        <input type="color" className="w-full h-10 p-1 rounded-lg border theme-border" value={catData.color || '#000000'} onChange={e => setCatData({...catData, color: e.target.value})} required />
                                    </div>
                                )}
                                
                                <div className="flex items-center gap-3">
                                    <input type="checkbox" id="cat_activo" checked={catData.activo} onChange={e => setCatData({...catData, activo: e.target.checked})} className="rounded border-gray-300 text-[var(--color-primario)] focus:ring-[var(--color-primario)]" />
                                    <label htmlFor="cat_activo" className={THEME_LABEL}>Activo</label>
                                </div>
                                
                                <div className="flex justify-end gap-3 pt-4 border-t theme-border">
                                    <button type="button" onClick={() => setEditModalOpen(false)} className={THEME_BTN_SECONDARY}>
                                        Cancelar
                                    </button>
                                    <button type="submit" className={THEME_BTN_PRIMARY}>
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
