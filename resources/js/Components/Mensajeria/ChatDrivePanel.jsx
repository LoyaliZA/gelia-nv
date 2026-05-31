import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
    X,
    FolderOpen,
    FileText,
    Image as ImageIcon,
    Link2,
    Download,
    ExternalLink,
    Loader2,
    Filter,
} from 'lucide-react';
import MensajeriaService from '@/Services/MensajeriaService';
import { nombreRemitenteGrupo } from '@/utils/mensajeriaGrupo';
import VisorDocumentoMensaje from './VisorDocumentoMensaje';
import VisorImagenMensaje from './VisorImagenMensaje';
import {
    puedePrevisualizarAdjunto,
    urlAdjuntoMensajeria,
    manejarClickPrevisualizar,
} from '@/utils/adjuntoPreview';

const TABS = [
    { id: 'documentos', label: 'Documentos', icon: FileText },
    { id: 'imagenes', label: 'Imágenes', icon: ImageIcon },
    { id: 'enlaces', label: 'Enlaces', icon: Link2 },
];

const formatearFecha = (iso) => {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return '';
    }
};

const formatearTamano = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function ChatDrivePanel({
    isOpen,
    onClose,
    conversacionId,
    participantes = [],
}) {
    const [categoria, setCategoria] = useState('documentos');
    const [userId, setUserId] = useState('');
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [items, setItems] = useState([]);
    const [remitentes, setRemitentes] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [adjuntoDocumentoPreview, setAdjuntoDocumentoPreview] = useState(null);
    const [adjuntoImagenPreview, setAdjuntoImagenPreview] = useState(null);

    const opcionesRemitentes = useMemo(() => {
        const mapa = new Map();
        for (const p of remitentes) {
            if (p?.id) mapa.set(p.id, p);
        }
        for (const p of participantes) {
            if (p?.id && !mapa.has(p.id)) mapa.set(p.id, p);
        }
        return [...mapa.values()].sort((a, b) =>
            nombreRemitenteGrupo(a).localeCompare(nombreRemitenteGrupo(b), 'es')
        );
    }, [remitentes, participantes]);

    const cargar = useCallback(async () => {
        if (!conversacionId || !isOpen) return;

        setCargando(true);
        setError(null);
        try {
            const data = await MensajeriaService.listarMedios(conversacionId, {
                categoria,
                user_id: userId || undefined,
                desde: desde || undefined,
                hasta: hasta || undefined,
            });
            setItems(data.items || []);
            setRemitentes(data.remitentes || []);
        } catch {
            setError('No se pudo cargar el contenido compartido.');
            setItems([]);
        } finally {
            setCargando(false);
        }
    }, [conversacionId, isOpen, categoria, userId, desde, hasta]);

    useEffect(() => {
        if (isOpen) cargar();
    }, [cargar, isOpen]);

    useEffect(() => {
        if (!isOpen) {
            setCategoria('documentos');
            setUserId('');
            setDesde('');
            setHasta('');
            setItems([]);
            setError(null);
        }
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <aside
            className="gelia-mensajeria-drive-panel flex flex-col h-full min-h-0 w-full sm:w-80 lg:w-96 shrink-0 border-l theme-border theme-surface"
            aria-label="Archivos del chat"
        >
            <div className="flex items-center gap-2 p-4 border-b theme-border shrink-0">
                <div
                    className="p-2 rounded-xl shrink-0"
                    style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                >
                    <FolderOpen className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                </div>
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-black uppercase italic theme-text-main m-0 truncate">
                        Archivos_
                    </h2>
                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5 m-0">
                        Contenido compartido
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="p-2 rounded-full theme-element border theme-border theme-text-muted hover:theme-text-main outline-none shrink-0"
                    aria-label="Cerrar archivos"
                >
                    <X className="w-5 h-5" />
                </button>
            </div>

            <div className="flex gap-1 p-2 border-b theme-border shrink-0 overflow-x-auto">
                {TABS.map(({ id, label, icon: Icon }) => (
                    <button
                        key={id}
                        type="button"
                        onClick={() => setCategoria(id)}
                        className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider whitespace-nowrap transition-all outline-none border ${
                            categoria === id
                                ? 'bg-[var(--color-primario)] text-white border-transparent'
                                : 'theme-element theme-text-muted border theme-border hover:border-[var(--color-primario)]'
                        }`}
                    >
                        <Icon className="w-3.5 h-3.5 shrink-0" />
                        {label}
                    </button>
                ))}
            </div>

            <div className="p-3 border-b theme-border shrink-0 space-y-2">
                <div className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                    <Filter className="w-3 h-3" />
                    Filtros
                </div>
                <select
                    value={userId}
                    onChange={(e) => setUserId(e.target.value)}
                    className="w-full text-xs rounded-xl px-3 py-2 theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                >
                    <option value="">Todos los remitentes</option>
                    {opcionesRemitentes.map((u) => (
                        <option key={u.id} value={u.id}>
                            {nombreRemitenteGrupo(u)}
                        </option>
                    ))}
                </select>
                <div className="grid grid-cols-2 gap-2">
                    <input
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                        className="w-full text-xs rounded-xl px-2 py-2 theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                        aria-label="Desde"
                    />
                    <input
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                        className="w-full text-xs rounded-xl px-2 py-2 theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                        aria-label="Hasta"
                    />
                </div>
            </div>

            <div className="flex-1 overflow-y-auto custom-scrollbar p-3 min-h-0">
                {cargando && (
                    <div className="flex justify-center py-12">
                        <Loader2 className="w-6 h-6 animate-spin opacity-50" />
                    </div>
                )}

                {!cargando && error && (
                    <p className="text-xs theme-text-muted text-center py-8">{error}</p>
                )}

                {!cargando && !error && items.length === 0 && (
                    <p className="text-xs theme-text-muted text-center py-8">
                        No hay {TABS.find((t) => t.id === categoria)?.label?.toLowerCase()} en este chat.
                    </p>
                )}

                {!cargando && !error && items.length > 0 && (
                    <ul className="space-y-2 m-0 p-0 list-none">
                        {items.map((item) => (
                            <li key={item.id}>
                                <DriveItem
                                    item={item}
                                    categoria={categoria}
                                    onPrevisualizarDocumento={setAdjuntoDocumentoPreview}
                                    onPrevisualizarImagen={setAdjuntoImagenPreview}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            {adjuntoDocumentoPreview && (
                <VisorDocumentoMensaje
                    adjunto={adjuntoDocumentoPreview}
                    onCerrar={() => setAdjuntoDocumentoPreview(null)}
                />
            )}
            {adjuntoImagenPreview && (
                <VisorImagenMensaje
                    adjunto={adjuntoImagenPreview}
                    onCerrar={() => setAdjuntoImagenPreview(null)}
                />
            )}
        </aside>
    );
}

function descargarAdjunto(adjunto) {
    const url = urlAdjuntoMensajeria(adjunto.id);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = adjunto.nombre_original || 'archivo';
    enlace.rel = 'noopener noreferrer';
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
}

function DriveItem({ item, categoria, onPrevisualizarDocumento, onPrevisualizarImagen }) {
    const nombre = nombreRemitenteGrupo(item.user);
    const fecha = formatearFecha(item.created_at);

    if (categoria === 'enlaces' && item.enlace) {
        return (
            <a
                href={item.enlace.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex gap-3 p-3 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors"
            >
                <div className="w-10 h-10 rounded-xl theme-surface border theme-border flex items-center justify-center shrink-0">
                    <Link2 className="w-5 h-5 theme-text-muted" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold theme-text-main m-0 truncate">{item.enlace.titulo}</p>
                    <p className="text-[10px] theme-text-muted m-0 mt-0.5 truncate">{item.enlace.url}</p>
                    <p className="text-[10px] theme-text-muted m-0 mt-1">
                        {nombre} · {fecha}
                    </p>
                </div>
                <ExternalLink className="w-4 h-4 shrink-0 theme-text-muted" />
            </a>
        );
    }

    if (categoria === 'imagenes' && item.adjunto) {
        const url = item.adjunto.thumbnail_url || item.adjunto.url;
        const esPdf = puedePrevisualizarAdjunto(item.adjunto) && item.adjunto.mime?.includes('pdf');

        return (
            <div className="flex gap-1 items-stretch">
                <button
                    type="button"
                    onClick={(e) => {
                        manejarClickPrevisualizar(e, () => {
                            if (esPdf) {
                                onPrevisualizarDocumento?.(item.adjunto);
                            } else {
                                onPrevisualizarImagen?.(item.adjunto);
                            }
                        });
                    }}
                    className="flex-1 p-2 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors text-left outline-none min-w-0"
                >
                    <div className="aspect-video rounded-xl overflow-hidden bg-black/5 mb-2 pointer-events-none">
                        {url ? (
                            <img
                                src={url}
                                alt={item.adjunto.nombre_original || 'Imagen'}
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center">
                                <ImageIcon className="w-8 h-8 theme-text-muted" />
                            </div>
                        )}
                    </div>
                    <p className="text-[10px] theme-text-muted m-0 px-1 pointer-events-none">
                        {nombre} · {fecha}
                    </p>
                </button>
                <button
                    type="button"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        descargarAdjunto(item.adjunto);
                    }}
                    className="flex items-center justify-center p-3 rounded-2xl theme-element border theme-border theme-text-muted hover:border-[var(--color-primario)] shrink-0 outline-none"
                    title="Descargar"
                    aria-label="Descargar"
                >
                    <Download className="w-4 h-4" />
                </button>
            </div>
        );
    }

    if (item.adjunto) {
        const puedeVer = puedePrevisualizarAdjunto(item.adjunto);

        const contenido = (
            <>
                <div className="w-10 h-10 rounded-xl theme-surface border theme-border flex items-center justify-center shrink-0">
                    <FileText className="w-5 h-5 theme-text-muted" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-bold theme-text-main m-0 truncate">
                        {item.adjunto.nombre_original || 'Documento'}
                    </p>
                    <p className="text-[10px] theme-text-muted m-0 mt-0.5">
                        {formatearTamano(item.adjunto.tamano)}
                        {item.adjunto.mime ? ` · ${item.adjunto.mime.split('/').pop()}` : ''}
                    </p>
                    <p className="text-[10px] theme-text-muted m-0 mt-1">
                        {nombre} · {fecha}
                        {puedeVer && (
                            <span className="ms-1" style={{ color: 'var(--color-primario)' }}>
                                · Vista previa
                            </span>
                        )}
                    </p>
                </div>
            </>
        );

        if (puedeVer) {
            return (
                <div className="flex gap-1 items-stretch">
                    <button
                        type="button"
                        onClick={(e) => manejarClickPrevisualizar(e, () => onPrevisualizarDocumento?.(item.adjunto))}
                        className="flex flex-1 gap-3 p-3 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors text-left outline-none"
                    >
                        {contenido}
                    </button>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            descargarAdjunto(item.adjunto);
                        }}
                        className="flex items-center justify-center p-3 rounded-2xl theme-element border theme-border theme-text-muted hover:border-[var(--color-primario)] shrink-0 outline-none"
                        title="Descargar"
                        aria-label="Descargar"
                    >
                        <Download className="w-4 h-4" />
                    </button>
                </div>
            );
        }

        return (
            <div className="flex gap-1 items-stretch">
                <button
                    type="button"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        descargarAdjunto(item.adjunto);
                    }}
                    className="flex flex-1 gap-3 p-3 rounded-2xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors text-left outline-none"
                >
                    {contenido}
                    <Download className="w-4 h-4 shrink-0 theme-text-muted self-center pointer-events-none" />
                </button>
            </div>
        );
    }

    return null;
}
