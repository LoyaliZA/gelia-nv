import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Images, ImagePlus, Loader2, Trash2, Pause, Play } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import useToastAlCambiar from '@/hooks/useToastAlCambiar';
import { reportarExitoOperacion, reportarMensajeOperacion } from '@/utils/geliaToast';
import { etiquetaEstadoPublicidad, formatearDuracionSeg, formatearTamanoBytes } from '@/utils/estadoPublicidadPdv';
import { subirMedioDirecto } from '@/utils/medios/mediaUploader';

const ACCEPT = 'image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';

function TarjetaPublicidad({
    item,
    puedeEditar,
    puedeEliminar,
    puedeOrdenar,
    onToggle,
    onEliminar,
    onGuardar,
    onDragStart,
    onDrop,
}) {
    const [editando, setEditando] = useState(false);
    const [form, setForm] = useState({
        alcance: item.alcance,
        ajuste: item.ajuste,
        duracion_seg: item.duracion_seg ?? 10,
        vigente_desde: item.vigente_desde || '',
        vigente_hasta: item.vigente_hasta || '',
    });

    return (
        <article
            draggable={puedeOrdenar}
            onDragStart={(e) => onDragStart(e, item.id)}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => onDrop(e, item.id)}
            className="rounded-2xl border theme-border theme-element p-3 space-y-3"
            data-pdv-publicidad-card
        >
            <div className="flex gap-3">
                {item.tipo === 'video' ? (
                    <div className="h-20 w-28 shrink-0 overflow-hidden rounded-xl theme-surface flex items-center justify-center text-xs font-bold theme-text-muted">
                        Video
                    </div>
                ) : (
                    <img src={item.url} alt="" className="h-20 w-28 shrink-0 rounded-xl object-cover" />
                )}
                <div className="min-w-0 flex-1">
                    <p className="font-bold theme-text-main m-0 truncate">{item.nombre_original || `Pieza ${item.id}`}</p>
                    <p className="text-xs theme-text-muted m-0">
                        {item.tipo === 'video' ? 'Video' : 'Imagen'}
                        {formatearDuracionSeg(item.duracion_seg) ? ` · ${formatearDuracionSeg(item.duracion_seg)}` : ''}
                        {formatearTamanoBytes(item.tamano_bytes) ? ` · ${formatearTamanoBytes(item.tamano_bytes)}` : ''}
                    </p>
                    <p className="text-xs theme-text-muted m-0 mt-1">
                        {item.alcance === 'global' ? 'Todas las sucursales' : 'Solo esta sucursal'}
                        {' · '}
                        {etiquetaEstadoPublicidad(item.estado)}
                    </p>
                    <p className="text-xs theme-text-muted m-0">
                        Inicio: {item.vigente_desde || 'inmediato'}
                        {' · '}
                        Fin: {item.vigente_hasta || 'Sin vigencia'}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                {puedeEditar ? (
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onToggle(item)}>
                        {item.activa ? 'Deshabilitar' : 'Activar'}
                    </button>
                ) : null}
                {puedeEditar ? (
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setEditando((v) => !v)}>
                        {editando ? 'Cerrar' : 'Programar'}
                    </button>
                ) : null}
                {puedeEliminar ? (
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onEliminar(item)} aria-label="Quitar de la playlist">
                        <Trash2 className="h-4 w-4" />
                    </button>
                ) : null}
            </div>
            {editando && puedeEditar ? (
                <div className="grid gap-3 md:grid-cols-2">
                    <label className="block">
                        <span className={THEME_LABEL}>Alcance</span>
                        <select className={THEME_INPUT} value={form.alcance} onChange={(e) => setForm((p) => ({ ...p, alcance: e.target.value }))}>
                            <option value="sucursal">Solo esta sucursal</option>
                            <option value="global">Todas las sucursales</option>
                        </select>
                    </label>
                    <label className="block">
                        <span className={THEME_LABEL}>Ajuste</span>
                        <select className={THEME_INPUT} value={form.ajuste} onChange={(e) => setForm((p) => ({ ...p, ajuste: e.target.value }))}>
                            <option value="cover">Cubrir</option>
                            <option value="contain">Contener</option>
                        </select>
                    </label>
                    {item.tipo === 'imagen' ? (
                        <label className="block">
                            <span className={THEME_LABEL}>Duración (segundos)</span>
                            <input
                                type="number"
                                min="3"
                                max="300"
                                className={THEME_INPUT}
                                value={form.duracion_seg}
                                onChange={(e) => setForm((p) => ({ ...p, duracion_seg: e.target.value }))}
                            />
                        </label>
                    ) : null}
                    <label className="block">
                        <span className={THEME_LABEL}>Inicio</span>
                        <input
                            type="datetime-local"
                            className={THEME_INPUT}
                            value={form.vigente_desde}
                            onChange={(e) => setForm((p) => ({ ...p, vigente_desde: e.target.value }))}
                        />
                    </label>
                    <label className="block">
                        <span className={THEME_LABEL}>Fin / vigencia</span>
                        <input
                            type="datetime-local"
                            className={THEME_INPUT}
                            value={form.vigente_hasta}
                            onChange={(e) => setForm((p) => ({ ...p, vigente_hasta: e.target.value }))}
                        />
                    </label>
                    <div className="md:col-span-2">
                        <button type="button" className={THEME_BTN_PRIMARY} onClick={() => onGuardar(item, form)}>
                            Guardar programación
                        </button>
                    </div>
                </div>
            ) : null}
        </article>
    );
}

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
    const [progresos, setProgresos] = useState([]);
    const [alcanceNuevo, setAlcanceNuevo] = useState('sucursal');
    const [pausado, setPausado] = useState(false);
    const pausedRef = useRef(false);
    const dragId = useRef(null);

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
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo cargar la publicidad.');
        } finally {
            setCargando(false);
        }
    }, [sucursalId]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const subirArchivos = async (files) => {
        if (!sucursalId || !permisos.crear) return;
        const lista = Array.from(files || []).filter(Boolean);
        for (const file of lista) {
            const key = `${file.name}-${file.lastModified}`;
            setProgresos((prev) => [...prev.filter((p) => p.key !== key), { key, nombre: file.name, estado: 'preparando', pct: 0 }]);
            try {
                const medio = await subirMedioDirecto({
                    axios,
                    file,
                    proposito: 'pdv_publicidad',
                    pausedRef,
                    onProgress: (p) => {
                        setProgresos((prev) => prev.map((row) => (row.key === key ? { ...row, ...p, nombre: file.name } : row)));
                    },
                });
                const { data } = await axios.post(route('punto_venta.publicidad.store'), {
                    sucursal_id: sucursalId,
                    medio_id: medio.media_id,
                    alcance: alcanceNuevo,
                    ajuste: 'cover',
                    duracion_seg: medio.tipo === 'imagen' ? 10 : medio.duracion_seg,
                });
                setItems(data.items ?? []);
                reportarExitoOperacion(`${file.name} se agregó a la playlist.`);
            } catch (err) {
                const primerError = Object.values(err?.response?.data?.errors || {})[0]?.[0];
                reportarMensajeOperacion(primerError || err?.response?.data?.message || `No se pudo cargar ${file.name}.`);
            } finally {
                setProgresos((prev) => prev.filter((p) => p.key !== key));
            }
        }
    };

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

    const guardar = async (item, form) => {
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.update', item.id), {
                sucursal_id: sucursalId,
                alcance: form.alcance,
                ajuste: form.ajuste,
                duracion_seg: item.tipo === 'imagen' ? form.duracion_seg : undefined,
                vigente_desde: form.vigente_desde || null,
                vigente_hasta: form.vigente_hasta || null,
            });
            setItems(data.items ?? []);
            reportarExitoOperacion('Programación guardada.');
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo guardar la programación.');
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
        setItems(siguiente);
        if (!permisos.ordenar) return;
        try {
            const { data } = await axios.patch(route('punto_venta.publicidad.ordenar'), {
                sucursal_id: sucursalId,
                ids: siguiente.map((i) => i.id),
            });
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo guardar el orden.');
            cargar();
        }
    };

    const onDropCard = (event, targetId) => {
        event.preventDefault();
        const origen = dragId.current;
        if (!origen || origen === targetId) return;
        const actual = [...items];
        const from = actual.findIndex((i) => i.id === origen);
        const to = actual.findIndex((i) => i.id === targetId);
        if (from < 0 || to < 0) return;
        const [movido] = actual.splice(from, 1);
        actual.splice(to, 0, movido);
        persistirOrden(actual);
    };

    const onDropZone = (event) => {
        event.preventDefault();
        subirArchivos(event.dataTransfer?.files);
    };

    const togglePausa = () => {
        pausedRef.current = !pausedRef.current;
        setPausado(pausedRef.current);
    };

    const acepta = useMemo(() => ACCEPT, []);

    return (
        <AppLayout auth={auth}>
            <Head title="Publicidad | Punto de venta" />
            <GeliaPageShell className="space-y-6">
                <GeliaTituloCard
                    title="Publicidad de sala"
                    description="Biblioteca de imágenes y videos para la TV de turnos. El orden de las tarjetas es el orden de reproducción."
                    icon={Images}
                >
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compacto"
                    />
                </GeliaTituloCard>

                <section className={geliaCardClass('p-6 md:p-8 space-y-6')} data-pdv-publicidad-admin>
                    {!sucursalId ? (
                        <p className="text-sm theme-text-muted m-0">Selecciona una sucursal activa para gestionar la playlist.</p>
                    ) : (
                        <>
                            {permisos.crear ? (
                                <div className="space-y-3">
                                    <label className="block max-w-sm">
                                        <span className={THEME_LABEL}>Alcance de nuevas piezas</span>
                                        <select className={THEME_INPUT} value={alcanceNuevo} onChange={(e) => setAlcanceNuevo(e.target.value)}>
                                            <option value="sucursal">Solo esta sucursal</option>
                                            <option value="global">Todas las sucursales</option>
                                        </select>
                                    </label>
                                    <label
                                        className="flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed theme-border p-6 text-center"
                                        onDragOver={(e) => e.preventDefault()}
                                        onDrop={onDropZone}
                                    >
                                        <ImagePlus className="h-8 w-8 theme-text-primario" />
                                        <span className="font-bold theme-text-main">Arrastra imágenes o videos</span>
                                        <span className="text-sm theme-text-muted">JPEG, PNG, WEBP, MP4, WEBM o MOV. La carga va directo al almacén, sin pasar por el servidor de la app.</span>
                                        <input
                                            type="file"
                                            accept={acepta}
                                            multiple
                                            className="sr-only"
                                            onChange={(e) => {
                                                subirArchivos(e.target.files);
                                                e.target.value = '';
                                            }}
                                        />
                                    </label>
                                    {progresos.length ? (
                                        <div className="space-y-2">
                                            <button type="button" className={THEME_BTN_SECONDARY} onClick={togglePausa}>
                                                {pausado ? <Play className="h-4 w-4" /> : <Pause className="h-4 w-4" />}
                                                {pausado ? 'Reanudar cargas' : 'Pausar cargas'}
                                            </button>
                                            {progresos.map((p) => (
                                                <p key={p.key} className="text-sm theme-text-muted m-0">
                                                    {p.nombre}: {p.estado} {p.pct ? `· ${p.pct}%` : ''} {p.partes ? `· ${p.partes}` : ''}
                                                </p>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            {cargando ? (
                                <p className="text-sm theme-text-muted inline-flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" /> Cargando playlist…
                                </p>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {items.map((item) => (
                                        <TarjetaPublicidad
                                            key={item.id}
                                            item={item}
                                            puedeEditar={permisos.editar}
                                            puedeEliminar={permisos.eliminar}
                                            puedeOrdenar={permisos.ordenar}
                                            onToggle={cambiarActiva}
                                            onEliminar={eliminar}
                                            onGuardar={guardar}
                                            onDragStart={(e, id) => {
                                                dragId.current = id;
                                                e.dataTransfer.effectAllowed = 'move';
                                            }}
                                            onDrop={onDropCard}
                                        />
                                    ))}
                                    {!items.length ? (
                                        <p className="text-sm theme-text-muted md:col-span-2">Aún no hay piezas. La TV mostrará la pantalla institucional.</p>
                                    ) : null}
                                </div>
                            )}
                        </>
                    )}
                </section>
            </GeliaPageShell>
        </AppLayout>
    );
}
