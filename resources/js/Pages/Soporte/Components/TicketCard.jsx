import React from 'react';
import { geliaCardClass } from '@/utils/geliaTheme';
import { Clock, ArrowLeft, Users } from 'lucide-react';

export default function TicketCard({ ticket, onClick, isAgent = false }) {
    const isUnread = ticket.has_unread;

    return (
        <div
            onClick={onClick}
            className={`${geliaCardClass('p-5 cursor-pointer transition-all hover:shadow-lg relative group flex flex-col h-full')} ${isUnread ? 'ring-2 ring-[var(--color-primario)]' : 'hover:border-[var(--color-primario)]'}`}
        >
            <div className="flex items-start justify-between gap-2 mb-3">
                <span className="text-xs font-black theme-text-muted shrink-0">
                    #{ticket.id}
                </span>
                <div className="flex flex-col items-end gap-1.5 min-w-0">
                    {isUnread && (
                        <span className="inline-flex items-center bg-[var(--color-primario)] text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md whitespace-nowrap">
                            Actualización
                        </span>
                    )}
                    <span
                        className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase whitespace-nowrap"
                        style={{ backgroundColor: `${ticket.estado?.color}20`, color: ticket.estado?.color }}
                    >
                        {ticket.estado?.nombre || 'Pendiente'}
                    </span>
                </div>
            </div>

            <h3 className="text-sm font-bold theme-text-main line-clamp-2 mb-2 flex-grow">
                {ticket.titulo}
            </h3>

            <div className="flex flex-wrap items-center gap-2 mb-4">
                {isAgent ? (
                    <>
                        <span className="text-[10px] theme-text-muted bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md flex items-center gap-1">
                            <Users className="w-3 h-3" />
                            {ticket.user?.name || 'Sistema'}
                        </span>
                        {ticket.modulo?.nombre && (
                            <span className="text-[10px] theme-text-muted bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md">
                                {ticket.modulo.nombre}
                            </span>
                        )}
                        {ticket.categoria?.nombre && (
                            <span className="text-[10px] theme-text-muted bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md">
                                {ticket.categoria.nombre}
                            </span>
                        )}
                    </>
                ) : (
                    <>
                        <span className="text-[10px] theme-text-muted bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md">
                            {ticket.modulo?.nombre}
                        </span>
                        {ticket.categoria?.nombre && (
                            <span className="text-[10px] theme-text-muted bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md">
                                {ticket.categoria.nombre}
                            </span>
                        )}
                    </>
                )}
                {(ticket.asignadoA || ticket.asignado_a) ? (
                    <span className="text-[10px] bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 px-2 py-1 rounded-md flex items-center gap-1" title="Agente Asignado">
                        <Users className="w-3 h-3" />
                        {(ticket.asignadoA || ticket.asignado_a).name}
                    </span>
                ) : null}
                {((ticket.prioridadAsignada || ticket.prioridad_asignada)) && (
                    <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase" style={{ color: (ticket.prioridadAsignada || ticket.prioridad_asignada).color || 'var(--color-primario)' }}>
                        <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: (ticket.prioridadAsignada || ticket.prioridad_asignada).color || 'var(--color-primario)' }}></span>
                        {(ticket.prioridadAsignada || ticket.prioridad_asignada).nombre}
                    </span>
                )}
            </div>

            <div className="pt-3 border-t theme-border flex items-center justify-between text-xs theme-text-muted mt-auto">
                <span className="flex items-center gap-1">
                    <Clock className="w-3 h-3" />
                    {new Date(ticket.created_at).toLocaleDateString('es-MX', { month: 'short', day: 'numeric' })}
                </span>
                <span className={`flex items-center gap-1 font-bold ${isUnread ? 'text-[var(--color-primario)]' : 'group-hover:text-[var(--color-primario)] transition-colors'}`}>
                    {isAgent ? 'Responder' : 'Ver Mensajes'}
                    <ArrowLeft className="w-3 h-3 rotate-180" />
                </span>
            </div>
        </div>
    );
}
