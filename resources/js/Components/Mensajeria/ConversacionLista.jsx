import React from 'react';
import { Link } from '@inertiajs/react';
import { MessageSquarePlus, Users, ArrowLeft, X, ExternalLink } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import AvatarUsuario from './AvatarUsuario';
import SelectorEstadoPresencia from './SelectorEstadoPresencia';
import EstadoPresenciaTexto from './EstadoPresenciaTexto';
import MensajeriaNavMenuButton from './MensajeriaNavMenuButton';

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
        ? 'w-full md:w-72 lg:w-80 shrink-0'
        : 'w-full sm:w-80 lg:w-96 shrink-0';

    return (
        <div className={`gelia-mensajeria-list-pane flex flex-col h-full theme-text-main border-r theme-border ${ancho}`}>
            <div className="gelia-mensajeria-list-header p-3 sm:p-4 border-b theme-border shrink-0">
                <div className="flex items-center gap-2 mb-3 min-w-0">
                    <MensajeriaNavMenuButton />
                    {onCerrar ? (
                        <button
                            type="button"
                            onClick={onCerrar}
                            className="flex p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors shrink-0"
                            title="Cerrar mensajería"
                            aria-label="Cerrar mensajería"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    ) : (
                        <Link
                            href={route('dashboard')}
                            className="hidden md:flex p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors shrink-0"
                            title="Volver al dashboard"
                        >
                            <ArrowLeft className="w-4 h-4" />
                        </Link>
                    )}
                    <h2 className="text-sm font-black uppercase italic tracking-tighter theme-text-main flex-1 truncate">Chats_</h2>
                    <div className="flex gap-1 shrink-0">
                        <button
                            type="button"
                            onClick={onNuevoChat}
                            className="p-2 rounded-full theme-element hover:border-[var(--color-primario)] border theme-border transition-colors"
                            title="Nuevo chat"
                        >
                            <MessageSquarePlus className="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            onClick={onNuevoGrupo}
                            className="p-2 rounded-full theme-element hover:border-[var(--color-primario)] border theme-border transition-colors"
                            title="Nuevo grupo"
                        >
                            <Users className="w-4 h-4" />
                        </button>
                        {onCerrar && (
                            <Link
                                href={route('mensajeria.index')}
                                className="p-2 rounded-full theme-element hover:border-[var(--color-primario)] border theme-border transition-colors"
                                title="Abrir mensajería"
                                aria-label="Abrir mensajería en página"
                            >
                                <ExternalLink className="w-4 h-4" />
                            </Link>
                        )}
                    </div>
                </div>

                <BuscadorInteligente
                    placeholder="Buscar chats, mensajes, PDFs, Excel…"
                    onSeleccionarConversacion={onSeleccionarConversacion}
                    onIrAMensaje={onIrAMensaje}
                />

                <div className="mt-3 flex items-center justify-between gap-2 flex-wrap">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted shrink-0">
                        Mi estado
                    </span>
                    <SelectorEstadoPresencia compact />
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {conversaciones.length === 0 && (
                    <p className="text-xs theme-text-muted text-center py-8">No hay conversaciones</p>
                )}

                {conversaciones.map((c) => {
                    const activa = conversacionActiva?.id === c.id;
                    return (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSeleccionar(c)}
                            className={`w-full flex items-center gap-3 px-4 py-3 text-left transition-colors border-b theme-border/50 ${
                                activa ? 'bg-[var(--color-primario)]/10' : 'hover:theme-element'
                            }`}
                        >
                            <AvatarUsuario
                                foto={c.foto}
                                nombre={c.nombre}
                                esGrupo={c.tipo === 'grupo'}
                            />
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-bold theme-text-main truncate">{c.nombre}</p>
                                    <span className="text-[10px] theme-text-muted shrink-0">
                                        {formatearFecha(c.ultimo_mensaje_at)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        {c.tipo !== 'grupo' && c.presencia_otro && (
                                            <EstadoPresenciaTexto presencia={c.presencia_otro} compact className="mb-0.5" />
                                        )}
                                        <p className="text-xs theme-text-muted truncate m-0">{c.ultimo_mensaje_preview || 'Sin mensajes'}</p>
                                    </div>
                                    {c.unread_count > 0 && (
                                        <span
                                            className="shrink-0 min-w-[1.125rem] h-[1.125rem] px-1 flex items-center justify-center text-[9px] font-black text-white rounded-full"
                                            style={{ backgroundColor: 'var(--color-primario)' }}
                                        >
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
