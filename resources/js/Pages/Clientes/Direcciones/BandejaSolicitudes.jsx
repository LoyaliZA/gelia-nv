import React, { useEffect, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { ArrowLeft, ClipboardList, Copy, FileText, Plus, Search, X } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    geliaCardClass,
    THEME_INPUT,
    THEME_SELECT,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';
import {
    labelAccionSolicitud,
    labelEstadoSolicitud,
    LABELS_ESTADO_SOLICITUD,
    LABELS_ACCION_SOLICITUD,
} from '../../ControlPedidos/Partials/DireccionPedidoResumen';

const ESTADOS = Object.keys(LABELS_ESTADO_SOLICITUD);
const ACCIONES = Object.keys(LABELS_ACCION_SOLICITUD);

function chipEstado(estado) {
    const base = 'inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border';
    const map = {
        pending: 'bg-amber-500/10 text-amber-700 border-amber-500/30',
        approved: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30',
        rejected: 'bg-red-500/10 text-red-700 border-red-500/30',
        requires_correction: 'bg-orange-500/10 text-orange-700 border-orange-500/30',
        identity_review_required: 'bg-violet-500/10 text-violet-700 border-violet-500/30',
        possible_duplicate: 'bg-sky-500/10 text-sky-700 border-sky-500/30',
        verified: 'bg-teal-500/10 text-teal-700 border-teal-500/30',
    };
    return `${base} ${map[estado] || 'theme-element theme-text-muted theme-border'}`;
}

function fechaLegible(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('es-MX', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return iso;
    }
}

/** Funciona también en HTTP (Clipboard API requiere contexto seguro). */
function copiarAlPortapapeles(texto) {
    if (!texto) return Promise.resolve(false);
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        return navigator.clipboard.writeText(texto).then(() => true).catch(() => false);
    }
    try {
        const textArea = document.createElement('textarea');
        textArea.value = texto;
        textArea.setAttribute('readonly', '');
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textArea);
        return Promise.resolve(ok);
    } catch {
        return Promise.resolve(false);
    }
}

const PANEL_INTERNO = 'rounded-xl border theme-border theme-element/40 bg-black/5 dark:bg-white/5';

