import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useGoogleMapsLoader } from '@/hooks/useGoogleMapsLoader';
import {
    Map, Layers, Upload, Download, Trash2, AlertTriangle,
    CheckCircle2, Ban, Clock, Pencil, X, Save, Plus
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import MapaEditorZonas from '@/Components/Entregas/MapaEditorZonas';
import { rutasGoogleAAnilloGeoJson } from '@/utils/poligonoGeoJson';
import { extraerMensajeErrorAxios } from '@/utils/extraerMensajeErrorAxios';

const TABS = [
    { id: 'principales', label: 'Principales', icon: Map },
    { id: 'restringidas', label: 'Restringidas', icon: Ban },
    { id: 'periferia', label: 'Periferia', icon: Clock },
];

const FORM_INICIAL = {
    nombre: '',
    color_hex: '#3B82F6',
    costo_base: '50',
    zona_referencia_id: '',
};

export default function MapaLogistico({
    auth,
    configuracion,
    googleApiKey,
    zonas_principales = [],
    zonas_restringidas = [],
    zonas_periferia = [],
}) {
    const [tabActiva, setTabActiva] = useState('principales');
    const [cargando, setCargando] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [error, setError] = useState(null);
    const [modoEditor, setModoEditor] = useState('ver');
    const [zonaSeleccionadaId, setZonaSeleccionadaId] = useState(null);
    const [borradorPaths, setBorradorPaths] = useState(null);
    const [pathsEditados, setPathsEditados] = useState(null);
    const [formZona, setFormZona] = useState(FORM_INICIAL);
    const inputImportRef = useRef(null);
    const mapaEditorRef = useRef(null);

    const { flash } = usePage().props;

    const { isLoaded, loadError } = useGoogleMapsLoader(googleApiKey);

    const zonasActivas = useMemo(() => {
        if (tabActiva === 'principales') return zonas_principales;
        if (tabActiva === 'restringidas') return zonas_restringidas;
        return zonas_periferia;
    }, [tabActiva, zonas_principales, zonas_restringidas, zonas_periferia]);

    const zonasVisibles = useMemo(
        () => zonasActivas.filter((z) => z.activo !== false),
        [zonasActivas]
    );

    useEffect(() => {
        if (flash?.success) {
            setMensaje(flash.success);
        }
    }, [flash?.success]);

    const normalizarCoordenadas = (paths) => (
        paths?.map((p) => ({ lat: Number(p.lat), lng: Number(p.lng) })) ?? null
    );

    const recargar = () => router.reload({ preserveScroll: true });

    const cancelarEdicion = () => {
        setModoEditor('ver');
        setZonaSeleccionadaId(null);
        setBorradorPaths(null);
        setPathsEditados(null);
        setFormZona(FORM_INICIAL);
    };

    useEffect(() => {
        cancelarEdicion();
    }, [tabActiva]);

    const iniciarCrear = () => {
        cancelarEdicion();
        setModoEditor('crear');
        setFormZona({
            ...FORM_INICIAL,
            nombre: tabActiva === 'periferia' ? 'PERIFERIA ZONA ' : tabActiva === 'principales' ? 'ZONA ' : 'ZONA RESTRINGIDA ',
        });
    };

    const iniciarEditar = (zona) => {
        setModoEditor('editar');
        setZonaSeleccionadaId(zona.id);
        setBorradorPaths(null);
        setPathsEditados(zona.rutas_formateadas);
        setFormZona({
            nombre: zona.nombre,
            color_hex: zona.color_hex || '#3B82F6',
            costo_base: String(zona.costo_base ?? 50),
            zona_referencia_id: zona.zona_referencia_id ? String(zona.zona_referencia_id) : '',
        });
    };

    const exportarGeoJson = () => {
        window.location.href = route('admin.mapa_logistico.exportar', tabActiva);
    };

    const abrirImportador = () => inputImportRef.current?.click();

    const importarGeoJson = async (event) => {
        const archivo = event.target.files?.[0];
        event.target.value = '';
        if (!archivo) return;

        setCargando(true);
        setError(null);
        setMensaje(null);

        try {
            const geojson = JSON.parse(await archivo.text());
            const { data } = await axios.post(route('admin.mapa_logistico.importar', tabActiva), { geojson });
            setMensaje(data.mensaje);
            recargar();
        } catch (err) {
            setError(extraerMensajeErrorAxios(err, 'No se pudo importar el GeoJSON. Verifica el formato.'));
        } finally {
            setCargando(false);
        }
    };

    const eliminarZona = async (id) => {
        if (!confirm('¿Eliminar esta zona del catálogo?')) return;

        setCargando(true);
        setError(null);
        try {
            await axios.delete(route('admin.mapa_logistico.eliminar', { tipo: tabActiva, id }));
            if (zonaSeleccionadaId === id) cancelarEdicion();
            recargar();
        } catch (err) {
            setError(extraerMensajeErrorAxios(err, 'No se pudo eliminar la zona.'));
        } finally {
            setCargando(false);
        }
    };

    const actualizarReferenciaPeriferia = async (id, zonaReferenciaId) => {
        setCargando(true);
        setError(null);
        try {
            await axios.put(route('admin.mapa_logistico.periferia.update', id), {
                zona_referencia_id: zonaReferenciaId,
            });
            setMensaje('Horario de referencia actualizado.');
            recargar();
        } catch (err) {
            setError(extraerMensajeErrorAxios(err, 'No se pudo actualizar la referencia.'));
        } finally {
            setCargando(false);
        }
    };

    const toggleActivo = async (id, activoActual) => {
        setCargando(true);
        setError(null);
        try {
            await axios.put(route('admin.mapa_logistico.toggle', { tipo: tabActiva, id }), {
                activo: !activoActual,
            });
            recargar();
        } catch (err) {
            setError(extraerMensajeErrorAxios(err, 'No se pudo cambiar el estado.'));
        } finally {
            setCargando(false);
        }
    };

    const guardarPoligono = () => {
        const coordenadas = normalizarCoordenadas(
            mapaEditorRef.current?.obtenerPathsActuales()
            ?? (modoEditor === 'crear' ? borradorPaths : pathsEditados)
        );

        if (!rutasGoogleAAnilloGeoJson(coordenadas)) {
            setError('El polígono debe tener al menos 3 vértices.');
            return;
        }

        if (!formZona.nombre.trim()) {
            setError('Ingresa un nombre para la zona.');
            return;
        }

        if (tabActiva === 'periferia' && !formZona.zona_referencia_id) {
            setError('Selecciona la zona de horario de referencia.');
            return;
        }

        setCargando(true);
        setError(null);
        setMensaje(null);

        const payload = {
            nombre: formZona.nombre.trim(),
            coordenadas,
        };

        if (tabActiva === 'principales') {
            payload.color_hex = formZona.color_hex;
            payload.costo_base = parseFloat(formZona.costo_base) || 50;
        }

        if (tabActiva === 'periferia') {
            payload.zona_referencia_id = formZona.zona_referencia_id;
        }

        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                setMensaje(modoEditor === 'crear' ? 'Zona creada correctamente.' : 'Polígono actualizado correctamente.');
                cancelarEdicion();
            },
            onError: (errors) => {
                const texto = Object.values(errors || {}).flat().join(' ');
                setError(texto || 'No se pudo guardar el polígono.');
            },
            onFinish: () => setCargando(false),
        };

        if (modoEditor === 'crear') {
            router.post(route('admin.mapa_logistico.store', tabActiva), payload, opciones);
        } else {
            router.put(
                route('admin.mapa_logistico.poligono.update', { tipo: tabActiva, id: zonaSeleccionadaId }),
                payload,
                opciones
            );
        }
    };

    const deshacerVertice = () => {
        if (!borradorPaths?.length) return;
        setBorradorPaths(borradorPaths.slice(0, -1));
    };

    const limpiarBorrador = () => setBorradorPaths(null);

    const editorActivo = modoEditor !== 'ver';
    const puedeGuardar = modoEditor === 'crear' ? Boolean(borradorPaths?.length >= 3) : Boolean(pathsEditados?.length >= 3);

    return (
        <AppLayout auth={auth}>
            <Head title="Mapa Logístico | GELIANV" />

            <input
                ref={inputImportRef}
                type="file"
                accept=".json,application/json"
                className="hidden"
                onChange={importarGeoJson}
            />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-6">
                <header className="theme-surface border theme-border rounded-[2rem] p-6 md:p-8">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-3 mb-2">
                                <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                                <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted">
                                    Entregas · Fase B
                                </span>
                            </div>
                            <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                                Mapa <span style={{ color: 'var(--color-primario)' }}>Logístico</span>_
                            </h1>
                            <p className="text-sm theme-text-muted mt-2 max-w-2xl">
                                Dibuja y edita polígonos directamente en el mapa. Importa/exporta GeoJSON. La periferia solo asigna horarios.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {!editorActivo && (
                                <button
                                    type="button"
                                    onClick={iniciarCrear}
                                    disabled={cargando}
                                    className="flex items-center gap-2 px-4 py-3 rounded-full text-white text-[10px] font-black uppercase tracking-widest hover:scale-[1.02] transition-transform outline-none disabled:opacity-50"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Plus className="w-4 h-4" />
                                    Dibujar zona
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={exportarGeoJson}
                                disabled={cargando || editorActivo}
                                className="flex items-center gap-2 px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main hover:scale-[1.02] transition-transform outline-none disabled:opacity-50"
                            >
                                <Download className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                Exportar
                            </button>
                            <button
                                type="button"
                                onClick={abrirImportador}
                                disabled={cargando || editorActivo}
                                className="flex items-center gap-2 px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main hover:scale-[1.02] transition-transform outline-none disabled:opacity-50"
                            >
                                <Upload className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                Importar
                            </button>
                        </div>
                    </div>
                </header>

                {(mensaje || error) && (
                    <div className={`p-4 rounded-2xl border flex items-start gap-3 ${error ? 'border-red-400/50 bg-red-500/10' : 'border-emerald-400/50 bg-emerald-500/10'}`}>
                        {error ? <AlertTriangle className="w-5 h-5 text-red-500 shrink-0" /> : <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />}
                        <p className={`text-sm font-bold m-0 ${error ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'}`}>
                            {error || mensaje}
                        </p>
                    </div>
                )}

                {editorActivo && (
                    <div className="theme-surface border theme-border rounded-[2rem] p-5 md:p-6 space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0 flex items-center gap-2">
                                <Pencil className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                {modoEditor === 'crear' ? 'Nueva zona' : 'Editar polígono'}
                            </h3>
                            <button type="button" onClick={cancelarEdicion} className="p-2 rounded-xl theme-element border theme-border outline-none">
                                <X className="w-4 h-4 theme-text-muted" />
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div className="sm:col-span-2">
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-1">Nombre</label>
                                <input
                                    value={formZona.nombre}
                                    onChange={(e) => setFormZona((f) => ({ ...f, nombre: e.target.value }))}
                                    className="w-full mt-1 px-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold theme-text-main outline-none"
                                    placeholder="ZONA 1 / PERIFERIA ZONA 2..."
                                />
                            </div>

                            {tabActiva === 'principales' && (
                                <>
                                    <div>
                                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-1">Color</label>
                                        <input
                                            type="color"
                                            value={formZona.color_hex}
                                            onChange={(e) => setFormZona((f) => ({ ...f, color_hex: e.target.value }))}
                                            className="w-full mt-1 h-10 theme-surface border theme-border rounded-xl cursor-pointer"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-1">Costo base</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={formZona.costo_base}
                                            onChange={(e) => setFormZona((f) => ({ ...f, costo_base: e.target.value }))}
                                            className="w-full mt-1 px-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold theme-text-main outline-none"
                                        />
                                    </div>
                                </>
                            )}

                            {tabActiva === 'periferia' && (
                                <div className="sm:col-span-2">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-1">Horario referencia</label>
                                    <select
                                        value={formZona.zona_referencia_id}
                                        onChange={(e) => setFormZona((f) => ({ ...f, zona_referencia_id: e.target.value }))}
                                        className="w-full mt-1 px-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold theme-text-main outline-none"
                                    >
                                        <option value="">Selecciona ZONA 1/2/3...</option>
                                        {zonas_principales.filter((z) => z.activo !== false).map((z) => (
                                            <option key={z.id} value={z.id}>{z.nombre}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {modoEditor === 'crear' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={deshacerVertice}
                                        disabled={!borradorPaths?.length || cargando}
                                        className="px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main outline-none disabled:opacity-50"
                                    >
                                        Deshacer vértice
                                    </button>
                                    <button
                                        type="button"
                                        onClick={limpiarBorrador}
                                        disabled={!borradorPaths?.length || cargando}
                                        className="px-4 py-3 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main outline-none disabled:opacity-50"
                                    >
                                        Limpiar dibujo
                                    </button>
                                </>
                            )}
                            <button
                                type="button"
                                onClick={guardarPoligono}
                                disabled={cargando || !puedeGuardar}
                                className="flex items-center gap-2 px-5 py-3 rounded-full text-white text-[10px] font-black uppercase tracking-widest outline-none disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" />
                                Guardar polígono
                            </button>
                            <button
                                type="button"
                                onClick={cancelarEdicion}
                                className="px-5 py-3 rounded-full theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main outline-none"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                )}

                <div className="theme-surface border theme-border rounded-[2rem] p-2 flex flex-wrap gap-2">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setTabActiva(tab.id)}
                            disabled={editorActivo}
                            className={`flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-[1.25rem] text-[10px] font-black uppercase tracking-widest transition-all outline-none min-w-[140px] disabled:opacity-50 ${tabActiva === tab.id ? 'text-white shadow-lg' : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" />
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <section className="theme-surface border theme-border rounded-[2rem] overflow-hidden flex flex-col min-h-[420px]">
                        <div className="p-5 border-b theme-border flex items-center gap-2">
                            <Layers className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                                Catálogo — {TABS.find((t) => t.id === tabActiva)?.label}
                            </h2>
                            <span className="ml-auto text-[10px] font-bold theme-text-muted">{zonasActivas.length} zona(s)</span>
                        </div>

                        <div className="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3">
                            {zonasActivas.length === 0 && (
                                <p className="text-sm theme-text-muted text-center py-8 italic">
                                    Sin zonas. Dibuja una nueva o importa GeoJSON.
                                </p>
                            )}

                            {zonasActivas.map((zona) => (
                                <div
                                    key={zona.id}
                                    className={`p-4 rounded-2xl border theme-border theme-element transition-all ${zona.activo === false ? 'opacity-50' : ''} ${zonaSeleccionadaId === zona.id ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-black theme-text-main truncate">{zona.nombre}</p>
                                            {tabActiva === 'principales' && (
                                                <p className="text-[10px] theme-text-muted mt-1">
                                                    Costo: ${zona.costo_base} · {zona.color_hex}
                                                </p>
                                            )}
                                            {tabActiva === 'periferia' && (
                                                <p className="text-[10px] theme-text-muted mt-1">
                                                    Horario ref.: {zona.zona_referencia_nombre || 'Sin asignar'}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 shrink-0">
                                            <button
                                                type="button"
                                                title="Editar polígono"
                                                disabled={editorActivo}
                                                onClick={() => iniciarEditar(zona)}
                                                className="p-2 rounded-xl theme-surface border theme-border theme-text-muted hover:theme-text-main transition-colors outline-none disabled:opacity-40"
                                            >
                                                <Pencil className="w-4 h-4" />
                                            </button>
                                            <button
                                                type="button"
                                                title={zona.activo === false ? 'Activar' : 'Desactivar'}
                                                onClick={() => toggleActivo(zona.id, zona.activo !== false)}
                                                disabled={editorActivo}
                                                className="p-2 rounded-xl theme-surface border theme-border theme-text-muted hover:theme-text-main transition-colors outline-none disabled:opacity-40"
                                            >
                                                {zona.activo === false ? 'Off' : 'On'}
                                            </button>
                                            <button
                                                type="button"
                                                title="Eliminar"
                                                onClick={() => eliminarZona(zona.id)}
                                                disabled={editorActivo}
                                                className="p-2 rounded-xl border border-red-400/30 text-red-500 hover:bg-red-500/10 transition-colors outline-none disabled:opacity-40"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {tabActiva === 'periferia' && !editorActivo && (
                                        <div className="mt-3">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-1">
                                                Zona de horario
                                            </label>
                                            <select
                                                value={zona.zona_referencia_id || ''}
                                                onChange={(e) => actualizarReferenciaPeriferia(zona.id, e.target.value)}
                                                className="w-full mt-1 px-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold theme-text-main outline-none"
                                            >
                                                <option value="">Selecciona...</option>
                                                {zonas_principales.filter((z) => z.activo !== false).map((z) => (
                                                    <option key={z.id} value={z.id}>{z.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="theme-surface border theme-border rounded-[2rem] overflow-hidden min-h-[480px] relative">
                        <div className="absolute inset-0">
                            {!googleApiKey ? (
                                <div className="flex flex-col items-center justify-center h-full gap-3 p-6 text-center">
                                    <AlertTriangle className="w-10 h-10 text-red-500" />
                                    <p className="text-sm font-bold theme-text-main">API Key de Google Maps no configurada.</p>
                                </div>
                            ) : (
                                <MapaEditorZonas
                                    ref={mapaEditorRef}
                                    configuracion={configuracion}
                                    tipoCapa={tabActiva}
                                    zonas={zonasVisibles}
                                    modoEditor={modoEditor}
                                    zonaSeleccionadaId={zonaSeleccionadaId}
                                    borradorPaths={borradorPaths}
                                    onBorradorChange={setBorradorPaths}
                                    onPathsEditados={setPathsEditados}
                                    isLoaded={isLoaded}
                                    loadError={loadError}
                                />
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
