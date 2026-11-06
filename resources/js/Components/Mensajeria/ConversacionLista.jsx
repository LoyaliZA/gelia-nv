import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MessageSquarePlus, Users, X } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import AvatarUsuario from './AvatarUsuario';
import MensajeriaNavMenuButton from './MensajeriaNavMenuButton';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import useIconoHover from '@/hooks/useIconoHover';

const FILTROS = [
    { id: 'todos', label: 'Todos' },
    { id: 'no-leidos', label: 'No leídos' },
    { id: 'grupos', label: 'Grupos' },
];

function EnlaceIcono({ href, icon, label, className }) {
    const { nodo, triggerProps } = useIconoHover(icon, { size: 20, className: 'w-5 h-5' });
    return (
        <Link href={href} className={`inline-flex items-center justify-center ${className}`} title={label} aria-label={label} {...triggerProps}>
            {nodo}
        </Link>
    );
}

const formatearFecha = (fecha) => {
    if (!fecha) return '';
    try {
        const d = new Date(fecha);
        const hoy = new Date();
        if (d.toDateString() === hoy.toDateString()) {
            return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
    } catch {
        return '';
    }
};

function textoVacio(filtro, hayConversaciones) {
    if (!hayConversaciones) return 'Todavía no hay conversaciones.';
    if (filtro === 'no-leidos') return 'No hay mensajes sin leer.';
    if (filtro === 'grupos') return 'No hay grupos.';
    return 'Todavía no hay conversaciones.';
}

export default function ConversacionLista({
    conversaciones,
    conversacionActiva,
    onSeleccionar,
    onNuevoChat,
    onNuevoGrupo,
    onSeleccionarConversacion,
    onIrAMensaje,
    onCerrar,
}) {
    const [filtro, setFiltro] = useState('todos');

    const visibles = conversaciones.filter((c) => {
        if (filtro === 'no-leidos') return c.unread_count > 0;
        if (filtro === 'grupos') return c.tipo === 'grupo';
        return true;
    });

    return (
        <div className="gelia-mensajeria-list-pane flex flex-col h-full theme-text-main border-r theme-border">
            <div className="gelia-mensajeria-list-header border-b theme-border shrink-0">
                <div className="flex items-center min-w-0 px-1 pt-1">
                    {onCerrar && <MensajeriaNavMenuButton plano />}
                    {onCerrar ? (
                        <BotonIconoMensajeria
                            icon={X}
                            label="Cerrar mensajería"
                            onClick={onCerrar}
                            plano
                        />
                    ) : (
                        <EnlaceIcono
                            href={route('dashboard')}
                            icon={ArrowLeft}
                            label="Volver al inicio"
                            className="gelia-toque gelia-mensajeria-accion hidden md:inline-flex"
                        />
                    )}
                    <div className="flex-1 min-w-0 px-2"><h2 className="text-xl font-semibold theme-text-main truncate m-0">Mensajes</h2><p className="gelia-mensajeria-subtitulo">Tu equipo, en una conversación</p></div>
                    <BotonIconoMensajeria
                        icon={MessageSquarePlus}
                        label="Nuevo chat"
                        onClick={onNuevoChat}
                        plano
                    />
                    <BotonIconoMensajeria
                        icon={Users}
                        label="Nuevo grupo"
                        onClick={onNuevoGrupo}
                        plano
                    />
                    {onCerrar && (
                        <EnlaceIcono
                            href={route('mensajeria.index')}
                            icon={ExternalLink}
                            label="Abrir mensajería en página"
                            className="gelia-toque gelia-mensajeria-accion inline-flex"
                        />
                    )}
                </div>

                <div className="px-3 pt-1 pb-2">
                    <BuscadorInteligente
                        placeholder="Buscar personas y mensajes"
                        onSeleccionarConversacion={onSeleccionarConversacion}
                        onIrAMensaje={onIrAMensaje}
                    />
                </div>

                <div className="gelia-mensajeria-chips" role="tablist" aria-label="Filtrar conversaciones">
                    {FILTROS.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            aria-selected={filtro === item.id}
                            className={`gelia-mensajeria-chip ${filtro === item.id ? 'gelia-mensajeria-chip--activo' : ''}`}
                            onClick={() => setFiltro(item.id)}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {visibles.length === 0 && (
                    <p className="text-sm theme-text-muted text-center py-10 px-6 m-0">
                        {textoVacio(filtro, conversaciones.length > 0)}
                    </p>
                )}

                {visibles.map((c) => {
                    const activa = conversacionActiva?.id === c.id;
                    const sinLeer = c.unread_count > 0;
                    const enLinea = c.tipo !== 'grupo' && c.presencia_otro?.en_linea === true;
                    return (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSeleccionar(c)}
                            aria-current={activa ? 'true' : undefined}
                            className={`gelia-chat-fila ${activa ? 'gelia-chat-fila--activa' : ''} ${sinLeer ? 'gelia-chat-fila--sin-leer' : ''}`}
                        >
                            <AvatarUsuario
                                foto={c.foto}
                                nombre={c.nombre}
                                esGrupo={c.tipo === 'grupo'}
                                enLinea={enLinea}
                                className="w-12 h-12"
                                iconClassName="w-6 h-6"
                            />
                            <span className="gelia-chat-fila__cuerpo">
                                <span className="gelia-chat-fila__tope">
                                    <span className="gelia-chat-fila__nombre">{c.nombre}</span>
                                    <span className="gelia-chat-fila__hora">{formatearFecha(c.ultimo_mensaje_at)}</span>
                                </span>
                                <span className="gelia-chat-fila__base">
                                    <span className="gelia-chat-fila__preview">{c.ultimo_mensaje_preview || 'Sin mensajes'}</span>
                                    {sinLeer && (
                                        <span className="gelia-chat-no-leidos">
                                            {c.unread_count > 9 ? '9+' : c.unread_count}
                                        </span>
                                    )}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
