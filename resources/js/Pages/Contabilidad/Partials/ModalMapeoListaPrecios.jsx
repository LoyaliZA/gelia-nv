import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, ArrowRight, Eye, FileSpreadsheet, X } from 'lucide-react';
import axios from 'axios';
import GeliaLoader from '../../../Components/GeliaLoader';
import { BTN_PRIMARY, BTN_PRIMARY_STYLE } from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';

const LABELS = {
    sku: 'SKU del producto *',
    descripcion: 'Descripción',
    precio_base: 'Columna de precio *',
};

function autoMapearHeaders(headers, mapping) {
    const newMapping = { ...mapping };
    headers.forEach((h) => {
        const lower = String(h).toLowerCase();
        if ((lower.includes('sku') || (lower.includes('codigo') && !lower.includes('barras'))) && !newMapping.sku) {
            newMapping.sku = h;
        }
        if ((lower.includes('descripcion') || lower.includes('descrip') || lower.includes('nombre')) && !newMapping.descripcion) {
            newMapping.descripcion = h;
        }
        if (lower === 'bronce' && !newMapping.precio_base) {
            newMapping.precio_base = h;
        }
    });
    if (!newMapping.precio_base) {
        headers.forEach((h) => {
            const lower = String(h).toLowerCase();
            if ((lower.includes('plataforma') || lower.includes('precio') || lower.includes('costo')) && !newMapping.precio_base) {
                newMapping.precio_base = h;
            }
        });
    }
    return newMapping;
}

export default function ModalMapeoListaPrecios({
    archivo,
    configuracion,
    puedeConfigurar,
    onCerrar,
    onConfirmar,
}) {
    const [headers, setHeaders] = useState([]);
    const [filePath, setFilePath] = useState('');
    const [sinCabecera, setSinCabecera] = useState(false);
    const [mapping, setMapping] = useState({
        sku: configuracion?.mapeo_precios?.sku || '',
        precio_base: configuracion?.mapeo_precios?.precio_base || 'Bronce',
        descripcion: configuracion?.mapeo_precios?.descripcion || '',
    });
    const [muestra, setMuestra] = useState(null);
    const [guardarPredeterminado, setGuardarPredeterminado] = useState(false);
    const [loading, setLoading] = useState(true);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [confirmLoading, setConfirmLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        const cargarPreview = async () => {
            setLoading(true);
            setError(null);
            const formData = new FormData();
            formData.append('lista_resurtido', archivo);

            try {
                const res = await axios.post(contabilidadRoutes.listaPreview(), formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                const data = res.data;
                if (!data?.success) {
                    throw new Error(data?.message || 'Error al leer el archivo.');
                }

                setHeaders(data.headers || []);
                setFilePath(data.file_path);
                setSinCabecera(!!data.sin_cabecera);
                const sugerido = data.mapeo_sugerido || configuracion?.mapeo_precios || {};
                setMapping(autoMapearHeaders(data.headers || [], {
                    sku: sugerido.sku || '',
                    precio_base: sugerido.precio_base || 'Bronce',
                    descripcion: sugerido.descripcion || '',
                }));
            } catch (e) {
                setError(e.response?.data?.message || e.message);
            } finally {
                setLoading(false);
            }
        };

        cargarPreview();
    }, [archivo, configuracion?.mapeo_precios]);

    const validarMapping = () => {
        if (!mapping.sku || !mapping.precio_base) {
            setError('Debes mapear SKU y columna de precio.');
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
            const res = await axios.post(contabilidadRoutes.previsualizarMapeo(), {
                file_path: filePath,
                mapping,
            });
            if (!res.data?.success) {
                throw new Error(res.data?.message || 'Error al previsualizar el mapeo.');
            }
            setMuestra(res.data.muestra || []);
        } catch (e) {
            setError(e.response?.data?.message || e.message);
        } finally {
            setPreviewLoading(false);
        }
    };

    const guardarMapeoPredeterminado = async () => {
        const res = await axios.put(contabilidadRoutes.configuracionUpdate(), {
            mapeo_precios: mapping,
        }, {
            headers: { Accept: 'application/json' },
        });
        if (!res.data?.success) {
            throw new Error(res.data?.message || 'No se pudo guardar el mapeo predeterminado.');
        }
    };

    const confirmar = async () => {
        if (!validarMapping()) return;
        setConfirmLoading(true);
        setError(null);
        try {
            if (guardarPredeterminado && puedeConfigurar) {
                await guardarMapeoPredeterminado();
            }
            const res = await axios.post(contabilidadRoutes.procesarLista(), {
                file_path: filePath,
                mapping,
            });
            if (!res.data?.success) {
                throw new Error(res.data?.message || 'No se pudo procesar la lista.');
            }
            onConfirmar({
                diccionario: res.data.data || {},
                mapping: res.data.mapping || mapping,
                nombreArchivo: archivo?.name || '',
            });
        } catch (e) {
            setError(e.response?.data?.message || e.message);
            setConfirmLoading(false);
        }
    };

    const modal = (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={loading || confirmLoading} message={confirmLoading ? 'Procesando_' : 'Leyendo archivo_'} />
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2rem] p-6 md:p-8 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-xl font-black italic uppercase theme-text-main flex items-center gap-2">
                        <FileSpreadsheet className="w-6 h-6 text-[var(--color-primario)]" />
                        Mapeo de columnas
                    </h2>
                    <button type="button" onClick={onCerrar} className="p-2 theme-text-muted hover:theme-text-main">
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
                                        <option value="">{key === 'descripcion' ? '— Opcional —' : '— Seleccionar columna —'}</option>
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
                                            <th className="p-3 text-left">Descripción</th>
                                            <th className="p-3 text-left">Precio</th>
                                            <th className="p-3 text-left">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {muestra.map((fila, i) => (
                                            <tr key={i} className="border-b theme-border/50">
                                                <td className="p-3 font-bold theme-text-main">{fila.sku || '—'}</td>
                                                <td className="p-3 theme-text-main">{fila.descripcion || '—'}</td>
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

                        {puedeConfigurar && (
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
                                onClick={onCerrar}
                                className="flex-1 py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-all"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={confirmar}
                                disabled={confirmLoading || !mapping.sku || !mapping.precio_base}
                                className={`${BTN_PRIMARY} flex-1 py-3 flex items-center justify-center gap-2 disabled:opacity-50`}
                                style={BTN_PRIMARY_STYLE}
                            >
                                <ArrowRight className="w-4 h-4" />
                                Cargar lista
                            </button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );

    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
