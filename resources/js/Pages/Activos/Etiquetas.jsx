import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Download, Eye, Tag } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import ModalVistaPreviaResponsiva from './Partials/ModalVistaPreviaResponsiva';
import VistaPreviaPdfEtiquetas from './Partials/VistaPreviaPdfEtiquetas';
import {
    calcularGridPorHoja,
    cmToMm,
    clampGapCm,
    DEFAULT_ANCHO_CM,
    DEFAULT_ANCHO_MM,
    DEFAULT_GAP_CM,
    dimensionesDesdeCm,
    formatCm,
    layoutOpcionesDesdeProps,
    MAX_ANCHO_CM,
    MAX_GAP_CM,
    MIN_ANCHO_CM,
    mmToCm,
    parseCmInput,
    paramsEtiquetasPdf,
} from './Partials/etiquetaLayout';
import { getActivosCardClass, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS, INPUT_CLASS, SELECT_CLASS, LABEL_CLASS } from './Partials/activosFormStyles';

export default function Etiquetas({
    auth,
    tipos = [],
    categorias = [],
    departamentos = [],
    usuarios = [],
    filtros: filtrosIniciales = {},
    conteo: conteoInicial = 0,
    layout = {},
    max_etiquetas = 500,
    tamanos_hoja = [],
}) {
    const layoutOpcionesIniciales = layoutOpcionesDesdeProps(layout);
    const layoutInicial = dimensionesDesdeCm(
        mmToCm(layout.ancho_mm || DEFAULT_ANCHO_MM),
        layoutOpcionesIniciales.proporcion,
    );

    const [filtros, setFiltros] = useState({
        busqueda: filtrosIniciales.busqueda || '',
        catalogo_tipo_activo_id: filtrosIniciales.catalogo_tipo_activo_id || '',
        catalogo_categoria_activo_id: filtrosIniciales.catalogo_categoria_activo_id || '',
        departamento_id: filtrosIniciales.departamento_id || '',
        estado: filtrosIniciales.estado || '',
        responsable_user_ids: filtrosIniciales.responsable_user_ids || [],
    });
    const [anchoCmInput, setAnchoCmInput] = useState(formatCm(layoutInicial.anchoCm));
    const [altoCmInput, setAltoCmInput] = useState(formatCm(layoutInicial.altoCm));
    const [proporcion, setProporcion] = useState(layoutOpcionesIniciales.proporcion);
    const [orientacionHoja, setOrientacionHoja] = useState(layoutOpcionesIniciales.orientacion_hoja);
    const [orientacionEtiqueta, setOrientacionEtiqueta] = useState(layoutOpcionesIniciales.orientacion_etiqueta);
    const [tamanioHoja, setTamanioHoja] = useState(layoutOpcionesIniciales.tamanio_hoja);
    const [gapCmInput, setGapCmInput] = useState(formatCm(mmToCm(layoutOpcionesIniciales.gap_mm)));
    const [conteo, setConteo] = useState(conteoInicial);
    const [cargandoConteo, setCargandoConteo] = useState(false);
    const [pdfPreviewUrl, setPdfPreviewUrl] = useState(null);
    const [cargandoPreview, setCargandoPreview] = useState(false);
    const [previewError, setPreviewError] = useState(null);
    const [previewPdfModal, setPreviewPdfModal] = useState(null);

    const debounceRef = useRef(null);
    const abortConteoRef = useRef(null);
    const abortPreviewRef = useRef(null);
    const pdfPreviewUrlRef = useRef(null);

    const layoutOpciones = useMemo(() => ({
        proporcion,
        tamanio_hoja: tamanioHoja,
        orientacion_hoja: orientacionHoja,
        orientacion_etiqueta: orientacionEtiqueta,
        gap_mm: cmToMm(clampGapCm(parseCmInput(gapCmInput) ?? DEFAULT_GAP_CM)),
    }), [proporcion, tamanioHoja, orientacionHoja, orientacionEtiqueta, gapCmInput]);

    const dimensionesEfectivas = useMemo(() => {
        const cm = parseCmInput(anchoCmInput);
        if (cm === null || cm <= 0) {
            return dimensionesDesdeCm(DEFAULT_ANCHO_CM, proporcion);
        }
        return dimensionesDesdeCm(cm, proporcion);
    }, [anchoCmInput, proporcion]);

    const grid = calcularGridPorHoja(
        dimensionesEfectivas.anchoMm,
        dimensionesEfectivas.altoMm,
        layoutOpciones,
    );
    const excedeLimite = conteo > max_etiquetas;
    const medidasFueraDeRango = useMemo(() => {
        const ancho = parseCmInput(anchoCmInput);
        if (ancho === null) return false;
        return ancho < MIN_ANCHO_CM || ancho > MAX_ANCHO_CM;
    }, [anchoCmInput]);

    const pdfParams = useMemo(
        () => paramsEtiquetasPdf(
            filtros,
            dimensionesEfectivas.anchoMm,
            dimensionesEfectivas.altoMm,
            layoutOpciones,
        ).toString(),
        [filtros, dimensionesEfectivas, layoutOpciones],
    );

    const liberarPreviewUrl = useCallback((url) => {
        if (url) URL.revokeObjectURL(url);
    }, []);

    const recargarConteo = useCallback(async (nextFiltros, dims, nextLayout) => {
        if (!dims) return;

        if (abortConteoRef.current) abortConteoRef.current.abort();
        const controller = new AbortController();
        abortConteoRef.current = controller;
        setCargandoConteo(true);

        try {
            const params = paramsEtiquetasPdf(nextFiltros, dims.anchoMm, dims.altoMm, nextLayout);
            const res = await axios.get(route('activos.etiquetas.contar'), {
                params,
                signal: controller.signal,
            });
            setConteo(res.data.total ?? 0);
        } catch (err) {
            if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                console.error(err);
            }
        } finally {
            if (abortConteoRef.current === controller) {
                setCargandoConteo(false);
            }
        }
    }, []);

    const recargarPreviewPdf = useCallback(async (nextFiltros, dims, nextLayout, totalEsperado) => {
        if (!dims || totalEsperado <= 0 || totalEsperado > max_etiquetas) {
            liberarPreviewUrl(pdfPreviewUrlRef.current);
            pdfPreviewUrlRef.current = null;
            setPdfPreviewUrl(null);
            setPreviewError(null);
            setCargandoPreview(false);
            return;
        }

        if (abortPreviewRef.current) abortPreviewRef.current.abort();
        const controller = new AbortController();
        abortPreviewRef.current = controller;
        setCargandoPreview(true);
        setPreviewError(null);

        try {
            const params = paramsEtiquetasPdf(nextFiltros, dims.anchoMm, dims.altoMm, nextLayout);
            const res = await axios.get(route('activos.etiquetas.vista_previa'), {
                params,
                responseType: 'blob',
                signal: controller.signal,
            });

            if (res.data?.type && !res.data.type.includes('pdf')) {
                throw new Error('No se pudo generar el PDF de vista previa.');
            }

            liberarPreviewUrl(pdfPreviewUrlRef.current);
            const nextUrl = URL.createObjectURL(res.data);
            pdfPreviewUrlRef.current = nextUrl;
            setPdfPreviewUrl(nextUrl);
        } catch (err) {
            if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                setPreviewError('No se pudo generar la vista previa. Verifica filtros y medidas.');
                liberarPreviewUrl(pdfPreviewUrlRef.current);
                pdfPreviewUrlRef.current = null;
                setPdfPreviewUrl(null);
            }
        } finally {
            if (abortPreviewRef.current === controller) {
                setCargandoPreview(false);
            }
        }
    }, [liberarPreviewUrl, max_etiquetas]);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);

        debounceRef.current = setTimeout(async () => {
            await recargarConteo(filtros, dimensionesEfectivas, layoutOpciones);
        }, 400);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [filtros, dimensionesEfectivas, layoutOpciones, recargarConteo]);

    useEffect(() => {
        const timer = setTimeout(() => {
            recargarPreviewPdf(filtros, dimensionesEfectivas, layoutOpciones, conteo);
        }, 700);

        return () => clearTimeout(timer);
    }, [filtros, dimensionesEfectivas, layoutOpciones, conteo, recargarPreviewPdf]);

    useEffect(() => () => {
        liberarPreviewUrl(pdfPreviewUrlRef.current);
    }, [liberarPreviewUrl]);

    const setFiltro = (key, value) => {
        setFiltros((prev) => ({ ...prev, [key]: value }));
    };

    const toggleResponsable = (userId) => {
        setFiltros((prev) => {
            const ids = prev.responsable_user_ids || [];
            const nextIds = ids.includes(userId)
                ? ids.filter((id) => id !== userId)
                : [...ids, userId];
            return { ...prev, responsable_user_ids: nextIds, responsable_user_id: '' };
        });
    };

    const onAnchoInputChange = (value) => {
        setAnchoCmInput(value);
        const cm = parseFloat(String(value).replace(',', '.'));
        if (Number.isFinite(cm) && cm > 0) {
            setAltoCmInput(formatCm(proporcion === '1:1' ? cm : cm / 2));
        }
    };

    const onAltoInputChange = (value) => {
        setAltoCmInput(value);
        const cm = parseFloat(String(value).replace(',', '.'));
        if (Number.isFinite(cm) && cm > 0) {
            setAnchoCmInput(formatCm(proporcion === '1:1' ? cm : cm * 2));
        }
    };

    const onProporcionChange = (value) => {
        setProporcion(value);
        const cm = parseCmInput(anchoCmInput);
        if (cm !== null && cm > 0) {
            setAltoCmInput(formatCm(value === '1:1' ? cm : cm / 2));
        }
    };

    const normalizarMedidas = () => {
        const cm = parseCmInput(anchoCmInput);
        const dims = (cm !== null && cm > 0)
            ? dimensionesDesdeCm(cm, proporcion)
            : dimensionesDesdeCm(DEFAULT_ANCHO_CM, proporcion);
        setAnchoCmInput(formatCm(dims.anchoCm));
        setAltoCmInput(formatCm(dims.altoCm));
    };

    const normalizarGap = () => {
        const cm = parseCmInput(gapCmInput);
        setGapCmInput(formatCm(clampGapCm(cm ?? DEFAULT_GAP_CM)));
    };

    const limpiarFiltros = () => {
        setFiltros({
            busqueda: '',
            catalogo_tipo_activo_id: '',
            catalogo_categoria_activo_id: '',
            departamento_id: '',
            estado: '',
            responsable_user_ids: [],
        });
    };

    const abrirModalPdf = () => {
        if (!pdfPreviewUrl || !pdfParams) return;
        setPreviewPdfModal({
            previewUrl: pdfPreviewUrl,
            downloadUrl: `${route('activos.etiquetas.descargar')}?${pdfParams}`,
        });
    };

    const card = getActivosCardClass('p-6 md:p-8');

    return (
        <AppLayout auth={auth}>
            <Head title="Etiquetas de activos" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${card} flex flex-col md:flex-row justify-between items-start md:items-center gap-4`}>
                    <div>
                        <Link href={route('activos.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main mb-3">
                            <ArrowLeft className="w-3.5 h-3.5" /> Volver al listado
                        </Link>
                        <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0 flex items-center gap-3">
                            <Tag className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            Etiquetas de <span style={{ color: 'var(--color-primario)' }}>Activos</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                            PDF listo para imprimir · separación y orientación configurables
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={conteo === 0 || excedeLimite || !pdfPreviewUrl}
                            onClick={abrirModalPdf}
                            className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact disabled:opacity-40`}
                        >
                            <Eye className="w-4 h-4 shrink-0" /> Pantalla completa
                        </button>
                        <a
                            href={conteo > 0 && !excedeLimite ? `${route('activos.etiquetas.descargar')}?${pdfParams}` : undefined}
                            className={`${BTN_PRIMARY_CLASS} theme-btn-primary--compact inline-flex items-center gap-2 ${conteo === 0 || excedeLimite ? 'pointer-events-none opacity-40' : ''}`}
                        >
                            <Download className="w-4 h-4 shrink-0" /> Descargar
                        </a>
                    </div>
                </header>

                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 md:gap-8">
                    <div className={`${card} space-y-5`}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Filtros</h2>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={LABEL_CLASS}>Búsqueda</label>
                                <input
                                    type="search"
                                    value={filtros.busqueda}
                                    onChange={(e) => setFiltro('busqueda', e.target.value)}
                                    className={INPUT_CLASS}
                                    placeholder="Folio, nombre, serie..."
                                />
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Departamento</label>
                                <select value={filtros.departamento_id} onChange={(e) => setFiltro('departamento_id', e.target.value)} className={SELECT_CLASS}>
                                    <option value="">Todos</option>
                                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Tipo</label>
                                <select value={filtros.catalogo_tipo_activo_id} onChange={(e) => setFiltro('catalogo_tipo_activo_id', e.target.value)} className={SELECT_CLASS}>
                                    <option value="">Todos</option>
                                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Categoría</label>
                                <select value={filtros.catalogo_categoria_activo_id} onChange={(e) => setFiltro('catalogo_categoria_activo_id', e.target.value)} className={SELECT_CLASS}>
                                    <option value="">Todas</option>
                                    {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                                </select>
                            </div>
                            <div className="sm:col-span-2">
                                <label className={LABEL_CLASS}>Estado</label>
                                <select value={filtros.estado} onChange={(e) => setFiltro('estado', e.target.value)} className={SELECT_CLASS}>
                                    <option value="">Activos operativos (sin baja)</option>
                                    <option value="disponible">Disponible</option>
                                    <option value="asignado">Asignado</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className={LABEL_CLASS}>Responsables (múltiple)</label>
                            <div className="flex flex-wrap gap-2 max-h-40 overflow-y-auto custom-scrollbar p-2 border theme-border rounded-xl">
                                {usuarios.map((u) => (
                                    <button
                                        key={u.id}
                                        type="button"
                                        onClick={() => toggleResponsable(u.id)}
                                        className={`px-3 py-1.5 rounded-lg text-[9px] font-black uppercase border transition-colors ${
                                            (filtros.responsable_user_ids || []).includes(u.id)
                                                ? 'text-white border-[var(--color-primario)]'
                                                : 'theme-border theme-element theme-text-muted'
                                        }`}
                                        style={(filtros.responsable_user_ids || []).includes(u.id) ? { backgroundColor: 'var(--color-primario)' } : undefined}
                                    >
                                        {u.name}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4 pt-2 border-t theme-border">
                            <div className="col-span-2">
                                <label className={LABEL_CLASS}>Proporción</label>
                                <select value={proporcion} onChange={(e) => onProporcionChange(e.target.value)} className={SELECT_CLASS}>
                                    <option value="2:1">Rectangular 2:1 (predeterminada)</option>
                                    <option value="1:1">Cuadrada 1:1</option>
                                </select>
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Ancho (cm)</label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={anchoCmInput}
                                    onChange={(e) => onAnchoInputChange(e.target.value)}
                                    onBlur={normalizarMedidas}
                                    className={INPUT_CLASS}
                                    placeholder="10"
                                />
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Alto (cm)</label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={altoCmInput}
                                    onChange={(e) => onAltoInputChange(e.target.value)}
                                    onBlur={normalizarMedidas}
                                    className={INPUT_CLASS}
                                    placeholder={proporcion === '1:1' ? '10' : '5'}
                                />
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Separación entre etiquetas (cm)</label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={gapCmInput}
                                    onChange={(e) => setGapCmInput(e.target.value)}
                                    onBlur={normalizarGap}
                                    className={INPUT_CLASS}
                                    placeholder="0"
                                />
                                <p className="text-[9px] theme-text-muted mt-1 m-0">0 = pegadas · máx. {MAX_GAP_CM} cm</p>
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Tamaño de hoja</label>
                                <select value={tamanioHoja} onChange={(e) => setTamanioHoja(e.target.value)} className={SELECT_CLASS}>
                                    {(tamanos_hoja.length ? tamanos_hoja : [
                                        { value: 'a4', label: 'A4' },
                                        { value: 'carta', label: 'Carta (Letter)' },
                                        { value: 'oficio', label: 'Oficio (Legal)' },
                                    ]).map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={LABEL_CLASS}>Orientación de hoja</label>
                                <select value={orientacionHoja} onChange={(e) => setOrientacionHoja(e.target.value)} className={SELECT_CLASS}>
                                    <option value="landscape">Horizontal (apaisado)</option>
                                    <option value="portrait">Vertical (retrato)</option>
                                </select>
                            </div>
                            <div className="col-span-2">
                                <label className={LABEL_CLASS}>Orientación de etiqueta en hoja</label>
                                <select value={orientacionEtiqueta} onChange={(e) => setOrientacionEtiqueta(e.target.value)} className={SELECT_CLASS}>
                                    <option value="horizontal">Horizontal (QR a la izquierda)</option>
                                    <option value="vertical">Vertical (rotada 90°)</option>
                                </select>
                            </div>
                            <p className="col-span-2 text-[10px] theme-text-muted m-0">
                                {MIN_ANCHO_CM}–{MAX_ANCHO_CM} cm de ancho · {grid.columnas}×{grid.filas} = {grid.por_pagina} etiquetas por hoja
                                {layoutOpciones.gap_mm > 0 ? ` · separación ${formatCm(mmToCm(layoutOpciones.gap_mm))} cm` : ' · sin separación'}
                            </p>
                            {medidasFueraDeRango && (
                                <p className="col-span-2 text-[10px] text-amber-600 dark:text-amber-400 m-0">
                                    Ancho fuera de rango; al salir del campo se ajustará automáticamente.
                                </p>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2 pt-2">
                            <button type="button" onClick={limpiarFiltros} className={`${BTN_SECONDARY_CLASS} theme-btn-primary--compact`}>
                                Limpiar filtros
                            </button>
                        </div>

                        <div className={`rounded-xl p-4 border ${excedeLimite ? 'border-red-500/40 bg-red-500/5' : 'theme-border theme-element'}`}>
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Resumen</p>
                            <p className="text-lg font-black theme-text-main m-0">
                                {cargandoConteo ? 'Calculando…' : `${conteo.toLocaleString('es-MX')} etiqueta${conteo !== 1 ? 's' : ''}`}
                            </p>
                            {excedeLimite && (
                                <p className="text-xs text-red-600 dark:text-red-400 mt-2 m-0">
                                    Máximo {max_etiquetas} por PDF. Acota los filtros.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className={card}>
                        <div className="flex items-center gap-2 mb-4">
                            <Eye className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Vista previa de impresión</h2>
                        </div>
                        <VistaPreviaPdfEtiquetas
                            pdfUrl={pdfPreviewUrl}
                            cargando={cargandoPreview}
                            error={previewError}
                            grid={grid}
                            tamanosHoja={tamanos_hoja}
                            conteo={conteo}
                            excedeLimite={excedeLimite}
                        />
                    </div>
                </div>
            </div>

            <ModalVistaPreviaResponsiva
                abierto={!!previewPdfModal}
                onCerrar={() => setPreviewPdfModal(null)}
                previewUrl={previewPdfModal?.previewUrl}
                downloadUrl={previewPdfModal?.downloadUrl}
                titulo="Vista previa — Etiquetas de activos"
            />
        </AppLayout>
    );
}