function ModalNuevaSolicitud({ abierto, onClose, esAuxiliar }) {
    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState([]);
    const [buscando, setBuscando] = useState(false);
    const [cliente, setCliente] = useState(null);
    const [accion, setAccion] = useState('add_address');
    const [enviando, setEnviando] = useState(false);
    const [enlaceUrl, setEnlaceUrl] = useState('');
    const [copiado, setCopiado] = useState(false);
    const debounce = useRef(null);

    useEffect(() => {
        if (!abierto) {
            setBusqueda('');
            setResultados([]);
            setCliente(null);
            setAccion('add_address');
            setEnlaceUrl('');
            setCopiado(false);
            setEnviando(false);
        }
    }, [abierto]);

    useEffect(() => {
        if (!abierto) return;
        if (debounce.current) clearTimeout(debounce.current);
        if (busqueda.trim().length < 2) {
            setResultados([]);
            return;
        }
        debounce.current = setTimeout(async () => {
            setBuscando(true);
            try {
                if (esAuxiliar) {
                    const { data } = await axios.get(route('control_pedidos.direcciones.buscar_cliente'), {
                        params: { q: busqueda.trim() },
                    });
                    setResultados(data?.data || []);
                } else {
                    const { data } = await axios.get('/api/clientes', { params: { q: busqueda.trim() } });
                    setResultados(Array.isArray(data) ? data : (data?.data || []));
                }
            } catch {
                setResultados([]);
            } finally {
                setBuscando(false);
            }
        }, 300);
        return () => clearTimeout(debounce.current);
    }, [busqueda, abierto, esAuxiliar]);

    if (!abierto) return null;

    const abrirFicha = () => {
        if (!cliente) return;
        onClose();
        if (esAuxiliar) {
            router.get(route('control_pedidos.direcciones.cliente', cliente.id));
        } else {
            router.get(route('admin.clientes.direcciones.index', cliente.id));
        }
    };

    const generarEnlace = () => {
        if (!cliente) return;
        setEnviando(true);
        const ruta = esAuxiliar
            ? route('control_pedidos.direcciones.enlace', cliente.id)
            : route('admin.clientes.direcciones.enlace', cliente.id);
        router.post(ruta, { accion }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const url = page?.props?.flash?.enlace_direccion_url || '';
                if (url) setEnlaceUrl(url);
                setEnviando(false);
            },
            onError: () => setEnviando(false),
            onFinish: () => setEnviando(false),
        });
    };

    const copiar = async () => {
        if (!enlaceUrl) return;
        const ok = await copiarAlPortapapeles(enlaceUrl);
        if (ok) {
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        }
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0">
                            Nueva solicitud de dirección
                        </h2>
                        <p className="text-xs theme-text-muted mt-1 m-0">
                            Busque el cliente y genere un enlace, o capture la dirección en la ficha.
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-5 space-y-4">
                    {!cliente ? (
                        <div>
                            <label className="block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Buscar cliente</span>
                                <div className="relative mt-1.5">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                    <input
                                        className={`${THEME_INPUT} pl-9`}
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value)}
                                        placeholder="Número o nombre…"
                                        autoFocus
                                    />
                                </div>
                            </label>
                            {(buscando || resultados.length > 0 || busqueda.trim().length >= 2) && (
                                <div className="mt-2 rounded-xl border theme-border max-h-48 overflow-y-auto">
                                    {buscando && (
                                        <p className="p-3 text-xs font-bold theme-text-muted m-0">Buscando…</p>
                                    )}
                                    {!buscando && resultados.length === 0 && (
                                        <p className="p-3 text-xs font-bold theme-text-muted m-0">Sin coincidencias</p>
                                    )}
                                    {resultados.map((c) => (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => setCliente(c)}
                                            className="w-full text-left px-3 py-2.5 border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5"
                                        >
                                            <span className="font-mono text-xs font-black theme-text-muted">{c.numero_cliente}</span>
                                            <span className="ml-2 text-sm font-bold theme-text-main">{c.nombre}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className={`${PANEL_INTERNO} px-4 py-3 flex items-center justify-between gap-3`}>
                                <div className="min-w-0 flex items-baseline gap-2 flex-wrap">
                                    <span className="font-mono text-sm font-black theme-text-main shrink-0">
                                        {cliente.numero_cliente}
                                    </span>
                                    <span className="text-sm font-bold theme-text-muted truncate">
                                        {cliente.nombre}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} shrink-0 !py-1.5 !px-3 text-[10px]`}
                                    onClick={() => {
                                        setCliente(null);
                                        setEnlaceUrl('');
                                    }}
                                >
                                    Cambiar
                                </button>
                            </div>

                            <label className="block">
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Acción del enlace</span>
                                <select
                                    className={`${THEME_SELECT} mt-1.5`}
                                    value={accion}
                                    onChange={(e) => setAccion(e.target.value)}
                                >
                                    {ACCIONES.map((a) => (
                                        <option key={a} value={a}>{labelAccionSolicitud(a)}</option>
                                    ))}
                                </select>
                            </label>

                            {enlaceUrl && (
                                <div className={`${PANEL_INTERNO} p-3 space-y-2`}>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                        Enlace listo
                                    </p>
                                    <div className="flex items-stretch gap-2">
                                        <input
                                            type="text"
                                            readOnly
                                            value={enlaceUrl}
                                            className={`${THEME_INPUT} flex-1 min-w-0 font-mono text-xs !py-2.5`}
                                            onFocus={(e) => e.target.select()}
                                        />
                                        <button
                                            type="button"
                                            className={`${THEME_BTN_PRIMARY} shrink-0 inline-flex items-center gap-1.5 !px-3`}
                                            onClick={copiar}
                                        >
                                            <Copy className="w-3.5 h-3.5" />
                                            {copiado ? 'Copiado' : 'Copiar'}
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-2 pt-1">
                                <button
                                    type="button"
                                    className={THEME_BTN_PRIMARY}
                                    disabled={enviando}
                                    onClick={generarEnlace}
                                >
                                    {enviando ? 'Generando…' : 'Generar enlace'}
                                </button>
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={abrirFicha}>
                                    Abrir ficha / capturar yo
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}

export default function BandejaSolicitudes({
    solicitudes,
    filtros = {},
    rutaBase = 'admin.clientes.direcciones.solicitudes',
    eyebrow = 'Clientes · Direcciones',
    backHref = null,
    backLabel = null,
}) {
    const { auth, flash } = usePage().props;
    const can = (p) => auth?.user?.permissions?.includes(p)
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const esAuxiliar = String(rutaBase).startsWith('control_pedidos.');
    const puedeNueva = can('clientes.direcciones.generar_enlace') || can('clientes.direcciones.crear');

    const [q, setQ] = useState(filtros.q || '');
    const [modalNueva, setModalNueva] = useState(false);
    const [copiadoFlash, setCopiadoFlash] = useState(false);
    const indexRoute = `${rutaBase}.index`;
    const showRoute = `${rutaBase}.show`;

    const aplicar = (extra = {}) => {
        const next = {
            q: q || undefined,
            estado: filtros.estado || undefined,
            accion: filtros.accion || undefined,
            con_remision: filtros.con_remision || undefined,
            cliente_id: filtros.cliente_id || undefined,
            ...extra,
        };
        Object.keys(next).forEach((k) => {
            if (next[k] === '' || next[k] === false || next[k] === undefined) delete next[k];
        });
        router.get(route(indexRoute), next, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const filas = solicitudes?.data || [];

    return (
        <AppLayout>
            <Head title="Solicitudes de dirección" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    {backHref && (
                        <Link
                            href={backHref}
                            className="inline-flex items-center gap-1 text-xs font-bold theme-text-muted mb-4"
                        >
                            <ArrowLeft className="w-3.5 h-3.5" />
                            {backLabel || 'Volver'}
                        </Link>
                    )}
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-3 mb-2">
                                <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                                <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                    {eyebrow}
                                </p>
                            </div>
                            <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                                Solicitudes de <span style={{ color: 'var(--color-primario)' }}>dirección</span>
                            </h1>
                            <p className="mt-2 text-sm theme-text-muted m-0">Revisa altas y cambios enviados por el formulario público.</p>
                        </div>
                        {puedeNueva && (
                            <button
                                type="button"
                                className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2`}
                                onClick={() => setModalNueva(true)}
                            >
                                <Plus className="w-4 h-4" />
                                Nueva Solicitud de Dirección
                            </button>
                        )}
                    </div>
                </header>

                {flash?.enlace_direccion_url && !modalNueva && (
                    <div className={`${geliaCardClass()} p-4`}>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-2">Enlace listo para compartir</p>
                        <div className="flex items-stretch gap-2">
                            <input
                                type="text"
                                readOnly
                                value={flash.enlace_direccion_url}
                                className={`${THEME_INPUT} flex-1 min-w-0 font-mono text-xs !py-2.5`}
                                onFocus={(e) => e.target.select()}
                            />
                            <button
                                type="button"
                                className={`${THEME_BTN_PRIMARY} shrink-0 inline-flex items-center gap-1.5 !px-3`}
                                onClick={async () => {
                                    const ok = await copiarAlPortapapeles(flash.enlace_direccion_url);
                                    if (ok) {
                                        setCopiadoFlash(true);
                                        setTimeout(() => setCopiadoFlash(false), 2000);
                                    }
                                }}
                            >
                                <Copy className="w-3.5 h-3.5" />
                                {copiadoFlash ? 'Copiado' : 'Copiar'}
                            </button>
                        </div>
                    </div>
                )}

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <div className="flex flex-wrap gap-3 items-end">
                        <label className="flex-1 min-w-[180px]">
                            <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5">Buscar</span>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                <input
                                    className={`${THEME_INPUT} pl-9`}
                                    value={q}
                                    placeholder="Folio / cliente"
                                    onChange={(e) => setQ(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && aplicar({ q: e.target.value || undefined, page: 1 })}
                                />
                            </div>
                        </label>
                        <label className="min-w-[140px]">
                            <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5">Estado</span>
                            <select
                                className={THEME_SELECT}
                                value={filtros.estado || ''}
                                onChange={(e) => aplicar({ estado: e.target.value || undefined, page: 1 })}
                            >
                                <option value="">Todos</option>
                                {ESTADOS.map((s) => (
                                    <option key={s} value={s}>{labelEstadoSolicitud(s)}</option>
                                ))}
                            </select>
                        </label>
                        <label className="min-w-[140px]">
                            <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5">Acción</span>
                            <select
                                className={THEME_SELECT}
                                value={filtros.accion || ''}
                                onChange={(e) => aplicar({ accion: e.target.value || undefined, page: 1 })}
                            >
                                <option value="">Todas</option>
                                {ACCIONES.map((a) => (
                                    <option key={a} value={a}>{labelAccionSolicitud(a)}</option>
                                ))}
                            </select>
                        </label>
                        <label className="flex items-center gap-2 pb-2.5 text-xs font-bold theme-text-main cursor-pointer select-none">
                            <input
                                type="checkbox"
                                checked={Boolean(filtros.con_remision)}
                                onChange={(e) => aplicar({
                                    con_remision: e.target.checked ? 1 : undefined,
                                    page: 1,
                                })}
                            />
                            Con remisión
                        </label>
                        <button type="button" className={THEME_BTN_PRIMARY} onClick={() => aplicar({ page: 1 })}>
                            Filtrar
                        </button>
                    </div>
                </div>

                <div className={`${geliaCardClass()} overflow-hidden`}>
                    {filas.length === 0 ? (
                        <div className="p-12 text-center space-y-3">
                            <ClipboardList className="w-10 h-10 mx-auto theme-text-muted opacity-40" />
                            <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted italic">
                                No hay solicitudes con estos filtros_
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse min-w-[720px]">
                                <thead>
                                    <tr className="border-b theme-border">
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Acción_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Fecha_</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((s) => (
                                        <tr key={s.id} className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                            <td className="p-4">
                                                <Link
                                                    href={route(showRoute, s.id)}
                                                    className="font-mono text-sm font-bold underline decoration-[var(--color-primario)]/40 hover:decoration-[var(--color-primario)]"
                                                    style={{ color: 'var(--color-primario)' }}
                                                >
                                                    {s.folio}
                                                </Link>
                                            </td>
                                            <td className="p-4 text-sm theme-text-main">
                                                {s.cliente_coincidente
                                                    ? `${s.cliente_coincidente.numero_cliente} · ${s.cliente_coincidente.nombre}`
                                                    : s.numero_cliente_declarado || 'Sin vincular'}
                                            </td>
                                            <td className="p-4 text-sm theme-text-muted">{labelAccionSolicitud(s.accion_solicitada)}</td>
                                            <td className="p-4">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className={chipEstado(s.estado)}>{labelEstadoSolicitud(s.estado)}</span>
                                                    {s.anexa_remision && (
                                                        <span className="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                            <FileText className="w-3 h-3" /> Remisión
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4 text-xs font-bold theme-text-muted whitespace-nowrap">
                                                {fechaLegible(s.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="p-4 border-t theme-border">
                        <GeliaPaginacion
                            paginator={solicitudes}
                            onIrAPagina={(page) => aplicar({ page })}
                        />
                    </div>
                </div>
            </GeliaPageShell>

            <ModalNuevaSolicitud
                abierto={modalNueva}
                onClose={() => setModalNueva(false)}
                esAuxiliar={esAuxiliar}
            />
        </AppLayout>
    );
}
