import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { geliaCardClass } from '../../../utils/geliaTheme';
import PreciosNav from './Partials/PreciosNav';
import FiltrosHistorial from './Partials/FiltrosHistorial';
import TablaHistorial from './Partials/TablaHistorial';
import DetalleOperacion from './Partials/DetalleOperacion';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const LOTE_KEY = 'tn_precio_lote_id';

const FILTROS_VACIOS = {
    q: '',
    desde: '',
    hasta: '',
    canal: '',
    resultado: '',
    variante_id: '',
    page: 1,
    per_page: 25,
};

export default function Historial({
    auth,
    configuracion,
    listado: listadoInicial,
    filtros: filtrosIniciales = {},
    permisos,
}) {
    const [filtros, setFiltros] = useState({ ...FILTROS_VACIOS, ...filtrosIniciales });
    const [listado, setListado] = useState(listadoInicial);
    const [detalle, setDetalle] = useState(null);
    const [preview, setPreview] = useState(null);
    const [seleccionRestauracion, setSeleccionRestauracion] = useState({});
    const [busy, setBusy] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const skip = useRef(true);

    const jsonHeaders = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() };

    const queryString = useCallback((next) => {
        const params = new URLSearchParams();
        Object.entries(next).forEach(([k, v]) => {
            if (v === '' || v === null || v === undefined) return;
            params.set(k, String(v));
        });
        return params.toString();
    }, []);

    const cargar = useCallback(async (next) => {
        const qs = queryString(next);
        const res = await fetch(`${route('tiendanube.precios.historial.operaciones')}?${qs}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('No se pudo cargar el historial.');
        return res.json();
    }, [queryString]);

    useEffect(() => {
        if (skip.current) {
            skip.current = false;
            return;
        }
        const t = setTimeout(async () => {
            try {
                const data = await cargar(filtros);
                setListado(data);
                router.get(route('tiendanube.precios.historial.index'), filtros, { preserveState: true, replace: true, preserveScroll: true });
            } catch (e) {
                setMensaje(e.message);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [filtros, cargar]);

    const abrir = async (loteId) => {
        setBusy(true);
        setMensaje(null);
        setPreview(null);
        try {
            const res = await fetch(route('tiendanube.precios.historial.operaciones.show', loteId), { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo abrir la operación.');
            setDetalle(data);
            setFiltros((prev) => ({ ...prev, operacion: loteId }));
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const cerrar = () => {
        setDetalle(null);
        setPreview(null);
        setFiltros((prev) => {
            const next = { ...prev };
            delete next.operacion;
            return next;
        });
    };

    const conciliar = async () => {
        if (!detalle?.operacion?.lote_id) return;
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.historial.conciliar', detalle.operacion.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({ evidencia_tipo: 'lectura_api' }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo conciliar.');
            setMensaje('Conciliación registrada. No se escribieron precios remotos.');
            await abrir(detalle.operacion.lote_id);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const preparar = async () => {
        if (!detalle?.operacion?.lote_id) return;
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.historial.restaurar.preparar', detalle.operacion.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({}),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo preparar la restauración.');
            setPreview(data);
            const sel = {};
            (data.filas || []).forEach((fila) => {
                (fila.campos_elegidos || []).forEach((campo) => {
                    sel[`${fila.variante_id}:${campo}`] = true;
                });
            });
            setSeleccionRestauracion(sel);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const confirmar = async () => {
        if (!detalle?.operacion?.lote_id) return;
        const agrupado = {};
        Object.entries(seleccionRestauracion).forEach(([clave, on]) => {
            if (!on) return;
            const [vid, campo] = clave.split(':');
            if (!agrupado[vid]) agrupado[vid] = [];
            agrupado[vid].push(campo);
        });
        const filas = Object.entries(agrupado).map(([variante_id, campos]) => ({ variante_id: Number(variante_id), campos }));
        setBusy(true);
        try {
            const res = await fetch(route('tiendanube.precios.historial.restaurar', detalle.operacion.lote_id), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({
                    filas,
                    aceptar_conflicto: !!preview?.tiene_conflictos,
                    motivo: 'Restauración desde historial',
                }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo crear la compensación.');
            if (data.lote?.lote_id) {
                sessionStorage.setItem(LOTE_KEY, data.lote.lote_id);
                router.visit(route('tiendanube.precios.index', { lote_id: data.lote.lote_id }));
            }
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    useEffect(() => {
        if (filtrosIniciales.operacion) {
            abrir(filtrosIniciales.operacion);
        } else if (filtrosIniciales.variante_id) {
            setFiltros((prev) => ({ ...prev, variante_id: filtrosIniciales.variante_id }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const meta = listado?.meta;

    return (
        <AppLayout auth={auth}>
            <Head title="Historial de precios" />
            <div className="max-w-[1440px] mx-auto p-3 md:p-4 space-y-3">
                <header className={`${geliaCardClass()} p-3 md:p-4`}>
                    <PreciosNav activa="historial" />
                    <h1 className="text-xl font-black italic uppercase theme-text-main m-0">Historial</h1>
                    <p className="text-xs theme-text-muted m-0">
                        {configuracion?.store_name || 'Tienda'}
                        {configuracion?.store_id ? ` · ${configuracion.store_id}` : ''}
                    </p>
                </header>
                {mensaje && <p className="text-sm">{mensaje}</p>}
                <section className={`${geliaCardClass()} p-3 space-y-3`}>
                    <FiltrosHistorial
                        filtros={filtros}
                        onChange={(patch) => setFiltros((prev) => ({ ...prev, ...patch }))}
                        onLimpiar={() => setFiltros({ ...FILTROS_VACIOS, variante_id: filtros.variante_id || '' })}
                    />
                    <TablaHistorial
                        filas={listado?.data || []}
                        vacio={!!listado?.vacio}
                        sinResultados={!!listado?.sin_resultados}
                        onAbrir={abrir}
                    />
                    {meta && meta.last_page > 1 && (
                        <div className="flex items-center justify-between text-sm">
                            <button
                                type="button"
                                disabled={meta.current_page <= 1}
                                onClick={() => setFiltros((p) => ({ ...p, page: meta.current_page - 1 }))}
                                className="px-3 py-1 rounded-lg border theme-border disabled:opacity-40"
                            >
                                Anterior
                            </button>
                            <span className="theme-text-muted">Página {meta.current_page} de {meta.last_page}</span>
                            <button
                                type="button"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => setFiltros((p) => ({ ...p, page: meta.current_page + 1 }))}
                                className="px-3 py-1 rounded-lg border theme-border disabled:opacity-40"
                            >
                                Siguiente
                            </button>
                        </div>
                    )}
                </section>
            </div>
            {detalle && (
                <DetalleOperacion
                    detalle={detalle}
                    onCerrar={cerrar}
                    onConciliar={conciliar}
                    onPreparar={preparar}
                    onConfirmarRestauracion={confirmar}
                    preview={preview}
                    seleccionRestauracion={seleccionRestauracion}
                    onToggleCampo={(vid, campo) => setSeleccionRestauracion((prev) => ({
                        ...prev,
                        [`${vid}:${campo}`]: !prev[`${vid}:${campo}`],
                    }))}
                    busy={busy}
                    puedeRestaurar={!!permisos?.restaurar}
                    diagnostico={!!permisos?.configurar}
                />
            )}
        </AppLayout>
    );
}
