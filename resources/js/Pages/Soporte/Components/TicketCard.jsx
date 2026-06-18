import React from 'react';
import { Clock, ArrowRight, User } from 'lucide-react';
import { geliaCardClass } from '@/utils/geliaTheme';

export default function TicketCard({ ticket, onClick, isAgent = false }) {
    const isUnread = ticket.has_unread;
    const isOverdue = ticket.fecha_vencimiento_sla && new Date(ticket.fecha_vencimiento_sla) < new Date();
    
    // Extracción de variables de color
    const prioridad = ticket.prioridadAsignada || ticket.prioridad_asignada;
    const primaryColor = prioridad?.color || 'var(--color-primario)';
    const statusColor = ticket.estado?.color || 'var(--color-primario)';

    return (
        <div 
            onClick={onClick}
            className={`${geliaCardClass('cursor-pointer flex flex-col relative w-full h-full p-0 transition-transform hover:-translate-y-1 hover:shadow-xl group overflow-hidden')} ${isUnread ? 'ring-2 ring-[var(--color-primario)]' : 'hover:border-[var(--color-primario)]'}`}
            style={{
                maskImage: 'radial-gradient(circle at 0 75%, transparent 12px, black 13px), radial-gradient(circle at 100% 75%, transparent 12px, black 13px)',
                maskSize: '51% 100%',
                maskRepeat: 'no-repeat',
                maskPosition: 'left, right',
                WebkitMaskImage: 'radial-gradient(circle at 0 75%, transparent 12px, black 13px), radial-gradient(circle at 100% 75%, transparent 12px, black 13px)',
                WebkitMaskSize: '51% 100%',
                WebkitMaskRepeat: 'no-repeat',
                WebkitMaskPosition: 'left, right',
            }}
        >
            {/* Tira superior (Striped border decoration) */}
            <div 
                className="absolute top-0 left-0 right-0 h-1.5 opacity-80" 
                style={{ background: `repeating-linear-gradient(45deg, ${primaryColor}, ${primaryColor} 10px, transparent 10px, transparent 20px)` }}
            ></div>

            {/* Top Section */}
            <div className="p-5 pt-6 flex flex-col flex-grow relative z-10">
                <div className="text-center mb-4">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        {ticket.modulo?.nombre || 'Soporte'}
                    </span>
                    <h3 className="text-lg font-black uppercase leading-tight theme-text-main line-clamp-2 mt-1" style={{ color: primaryColor }}>
                        {ticket.titulo}
                    </h3>
                </div>

                <div className="grid grid-cols-3 gap-2 text-center mb-4 border-y theme-border py-3">
                    <div>
                        <span className="block text-[9px] uppercase theme-text-muted mb-1 italic">Ticket</span>
                        <span className="font-bold text-sm theme-text-main">#{ticket.id}</span>
                    </div>
                    <div className="border-l border-r theme-border">
                        <span className="block text-[9px] uppercase theme-text-muted mb-1 italic">Prioridad</span>
                        <span className="font-bold text-sm" style={{ color: primaryColor }}>
                            {prioridad?.nombre || 'N/A'}
                        </span>
                    </div>
                    <div>
                        <span className="block text-[9px] uppercase theme-text-muted mb-1 italic">Estado</span>
                        <span className="font-bold text-xs uppercase" style={{ color: statusColor }}>
                            {ticket.estado?.nombre || 'Pendiente'}
                        </span>
                    </div>
                </div>

                <div className="flex justify-between items-end text-xs mt-auto">
                    <div>
                        <span className="block text-[9px] uppercase theme-text-muted mb-1 italic">Creado</span>
                        <span className="font-bold theme-text-main flex items-center gap-1 text-[11px]">
                            {new Date(ticket.created_at).toLocaleDateString('es-MX', { month: 'short', day: 'numeric' })}
                        </span>
                    </div>
                    <div className="text-right">
                        <span className="block text-[9px] uppercase theme-text-muted mb-1 italic">Vence SLA</span>
                        <span className={`font-bold text-[11px] flex items-center gap-1 justify-end ${isOverdue ? 'text-red-500' : 'theme-text-main'}`}>
                            {ticket.fecha_vencimiento_sla 
                                ? <>
                                    <Clock className="w-3 h-3" />
                                    {new Date(ticket.fecha_vencimiento_sla).toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                  </>
                                : 'Sin definir'}
                        </span>
                    </div>
                </div>
            </div>

            {/* Separator Line */}
            <div className="relative z-10 w-full flex items-center">
                <div className="absolute top-0 left-4 right-4 border-t-2 border-dashed theme-border opacity-50"></div>
            </div>

            {/* Bottom Section */}
            <div 
                className="p-5 pt-5 flex justify-between items-center relative overflow-hidden"
                style={{ backgroundColor: `${statusColor}10` }}
            >
                <div className="absolute top-0 left-0 w-1.5 h-full" style={{ backgroundColor: statusColor }}></div>

                <div className="relative z-10 pl-2">
                    <span className="block text-[10px] uppercase opacity-80 mb-1 italic theme-text-muted">
                        {isAgent ? 'Usuario' : 'Asignado a'}
                    </span>
                    <span className="font-bold text-sm flex items-center gap-2 theme-text-main">
                        <User className="w-4 h-4" />
                        {isAgent ? (ticket.user?.name || 'Sistema') : (ticket.asignadoA?.name || ticket.asignado_a?.name || 'Sin Asignar')}
                    </span>
                </div>
                
                <div className="relative z-10 flex flex-col items-end">
                    {isUnread && (
                        <span className="bg-[var(--color-primario)] text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-md mb-1 shadow-sm">
                            ¡Nuevo!
                        </span>
                    )}
                    <span className="text-[10px] font-black uppercase opacity-90 flex items-center gap-1 group-hover:translate-x-1 transition-transform theme-text-main" style={{ color: statusColor }}>
                        Entrar <ArrowRight className="w-3 h-3" />
                    </span>
                </div>

                {/* Decorative Pattern GELIA Style */}
                <div className="absolute -right-4 -bottom-4 opacity-10">
                    <div className="grid grid-cols-3 gap-1 p-2 rounded-xl transform rotate-12 theme-text-main">
                        <div className="w-4 h-4 bg-current"></div>
                        <div className="w-4 h-4 bg-transparent"></div>
                        <div className="w-4 h-4 bg-current"></div>
                        <div className="w-4 h-4 bg-current"></div>
                        <div className="w-4 h-4 bg-current"></div>
                        <div className="w-4 h-4 bg-transparent"></div>
                        <div className="w-4 h-4 bg-transparent"></div>
                        <div className="w-4 h-4 bg-current"></div>
                        <div className="w-4 h-4 bg-current"></div>
                    </div>
                </div>
            </div>
        </div>
    );
}
