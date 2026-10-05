import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Images, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT } from '@/utils/geliaTheme';
import useToastAlCambiar from '@/hooks/useToastAlCambiar';
import { reportarExitoOperacion } from '@/utils/geliaToast';
import { contarEstados, duracionVueltaSegundos, formatearDuracionSeg } from '@/utils/estadoPublicidadPdv';
import PlaylistPublicidad from './Partials/PlaylistPublicidad';
import ModalAgregarPublicidad from './Partials/ModalAgregarPublicidad';
import ModalEditarPublicidad from './Partials/ModalEditarPublicidad';
import VistaPreviaPublicidad from './Partials/VistaPreviaPublicidad';

const FILTROS = [
    ['todas', 'Todas'],
    ['activa', 'Activas'],
    ['programada', 'Programadas'],
    ['deshabilitada', 'Deshabilitadas'],
    ['expirada', 'Expiradas'],
];

export default function Index({
    auth,
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    permisos = {},
}) {
    const sucursalId = sucursalActiva?.id ?? null;
    const [items, setItems] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [filtro, setFiltro] = useState('todas');
    const [busqueda, setBusqueda] = useState('');
    const [guardandoOrden, setGuardandoOrden] = useState(false);
    const [agregarAbierto, setAgregarAbierto] = useState(false);
    const [editando, setEditando] = useState(null);
    const [vistaPrevia, setVistaPrevia] = useState(null);
    const [volumen, setVolumen] = useState(35);
    const [guardandoVolumen, setGuardandoVolumen] = useState(false);

    useToastAlCambiar(error, 'error');

    const cargar = useCallback(async () => {
        if (!sucursalId) return;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.get(route('punto_venta.publicidad.items'), {
                params: { sucursal_id: sucursalId },
            });
            setItems(data.items ?? []);
            if (Number.isFinite(Number(data.volumen))) {
                setVolumen(Number(data.volumen));
            }
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo cargar la publicidad.');
        } finally {
            setCargando(false);
        }
    }, [sucursalId]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const cuentas = useMemo(() => contarEstados(items), [items]);
    const busquedaNormalizada = busqueda.trim().toLowerCase();
    const visibles = useMemo(() => items.filter((item) => {
        if (filtro !== 'todas' && item.estado !== filtro) return false;
        if (!busquedaNormalizada) return true;
        return String(item.nombre_original || '').toLowerCase().includes(busquedaNormalizada);
    }), [items, filtro, busquedaNormalizada]);
    const posiciones = useMemo(() => new Map(items.map((item, index) => [item.id, index + 1])), [items]);
    const puedeOrdenar = Boolean(permisos.ordenar) && filtro === 'todas' && !busquedaNormalizada;
    const enReproduccion = items.filter((item) => item.estado === 'activa');

    const cambiarActiva = async (item) => {
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.update', item.id), {
                sucursal_id: sucursalId,
                activa: !item.activa,
            });
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo actualizar la publicidad.');
        }
    };

    const guardarDuracion = async (item, duracionSeg) => {
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.update', item.id), {
                sucursal_id: sucursalId,
                duracion_seg: duracionSeg,
            });
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo guardar la duración.');
        }
    };

    const guardar = async (item, form) => {
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.update', item.id), {
                sucursal_id: sucursalId,
                alcance: form.alcance,
                ajuste: form.ajuste,
                duracion_seg: item.tipo === 'imagen' ? form.duracion_seg : undefined,
                vigente_desde: form.vigente_desde || null,
                vigente_hasta: form.vigente_hasta || null,
                eliminar_automaticamente: Boolean(form.eliminar_automaticamente),
                conservar_dias: form.eliminar_automaticamente ? form.conservar_dias : null,
            });
            setItems(data.items ?? []);
            reportarExitoOperacion('Programación guardada.');
        } catch (err) {
            const primerError = Object.values(err?.response?.data?.errors || {})[0]?.[0];
            setError(primerError || err?.response?.data?.message || 'No se pudo guardar la programación.');
            throw err;
        }
    };

    const eliminar = async (item) => {
        try {
            const { data } = await axios.delete(route('punto_venta.publicidad.destroy', item.id), {
                params: { sucursal_id: sucursalId },
            });
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo quitar la publicidad.');
        }
    };

    const persistirOrden = async (siguiente) => {
        const anterior = items;
        setItems(siguiente);
        if (!permisos.ordenar) return;
        setGuardandoOrden(true);
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.ordenar'), {
                sucursal_id: sucursalId,
                ids: siguiente.map((item) => item.id),
            });
            setItems(data.items ?? []);
            reportarExitoOperacion('Orden actualizado');
        } catch (err) {
            setItems(anterior);
            setError(err?.response?.data?.message || 'No se pudo guardar el orden.');
        } finally {
            setGuardandoOrden(false);
        }
    };

    const guardarVolumen = async () => {
        if (!sucursalId || !permisos.editar) return;
        setGuardandoVolumen(true);
        setError(null);
        try {
            const { data } = await axios.put(route('punto_venta.publicidad.volumen'), {
                sucursal_id: sucursalId,
                volumen: Number(volumen),
            });
            if (Number.isFinite(Number(data.volumen))) {
                setVolumen(Number(data.volumen));
            }
            reportarExitoOperacion('Volumen de la pantalla de turnos actualizado');
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo guardar el volumen.');
        } finally {
            setGuardandoVolumen(false);
        }
    };

    const cerrarAgregar = useCallback(() => setAgregarAbierto(false), []);
    const cerrarEdicion = useCallback(() => setEditando(null), []);
    const cerrarVista = useCallback(() => setVistaPrevia(null), []);

    return (
        <AppLayout auth={auth}>
            <Head title="Publicidad | Punto de venta" />
            <GeliaPageShell className="space-y-4">
                <GeliaTituloCard
                    title="Publicidad de sala"
                    description="Playlist de la TV de turnos. El orden de arriba hacia abajo es el de reproducción."
                    icon={Images}
                >
                    {sucursalId ? (
                        <div className="flex flex-wrap items-center gap-3 min-w-[16rem]">
                            <label className="flex items-center gap-2 text-xs font-bold theme-text-main" htmlFor="volumen-publicidad-sala">
                                Volumen videos
                                <span className="tabular-nums">{volumen}%</span>
                            </label>
                            <input
                                id="volumen-publicidad-sala"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                value={volumen}
                                disabled={!permisos.editar || guardandoVolumen}
                                onChange={(event) => setVolumen(Number(event.target.value))}
                                className="w-36"
                            />
                            {permisos.editar ? (
                                <button
                                    type="button"
                                    className={THEME_BTN_SECONDARY}
                                    disabled={guardandoVolumen}
                                    onClick={guardarVolumen}
                                >
                                    Guardar volumen
                                </button>
                            ) : null}
                        </div>
                    ) : null}
                    <div className="flex flex-wrap items-center gap-2">
                        <SelectorSucursalActivaPdv
                            sucursalActiva={sucursalActiva}
                            sucursalesAsignadas={sucursalesAsignadas}
                            variante="compacto"
                        />
                        {permisos.crear && sucursalId ? (
                            <button type="button" className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2`} onClick={() => setAgregarAbierto(true)}>
                                <Plus className="h-4 w-4" /> Agregar publicidad
                            </button>
                        ) : null}
                        <button
                            type="button"
                            className={THEME_BTN_SECONDARY}
                            disabled={!enReproduccion.length}
                            onClick={() => setVistaPrevia({ items: enReproduccion, indice: 0 })}
                        >
                            Vista previa de playlist
                        </button>
                    </div>
                </GeliaTituloCard>

                <section className={geliaCardClass('p-4 md:p-5 space-y-4')} data-pdv-publicidad-admin>
                    {!sucursalId ? (
                        <p className="text-sm theme-text-muted m-0">Selecciona una sucursal activa para gestionar la playlist.</p>
                    ) : (
                        <>
                            <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <Resumen etiqueta="Piezas" valor={cuentas.todas} />
                                <Resumen etiqueta="Activas ahora" valor={cuentas.activa} />
                                <Resumen etiqueta="Programadas" valor={cuentas.programada} />
                                <Resumen etiqueta="Vuelta actual" valor={formatearDuracionSeg(duracionVueltaSegundos(items)) || '0s'} />
                            </div>
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Filtrar por estado">
                                    {FILTROS.map(([id, etiqueta]) => (
                                        <button
                                            key={id}
                                            type="button"
                                            role="radio"
                                            aria-checked={filtro === id}
                                            className={`rounded-full px-3 py-1 text-xs font-bold border theme-border ${filtro === id ? 'theme-btn-primary' : 'theme-element theme-text-main'}`}
                                            onClick={() => setFiltro(id)}
                                        >
                                            {etiqueta} {cuentas[id] ?? 0}
                                        </button>
                                    ))}
                                </div>
                                <input
                                    className={`${THEME_INPUT} max-w-xs`}
                                    placeholder="Buscar por nombre"
                                    value={busqueda}
                                    onChange={(event) => setBusqueda(event.target.value)}
                                    aria-label="Buscar por nombre de archivo"
                                />
                            </div>
                            <p className="text-xs theme-text-muted m-0">
                                {puedeOrdenar
                                    ? 'Arrastra las cards para cambiar el orden de reproducción.'
                                    : 'Quita el filtro y la búsqueda para reordenar la playlist.'}
                            </p>
                            {!permisos.editar && !permisos.crear ? (
                                <p className="text-xs theme-text-muted m-0">Solo puedes consultar esta playlist.</p>
                            ) : null}
                            <PlaylistPublicidad
                                items={visibles}
                                posiciones={posiciones}
                                cargando={cargando}
                                puedeOrdenar={puedeOrdenar}
                                puedeEditar={Boolean(permisos.editar)}
                                puedeEliminar={Boolean(permisos.eliminar)}
                                guardandoOrden={guardandoOrden}
                                onReordenar={persistirOrden}
                                onToggle={cambiarActiva}
                                onDuracion={guardarDuracion}
                                onEditar={setEditando}
                                onPrevisualizar={(item) => setVistaPrevia({ items: [item], indice: 0 })}
                                onEliminar={eliminar}
                                mensajeVacio={items.length ? 'Ninguna pieza coincide con este filtro.' : 'Aún no hay piezas. La TV mostrará la pantalla institucional.'}
                            />
                        </>
                    )}
                </section>
            </GeliaPageShell>
            {agregarAbierto && sucursalId ? (
                <ModalAgregarPublicidad sucursalId={sucursalId} onClose={cerrarAgregar} onItems={setItems} />
            ) : null}
            {editando ? (
                <ModalEditarPublicidad item={editando} onClose={cerrarEdicion} onGuardar={guardar} />
            ) : null}
            {vistaPrevia ? (
                <VistaPreviaPublicidad items={vistaPrevia.items} indiceInicial={vistaPrevia.indice} onClose={cerrarVista} />
            ) : null}
        </AppLayout>
    );
}

function Resumen({ etiqueta, valor }) {
    return (
        <div className="rounded-xl border theme-border px-3 py-2">
            <p className="text-[11px] font-bold uppercase tracking-wide theme-text-muted m-0">{etiqueta}</p>
            <p className="text-lg font-black theme-text-main m-0">{valor}</p>
        </div>
    );
}
