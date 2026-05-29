import React from 'react';
import { User, Calendar, Hash, FileText, Landmark, Ban, MoreVertical } from 'lucide-react';
import { ACCENT, ESTADO_BADGE, tipoOperativoDeProceso } from './operativasStyles';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FeedbackResolucion from './FeedbackResolucion';

const formatearFecha = (fecha) => {
    if (!fecha) return '—';
    const normalizada = String(fecha).includes('T') ? fecha : `${fecha}T12:00:00`;
    return new Date(normalizada).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

export default function TarjetaOperativa({ solicitud, auth, onMenu, onAprobar, onReportar, onVerificar }) {
    const permisos = auth?.user?.permissions || [];
    const puedeResponder = permisos.includes('cancelaciones_cotizaciones.reportar');
    const puedeVerificar = permisos.includes('cancelaciones_cotizaciones.verificar');
    const estadoId = solicitud.catalogo_estado_solicitud_id ?? solicitud.estado?.id;
    const estadoNombre = solicitud.estado?.nombre || '—';
    const subtipo = tipoOperativoDeProceso(solicitud.proceso);

    return (
        <article
            className={geliaCardClass('rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden')}
            style={{ borderLeftWidth: '4px', borderLeftColor: ACCENT }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div className="min-w-0 flex-1">
                    <p className="text-[10px] font-mono font-black uppercase tracking-widest mb-1" style={{ color: ACCENT }}>
                        FOL-{solicitud.id}
                    </p>
                    <h3 className="text-sm font-black theme-text-main m-0 leading-tight">
                        {solicitud.proceso?.nombre || '—'}
                    </h3>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 truncate">
                        {solicitud.cliente?.numero_cliente} — {solicitud.cliente?.nombre || 'Sin cliente'}
                    </p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <span className={`px-2.5 py-1 rounded-lg text-[9px] font-black uppercase border ${ESTADO_BADGE[estadoId] || 'theme-element theme-border theme-text-muted'}`}>
                        {estadoNombre}
                    </span>
                    <button type="button" onClick={(e) => onMenu(e, solicitud)} className="p-2 rounded-xl theme-element border theme-border outline-none hover:border-orange-500">
                        <MoreVertical className="w-4 h-4 theme-text-muted" />
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {solicitud.numero_remision && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-orange-500/10 text-orange-600 border border-orange-500/20">
                        <Hash className="w-3 h-3" /> Rem. {solicitud.numero_remision}
                    </span>
                )}
                {solicitud.numero_pedido && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-cyan-500/10 text-cyan-600 border border-cyan-500/20">
                        <Hash className="w-3 h-3" /> Ped. {solicitud.numero_pedido}
                    </span>
                )}
                {solicitud.solicitar_cotizacion && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-violet-500/10 text-violet-600 border border-violet-500/20">
                        + Cotización
                    </span>
                )}
                {solicitud.cancelacion_solicitada_at && estadoNombre !== 'Cancelada' && (
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-red-500/10 text-red-600 border border-red-500/20">
                        <Ban className="w-3 h-3" /> Cancelación solicitada
                    </span>
                )}
            </div>

            {(subtipo === 'remision' || solicitud.motivo_operacion) && (
                <div className="mb-4 p-3 rounded-xl theme-element border theme-border space-y-2 text-[10px]">
                    {solicitud.fecha_operacion && (
                        <div className="flex items-center gap-2 theme-text-muted">
                            <Calendar className="w-3.5 h-3.5" />
                            <span className="font-bold">{formatearFecha(solicitud.fecha_operacion)}</span>
                        </div>
                    )}
                    {solicitud.banco?.nombre && (
                        <div className="flex items-center gap-2 theme-text-muted">
                            <Landmark className="w-3.5 h-3.5" />
                            <span className="font-bold">{solicitud.banco.nombre}</span>
                        </div>
                    )}
                    {solicitud.motivo_operacion && (
                        <div className="flex items-start gap-2">
                            <FileText className="w-3.5 h-3.5 shrink-0 mt-0.5 theme-text-muted" />
                            <p className="font-bold theme-text-main line-clamp-2 m-0">{solicitud.motivo_operacion}</p>
                        </div>
                    )}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-4 text-[10px] font-bold theme-text-muted mb-4">
                <span className="inline-flex items-center gap-1"><User className="w-3.5 h-3.5" /> {solicitud.vendedor?.name}</span>
                <span className="inline-flex items-center gap-1"><Calendar className="w-3.5 h-3.5" /> {new Date(solicitud.created_at).toLocaleDateString('es-MX')}</span>
            </div>

            <FeedbackResolucion solicitud={solicitud} />

            <div className="flex flex-wrap gap-2 pt-3 border-t theme-border">
                {estadoId === 1 && puedeResponder && (
                    <>
                        <button type="button" onClick={() => onAprobar(solicitud)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase text-white outline-none" style={{ backgroundColor: ACCENT }}>
                            Aprobar
                        </button>
                        <button type="button" onClick={() => onReportar(solicitud)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-red-500/10 text-red-600 border border-red-500/30 outline-none">
                            Error
                        </button>
                    </>
                )}
                {estadoId === 2 && puedeVerificar && (
                    <button type="button" onClick={() => onVerificar(solicitud)} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-700 border border-emerald-500/30 outline-none">
                        Verificar
                    </button>
                )}
            </div>
        </article>
    );
}
