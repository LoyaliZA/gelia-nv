import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Package, Boxes, Warehouse, User, Calendar, Copy, Check } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import FeedbackResolucionTraspaso from './FeedbackResolucionTraspaso';

function formatearFecha(valor) {
    if (!valor) return '—';
    return new Date(valor).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function copiarAlPortapapeles(texto) {
    const valor = String(texto ?? '');
    if (!valor) return Promise.resolve(false);

    if (navigator.clipboard?.writeText && window.isSecureContext) {
        return navigator.clipboard.writeText(valor).then(() => true).catch(() => false);
    }

    try {
        const ta = document.createElement('textarea');
        ta.value = valor;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return Promise.resolve(ok);
    } catch {
        return Promise.resolve(false);
    }
}

function BotonCopiarTexto({ texto, label }) {
    const [copiado, setCopiado] = useState(false);
    if (!texto) return null;

    const copiar = async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const ok = await copiarAlPortapapeles(texto);
        if (!ok) return;
        setCopiado(true);
        setTimeout(() => setCopiado(false), 1500);
    };

    return (
        <button
            type="button"
            onClick={copiar}
            className="p-1.5 rounded-lg theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none shrink-0 transition-colors"
            title={copiado ? 'Copiado' : `Copiar ${label}`}
            aria-label={copiado ? 'Copiado' : `Copiar ${label}`}
        >
            {copiado ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
        </button>
    );
}

export default function ModalDetalleTraspaso({ traspaso, onClose }) {
    if (!traspaso) return null;
    const productos = traspaso.productos || [];
    const estadoNombre = traspaso.estado?.nombre || '—';

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden text-left`}
                style={{ fontFamily: 'var(--font-principal)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-6 md:p-8 flex justify-between items-start gap-3 border-b theme-border bg-black/5 dark:bg-white/5 shrink-0">
                    <div className="min-w-0">
                        <h3 className="text-lg md:text-xl font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2 m-0">
                            <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            Detalle de traspaso
                        </h3>
                        <p className="text-sm theme-text-muted mt-2 font-bold m-0">
                            Cliente:{' '}
                            <span className="theme-text-main">
                                {traspaso.cliente?.nombre}
                            </span>
                            {' '}(No. {traspaso.cliente?.numero_cliente})
                        </p>
                        <p className="text-sm font-black theme-text-main mt-1 m-0">
                            {traspaso.folio}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main shrink-0"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 md:p-8 custom-scrollbar">
                    <div className="flex flex-col gap-6">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="p-4 border theme-border rounded-xl bg-white/50 dark:bg-black/20">
                                <span className="block text-xs uppercase tracking-widest theme-text-muted font-black mb-1">
                                    Total piezas
                                </span>
                                <span className="text-2xl font-black tabular-nums" style={{ color: 'var(--color-primario)' }}>
                                    {traspaso.total_piezas}
                                </span>
                            </div>
                            <div className="p-4 border theme-border rounded-xl bg-white/50 dark:bg-black/20">
                                <span className="block text-xs uppercase tracking-widest theme-text-muted font-black mb-1">
                                    Líneas
                                </span>
                                <span className="text-2xl font-black theme-text-main tabular-nums">
                                    {productos.length}
                                </span>
                            </div>
                            <div className="p-4 border theme-border rounded-xl bg-white/50 dark:bg-black/20">
                                <span className="block text-xs uppercase tracking-widest theme-text-muted font-black mb-1">
                                    Estado
                                </span>
                                <span className="text-xl font-black theme-text-main">
                                    {estadoNombre}
                                </span>
                            </div>
                        </div>

                        <FeedbackResolucionTraspaso traspaso={traspaso} />

                        <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm theme-text-muted font-semibold">
                            <span className="inline-flex items-center gap-1.5">
                                <User className="w-4 h-4" /> {traspaso.vendedor?.name}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Warehouse className="w-4 h-4" /> {traspaso.almacen_origen?.nombre || '—'}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Calendar className="w-4 h-4" /> Solicitado {formatearFecha(traspaso.created_at)}
                            </span>
                        </div>

                        {traspaso.fecha_entrega_estimada && (
                            <p className="text-sm theme-text-muted font-bold m-0">
                                Entrega estimada: {formatearFecha(traspaso.fecha_entrega_estimada)}
                                {traspaso.horario?.nombre ? ` · ${traspaso.horario.nombre}` : ''}
                            </p>
                        )}

                        <div className="space-y-3">
                            <h4 className="text-sm font-black uppercase tracking-widest theme-text-muted m-0 flex items-center gap-2">
                                <Boxes className="w-4 h-4" />
                                Piezas solicitadas ({productos.length})
                            </h4>

                            <div className="space-y-2">
                                {productos.map((p) => {
                                    const detalle = p.detalle_dano || p.detalleDano;
                                    return (
                                    <div
                                        key={p.id}
                                        className="rounded-lg border theme-border px-4 py-3 bg-black/[0.02] dark:bg-white/[0.02]"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 space-y-1.5">
                                                <div className="flex items-center gap-1.5 min-w-0">
                                                    <p className="text-sm font-black theme-text-main m-0 tabular-nums break-all">
                                                        {p.sku}
                                                    </p>
                                                    <BotonCopiarTexto texto={p.sku} label="SKU" />
                                                </div>
                                                <div className="flex items-start gap-1.5 min-w-0">
                                                    <p className="text-base font-bold theme-text-main m-0 leading-snug break-words">
                                                        {p.descripcion}
                                                    </p>
                                                    <BotonCopiarTexto texto={p.descripcion} label="nombre" />
                                                </div>
                                                {detalle && (
                                                    <p className="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 m-0">
                                                        Detalle/daño CEDIS: {detalle.motivo}
                                                    </p>
                                                )}
                                            </div>
                                            <span
                                                className="text-xl font-black shrink-0 tabular-nums"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                {p.piezas}
                                            </span>
                                        </div>
                                    </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
