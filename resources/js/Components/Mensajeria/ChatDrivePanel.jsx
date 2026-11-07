import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    X,
    FileText,
    Image as ImageIcon,
    Link2,
    Download,
    ExternalLink,
    Loader2,
} from 'lucide-react';
import BotonIconoMensajeria from './BotonIconoMensajeria';
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
    const peticion = useRef(0);
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

        const actual = ++peticion.current;
        setCargando(true);
        setItems([]);
        setError(null);
        try {
            const data = await MensajeriaService.listarMedios(conversacionId, {
                categoria,
                user_id: userId || undefined,
                desde: desde || undefined,
                hasta: hasta || undefined,
            });
            if (actual !== peticion.current) return;
            setItems(data.items || []);
            setRemitentes(data.remitentes || []);
        } catch {
            if (actual !== peticion.current) return;
            setError('No se pudo cargar el contenido compartido.');
            setItems([]);
        } finally {
            if (actual === peticion.current) setCargando(false);
        }
    }, [conversacionId, isOpen, categoria, userId, desde, hasta]);

    useEffect(() => {
        if (isOpen) cargar();
        return () => { peticion.current += 1; };
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

    const IconoCategoria = TABS.find((t) => t.id === categoria)?.icon || FileText;
    if (!isOpen) return null;

    return (
        <aside
            className="gelia-mensajeria-drive-panel flex flex-col h-full min-h-0 w-full sm:w-80 lg:w-96 shrink-0 border-l theme-border theme-surface"
            aria-label="Archivos del chat"
        >
            <div className="flex items-center gap-1 px-1 py-1 border-b theme-border shrink-0">
                <h2 className="text-base font-semibold theme-text-main m-0 truncate flex-1 min-w-0 px-3">
                    Contenido compartido
                </h2>
                <BotonIconoMensajeria
                    icon={X}
                    label="Cerrar archivos"
                    onClick={onClose}
                    plano
                />
            </div>

            <div className="gelia-mensajeria-pestanas" role="tablist">
                {TABS.map(({ id, label, icon: Icono }) => (
                    <button
                        key={id}
                        type="button"
                        role="tab"
                        aria-selected={categoria === id}
                        onClick={() => setCategoria(id)}
                    >
                        <Icono size={16} aria-hidden />{label}
                    </button>
                ))}
            </div>

            <details className="gelia-mensajeria-filtros">
                <summary>Filtros</summary>
                <div className="gelia-mensajeria-filtros__cuerpo">
                    <select
                        value={userId}
                        onChange={(e) => setUserId(e.target.value)}
                        className="w-full text-xs rounded-xl px-3 py-2 theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)]"
                        aria-label="Remitente"
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
            </details>

            <div className="flex-1 overflow-y-auto custom-scrollbar min-h-0">
                {cargando && (
                    <div className="flex justify-center py-12">
                        <Loader2 className="w-6 h-6 animate-spin opacity-50" />
                    </div>
                )}

                {!cargando && error && (
                    <div role="alert" className="gelia-mensajeria-vacio"><FileText size={32} aria-hidden /><p>{error}</p><button type="button" className="gelia-sticker-accion" onClick={cargar}>Volver a intentar</button></div>
                )}

                {!cargando && !error && items.length === 0 && (
                    <div className="gelia-mensajeria-vacio"><IconoCategoria size={36} strokeWidth={1.5} aria-hidden /><p className="font-semibold">Sin {TABS.find((t) => t.id === categoria)?.label?.toLowerCase()} por aquí</p><p className="text-xs">Lo que compartan en este chat aparecerá aquí. Si aplicaste filtros, prueba ajustarlos.</p></div>
                )}

                {!cargando && !error && items.length > 0 && categoria === 'imagenes' && (
                    <div className="gelia-mensajeria-mosaico">
                        {items.map((item) => {
                            const url = item.adjunto?.thumbnail_url || item.adjunto?.url;
                            const esPdf = item.adjunto && puedePrevisualizarAdjunto(item.adjunto) && item.adjunto.mime?.includes('pdf');
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    className="gelia-mensajeria-mosaico__celda"
                                    aria-label={item.adjunto?.nombre_original || 'Imagen'}
                                    onClick={() => {
                                        if (!item.adjunto) return;
                                        if (esPdf) setAdjuntoDocumentoPreview(item.adjunto);
                                        else setAdjuntoImagenPreview(item.adjunto);
                                    }}
                                >
                                    {url ? (
                                        <img src={url} alt="" />
                                    ) : (
                                        <ImageIcon className="w-6 h-6 theme-text-muted" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                )}

                {!cargando && !error && items.length > 0 && categoria !== 'imagenes' && (
                    <ul className="m-0 p-0 list-none">
                        {items.map((item) => (
                            <li key={item.id}>
                                <DriveItem
                                    item={item}
                                    categoria={categoria}
                                    onPrevisualizarDocumento={setAdjuntoDocumentoPreview}
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

function DriveItem({ item, categoria, onPrevisualizarDocumento }) {
    const nombre = nombreRemitenteGrupo(item.user);
    const fecha = formatearFecha(item.created_at);

    if (categoria === 'enlaces' && item.enlace) {
        return (
            <a
                href={item.enlace.url}
                target="_blank"
                rel="noopener noreferrer"
                className="gelia-mensajeria-archivo-fila"
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
                        className="gelia-mensajeria-archivo-fila flex-1 text-left"
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
                        className="gelia-mensajeria-accion theme-text-muted shrink-0"
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
                    className="gelia-mensajeria-archivo-fila flex-1 text-left"
                >
                    {contenido}
                    <Download className="w-4 h-4 shrink-0 theme-text-muted self-center pointer-events-none" />
                </button>
            </div>
        );
    }

    return null;
}
