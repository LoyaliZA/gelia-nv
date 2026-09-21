import React, { useCallback, useEffect, useState } from 'react';
import { ImagePlus, Loader2, Trash2 } from 'lucide-react';
import axios from 'axios';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import useToastAlCambiar from '@/hooks/useToastAlCambiar';

const VACIO = {
    alcance: 'sucursal',
    ajuste: 'cover',
    orden: 0,
    duracion_seg: 8,
    vigente_desde: '',
    vigente_hasta: '',
};

export default function PlaylistPublicidadSalaPdv({ sucursalActiva = null }) {
    const sucursalId = sucursalActiva?.id ?? null;
    const [items, setItems] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState(null);
    const [form, setForm] = useState(VACIO);
    const [archivo, setArchivo] = useState(null);

    useToastAlCambiar(error, 'error');

    const cargar = useCallback(async () => {
        if (!sucursalId) return;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.get(route('punto_venta.pantalla_sala.publicidad.index'), {
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

    const enviar = async (event) => {
        event.preventDefault();
        if (!sucursalId || !archivo) {
            setError('Selecciona un archivo de imagen o video.');
            return;
        }

        const datos = new FormData();
        datos.append('sucursal_id', String(sucursalId));
        datos.append('alcance', form.alcance);
        datos.append('ajuste', form.ajuste);
        datos.append('orden', String(form.orden ?? 0));
        if (form.duracion_seg) datos.append('duracion_seg', String(form.duracion_seg));
        if (form.vigente_desde) datos.append('vigente_desde', form.vigente_desde);
        if (form.vigente_hasta) datos.append('vigente_hasta', form.vigente_hasta);
        datos.append('archivo', archivo);

        setGuardando(true);
        setError(null);
        try {
            const { data } = await axios.post(route('punto_venta.pantalla_sala.publicidad.store'), datos, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setItems(data.items ?? []);
            setArchivo(null);
            setForm(VACIO);
            event.target.reset?.();
        } catch (err) {
            const primerError = Object.values(err?.response?.data?.errors || {})[0]?.[0];
            setError(primerError || err?.response?.data?.message || 'No se pudo guardar la publicidad.');
        } finally {
            setGuardando(false);
        }
    };

    const cambiarActiva = async (item) => {
        const datos = new FormData();
        datos.append('sucursal_id', String(sucursalId));
        datos.append('alcance', item.alcance);
        datos.append('ajuste', item.ajuste);
        datos.append('orden', String(item.orden ?? 0));
        if (item.duracion_seg) datos.append('duracion_seg', String(item.duracion_seg));
        datos.append('activa', item.activa ? '0' : '1');

        try {
            const { data } = await axios.post(
                route('punto_venta.pantalla_sala.publicidad.update', item.id),
                datos,
            );
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo actualizar la publicidad.');
        }
    };

    const eliminar = async (item) => {
        try {
            const { data } = await axios.delete(
                route('punto_venta.pantalla_sala.publicidad.destroy', item.id),
                { params: { sucursal_id: sucursalId } },
            );
            setItems(data.items ?? []);
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo eliminar la publicidad.');
        }
    };

    return (
        <section className={geliaCardClass('p-6 md:p-8 space-y-6')} data-pdv-sala-publicidad-admin>
            <div>
                <h2 className="text-xl font-black theme-text-main m-0">Publicidad de sala</h2>
                <p className="mt-1 text-sm theme-text-muted m-0">
                    Imágenes y videos para la TV. Lo global se muestra en todas las sucursales; lo exclusivo solo en la sucursal activa.
                </p>
            </div>

            {!sucursalId ? (
                <p className="text-sm theme-text-muted">Selecciona una sucursal activa para gestionar la playlist.</p>
            ) : (
                <>
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={enviar}>
                        <label className="block md:col-span-2">
                            <span className={THEME_LABEL}>Archivo</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg"
                                className={THEME_INPUT}
                                onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
                                required
                            />
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Alcance</span>
                            <select
                                className={THEME_INPUT}
                                value={form.alcance}
                                onChange={(e) => setForm((prev) => ({ ...prev, alcance: e.target.value }))}
                            >
                                <option value="sucursal">Solo esta sucursal</option>
                                <option value="global">Todas las sucursales</option>
                            </select>
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Ajuste</span>
                            <select
                                className={THEME_INPUT}
                                value={form.ajuste}
                                onChange={(e) => setForm((prev) => ({ ...prev, ajuste: e.target.value }))}
                            >
                                <option value="cover">Cubrir</option>
                                <option value="contain">Contener</option>
                            </select>
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Duración (segundos)</span>
                            <input
                                type="number"
                                min="3"
                                max="300"
                                className={THEME_INPUT}
                                value={form.duracion_seg}
                                onChange={(e) => setForm((prev) => ({ ...prev, duracion_seg: e.target.value }))}
                            />
                            <span className="mt-1 block text-xs theme-text-muted">
                                En videos puede dejarse vacía para usar la duración del archivo.
                            </span>
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Orden</span>
                            <input
                                type="number"
                                min="0"
                                className={THEME_INPUT}
                                value={form.orden}
                                onChange={(e) => setForm((prev) => ({ ...prev, orden: e.target.value }))}
                            />
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Vigente desde</span>
                            <input
                                type="datetime-local"
                                className={THEME_INPUT}
                                value={form.vigente_desde}
                                onChange={(e) => setForm((prev) => ({ ...prev, vigente_desde: e.target.value }))}
                            />
                        </label>
                        <label className="block">
                            <span className={THEME_LABEL}>Vigente hasta</span>
                            <input
                                type="datetime-local"
                                className={THEME_INPUT}
                                value={form.vigente_hasta}
                                onChange={(e) => setForm((prev) => ({ ...prev, vigente_hasta: e.target.value }))}
                            />
                        </label>
                        <div className="md:col-span-2">
                            <button type="submit" className={THEME_BTN_PRIMARY} disabled={guardando}>
                                {guardando ? <Loader2 className="h-4 w-4 animate-spin" /> : <ImagePlus className="h-4 w-4" />}
                                Agregar a la playlist
                            </button>
                        </div>
                    </form>

                    {cargando ? (
                        <p className="text-sm theme-text-muted">Cargando playlist…</p>
                    ) : (
                        <ul className="space-y-3 m-0 p-0 list-none">
                            {items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center gap-4 rounded-2xl border theme-border theme-element p-3"
                                >
                                    {item.tipo === 'video' ? (
                                        <video src={item.url} className="h-16 w-24 rounded-xl object-cover" muted />
                                    ) : (
                                        <img src={item.url} alt="" className="h-16 w-24 rounded-xl object-cover" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="font-bold theme-text-main m-0">{item.nombre_original || `Pieza ${item.id}`}</p>
                                        <p className="text-xs theme-text-muted m-0">
                                            {item.alcance === 'global' ? 'Todas las sucursales' : 'Solo esta sucursal'}
                                            {' · '}
                                            {item.tipo}
                                            {item.duracion_seg ? ` · ${item.duracion_seg}s` : ''}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className={THEME_BTN_SECONDARY}
                                        onClick={() => cambiarActiva(item)}
                                    >
                                        {item.activa ? 'Desactivar' : 'Activar'}
                                    </button>
                                    <button
                                        type="button"
                                        className={THEME_BTN_SECONDARY}
                                        onClick={() => eliminar(item)}
                                        aria-label="Eliminar publicidad"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                            {!items.length ? (
                                <li className="text-sm theme-text-muted">Aún no hay piezas. Se mostrará la pantalla institucional.</li>
                            ) : null}
                        </ul>
                    )}
                </>
            )}
        </section>
    );
}
