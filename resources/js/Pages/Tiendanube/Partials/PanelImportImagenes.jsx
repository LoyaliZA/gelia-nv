import React, { useEffect, useMemo, useState } from 'react';
import { FileSpreadsheet, ImagePlus, Upload } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalContinuarSegundoPlano from './ModalContinuarSegundoPlano';
import ModalReportesImagenes from './ModalReportesImagenes';
import { ESTADOS_ACTIVOS, startTiendanubeImageImportTracking } from '../../../utils/tiendanubeImageImportTracker';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const MOTIVO_LABELS = {
    nombre_invalido: 'Nombre inválido',
    sku_no_encontrado: 'SKU no encontrado',
    sku_ambiguo: 'SKU ambiguo',
    archivo_grande: 'Archivo grande',
    error_carga: 'Error al subir',
};

const MOTIVO_ORDER = ['nombre_invalido', 'sku_no_encontrado', 'sku_ambiguo', 'archivo_grande', 'error_carga'];

const ESTADOS_PROCESANDO = ['pendiente', 'en_proceso', 'validando', 'procesando'];
const ESTADOS_REVISION = ['requiere_revision', 'lista'];

export default function PanelImportImagenes({
    permisos,
    credencialesOk,
    imageImportActivo,
    ultimosImportImagenes = [],
    onImportStarted,
    embedded = false,
    convertirWebp = true,
    modo1280 = 'none',
}) {
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState(null);
    const [preview, setPreview] = useState(null);
    const [importId, setImportId] = useState(imageImportActivo?.id || null);
    const [progreso, setProgreso] = useState(null);
    const [revision, setRevision] = useState(null);
    const [selecciones, setSelecciones] = useState({});
    const [showBgModal, setShowBgModal] = useState(false);
    const [bgModalPreview, setBgModalPreview] = useState(null);
    const [showReportes, setShowReportes] = useState(false);

    useEffect(() => {
        if (imageImportActivo?.id) {
            setImportId(imageImportActivo.id);
        }
    }, [imageImportActivo?.id]);

    useEffect(() => {
        if (!importId) return undefined;

        let cancelled = false;
        const poll = async () => {
            try {
                const res = await fetch(route('tiendanube.imagenes.importar.progreso', importId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (cancelled) return;
                setProgreso(data);
            } catch {
                // ignore
            }
        };

        poll();
        const id = setInterval(poll, 2000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [importId]);

    useEffect(() => {
        if (!importId || !progreso) return undefined;
        const enRevision = ESTADOS_REVISION.includes(progreso.estado) && !progreso.confirmado_at;
        if (!enRevision) {
            return undefined;
        }

        let cancelled = false;
        const load = async () => {
            try {
                const res = await fetch(route('tiendanube.imagenes.importar.revision', importId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (cancelled) return;
                setRevision(data);
                setSelecciones((prev) => {
                    const next = { ...prev };
                    (data.items || []).forEach((item) => {
                        if (next[item.id]) return;
                        next[item.id] = {
                            producto_id: item.producto_id || '',
                            excluido: !!item.excluido,
                        };
                    });
                    return next;
                });
            } catch {
                // ignore
            }
        };
        load();
        return () => {
            cancelled = true;
        };
    }, [importId, progreso?.estado, progreso?.confirmado_at]);

    const subir = async (e) => {
        e.preventDefault();
        if (!file || !permisos.editar || !credencialesOk) return;

        setUploading(true);
        setError(null);
        setPreview(null);
        setRevision(null);
        setSelecciones({});
        try {
            const body = new FormData();
            body.append('zip', file);
            body.append('convertir_webp', convertirWebp ? '1' : '0');
            body.append('modo_1280', modo1280);
            const res = await fetch(route('tiendanube.imagenes.importar'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body,
            });
            if (res.status === 413) {
                throw new Error('El ZIP supera el límite del servidor (máx. ~512 MB). Divide el archivo o comprime más.');
            }
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo iniciar la importación.');
            }
            setPreview(data.preview || null);
            setImportId(data.import_id);
            onImportStarted?.(data.import_id);
            setFile(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setUploading(false);
        }
    };

    const confirmar = async () => {
        if (!importId || !revision?.items) return;
        setConfirming(true);
        setError(null);
        try {
            const items = revision.items
                .filter((item) => ['pendiente', 'requiere_seleccion'].includes(item.estado))
                .map((item) => {
                    const sel = selecciones[item.id] || {};
                    return {
                        id: item.id,
                        producto_id: sel.producto_id ? Number(sel.producto_id) : item.producto_id,
                        excluido: !!sel.excluido,
                    };
                });
            const res = await fetch(route('tiendanube.imagenes.importar.confirmar', importId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ items }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo confirmar el lote.');
            }
            setRevision(null);
            setBgModalPreview(preview);
            setShowBgModal(true);
            startTiendanubeImageImportTracking(importId);
        } catch (err) {
            setError(err.message);
        } finally {
            setConfirming(false);
        }
    };

    const reintentar = async () => {
        if (!importId) return;
        setConfirming(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.imagenes.importar.reintentar', importId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo reintentar.');
            }
            startTiendanubeImageImportTracking(importId);
        } catch (err) {
            setError(err.message);
        } finally {
            setConfirming(false);
        }
    };

    const irASegundoPlano = () => {
        if (importId) startTiendanubeImageImportTracking(importId);
        setShowBgModal(false);
    };

    const ocupado = progreso && ESTADOS_ACTIVOS.includes(progreso.estado);
    const procesando = progreso && ESTADOS_PROCESANDO.includes(progreso.estado);
    const enRevision = progreso && ESTADOS_REVISION.includes(progreso.estado) && !progreso.confirmado_at;

    const erroresPorMotivo = useMemo(() => {
        const groups = {};
        for (const key of MOTIVO_ORDER) {
            groups[key] = [];
        }
        for (const err of progreso?.errores || []) {
            const key = err.motivo && groups[err.motivo] ? err.motivo : 'error_carga';
            if (!groups[key]) groups[key] = [];
            groups[key].push(err);
        }
        return groups;
    }, [progreso?.errores]);

    const erroresTotal = progreso?.errores_total ?? progreso?.errores?.length ?? 0;
    const erroresMostrados = progreso?.errores?.length ?? 0;
    const resumen = progreso?.resumen;
    const puedeReintentar = (resumen?.error_carga ?? 0) > 0
        && ['completado_con_incidencias', 'error', 'completado'].includes(progreso?.estado);

    const filasRevision = revision?.items || [];

    return (
        <div className={embedded ? 'space-y-4' : `${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <GeliaLoader isVisible={uploading || confirming} message={confirming ? 'Confirmando lote_' : 'Subiendo ZIP_'} />
            <ModalContinuarSegundoPlano
                open={showBgModal}
                importId={importId}
                preview={bgModalPreview}
                onContinuarSegundoPlano={irASegundoPlano}
                onSeguirAqui={() => setShowBgModal(false)}
            />
            <ModalReportesImagenes
                open={showReportes}
                onClose={() => setShowReportes(false)}
                importId={importId}
                alertasDimension={progreso?.alertas_dimension ?? 0}
                fallidos={erroresTotal}
            />
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                        <ImagePlus className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        Carga masiva de imágenes
                    </h2>
                    <p className="text-xs theme-text-muted mt-1">
                        ZIP con archivos <code className="font-mono">SKU.webp</code> o{' '}
                        <code className="font-mono">SKU_2.jpg</code>. Se relacionan con el catálogo por SKU.
                        La primera imagen de cada producto reemplaza las anteriores; <code className="font-mono">SKU_n</code> se agrega.
                        Primero se valida el lote; las imágenes se envían a TiendaNube solo al confirmar.
                    </p>
                </div>
            </div>

            {permisos.editar && (
                <form onSubmit={subir} className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <input
                        type="file"
                        accept=".zip,application/zip"
                        onChange={(e) => setFile(e.target.files?.[0] || null)}
                        className="flex-1 text-xs theme-text-main file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-zinc-100 dark:file:bg-zinc-800"
                        disabled={uploading || ocupado || !credencialesOk}
                    />
                    <button
                        type="submit"
                        disabled={!file || uploading || ocupado || !credencialesOk}
                        className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Upload className="w-4 h-4" />
                        {procesando ? 'Importando…' : enRevision ? 'En revisión' : 'Importar ZIP'}
                    </button>
                </form>
            )}

            {!credencialesOk && (
                <p className="text-xs font-bold text-amber-600">Configura credenciales antes de importar.</p>
            )}
            {error && <p className="text-xs font-bold text-red-500">{error}</p>}

            {preview && (
                <p className="text-xs theme-text-muted">
                    Preview: {preview.matched} listos
                    {' · '}
                    {preview.sku_no_encontrado ?? 0} SKU no encontrado
                    {' · '}
                    {preview.nombre_invalido ?? 0} nombre inválido
                    {' · '}
                    {preview.archivo_grande ?? 0} archivo grande
                    {' · '}
                    {preview.total} total
                </p>
            )}

            {enRevision && filasRevision.length > 0 && (
                <div className="space-y-3 border theme-border rounded-xl p-3">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        Revisión del lote — confirma destino y alcance del reemplazo
                    </p>
                    <div className="max-h-72 overflow-y-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted text-left">
                                    <th className="py-1 pr-2">Archivo</th>
                                    <th className="py-1 pr-2">SKU</th>
                                    <th className="py-1 pr-2">Pos.</th>
                                    <th className="py-1 pr-2">Destino</th>
                                    <th className="py-1 pr-2">Modo</th>
                                    <th className="py-1">Excluir</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filasRevision.map((item) => {
                                    const sel = selecciones[item.id] || { producto_id: item.producto_id || '', excluido: false };
                                    const ambiguo = item.estado === 'requiere_seleccion' || item.resolucion_estado === 'ambiguo';
                                    return (
                                        <tr key={item.id} className="border-t theme-border">
                                            <td className="py-2 pr-2 font-mono">{item.filename}</td>
                                            <td className="py-2 pr-2 font-mono">{item.sku || '—'}</td>
                                            <td className="py-2 pr-2">{item.position}</td>
                                            <td className="py-2 pr-2">
                                                {ambiguo && (item.candidatos || []).length > 0 ? (
                                                    <select
                                                        className="w-full text-xs rounded-lg border theme-border bg-transparent px-2 py-1"
                                                        value={sel.producto_id}
                                                        disabled={sel.excluido}
                                                        onChange={(e) => setSelecciones((prev) => ({
                                                            ...prev,
                                                            [item.id]: { ...sel, producto_id: e.target.value },
                                                        }))}
                                                    >
                                                        <option value="">Elegir producto</option>
                                                        {(item.candidatos || []).map((c) => (
                                                            <option key={c.producto_id} value={c.producto_id}>
                                                                {c.nombre} (#{c.producto_id})
                                                            </option>
                                                        ))}
                                                    </select>
                                                ) : (
                                                    <span>
                                                        {item.producto_id
                                                            ? `#${item.producto_id}`
                                                            : (item.mensaje || item.estado)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-2">{item.modo_reemplazo === 'reemplazar_primera' ? 'Reemplaza' : 'Agrega'}</td>
                                            <td className="py-2">
                                                {['pendiente', 'requiere_seleccion'].includes(item.estado) && (
                                                    <input
                                                        type="checkbox"
                                                        checked={!!sel.excluido}
                                                        onChange={(e) => setSelecciones((prev) => ({
                                                            ...prev,
                                                            [item.id]: { ...sel, excluido: e.target.checked },
                                                        }))}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <button
                        type="button"
                        onClick={confirmar}
                        disabled={confirming || !permisos.editar}
                        className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Confirmar e importar
                    </button>
                </div>
            )}

            {(progreso || imageImportActivo) && (
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex justify-between text-[10px] font-black uppercase tracking-widest theme-text-muted gap-4 flex-1">
                            <span>Estado: {progreso?.estado || imageImportActivo?.estado}</span>
                            <span>{progreso?.porcentaje ?? 0}%</span>
                        </div>
                        {importId && (erroresTotal > 0 || (progreso?.alertas_dimension ?? 0) > 0) && (
                            <button
                                type="button"
                                onClick={() => setShowReportes(true)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main hover:bg-black/[0.03] dark:hover:bg-white/[0.03]"
                            >
                                <FileSpreadsheet className="w-3.5 h-3.5" />
                                Reportes
                            </button>
                        )}
                    </div>
                    <div className="h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                        <div
                            className="h-full transition-all"
                            style={{
                                width: `${progreso?.porcentaje ?? 0}%`,
                                backgroundColor: 'var(--color-primario)',
                            }}
                        />
                    </div>
                    <p className="text-xs theme-text-muted">
                        Procesados {progreso?.procesados ?? imageImportActivo?.procesados ?? 0}
                        {' / '}
                        {progreso?.total_archivos ?? imageImportActivo?.total_archivos ?? '…'}
                        {' · '}
                        OK {progreso?.exitosos ?? imageImportActivo?.exitosos ?? 0}
                        {' · '}
                        Fallidos {progreso?.fallidos ?? imageImportActivo?.fallidos ?? 0}
                    </p>
                    {progreso?.estado === 'completado_con_incidencias' && (
                        <p className="text-xs font-bold text-amber-600">Completado con incidencias: no todas las imágenes se cargaron.</p>
                    )}
                    {resumen && (
                        <p className="text-[10px] theme-text-muted">
                            {resumen.sku_no_encontrado > 0 && (
                                <span className="mr-2">SKU no encontrado: {resumen.sku_no_encontrado}</span>
                            )}
                            {resumen.sku_ambiguo > 0 && (
                                <span className="mr-2">SKU ambiguo: {resumen.sku_ambiguo}</span>
                            )}
                            {resumen.nombre_invalido > 0 && (
                                <span className="mr-2">Nombre inválido: {resumen.nombre_invalido}</span>
                            )}
                            {resumen.archivo_grande > 0 && (
                                <span className="mr-2">Archivo grande: {resumen.archivo_grande}</span>
                            )}
                            {resumen.error_carga > 0 && (
                                <span className="mr-2">Error al subir: {resumen.error_carga}</span>
                            )}
                        </p>
                    )}
                    {puedeReintentar && permisos.editar && (
                        <button
                            type="button"
                            onClick={reintentar}
                            disabled={confirming}
                            className="text-[10px] font-black uppercase tracking-widest"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            Reintentar fallidos
                        </button>
                    )}
                    {progreso?.mensaje_error && (
                        <p className="text-xs font-bold text-red-500">{progreso.mensaje_error}</p>
                    )}
                    {erroresMostrados > 0 && (
                        <div className="space-y-3 border-t theme-border pt-2 max-h-64 overflow-y-auto">
                            {MOTIVO_ORDER.map((motivo) => {
                                const lista = erroresPorMotivo[motivo] || [];
                                if (!lista.length) return null;
                                return (
                                    <div key={motivo}>
                                        <p className="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-1">
                                            {MOTIVO_LABELS[motivo]} ({lista.length}
                                            {resumen?.[motivo] != null && resumen[motivo] > lista.length
                                                ? ` de ${resumen[motivo]}`
                                                : ''}
                                            )
                                        </p>
                                        <ul className="text-xs theme-text-muted space-y-1">
                                            {lista.map((err, i) => (
                                                <li key={`${motivo}-${i}`}>
                                                    <span className="font-mono theme-text-main">{err.filename}</span>
                                                    {err.sku ? ` (${err.sku})` : ''}: {err.mensaje || err.estado}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                );
                            })}
                            {erroresTotal > erroresMostrados && (
                                <p className="text-[10px] theme-text-muted">
                                    Mostrando {erroresMostrados} de {erroresTotal}. Descarga el CSV para el listado completo.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            )}

            {ultimosImportImagenes?.length > 0 && (
                <div className="pt-2 border-t theme-border">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Últimas importaciones</p>
                    <ul className="space-y-1">
                        {ultimosImportImagenes.map((s) => (
                            <li key={s.id} className="text-xs theme-text-muted flex justify-between gap-2 items-center">
                                <span>#{s.id} · {s.estado}</span>
                                <span className="flex items-center gap-2">
                                    <span>{s.exitosos}/{s.total_archivos} ok</span>
                                    {(s.fallidos > 0 || s.estado === 'completado' || s.estado === 'completado_con_incidencias' || s.estado === 'error') && (
                                        <a
                                            href={route('tiendanube.imagenes.importar.reporte', s.id)}
                                            className="font-black uppercase tracking-widest text-[9px]"
                                            style={{ color: 'var(--color-primario)' }}
                                        >
                                            Reporte
                                        </a>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
