import React from 'react';
import {
    Package, User, Calendar, Eye, CheckCircle2, XCircle, Trash2,
    AlertTriangle, Clock, History,
} from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { puedePermiso } from '../../../utils/permisos';
import FeedbackResolucionTraspaso from './FeedbackResolucionTraspaso';

const ESTADO_UI = {
    Pendiente: {
        badge: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        valor: 'text-amber-600 dark:text-amber-400',
        Icon: Clock,
    },
    Respondida: {
        badge: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
        valor: 'text-sky-600 dark:text-sky-400',
        Icon: CheckCircle2,
    },
    Verificada: {
        badge: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        valor: 'text-emerald-600 dark:text-emerald-400',
        Icon: CheckCircle2,
    },
    Incorrecta: {
        badge: 'bg-red-500/10 text-red-500 border-red-500/20',
        valor: 'text-red-500',
        Icon: AlertTriangle,
    },
};

function formatearFecha(valor) {
    if (!valor) return '—';
    return new Date(valor).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function TarjetaTraspaso({
    traspaso,
    auth,
    onVerDetalle,
    onResponder,
    onReportar,
    onVerificar,
    onEliminar,
    onBitacora,
}) {
    const puedeResponder = puedePermiso(auth, 'traspasos.responder');
    const puedeReportar = puedePermiso(auth, 'traspasos.reportar_error');
    const puedeVerificar = puedePermiso(auth, 'traspasos.verificar');
    const puedeEliminar = puedePermiso(auth, 'traspasos.eliminar');
    const puedeBitacora = puedePermiso(auth, 'configuracion.ver_auditoria');
    const estadoNombre = traspaso.estado?.nombre || '—';
    const esPendiente = estadoNombre === 'Pendiente';
    const esRespondida = estadoNombre === 'Respondida';
    const ui = ESTADO_UI[estadoNombre] || {
        badge: 'theme-element theme-text-muted border theme-border',
        valor: 'theme-text-main',
        Icon: Package,
    };
    const StatusIcon = ui.Icon;

    return (
        <article
            className={geliaCardClass(
                'p-6 border theme-border flex flex-col h-full min-w-0 hover:shadow-md transition-all duration-300 hover:border-[var(--color-primario)]/50',
            )}
        >
            <div className="flex justify-between items-start gap-3 mb-4">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center flex-wrap gap-2 mb-2">
                        <span className="text-xs font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                            {traspaso.folio}
                        </span>
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border ${ui.badge}`}>
                            <StatusIcon className="w-3 h-3" />
                            {estadoNombre}
                        </span>
                    </div>
                    <h3 className="text-base font-bold theme-text-main leading-snug m-0 break-words">
                        {traspaso.cliente?.numero_cliente} — {traspaso.cliente?.nombre}
                    </h3>
                    <p className="text-xs theme-text-muted mt-2 font-bold m-0 inline-flex items-center gap-1.5">
                        <User className="w-3.5 h-3.5 shrink-0" />
                        <span className="truncate">{traspaso.vendedor?.name}</span>
                    </p>
                </div>
                <div className="p-2 rounded-xl shrink-0" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)', color: 'var(--color-primario)' }}>
                    <Package className="w-5 h-5" />
                </div>
            </div>

            <div className="grid grid-cols-3 gap-3 mt-auto pt-4 border-t theme-border/60">
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Piezas</p>
                    <p className={`text-base font-black m-0 tabular-nums ${ui.valor}`}>{traspaso.total_piezas}</p>
                </div>
                <div>
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Origen</p>
                    <p className="text-sm font-bold theme-text-main m-0 truncate" title={traspaso.almacen_origen?.nombre}>
                        {traspaso.almacen_origen?.nombre || '—'}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">Solicitado</p>
                    <p className="text-sm font-bold theme-text-main m-0">{formatearFecha(traspaso.created_at)}</p>
                </div>
            </div>

            {traspaso.fecha_entrega_estimada && (
                <p className="text-xs theme-text-muted font-bold mt-3 mb-0 inline-flex items-center gap-1.5">
                    <Calendar className="w-3.5 h-3.5 shrink-0" />
                    Entrega {formatearFecha(traspaso.fecha_entrega_estimada)}
                    {traspaso.horario?.nombre ? ` · ${traspaso.horario.nombre}` : ''}
                </p>
            )}

            <div className="mt-3">
                <FeedbackResolucionTraspaso traspaso={traspaso} />
            </div>

            <div className="flex flex-wrap gap-2 pt-4 mt-4 border-t theme-border/60">
                <button
                    type="button"
                    onClick={() => onVerDetalle(traspaso)}
                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border outline-none hover:border-[var(--color-primario)] transition-colors"
                >
                    <Eye className="w-3.5 h-3.5 shrink-0" /> Detalle
                </button>
                {puedeBitacora && onBitacora && (
                    <button
                        type="button"
                        onClick={() => onBitacora(traspaso)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border outline-none hover:border-purple-500 transition-colors"
                    >
                        <History className="w-3.5 h-3.5 shrink-0" /> Bitácora
                    </button>
                )}
                {esPendiente && (
                    <>
                        {puedeResponder && (
                            <button
                                type="button"
                                onClick={() => onResponder(traspaso)}
                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white outline-none"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <CheckCircle2 className="w-3.5 h-3.5 shrink-0" /> Responder
                            </button>
                        )}
                        {puedeReportar && (
                            <button
                                type="button"
                                onClick={() => onReportar(traspaso)}
                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-red-500/10 text-red-600 dark:text-red-300 border border-red-500/30 outline-none"
                            >
                                <XCircle className="w-3.5 h-3.5 shrink-0" /> Error
                            </button>
                        )}
                    </>
                )}
                {esRespondida && puedeVerificar && (
                    <button
                        type="button"
                        onClick={() => onVerificar(traspaso)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 outline-none"
                    >
                        <Package className="w-3.5 h-3.5 shrink-0" /> Verificar
                    </button>
                )}
                {puedeEliminar && (
                    <button
                        type="button"
                        onClick={() => onEliminar(traspaso)}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-muted border theme-border outline-none hover:text-red-500 hover:border-red-500/40"
                        aria-label="Eliminar"
                    >
                        <Trash2 className="w-3.5 h-3.5 shrink-0" />
                    </button>
                )}
            </div>
        </article>
    );
}
