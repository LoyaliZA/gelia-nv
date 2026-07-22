import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import {
    X, History, ShieldCheck, CheckCircle2, FileImage, Camera,
    AlertOctagon, Package, Warehouse, User, Boxes,
} from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

function formatearFecha(valor) {
    if (!valor) return '—';
    return new Date(valor).toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const LightboxEvidencia = ({ url, onClose }) => {
    if (!url) return null;

    return createPortal(
        <div
            className="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 backdrop-blur-md p-4 md:p-8"
            onClick={(e) => {
                e.stopPropagation();
                onClose();
            }}
        >
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onClose();
                }}
                className="absolute top-6 right-6 p-3 rounded-2xl bg-white/10 text-white border border-white/20 hover:bg-white/20 outline-none"
            >
                <X className="w-5 h-5" />
            </button>
            <div className="max-w-5xl w-full max-h-full" onClick={(e) => e.stopPropagation()}>
                <img src={url} alt="Evidencia" className="max-w-full max-h-[85vh] mx-auto object-contain rounded-2xl shadow-2xl" />
            </div>
        </div>,
        document.body,
    );
};

const BotonEvidencia = ({ url, label = 'Ver evidencia', onAbrir, miniatura = false }) => {
    if (!url) return null;

    if (miniatura) {
        return (
            <button
                type="button"
                onClick={() => onAbrir(url)}
                className="block w-full rounded-2xl overflow-hidden border theme-border h-32 relative group text-left outline-none"
            >
                <img src={url} className="w-full h-full object-cover group-hover:scale-105 transition-transform" alt={label} />
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onAbrir(url)}
            className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-blue-500 hover:text-blue-600 transition-colors w-fit outline-none"
        >
            <FileImage className="w-3 h-3" /> {label}
        </button>
    );
};

function describirPaso(registro) {
    const motivo = (registro.motivo_reporte || '').toUpperCase();
    const nuevo = registro.estado_nuevo?.nombre || registro.estadoNuevo?.nombre || '';
    const anterior = registro.estado_anterior?.nombre || registro.estadoAnterior?.nombre || '';
    const esCreacion = (!anterior && nuevo === 'Pendiente') || motivo.includes('CREACIÓN');

    if (motivo.includes('ELIMIN')) {
        return { titulo: 'Solicitud eliminada', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : (nuevo || 'Eliminada'), tono: 'error' };
    }

    switch (nuevo) {
        case 'Pendiente':
            return {
                titulo: esCreacion ? 'Solicitud creada' : 'Reenviada a revisión',
                detalle: anterior ? `${anterior} → Pendiente` : 'Pendiente de respuesta',
                tono: 'info',
            };
        case 'Respondida':
            return {
                titulo: anterior === 'Incorrecta' ? 'Respuesta a corrección' : 'Traspaso respondido',
                detalle: `${anterior || '—'} → Respondida`,
                tono: 'ok',
            };
        case 'Verificada':
            return { titulo: 'Solicitud verificada', detalle: `${anterior || '—'} → Verificada`, tono: 'ok' };
        case 'Incorrecta':
            return { titulo: 'Error reportado', detalle: `${anterior || '—'} → Incorrecta`, tono: 'error' };
        default:
            return {
                titulo: 'Actualización',
                detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : (nuevo || 'Sin cambio de estado'),
                tono: 'info',
            };
    }
}

const estilosPaso = {
    ok: { iconBg: 'bg-emerald-500 text-white', border: 'border-emerald-500/30', label: 'text-emerald-600 dark:text-emerald-400', Icon: CheckCircle2 },
    error: { iconBg: 'bg-red-500 text-white', border: 'border-red-500/30', label: 'text-red-600 dark:text-red-400', Icon: AlertOctagon },
    info: { iconBg: 'bg-purple-500 text-white', border: 'theme-border', label: 'text-purple-600 dark:text-purple-400', Icon: ShieldCheck },
};

function urlEvidenciaActual(traspaso) {
    if (!traspaso?.tiene_evidencia_respuesta && !traspaso?.evidencia_respuesta_path) return null;
    return route('traspasos.evidencia', traspaso.id);
}

function urlDesdeSnapshot(snapshot, traspaso) {
    if (snapshot?.evidencia_respuesta_path && traspaso?.id) {
        // Misma captura autorizada vía ruta del traspaso (archivo vigente).
        return route('traspasos.evidencia', traspaso.id);
    }
    if (snapshot?.evidencia_respuesta_path) {
        return `/storage/${snapshot.evidencia_respuesta_path}`;
    }
    return null;
}

