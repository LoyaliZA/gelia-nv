import React, { useEffect, useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Eye, GripVertical, Minus, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { THEME_BTN_SECONDARY } from '@/utils/geliaTheme';
import { formatearDuracionSeg, formatearTamanoBytes, textoEliminacion, textoProgramacion } from '@/utils/estadoPublicidadPdv';
import BadgeEstadoPublicidad, { BadgeAlcancePublicidad } from './BadgeEstadoPublicidad';

export default function TarjetaPublicidad({
    item,
    posicion,
    puedeEditar,
    puedeEliminar,
    puedeOrdenar,
    onToggle,
    onDuracion,
    onVolumen,
    onEditar,
    onPrevisualizar,
    onEliminar,
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: item.id,
        disabled: !puedeOrdenar,
    });
    const [duracion, setDuracion] = useState(item.duracion_seg ?? 10);
    const [volumenPieza, setVolumenPieza] = useState(item.volumen_pct ?? 100);
    const [menu, setMenu] = useState(false);
    const [confirmar, setConfirmar] = useState(false);
    useEffect(() => {
        setDuracion(item.duracion_seg ?? 10);
    }, [item.duracion_seg]);
    useEffect(() => {
        setVolumenPieza(item.volumen_pct ?? 100);
    }, [item.volumen_pct]);

    const guardarDuracion = (siguiente) => {
        const valor = Math.min(300, Math.max(3, Number(siguiente) || 3));
        setDuracion(valor);
        if (valor !== Number(item.duracion_seg)) onDuracion(item, valor);
    };

    const guardarVolumenPieza = () => {
        const valor = Math.min(100, Math.max(0, Number(volumenPieza) || 0));
        setVolumenPieza(valor);
        if (valor !== Number(item.volumen_pct ?? 100) && typeof onVolumen === 'function') {
            onVolumen(item, valor);
        }
    };

    const estilo = {
        transform: CSS.Transform.toString(transform),
        transition,
    };
    const atenuada = item.estado === 'deshabilitada' || item.estado === 'expirada';

    return (
        <article
            ref={setNodeRef}
            style={estilo}
            data-pdv-publicidad-card
            className={`flex flex-col gap-3 rounded-2xl border theme-border theme-element p-3 md:flex-row md:items-center ${isDragging ? 'relative z-10 scale-[1.01] shadow-xl' : ''} ${atenuada ? 'opacity-75' : ''}`}
        >
            <div className="flex items-center gap-2 md:w-16 md:flex-col">
                <button
                    type="button"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-xl border theme-border theme-text-muted disabled:opacity-40"
                    aria-label={`Mover ${item.nombre_original || 'pieza'} de la posición ${posicion}`}
                    disabled={!puedeOrdenar}
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="h-4 w-4" />
                </button>
                <span className="text-sm font-black theme-text-main">{String(posicion).padStart(2, '0')}</span>
            </div>
            <button type="button" className="relative h-24 w-full shrink-0 overflow-hidden rounded-xl bg-black md:h-20 md:w-36" onClick={() => onPrevisualizar(item)} aria-label="Previsualizar">
                {item.tipo === 'video' ? (
                    <video src={item.url || undefined} muted playsInline preload="metadata" className="h-full w-full object-cover" />
                ) : (
                    <img src={item.url || undefined} alt="" className="h-full w-full object-cover" />
                )}
            </button>
            <div className="min-w-0 flex-1 space-y-1">
                <p className="font-bold theme-text-main m-0 truncate" title={item.nombre_original || ''}>
                    {item.nombre_original || `Pieza ${item.id}`}
                </p>
                <p className="text-xs theme-text-muted m-0">
                    {item.tipo === 'video' ? 'Video' : 'Imagen'}
                    {formatearTamanoBytes(item.tamano_bytes) ? ` · ${formatearTamanoBytes(item.tamano_bytes)}` : ''}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    <BadgeEstadoPublicidad estado={item.estado} />
                    <BadgeAlcancePublicidad alcance={item.alcance} />
                </div>
                {item.alcance === 'global' ? (
                    <p className="text-xs theme-text-muted m-0">Pieza compartida: el orden se ve en todas las sucursales.</p>
                ) : null}
                <p className="text-xs theme-text-muted m-0">{textoProgramacion(item)}</p>
                {textoEliminacion(item) ? <p className="text-xs theme-text-muted m-0">{textoEliminacion(item)}</p> : null}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {item.tipo === 'imagen' && puedeEditar ? (
                    <div className="inline-flex items-center gap-1 rounded-xl border theme-border px-1">
                        <button type="button" className="p-1 theme-text-main" aria-label="Reducir duración" onClick={() => guardarDuracion(Number(duracion) - 1)}>
                            <Minus className="h-4 w-4" />
                        </button>
                        <input
                            aria-label="Duración en segundos"
                            className="w-12 bg-transparent text-center text-sm font-bold theme-text-main"
                            inputMode="numeric"
                            value={duracion}
                            onChange={(event) => setDuracion(event.target.value)}
                            onBlur={() => guardarDuracion(duracion)}
                        />
                        <span className="text-xs theme-text-muted">s</span>
                        <button type="button" className="p-1 theme-text-main" aria-label="Aumentar duración" onClick={() => guardarDuracion(Number(duracion) + 1)}>
                            <Plus className="h-4 w-4" />
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-1 min-w-[8rem]">
                        <span className="text-sm font-bold theme-text-main tabular-nums">
                            {formatearDuracionSeg(item.duracion_seg) || 'Sin duración'}
                        </span>
                        {item.tipo === 'video' && puedeEditar && typeof onVolumen === 'function' ? (
                            <label className="flex flex-col gap-0.5 text-[10px] font-bold theme-text-muted">
                                <span className="flex justify-between gap-2">
                                    Volumen
                                    <span className="tabular-nums theme-text-main">{volumenPieza}%</span>
                                </span>
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    value={volumenPieza}
                                    aria-label={`Volumen de ${item.nombre_original || 'video'}`}
                                    onChange={(event) => setVolumenPieza(Number(event.target.value))}
                                    onMouseUp={guardarVolumenPieza}
                                    onTouchEnd={guardarVolumenPieza}
                                    onKeyUp={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') guardarVolumenPieza();
                                    }}
                                />
                            </label>
                        ) : item.tipo === 'video' ? (
                            <span className="text-[10px] theme-text-muted tabular-nums">
                                Vol. {item.volumen_pct ?? 100}%
                            </span>
                        ) : null}
                    </div>
                )}
                {puedeEditar ? (
                    <button
                        type="button"
                        role="switch"
                        aria-checked={item.activa}
                        className={THEME_BTN_SECONDARY}
                        onClick={() => onToggle(item)}
                    >
                        {item.activa ? 'Desactivar' : 'Activar'}
                    </button>
                ) : null}
                {puedeEditar ? (
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onEditar(item)}>
                        <Pencil className="h-4 w-4" /> Editar
                    </button>
                ) : null}
                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onPrevisualizar(item)}>
                    <Eye className="h-4 w-4" />
                </button>
                {puedeEliminar ? (
                    <div className="relative">
                        <button type="button" className={THEME_BTN_SECONDARY} aria-label="Más acciones" onClick={() => setMenu((v) => !v)}>
                            <MoreHorizontal className="h-4 w-4" />
                        </button>
                        {menu ? (
                            <div className="absolute right-0 z-20 mt-1 w-56 rounded-xl border theme-border theme-element p-2 shadow-lg">
                                {confirmar ? (
                                    <div className="space-y-2">
                                        <p className="text-xs theme-text-main m-0">Se eliminará la pieza y el archivo si nadie más lo usa.</p>
                                        <button type="button" className={THEME_BTN_SECONDARY} onClick={() => { setMenu(false); onEliminar(item); }}>
                                            <Trash2 className="h-4 w-4" /> Eliminar ahora
                                        </button>
                                    </div>
                                ) : (
                                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setConfirmar(true)}>
                                        Eliminar
                                    </button>
                                )}
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </article>
    );
}
