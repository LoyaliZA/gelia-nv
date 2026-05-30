import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Search, MessageSquarePlus, Users, ArrowLeft } from 'lucide-react';
import AvatarUsuario from './AvatarUsuario';

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
    compact = false,
}) {
    const [busqueda, setBusqueda] = useState('');

    const filtradas = conversaciones.filter((c) =>
        c.nombre?.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <div className={`flex flex-col h-full theme-surface theme-text-main border-r theme-border ${compact ? 'w-full' : 'w-full sm:w-80 lg:w-96 shrink-0'}`}>
            <div className="gelia-mensajeria-list-header p-3 border-b theme-border shrink-0 bg-black/[0.03] dark:bg-white/[0.03]">
                <div className="flex items-center gap-2 mb-3 min-w-0">
                    <Link
                        href={route('dashboard')}
                        className="p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors shrink-0"
                        title="Volver al dashboard"
                    >
                        <ArrowLeft className="w-4 h-4" />
                    </Link>
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
                    </div>
                </div>

                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 opacity-40" />
                    <input
                        type="text"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar conversación..."
                        className="w-full pl-10 pr-4 py-2 rounded-xl text-xs theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)] placeholder:theme-text-muted"
                    />
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {filtradas.length === 0 && (
                    <p className="text-xs theme-text-muted text-center py-8">No hay conversaciones</p>
                )}

                {filtradas.map((c) => {
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
                                    <p className="text-xs theme-text-muted truncate">{c.ultimo_mensaje_preview || 'Sin mensajes'}</p>
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