export default function ModalBitacoraTraspaso({ traspaso, onClose }) {
    const [evidenciaAbierta, setEvidenciaAbierta] = useState(null);

    if (!traspaso) return null;

    const auditorias = [...(traspaso.auditorias || [])].sort((a, b) => a.id - b.id);
    const productos = traspaso.productos || [];
    const evidenciaActual = urlEvidenciaActual(traspaso);

    return (
        <>
            {createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[100] p-4 md:p-8`} onClick={onClose}>
                    <div
                        className={`${THEME_MODAL_SHELL} w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden`}
                        style={{ fontFamily: 'var(--font-principal)' }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="p-6 md:p-8 flex justify-between items-start gap-3 border-b theme-border shrink-0">
                            <div className="flex items-center gap-4 min-w-0">
                                <History className="w-9 h-9 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <div className="min-w-0">
                                    <h2 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                                        Expediente de Auditoría_
                                    </h2>
                                    <p className="text-xs font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                                        Folio: {traspaso.folio}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={onClose}
                                className="p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none shrink-0"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 flex-1 overflow-hidden p-6 md:p-8">
                            <div className="theme-element border theme-border rounded-3xl p-6 md:p-8 overflow-y-auto custom-scrollbar">
                                <h3 className="text-sm font-black uppercase tracking-widest mb-6 m-0" style={{ color: 'var(--color-primario)' }}>
                                    Estado actual del traspaso
                                </h3>
                                <div className="space-y-5">
                                    <div>
                                        <p className="text-[10px] font-bold theme-text-muted uppercase mb-1 m-0">Cliente</p>
                                        <p className="text-base font-black theme-text-main m-0">
                                            {traspaso.cliente?.numero_cliente} — {traspaso.cliente?.nombre}
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="p-4 rounded-xl bg-black/5 dark:bg-white/5 border theme-border">
                                            <p className="text-[10px] font-black uppercase theme-text-muted tracking-widest mb-1 m-0 flex items-center gap-1">
                                                <User className="w-3 h-3" /> Vendedor
                                            </p>
                                            <p className="text-sm font-bold theme-text-main m-0">{traspaso.vendedor?.name || '—'}</p>
                                        </div>
                                        <div className="p-4 rounded-xl bg-black/5 dark:bg-white/5 border theme-border">
                                            <p className="text-[10px] font-black uppercase theme-text-muted tracking-widest mb-1 m-0 flex items-center gap-1">
                                                <Warehouse className="w-3 h-3" /> Origen
                                            </p>
                                            <p className="text-sm font-bold theme-text-main m-0">{traspaso.almacen_origen?.nombre || '—'}</p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-1 m-0 flex items-center gap-1">
                                                <Package className="w-3 h-3" /> Estado
                                            </p>
                                            <p className="text-base font-black theme-text-main m-0">{traspaso.estado?.nombre || '—'}</p>
                                        </div>
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-1 m-0 flex items-center gap-1">
                                                <Boxes className="w-3 h-3" /> Piezas
                                            </p>
                                            <p className="text-base font-black theme-text-main m-0">{traspaso.total_piezas}</p>
                                        </div>
                                    </div>
                                    {traspaso.folio_traspaso && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-1 m-0">Folio externo</p>
                                            <p className="text-base font-black theme-text-main m-0">{traspaso.folio_traspaso}</p>
                                        </div>
                                    )}
                                    {traspaso.motivo_respuesta?.trim() && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-2 m-0">Observaciones</p>
                                            <p className="text-sm font-bold theme-text-main italic leading-relaxed p-4 rounded-2xl border theme-border theme-element m-0">
                                                {traspaso.motivo_respuesta}
                                            </p>
                                        </div>
                                    )}
                                    {productos.length > 0 && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-2 m-0">
                                                Piezas solicitadas ({productos.length})
                                            </p>
                                            <div className="space-y-2">
                                                {productos.map((p) => (
                                                    <div key={p.id} className="flex justify-between gap-2 text-xs border theme-border rounded-xl px-3 py-2">
                                                        <span className="font-bold theme-text-main truncate">{p.sku} · {p.descripcion}</span>
                                                        <span className="font-black shrink-0" style={{ color: 'var(--color-primario)' }}>{p.piezas}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {evidenciaActual && (
                                        <div>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase mb-3 m-0">Evidencia de respuesta</p>
                                            <button
                                                type="button"
                                                onClick={() => setEvidenciaAbierta(evidenciaActual)}
                                                className="block w-full overflow-hidden rounded-2xl border theme-border hover:ring-2 transition-all h-40 outline-none text-left"
                                            >
                                                <img src={evidenciaActual} className="w-full h-full object-cover hover:scale-105 transition-transform" alt="Evidencia" />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="overflow-y-auto custom-scrollbar relative px-2 md:px-6 py-2 before:absolute before:inset-0 before:ml-6 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-purple-300 dark:before:via-purple-900/50 before:to-transparent">
                                <h3 className="text-sm font-black uppercase text-purple-600 dark:text-purple-400 tracking-widest mb-8 ml-8 relative z-10 theme-surface inline-block pr-4 m-0">
                                    Línea de tiempo operativa
                                </h3>

                                <div className="space-y-8">
                                    {auditorias.length === 0 && (
                                        <p className="ml-10 text-xs font-bold theme-text-muted italic m-0">
                                            No hay registros de auditoría disponibles.
                                        </p>
                                    )}

                                    {auditorias.map((registro) => {
                                        const paso = describirPaso(registro);
                                        const estilo = estilosPaso[paso.tono] || estilosPaso.info;
                                        const IconoPaso = estilo.Icon;
                                        const snapshot = typeof registro.datos_snapshot === 'string'
                                            ? JSON.parse(registro.datos_snapshot)
                                            : registro.datos_snapshot;
                                        const evidenciaUrl = urlDesdeSnapshot(snapshot, traspaso)
                                            || (snapshot?.tiene_evidencia ? evidenciaActual : null);

                                        return (
                                            <div key={registro.id} className="relative flex flex-col ml-10">
                                                <div className={`absolute -left-[3.5rem] top-1 w-10 h-10 rounded-full border-4 theme-surface flex items-center justify-center shadow-md z-10 ${estilo.iconBg}`}>
                                                    <IconoPaso className="w-4 h-4" />
                                                </div>

                                                <div className={`theme-element border ${estilo.border} p-5 md:p-6 rounded-3xl shadow-sm`}>
                                                    <div className="flex justify-between items-center gap-2 mb-2">
                                                        <span className={`font-black text-xs uppercase tracking-widest ${estilo.label}`}>
                                                            {paso.titulo}
                                                        </span>
                                                        <span className="text-[10px] font-bold theme-text-muted shrink-0">
                                                            {formatearFecha(registro.created_at)}
                                                        </span>
                                                    </div>
                                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mb-1 m-0">
                                                        Por: {registro.usuario?.name || 'Usuario'}
                                                    </p>
                                                    <p className="text-sm font-black theme-text-main mb-3 m-0">
                                                        {paso.detalle}
                                                    </p>

                                                    {snapshot?.folio_traspaso && (
                                                        <p className="text-xs font-bold theme-text-main mb-3 m-0">
                                                            Folio externo: {snapshot.folio_traspaso}
                                                        </p>
                                                    )}

                                                    {(snapshot?.total_piezas != null || snapshot?.lineas != null) && (
                                                        <div className="mb-3 p-3 rounded-2xl border theme-border bg-black/5 dark:bg-white/5 text-xs font-bold theme-text-muted">
                                                            {snapshot.total_piezas != null && <span>Piezas: {snapshot.total_piezas}</span>}
                                                            {snapshot.lineas != null && (
                                                                <span>{snapshot.total_piezas != null ? ' · ' : ''}Líneas: {snapshot.lineas}</span>
                                                            )}
                                                        </div>
                                                    )}

                                                    {registro.motivo_reporte && (
                                                        <div className="p-4 theme-surface rounded-2xl border theme-border">
                                                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1 m-0">
                                                                Nota del registro
                                                            </p>
                                                            <p className="text-xs font-bold theme-text-main m-0 leading-relaxed">
                                                                {registro.motivo_reporte}
                                                            </p>
                                                        </div>
                                                    )}

                                                    {evidenciaUrl && (
                                                        <div className="mt-4 pt-4 border-t theme-border">
                                                            <h4 className={`text-[10px] font-black uppercase tracking-widest mb-3 flex items-center gap-2 m-0 ${estilo.label}`}>
                                                                <Camera className="w-3 h-3" /> Evidencia de respuesta
                                                            </h4>
                                                            <BotonEvidencia
                                                                url={evidenciaUrl}
                                                                label="Ver evidencia"
                                                                onAbrir={setEvidenciaAbierta}
                                                                miniatura
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>,
                document.body,
            )}

            {evidenciaAbierta && (
                <LightboxEvidencia url={evidenciaAbierta} onClose={() => setEvidenciaAbierta(null)} />
            )}
        </>
    );
}
