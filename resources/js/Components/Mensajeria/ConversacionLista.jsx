import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, MessageSquarePlus, Users, X } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import AvatarUsuario from './AvatarUsuario';
import MensajeriaNavMenuButton from './MensajeriaNavMenuButton';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import useIconoHover from '@/hooks/useIconoHover';

function EnlaceIcono({ href, icon, label, className }) {
    const { nodo, triggerProps } = useIconoHover(icon, { size: 16, className: 'w-4 h-4' });
    return (
        <Link href={href} className={className} title={label} aria-label={label} {...triggerProps}>
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

export default function ConversacionLista({
    conversaciones,
    conversacionActiva,
    onSeleccionar,
    onNuevoChat,
    onNuevoGrupo,
    onSeleccionarConversacion,
    onIrAMensaje,
    compact = false,
    compacta = false,
    onCerrar,
}) {
    const ancho = compact || compacta
        ? 'w-full md:w-[22rem] shrink-0'
        : 'w-full sm:w-[22rem] lg:w-96 shrink-0';

    return (
        <div className={`gelia-mensajeria-list-pane flex flex-col h-full theme-text-main border-r theme-border ${ancho}`}>
            <div className="gelia-mensajeria-list-header p-3 sm:p-4 border-b theme-border shrink-0">
                <div className="flex items-center gap-2 mb-3 min-w-0">
                    <MensajeriaNavMenuButton />
                    {onCerrar ? (
                        <BotonIconoMensajeria
                            icon={X}
                            label="Cerrar mensajería"
                            onClick={onCerrar}
                            className="rounded-full theme-element border theme-border"
                            size={16}
                        />
                    ) : (
                        <EnlaceIcono
                            href={route('dashboard')}
                            icon={ArrowLeft}
                            label="Volver al inicio"
                            className="gelia-toque hidden md:inline-flex items-center justify-center rounded-full theme-element theme-text-main border theme-border shrink-0"
                        />
                    )}
                    <h2 className="text-base font-semibold theme-text-main flex-1 truncate m-0">Mensajes</h2>
                    <div className="flex gap-1 shrink-0">
                        <BotonIconoMensajeria
                            icon={MessageSquarePlus}
                            label="Nuevo chat"
                            onClick={onNuevoChat}
                            className="rounded-full theme-element border theme-border"
                            size={16}
                        />
                        <BotonIconoMensajeria
                            icon={Users}
                            label="Nuevo grupo"
                            onClick={onNuevoGrupo}
                            className="rounded-full theme-element border theme-border"
                            size={16}
                        />
                        {onCerrar && (
                            <EnlaceIcono
                                href={route('mensajeria.index')}
                                icon={ExternalLink}
                                label="Abrir mensajería en página"
                                className="gelia-toque inline-flex items-center justify-center rounded-full theme-element border theme-border"
                            />
                        )}
                    </div>
                </div>

                <BuscadorInteligente
                    placeholder="Buscar chats, mensajes y archivos"
                    onSeleccionarConversacion={onSeleccionarConversacion}
                    onIrAMensaje={onIrAMensaje}
                />
            </div>

            <div className="flex-1 overflow-y-auto">
                {conversaciones.length === 0 && (
                    <p className="text-sm theme-text-muted text-center py-10 px-6 m-0">Todavía no hay conversaciones.</p>
                )}

                {conversaciones.map((c) => {
                    const activa = conversacionActiva?.id === c.id;
                    const enLinea = c.tipo !== 'grupo' && c.presencia_otro?.en_linea === true;
                    return (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSeleccionar(c)}
                            className={`gelia-chat-fila w-full flex items-center gap-3 px-4 py-3 text-left border-b theme-border/40 ${
                                activa ? 'gelia-chat-fila--activa' : ''
                            }`}
                        >
                            <AvatarUsuario
                                foto={c.foto}
                                nombre={c.nombre}
                                esGrupo={c.tipo === 'grupo'}
                                enLinea={enLinea}
                            />
                            <div className="flex-1 min-w-0">
                                <div className="flex items-baseline justify-between gap-2">
                                    <p className="text-sm font-semibold theme-text-main truncate m-0">{c.nombre}</p>
                                    <span className="text-[11px] theme-text-muted shrink-0">
                                        {formatearFecha(c.ultimo_mensaje_at)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-2 mt-0.5">
                                    <p className="text-[13px] theme-text-muted truncate m-0">
                                        {c.ultimo_mensaje_preview || 'Sin mensajes'}
                                    </p>
                                    {c.unread_count > 0 && (
                                        <span className="gelia-chat-no-leidos">
                                            {c.unread_count > 9 ? '9+' : c.unread_count}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
