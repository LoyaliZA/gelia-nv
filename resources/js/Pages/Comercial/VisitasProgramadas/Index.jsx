import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Calendar, Plus, X } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import TarjetaVisitaProgramada from './Partials/TarjetaVisitaProgramada';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_SELECT,
} from '../../../utils/geliaTheme';

/** Contenedores de lista y filtros: radio menor que el card de título (2.5rem). */
const CARD_SECCION = geliaCardClass('!rounded-xl');

export default function VisitasProgramadasIndex({
    auth,
    vista,
    visitas,
    sucursales = [],
    intenciones = [],
    fecha_hoy,
}) {
    const { flash } = usePage().props;
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modoBusqueda, setModoBusqueda] = useState('nombre');
    const [terminoBusqueda, setTerminoBusqueda] = useState('');
    const [resultadosCliente, setResultadosCliente] = useState([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [errorBusqueda, setErrorBusqueda] = useState(null);
    const [bloquearBusquedaAuto, setBloquearBusquedaAuto] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        cliente_id: '',
        sucursal_id: sucursales[0]?.id ?? '',
        fecha: fecha_hoy,
        tipo_hora: 'sin_hora',
        hora_exacta: '',
        hora_inicio: '',
        hora_fin: '',
        intencion: intenciones[0]?.valor ?? 'confirmo_asistencia',
    });

    const cambiarVista = (nueva) => {
        router.get(route('visitas_programadas.index'), { vista: nueva }, { preserveState: true, replace: true });
    };

    const minimoBusqueda = modoBusqueda === 'numero' ? 1 : 2;

    const buscarCliente = useCallback(async (termino, modo) => {
        const limpio = termino.trim();
        if (limpio.length < (modo === 'numero' ? 1 : 2)) {
            setResultadosCliente([]);
            setErrorBusqueda(null);
            return;
        }
        setBuscandoCliente(true);
        setErrorBusqueda(null);
        try {
            const params = new URLSearchParams({ modo, q: limpio });
            const res = await fetch(`${route('visitas_programadas.buscar_cliente')}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('No se pudo buscar.');
            const json = await res.json();
            setResultadosCliente((json.data ?? []).slice(0, 5));
        } catch {
            setErrorBusqueda('No se pudo completar la búsqueda.');
            setResultadosCliente([]);
        } finally {
            setBuscandoCliente(false);
        }
    }, []);

    useEffect(() => {
        if (!modalAbierto || bloquearBusquedaAuto) return undefined;
        const limpio = terminoBusqueda.trim();
        if (limpio.length < minimoBusqueda) {
            setResultadosCliente([]);
            setErrorBusqueda(null);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            void buscarCliente(terminoBusqueda, modoBusqueda);
        }, 320);
        return () => window.clearTimeout(timer);
    }, [modalAbierto, bloquearBusquedaAuto, terminoBusqueda, modoBusqueda, minimoBusqueda, buscarCliente]);

    const seleccionarCliente = (cliente) => {
        setData('cliente_id', cliente.id);
        setResultadosCliente([]);
        setTerminoBusqueda(`${cliente.numero_cliente} — ${cliente.nombre}`);
        setBloquearBusquedaAuto(true);
        setErrorBusqueda(null);
    };

    const abrirModal = () => {
        reset();
        setData('fecha', fecha_hoy);
        setTerminoBusqueda('');
        setResultadosCliente([]);
        setBloquearBusquedaAuto(false);
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setModalAbierto(false);
        clearErrors();
    };

    const registrar = (e) => {
        e.preventDefault();
        post(route('visitas_programadas.store'), {
            onSuccess: () => cerrarModal(),
            preserveScroll: true,
        });
    };

    const lista = visitas?.data ?? [];

    const modalRegistrar = modalAbierto ? (
        <div
            className={`${THEME_MODAL_OVERLAY} z-[100]`}
            onClick={cerrarModal}
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-registrar-visita-titulo"
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg modal-pop w-full p-6 md:p-8 max-h-[90vh] overflow-y-auto`}
                onClick={(e) => e.stopPropagation()}
            >
                <form onSubmit={registrar} className="space-y-4">
                    <div className="flex justify-between items-center gap-3">
                        <h2 id="modal-registrar-visita-titulo" className="text-xl font-black uppercase tracking-tight theme-text-main m-0">
                            Registrar visita
                        </h2>
                        <button type="button" onClick={cerrarModal} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="space-y-2">
                        <label className={THEME_LABEL}>Cliente</label>
                        <div className="flex flex-col sm:flex-row gap-2">
                            <select
                                value={modoBusqueda}
                                onChange={(e) => setModoBusqueda(e.target.value)}
                                className={`${THEME_SELECT} min-h-[44px] sm:max-w-[9rem]`}
                            >
                                <option value="nombre">Nombre</option>
                                <option value="numero">Número</option>
                            </select>
                            <input
                                type="text"
                                value={terminoBusqueda}
                                onChange={(e) => {
                                    setBloquearBusquedaAuto(false);
                                    setData('cliente_id', '');
                                    setTerminoBusqueda(e.target.value);
                                }}
                                className={`${THEME_INPUT} flex-1 min-h-[44px]`}
                                placeholder={modoBusqueda === 'numero' ? 'Número de cliente' : 'Nombre'}
                                autoComplete="off"
                                aria-busy={buscandoCliente}
                            />
                        </div>
                        {buscandoCliente && (
                            <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted m-0">Buscando…</p>
                        )}
                        {errorBusqueda && <p className="text-sm text-red-600">{errorBusqueda}</p>}
                        {errors.cliente_id && <p className="text-sm text-red-600">{errors.cliente_id}</p>}
                        {resultadosCliente.length > 0 && (
                            <ul className="border theme-border rounded-lg divide-y max-h-40 overflow-y-auto">
                                {resultadosCliente.map((c) => (
                                    <li key={c.id}>
                                        <button
                                            type="button"
                                            className="w-full text-left px-3 py-2 text-sm hover:bg-black/5 dark:hover:bg-white/5"
                                            onClick={() => seleccionarCliente(c)}
                                        >
                                            <span className="font-bold tabular-nums">{c.numero_cliente}</span> — {c.nombre}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Sucursal</label>
                        <select
                            value={data.sucursal_id}
                            onChange={(e) => setData('sucursal_id', e.target.value)}
                            className={`${THEME_SELECT} w-full mt-1 min-h-[44px]`}
                        >
                            {sucursales.map((s) => (
                                <option key={s.id} value={s.id}>{s.nombre}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Fecha</label>
                        <div className="flex gap-2 mt-1">
                            <input
                                type="date"
                                value={data.fecha}
                                min={fecha_hoy}
                                onChange={(e) => setData('fecha', e.target.value)}
                                className={`${THEME_INPUT} flex-1 min-h-[44px]`}
                            />
                            <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 text-[10px] font-black uppercase tracking-widest`} onClick={() => setData('fecha', fecha_hoy)}>
                                Hoy
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Hora (opcional)</label>
                        <select
                            value={data.tipo_hora}
                            onChange={(e) => setData('tipo_hora', e.target.value)}
                            className={`${THEME_SELECT} w-full mt-1 min-h-[44px]`}
                        >
                            <option value="sin_hora">Sin hora definida</option>
                            <option value="exacta">Hora exacta</option>
                            <option value="rango">Rango de hora</option>
                        </select>
                        {data.tipo_hora === 'exacta' && (
                            <input type="time" value={data.hora_exacta} onChange={(e) => setData('hora_exacta', e.target.value)} className={`${THEME_INPUT} w-full mt-2 min-h-[44px]`} />
                        )}
                        {data.tipo_hora === 'rango' && (
                            <div className="flex gap-2 mt-2">
                                <input type="time" value={data.hora_inicio} onChange={(e) => setData('hora_inicio', e.target.value)} className={`${THEME_INPUT} flex-1 min-h-[44px]`} />
                                <input type="time" value={data.hora_fin} onChange={(e) => setData('hora_fin', e.target.value)} className={`${THEME_INPUT} flex-1 min-h-[44px]`} />
                            </div>
                        )}
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Probabilidad de asistencia</label>
                        <select value={data.intencion} onChange={(e) => setData('intencion', e.target.value)} className={`${THEME_SELECT} w-full mt-1 min-h-[44px]`}>
                            {intenciones.map((i) => (
                                <option key={i.valor} value={i.valor}>{i.etiqueta}</option>
                            ))}
                        </select>
                    </div>

                    <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full min-h-[44px]`}>
                        Guardar visita
                    </button>
                </form>
            </div>
        </div>
    ) : null;

    return (
        <AppLayout auth={auth}>
            <Head title="Visitas programadas" />
            <GeliaPageShell className="space-y-5">
                <GeliaTituloCard
                    eyebrow="Comercial"
                    title="Visitas"
                    titleHighlight="programadas"
                    description="Registra la intención de asistencia de tus clientes a sucursal."
                    icon={Calendar}
                    aside={(
                        <button
                            type="button"
                            onClick={abrirModal}
                            className={`${THEME_BTN_PRIMARY} min-h-[44px] px-5 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 w-full sm:w-auto`}
                        >
                            <Plus className="w-4 h-4" aria-hidden />
                            Nueva visita
                        </button>
                    )}
                />

                {flash?.success && (
                    <div className={`${geliaCardClass()} p-4 text-[11px] font-bold uppercase tracking-widest text-emerald-700`}>
                        {flash.success}
                    </div>
                )}

                <div className={`${CARD_SECCION} p-3 flex flex-wrap gap-2`}>
                    <button
                        type="button"
                        onClick={() => cambiarVista('vigentes')}
                        className={`min-h-[44px] px-4 rounded-lg text-[10px] font-black uppercase tracking-widest ${vista === 'vigentes' ? THEME_BTN_PRIMARY : THEME_BTN_SECONDARY}`}
                    >
                        Programadas
                    </button>
                    <button
                        type="button"
                        onClick={() => cambiarVista('historial')}
                        className={`min-h-[44px] px-4 rounded-lg text-[10px] font-black uppercase tracking-widest ${vista === 'historial' ? THEME_BTN_PRIMARY : THEME_BTN_SECONDARY}`}
                    >
                        Registros pasados
                    </button>
                </div>

                <div className="space-y-3">
                    {lista.length === 0 && (
                        <div className={`${CARD_SECCION} p-6 text-center theme-text-muted`}>
                            No hay visitas en esta vista.
                        </div>
                    )}
                    {lista.map((visita) => (
                        <TarjetaVisitaProgramada key={visita.id} visita={visita} variante="comercial" />
                    ))}
                </div>

                {visitas?.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2 justify-center text-sm">
                        {visitas.links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                className={`px-3 py-1 rounded-lg border ${link.active ? 'bg-[var(--color-primario)] text-white' : 'theme-surface'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </GeliaPageShell>

            {typeof document !== 'undefined' && modalRegistrar
                ? createPortal(modalRegistrar, document.body)
                : null}
        </AppLayout>
    );
}
