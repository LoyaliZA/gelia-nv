import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { ImagePlus, Pause, Play, RotateCcw } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';
import { formatearDuracionSeg, formatearTamanoBytes } from '@/utils/estadoPublicidadPdv';
import { reportarExitoOperacion, reportarMensajeOperacion } from '@/utils/geliaToast';
import { subirMedioDirecto } from '@/utils/medios/mediaUploader';
import useModalPublicidad from '../useModalPublicidad';
import FormularioPublicidad from './FormularioPublicidad';

const ACCEPT = 'image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';
const ESPERA_MEDIO_MS = 2000;
const ESPERA_MEDIO_TOPE_MS = 15 * 60 * 1000;

function esperar(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

async function esperarMedioListo(mediaId, estadoInicial) {
    if (estadoInicial === 'ready') return;
    const inicio = Date.now();
    while (Date.now() - inicio < ESPERA_MEDIO_TOPE_MS) {
        const { data } = await axios.get(route('medios.estado', mediaId));
        if (data.estado === 'ready') return;
        if (data.estado === 'failed') throw new Error('No se pudo registrar el archivo.');
        await esperar(ESPERA_MEDIO_MS);
    }
    throw new Error('El archivo sigue registrándose. Intenta de nuevo en unos minutos.');
}

function etiquetaCarga(estado, pausado) {
    if (estado === 'subiendo' && pausado) return 'Pausado';
    if (estado === 'preparando') return 'Preparando';
    if (estado === 'subiendo') return 'Subiendo';
    if (estado === 'registrando') return 'Registrando';
    if (estado === 'completado') return 'Completado';
    if (estado === 'error') return 'Error';
    return estado;
}

async function duracionDeArchivo(file) {
    if (!file.type.startsWith('video/')) return null;
    const url = URL.createObjectURL(file);
    try {
        const segundos = await new Promise((resolve) => {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = () => resolve(Math.round(video.duration));
            video.onerror = () => resolve(null);
            video.src = url;
        });
        return Number.isFinite(segundos) ? segundos : null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

const FORMULARIO_INICIAL = {
    alcance: 'sucursal',
    ajuste: 'cover',
    publicacion: 'ahora',
    duracion_seg: 10,
    aplicar_duracion_todas: true,
    vigente_desde: '',
    vigente_hasta: '',
    eliminar_automaticamente: false,
    conservar_dias: 30,
};

export default function ModalAgregarPublicidad({ sucursalId, onClose, onItems }) {
    const ref = useRef(null);
    const pausedRef = useRef(false);
    const [pausado, setPausado] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [form, setForm] = useState(FORMULARIO_INICIAL);
    const [enviando, setEnviando] = useState(false);
    const archivosRef = useRef([]);
    archivosRef.current = archivos;
    useModalPublicidad(true, onClose, ref);

    useEffect(() => () => {
        archivosRef.current.forEach((archivo) => {
            if (archivo.previewUrl) URL.revokeObjectURL(archivo.previewUrl);
        });
    }, []);

    const agregarArchivos = async (lista) => {
        const nuevos = [];
        for (const file of Array.from(lista || []).filter(Boolean)) {
            const key = `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 7)}`;
            const esVideo = file.type.startsWith('video/');
            nuevos.push({
                key,
                file,
                nombre: file.name,
                tipo: esVideo ? 'video' : 'imagen',
                tamano: file.size,
                previewUrl: esVideo ? null : URL.createObjectURL(file),
                duracionSeg: await duracionDeArchivo(file),
                estado: 'preparando',
                pct: 0,
                partes: null,
                error: null,
            });
        }
        setArchivos((prev) => [...prev, ...nuevos]);
    };

    const actualizar = (key, patch) => {
        setArchivos((prev) => prev.map((archivo) => (archivo.key === key ? { ...archivo, ...patch } : archivo)));
    };

    const subirUno = async (archivo) => {
        actualizar(archivo.key, { estado: 'preparando', error: null, pct: 0 });
        try {
            const medio = await subirMedioDirecto({
                axios,
                file: archivo.file,
                proposito: 'pdv_publicidad',
                pausedRef,
                onProgress: (progreso) => {
                    actualizar(archivo.key, {
                        estado: 'subiendo',
                        pct: progreso.pct || 0,
                        partes: progreso.partes || null,
                    });
                },
            });
            actualizar(archivo.key, { estado: 'registrando', pct: 100, partes: null });
            await esperarMedioListo(medio.media_id, medio.estado);
            const duracionImagen = form.aplicar_duracion_todas
                ? Number(form.duracion_seg)
                : Number(archivo.duracionSeg || form.duracion_seg);
            const { data } = await axios.post(route('punto_venta.publicidad.store'), {
                sucursal_id: sucursalId,
                medio_id: medio.media_id,
                alcance: form.alcance,
                ajuste: form.ajuste,
                activa: form.publicacion !== 'deshabilitada',
                ...(archivo.tipo === 'imagen' ? { duracion_seg: duracionImagen } : {}),
                vigente_desde: form.publicacion === 'programar' ? (form.vigente_desde || null) : null,
                vigente_hasta: form.vigente_hasta || null,
                eliminar_automaticamente: Boolean(form.eliminar_automaticamente),
                conservar_dias: form.eliminar_automaticamente ? form.conservar_dias : null,
            });
            onItems(data.items ?? []);
            actualizar(archivo.key, { estado: 'completado', error: null });
            reportarExitoOperacion(`${archivo.nombre} se agregó a la playlist.`);
            return true;
        } catch (err) {
            const primerError = Object.values(err?.response?.data?.errors || {})[0]?.[0];
            const mensaje = primerError || err?.response?.data?.message || err?.message || `No se pudo cargar ${archivo.nombre}.`;
            actualizar(archivo.key, { estado: 'error', error: mensaje });
            reportarMensajeOperacion(mensaje);
            return false;
        }
    };

    const confirmar = async () => {
        const pendientes = archivos.filter((archivo) => archivo.estado !== 'completado');
        if (!pendientes.length) return;
        if (form.eliminar_automaticamente && !form.vigente_hasta) {
            reportarMensajeOperacion('La eliminación automática requiere una fecha de fin.');
            return;
        }
        setEnviando(true);
        try {
            for (const archivo of pendientes) {
                await subirUno(archivo);
            }
        } finally {
            setEnviando(false);
        }
    };

    const hayImagenes = archivos.some((archivo) => archivo.tipo === 'imagen');
    const hayVideos = archivos.some((archivo) => archivo.tipo === 'video');
    const tipoFormulario = hayImagenes && hayVideos ? 'mixto' : (hayVideos ? 'video' : 'imagen');
    const resumen = archivos.length
        ? `${archivos.length} archivo${archivos.length === 1 ? '' : 's'} · ${form.alcance === 'global' ? 'todas las sucursales' : 'sucursal actual'}`
        : 'Selecciona al menos un archivo.';

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={() => { if (!enviando) onClose(); }}>
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby="agregar-publicidad-titulo"
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full max-h-[90vh] overflow-y-auto p-4 md:p-6 space-y-4`}
                onClick={(event) => event.stopPropagation()}
            >
                <h2 id="agregar-publicidad-titulo" className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">Agregar publicidad</h2>
                <label
                    className="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border border-dashed theme-border p-4 text-center"
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                        event.preventDefault();
                        agregarArchivos(event.dataTransfer?.files);
                    }}
                >
                    <ImagePlus className="h-6 w-6 theme-text-primario" />
                    <span className="text-sm font-bold theme-text-main">Arrastra o selecciona imágenes y videos</span>
                    <span className="text-xs theme-text-muted">JPEG, PNG, WEBP, MP4, WEBM o MOV.</span>
                    <input
                        type="file"
                        accept={ACCEPT}
                        multiple
                        className="sr-only"
                        onChange={(event) => {
                            agregarArchivos(event.target.files);
                            event.target.value = '';
                        }}
                    />
                </label>
                {archivos.length ? (
                    <ul className="space-y-2 m-0 p-0 list-none">
                        {archivos.map((archivo) => (
                            <li key={archivo.key} className="flex gap-3 rounded-xl border theme-border p-2">
                                {archivo.previewUrl ? (
                                    <img src={archivo.previewUrl} alt="" className="h-14 w-24 rounded-lg object-cover" />
                                ) : (
                                    <div className="flex h-14 w-24 items-center justify-center rounded-lg bg-black text-[10px] font-bold text-white">Video</div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold theme-text-main m-0 truncate">{archivo.nombre}</p>
                                    <p className="text-xs theme-text-muted m-0">
                                        {archivo.tipo === 'video' ? 'Video' : 'Imagen'}
                                        {formatearTamanoBytes(archivo.tamano) ? ` · ${formatearTamanoBytes(archivo.tamano)}` : ''}
                                        {archivo.tipo === 'video' && formatearDuracionSeg(archivo.duracionSeg) ? ` · ${formatearDuracionSeg(archivo.duracionSeg)}` : ''}
                                    </p>
                                    <p className="text-xs theme-text-muted m-0">
                                        {etiquetaCarga(archivo.estado, pausado)}
                                        {archivo.estado === 'subiendo' && archivo.pct ? ` · ${archivo.pct}%` : ''}
                                        {archivo.estado === 'subiendo' && archivo.partes ? ` · ${archivo.partes}` : ''}
                                    </p>
                                    {archivo.estado === 'subiendo' ? (
                                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-black/10">
                                            <div className="h-full bg-emerald-600" style={{ width: `${archivo.pct || 0}%` }} />
                                        </div>
                                    ) : null}
                                    {archivo.error ? <p className="text-xs text-red-700 m-0">{archivo.error}</p> : null}
                                </div>
                                {archivo.estado === 'error' ? (
                                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => subirUno(archivo)} aria-label={`Reintentar ${archivo.nombre}`}>
                                        <RotateCcw className="h-4 w-4" />
                                    </button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                ) : null}
                {archivos.some((archivo) => archivo.estado === 'subiendo' || archivo.estado === 'registrando') ? (
                    <button
                        type="button"
                        className={THEME_BTN_SECONDARY}
                        onClick={() => {
                            pausedRef.current = !pausedRef.current;
                            setPausado(pausedRef.current);
                        }}
                    >
                        {pausado ? <Play className="h-4 w-4" /> : <Pause className="h-4 w-4" />}
                        {pausado ? 'Reanudar cargas' : 'Pausar cargas'}
                    </button>
                ) : null}
                <FormularioPublicidad
                    valor={form}
                    onChange={setForm}
                    tipo={tipoFormulario}
                    modo="alta"
                    duracionVideo={archivos.find((archivo) => archivo.tipo === 'video')?.duracionSeg}
                    mostrarAplicarDuracion={hayImagenes && archivos.filter((archivo) => archivo.tipo === 'imagen').length > 1}
                />
                <p className="text-sm theme-text-muted m-0">{resumen}</p>
                <div className="flex justify-end gap-2">
                    <button type="button" className={THEME_BTN_SECONDARY} disabled={enviando} onClick={onClose}>Cerrar</button>
                    <button type="button" className={THEME_BTN_PRIMARY} disabled={enviando || !archivos.some((archivo) => archivo.estado !== 'completado')} onClick={confirmar}>
                        {enviando ? 'Agregando…' : 'Agregar a la playlist'}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
