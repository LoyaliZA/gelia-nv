import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, FileSpreadsheet, AlertTriangle, Eye, ArrowRight } from 'lucide-react';
import { THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';

const LABELS = {
    sku: 'SKU del producto *',
    precio_base: 'Precio base *',
};

function autoMapearHeaders(headers, mapping) {
    const newMapping = { ...mapping };
    headers.forEach((h) => {
        const lower = String(h).toLowerCase();
        if ((lower.includes('sku') || (lower.includes('codigo') && !lower.includes('barras'))) && !newMapping.sku) {
            newMapping.sku = h;
        }
        if ((lower.includes('plataforma') || lower.includes('precio') || lower.includes('costo')) && !newMapping.precio_base) {
            newMapping.precio_base = h;
        }
    });
    return newMapping;
}

export default function ModalMapeoPrecios({
    archivo,
    configuracion,
    margenes = [],
    permisos,
    modo,
    onClose,
    onConfirm,
}) {
    const [headers, setHeaders] = useState([]);
    const [filePath, setFilePath] = useState('');
    const [sinCabecera, setSinCabecera] = useState(false);
    const [mapping, setMapping] = useState({
        sku: configuracion?.mapeo_precios?.sku || '',
        precio_base: configuracion?.mapeo_precios?.precio_base || '',
    });
    const [muestra, setMuestra] = useState(null);
    const [guardarPredeterminado, setGuardarPredeterminado] = useState(false);
    const [loading, setLoading] = useState(true);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [confirmLoading, setConfirmLoading] = useState(false);
    const [error, setError] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    useEffect(() => {
        const cargarPreview = async () => {
            setLoading(true);
            setError(null);
            const formData = new FormData();
            formData.append('listado_aromas', archivo);
            try {
                const response = await fetch(route('woocommerce.import_preview'), {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Error al leer el archivo.');

                setHeaders(data.headers || []);
                setFilePath(data.file_path);
                setSinCabecera(!!data.sin_cabecera);
                const sugerido = data.mapeo_sugerido || configuracion?.mapeo_precios || {};
                setMapping(autoMapearHeaders(data.headers || [], {
                    sku: sugerido.sku || '',
                    precio_base: sugerido.precio_base || '',
                }));
            } catch (e) {
                setError(e.message);
            } finally {
                setLoading(false);
            }
        };

        cargarPreview();
    }, [archivo, configuracion?.mapeo_precios]);

    const validarMapping = () => {
        if (!mapping.sku || !mapping.precio_base) {
            setError('Debes mapear SKU y Precio base.');
            return false;
        }
        setError(null);
        return true;
    };

    const verPreviewMapeo = async () => {
        if (!validarMapping()) return;
        setPreviewLoading(true);
        setError(null);
        try {
            const response = await fetch(route('woocommerce.previsualizar_mapeo'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ file_path: filePath, mapping }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Error al previsualizar el mapeo.');
            setMuestra(data.muestra || []);
        } catch (e) {
            setError(e.message);
        } finally {
            setPreviewLoading(false);
        }
    };

    const guardarMapeoPredeterminado = async () => {
        const margenesPayload = margenes.reduce((acc, m) => ({
            ...acc,
            [m.id]: { rebaja: m.multiplicador_rebaja, normal: m.multiplicador_normal },
        }), {});

        const response = await fetch(route('woocommerce.configuracion.update'), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                store_url: configuracion?.store_url || '',
                iva: configuracion?.iva || 1.16,
                notified_users: configuracion?.notified_user_ids || [],
                margenes: margenesPayload,
                mapeo_precios: mapping,
            }),
        });
        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'No se pudo guardar el mapeo predeterminado.');
        }
    };

    const confirmar = async () => {
        if (!validarMapping()) return;
        setConfirmLoading(true);
        setError(null);
        try {
            if (guardarPredeterminado && permisos?.configurar) {
                await guardarMapeoPredeterminado();
            }
            await onConfirm({ file_path: filePath, mapping, modo });
        } catch (e) {
            setError(e.message);
            setConfirmLoading(false);
        }
    };

    const modoLabel = {
        previsualizar: 'Previsualizar cambios',
        local: 'Generar CSV',
        nube: 'Sincronizar WooCommerce',
    }[modo] || 'Continuar';

    const modal = (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={loading || confirmLoading} message={confirmLoading ? 'Procesando_' : 'Leyendo archivo_'} />
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2rem] p-6 md:p-8 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-xl font-black italic uppercase theme-text-main flex items-center gap-2">
                        <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        Mapeo de columnas
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <p className="text-[11px] font-bold theme-text-muted mb-4">
                    Archivo: <span className="theme-text-main">{archivo?.name}</span>
                    {sinCabecera && (
                        <span className="ml-2 text-amber-600">· Sin cabeceras detectadas (columnas sintéticas)</span>
                    )}
                </p>

                {error && (
                    <div className="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-bold flex gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0" /> {error}
                    </div>
                )}

                {!loading && (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            {Object.keys(mapping).map((key) => (
                                <div key={key}>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">{LABELS[key]}</label>
                                    <select
                                        value={mapping[key]}
                                        onChange={(e) => {
                                            setMapping({ ...mapping, [key]: e.target.value });
                                            setMuestra(null);
                                        }}
                                        className="theme-input w-full mt-1 px-3 py-2 text-[11px] font-bold"
                                    >
                                        <option value="">— Seleccionar columna —</option>
                                        {headers.map((h, i) => (
                                            <option key={i} value={h}>{h}</option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-wrap gap-3 mb-6">
                            <button
                                type="button"
                                onClick={verPreviewMapeo}
                                disabled={previewLoading || !mapping.sku || !mapping.precio_base}
                                className="px-4 py-2 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest flex items-center gap-2 disabled:opacity-50 hover:border-[var(--color-primario)] transition-all"
                            >
                                <Eye className="w-4 h-4" />
                                {previewLoading ? 'Cargando...' : 'Vista previa del mapeo'}
                            </button>
                        </div>

                        {muestra && (
                            <div className="mb-6 overflow-x-auto rounded-2xl border theme-border">
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                            <th className="p-3 text-left">SKU</th>
                                            <th className="p-3 text-left">Precio base</th>
                                            <th className="p-3 text-left">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {muestra.map((fila, i) => (
                                            <tr key={i} className="border-b theme-border/50">
                                                <td className="p-3 font-bold theme-text-main">{fila.sku || '—'}</td>
                                                <td className="p-3 theme-text-main">
                                                    {fila.precio_base != null ? `$${Number(fila.precio_base).toFixed(2)}` : '—'}
                                                </td>
                                                <td className="p-3">
                                                    {fila.advertencia ? (
                                                        <span className="text-amber-600 font-bold">{fila.advertencia}</span>
                                                    ) : (
                                                        <span className="text-emerald-600 font-bold">OK</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {permisos?.configurar && (
                            <label className="flex items-center gap-2 mb-6 text-[11px] font-bold theme-text-muted cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={guardarPredeterminado}
                                    onChange={(e) => setGuardarPredeterminado(e.target.checked)}
                                    className="rounded"
                                />
                                Guardar como mapeo predeterminado
                            </label>
                        )}

                        <div className="flex gap-3 pt-4 border-t theme-border">
                            <button
                                type="button"
                                onClick={onClose}
                                className="flex-1 py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-all"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={confirmar}
                                disabled={confirmLoading || !mapping.sku || !mapping.precio_base}
                                className={`${THEME_BTN_PRIMARY} flex-1 py-3 flex items-center justify-center gap-2 disabled:opacity-50`}
                            >
                                <ArrowRight className="w-4 h-4" />
                                {modoLabel}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );

    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
