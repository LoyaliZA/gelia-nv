import React, { useEffect, useRef, useState } from 'react';
import { FileText, Image as ImageIcon, Loader2, MessageSquare, Music, Search, User, Video, X } from 'lucide-react';
import MensajeriaService from '@/Services/MensajeriaService';
import { resaltarTexto } from '@/utils/resaltarBusqueda';

const MIN_CHARS = 2;
const DEBOUNCE_MS = 280;

const seccionVacia = (resultados) =>
    !resultados?.personas?.length
    && !resultados?.conversaciones?.length
    && !resultados?.mensajes?.length
    && !resultados?.archivos?.length;

export default function BuscadorInteligente({
    conversacionId = null,
    placeholder = 'Buscar mensajes, archivos y personas…',
    onSeleccionarConversacion,
    onIrAMensaje,
    className = '',
    autoFocus = false,
}) {
    const [q, setQ] = useState('');
    const [debounced, setDebounced] = useState('');
    const [cargando, setCargando] = useState(false);
    const [resultados, setResultados] = useState(null);
    const [abierto, setAbierto] = useState(false);
    const inputRef = useRef(null);
    const panelRef = useRef(null);

    useEffect(() => {
        const t = setTimeout(() => setDebounced(q.trim()), DEBOUNCE_MS);
        return () => clearTimeout(t);
    }, [q]);

    useEffect(() => {
        if (debounced.length < MIN_CHARS) {
            setResultados(null);
            setCargando(false);
            return;
        }

        let cancelado = false;
        setCargando(true);
        setAbierto(true);

        MensajeriaService.buscar(debounced, conversacionId)
            .then((data) => {
                if (!cancelado) setResultados(data);
            })
            .catch((err) => {
                console.warn('[Mensajeria] Error en búsqueda:', err?.response?.status, err?.response?.data);
                if (!cancelado) setResultados({ personas: [], conversaciones: [], mensajes: [], archivos: [] });
            })
            .finally(() => {
                if (!cancelado) setCargando(false);
            });

        return () => { cancelado = true; };
    }, [debounced, conversacionId]);

    useEffect(() => {
        if (!autoFocus) return;
        inputRef.current?.focus();
    }, [autoFocus]);

    useEffect(() => {
        const handler = (e) => {
            if (!panelRef.current?.contains(e.target)) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const limpiar = () => {
        setQ('');
        setDebounced('');
        setResultados(null);
        setAbierto(false);
        inputRef.current?.focus();
    };

    const mostrarPanel = abierto && debounced.length >= MIN_CHARS;

    return (
        <div
            ref={panelRef}
            className={`gelia-buscador-mensajeria relative ${mostrarPanel ? 'gelia-buscador-mensajeria--activo' : ''} ${className}`}
        >
            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 opacity-40 pointer-events-none" />
                <input
                    ref={inputRef}
                    type="search"
                    value={q}
                    onChange={(e) => {
                        setQ(e.target.value);
                        if (e.target.value.trim().length >= MIN_CHARS) setAbierto(true);
                    }}
                    onFocus={() => {
                        if (debounced.length >= MIN_CHARS) setAbierto(true);
                    }}
                    placeholder={placeholder}
                    className="w-full pl-10 pr-9 py-2 rounded-xl text-xs theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)] placeholder:theme-text-muted"
                    enterKeyHint="search"
                    autoComplete="off"
                    aria-label="Búsqueda inteligente"
                    aria-expanded={mostrarPanel}
                />
                {q && (
                    <button
                        type="button"
                        onClick={limpiar}
                        className="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-full theme-text-muted hover:theme-text-main"
                        aria-label="Limpiar búsqueda"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>

            {mostrarPanel && (
                <div className="gelia-buscador-mensajeria-panel theme-no-blur" role="listbox">
                    {cargando && (
                        <div className="flex items-center justify-center gap-2 py-6 text-xs theme-text-muted">
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Buscando…
                        </div>
                    )}

                    {!cargando && resultados && seccionVacia(resultados) && (
                        <p className="text-xs theme-text-muted text-center py-6 m-0 px-3">
                            Sin resultados para «{debounced}»
                        </p>
                    )}

                    {!cargando && resultados && !seccionVacia(resultados) && (
                        <div className="gelia-buscador-mensajeria-panel__scroll py-1">
                            {!conversacionId && resultados.conversaciones?.length > 0 && (
                                <Seccion titulo="Chats">
                                    {resultados.conversaciones.map((c) => (
                                        <button
                                            key={`conv-${c.id}`}
                                            type="button"
                                            className="gelia-buscador-item w-full text-left"
                                            onClick={() => {
                                                onSeleccionarConversacion?.(c.id);
                                                limpiar();
                                            }}
                                        >
                                            <MessageSquare className="w-4 h-4 shrink-0 opacity-50" />
                                            <span className="truncate font-bold text-xs">{resaltarTexto(c.nombre, debounced)}</span>
                                        </button>
                                    ))}
                                </Seccion>
                            )}

                            {resultados.personas?.length > 0 && (
                                <Seccion titulo="Personas">
                                    {resultados.personas.map((p) => (
                                        <div key={`user-${p.id}`} className="gelia-buscador-item gelia-buscador-item--static">
                                            <User className="w-4 h-4 shrink-0 opacity-50" />
                                            <span className="truncate text-xs">
                                                {resaltarTexto(p.name, debounced)}
                                                {p.username && (
                                                    <span className="opacity-60 ml-1">@{p.username}</span>
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </Seccion>
                            )}

                            {resultados.mensajes?.length > 0 && (
                                <Seccion titulo="Mensajes">
                                    {resultados.mensajes.map((m) => (
                                        <button
                                            key={`msg-${m.mensaje_id}`}
                                            type="button"
                                            className="gelia-buscador-item w-full text-left flex-col items-stretch !py-2.5"
                                            onClick={() => {
                                                onIrAMensaje?.(m.conversacion_id, m.mensaje_id);
                                                limpiar();
                                            }}
                                        >
                                            <span className="text-[10px] font-black uppercase opacity-50 truncate">
                                                {!conversacionId && `${m.conversacion_nombre} · `}
                                                {m.user_name}
                                            </span>
                                            <span className="text-xs truncate mt-0.5">{resaltarTexto(m.fragmento, debounced)}</span>
                                        </button>
                                    ))}
                                </Seccion>
                            )}

                            {resultados.archivos?.length > 0 && (
                                <Seccion titulo="Archivos e imágenes">
                                    {resultados.archivos.map((a) => {
                                        const Icono = iconoArchivo(a);
                                        return (
                                        <button
                                            key={`file-${a.adjunto_id}`}
                                            type="button"
                                            className="gelia-buscador-item w-full text-left flex-col items-stretch !py-2.5"
                                            onClick={() => {
                                                onIrAMensaje?.(a.conversacion_id, a.mensaje_id);
                                                limpiar();
                                            }}
                                        >
                                            <div className="flex items-center gap-2 min-w-0">
                                                <Icono className="w-4 h-4 shrink-0 opacity-50" />
                                                <span className="text-xs font-bold truncate">
                                                    {resaltarTexto(a.nombre_original || 'Archivo', debounced)}
                                                </span>
                                            </div>
                                            {(a.coincidencia_en === 'contenido' || a.coincidencia_en === 'leyenda') && a.fragmento && (
                                                <span className="text-[11px] theme-text-muted truncate mt-0.5 pl-6">
                                                    {resaltarTexto(a.fragmento, debounced)}
                                                </span>
                                            )}
                                        </button>
                                        );
                                    })}
                                </Seccion>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function iconoArchivo(item) {
    const tipo = item?.tipo_mensaje;
    const mime = (item?.mime || '').toLowerCase();
    if (tipo === 'imagen' || mime.startsWith('image/')) return ImageIcon;
    if (tipo === 'video' || mime.startsWith('video/')) return Video;
    if (tipo === 'audio' || mime.startsWith('audio/')) return Music;
    return FileText;
}

function Seccion({ titulo, children }) {
    return (
        <div className="gelia-buscador-seccion">
            <p className="gelia-buscador-seccion__titulo m-0 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                {titulo}
            </p>
            {children}
        </div>
    );
}
