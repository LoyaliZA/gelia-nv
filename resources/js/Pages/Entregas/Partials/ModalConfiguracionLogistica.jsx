import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useForm, Link } from '@inertiajs/react';
import {
    Settings2, X, Key, Map,
    DollarSign, Route, CheckCircle2, Layers
} from 'lucide-react';

export default function ModalConfiguracionLogistica({ configuracion, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        latitud_origen: configuracion?.latitud_origen || '',
        longitud_origen: configuracion?.longitud_origen || '',
        radio_tolerancia_km: configuracion?.radio_tolerancia_km || 12,
        tarifa_envio_extra: configuracion?.tarifa_envio_extra || 60,
        cobro_extra_por_km: !!configuracion?.cobro_extra_por_km,
        usar_api_distancia: !!configuracion?.usar_api_distancia,
        api_key_google: '',
        google_map_id: configuracion?.google_map_id || '',
        mostrar_zonas_principales: configuracion?.mostrar_zonas_principales ?? true,
        mostrar_zonas_restringidas: configuracion?.mostrar_zonas_restringidas ?? true,
        mostrar_zonas_periferia: configuracion?.mostrar_zonas_periferia ?? false,
        mostrar_radio_tolerancia: configuracion?.mostrar_radio_tolerancia ?? true,
    });

    useEffect(() => {
        const scrollPrevio = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = scrollPrevio;
        };
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        put('/entregas/configuracion', {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                window.location.reload();
            },
        });
    };

    return createPortal(
        <div
            className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in"
            onClick={onClose}
            role="dialog"
            aria-modal="true"
            aria-label="Parámetros del motor de entregas"
        >
            <div
                className="w-full max-w-4xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-6 md:p-10 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto custom-scrollbar"
                onClick={(e) => e.stopPropagation()}
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-5 right-5 md:top-6 md:right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl transition-all outline-none hover:scale-110 z-50"
                    aria-label="Cerrar configuración"
                >
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 mb-6 md:mb-8 pr-12">
                    <Settings2 className="w-8 h-8 shrink-0 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-xl md:text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                        Parámetros del Motor_
                    </h2>
                </div>

                <div className="mb-6 p-4 theme-element border theme-border rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-sm font-bold theme-text-main flex items-center gap-2">
                            <Layers className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            Editor de zonas (GeoJSON)
                        </p>
                        <p className="text-xs theme-text-muted mt-1">
                            Crear, dibujar e importar polígonos está en el Mapa Logístico.
                        </p>
                    </div>
                    <Link
                        href={route('admin.mapa_logistico.index')}
                        className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-full text-white text-[10px] font-black uppercase tracking-widest hover:scale-[1.02] transition-transform outline-none shrink-0"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Map className="w-4 h-4" />
                        Abrir Mapa Logístico
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-6 relative z-10">
                    <div className="md:col-span-2">
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-1">
                            API Key - Google Maps Cartography
                        </label>
                        <div className="flex items-center theme-element border theme-border rounded-xl px-4 py-1 focus-within:ring-2 focus-within:ring-[var(--color-primario)] transition-all">
                            <Key className="w-4 h-4 theme-text-muted shrink-0" />
                            <input
                                type="password"
                                value={data.api_key_google}
                                onChange={(e) => setData('api_key_google', e.target.value)}
                                className="w-full min-w-0 bg-transparent border-none p-3 text-sm font-bold theme-text-main outline-none focus:ring-0 placeholder:text-gray-400 placeholder:font-normal"
                                placeholder="Dejar en blanco para conservar la clave actual..."
                            />
                        </div>
                        {errors.api_key_google && <p className="text-xs text-red-500 mt-1 font-bold">{errors.api_key_google}</p>}
                    </div>

                    <div className="md:col-span-2">
                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-1">
                            Map ID — Marcadores avanzados (Google Cloud)
                        </label>
                        <div className="flex items-center theme-element border theme-border rounded-xl px-4 py-1 focus-within:ring-2 focus-within:ring-[var(--color-primario)] transition-all">
                            <Map className="w-4 h-4 theme-text-muted shrink-0" />
                            <input
                                type="text"
                                value={data.google_map_id}
                                onChange={(e) => setData('google_map_id', e.target.value)}
                                className="w-full min-w-0 bg-transparent border-none p-3 text-sm font-bold theme-text-main outline-none focus:ring-0 placeholder:text-gray-400 placeholder:font-normal"
                                placeholder="ej. a1b2c3d4e5f6g7h8 (Map ID vectorial)"
                            />
                        </div>
                        <p className="text-[10px] theme-text-muted mt-1.5 font-medium leading-relaxed ml-1">
                            Opcional. Si lo dejas vacío, el mapa usa el estilo monocromático integrado de la app.
                        </p>
                        {errors.google_map_id && <p className="text-xs text-red-500 mt-1 font-bold">{errors.google_map_id}</p>}
                    </div>

                    <div className="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 theme-element border theme-border rounded-2xl">
                        <div className="sm:col-span-2 flex items-center gap-2 mb-1">
                            <Map className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Punto Central Logístico (Km 0)</span>
                        </div>
                        <div>
                            <label className="block text-xs font-bold theme-text-muted mb-1">Latitud</label>
                            <input
                                type="number"
                                step="any"
                                value={data.latitud_origen}
                                onChange={(e) => setData('latitud_origen', e.target.value)}
                                className="w-full theme-surface border theme-border rounded-lg p-2.5 text-sm theme-text-main outline-none focus:ring-1 focus:ring-[var(--color-primario)]"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold theme-text-muted mb-1">Longitud</label>
                            <input
                                type="number"
                                step="any"
                                value={data.longitud_origen}
                                onChange={(e) => setData('longitud_origen', e.target.value)}
                                className="w-full theme-surface border theme-border rounded-lg p-2.5 text-sm theme-text-main outline-none focus:ring-1 focus:ring-[var(--color-primario)]"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-1">
                            <Route className="w-4 h-4 shrink-0" /> Tolerancia Máxima (Km)
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            value={data.radio_tolerancia_km}
                            onChange={(e) => setData('radio_tolerancia_km', e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                        />
                        {errors.radio_tolerancia_km && <p className="text-xs text-red-500 mt-1 font-bold">{errors.radio_tolerancia_km}</p>}
                    </div>

                    <div>
                        <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-1">
                            <DollarSign className="w-4 h-4 shrink-0" /> Tarifa Extra Fuera de Zona
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            value={data.tarifa_envio_extra}
                            onChange={(e) => setData('tarifa_envio_extra', e.target.value)}
                            className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                        />
                        {errors.tarifa_envio_extra && <p className="text-xs text-red-500 mt-1 font-bold">{errors.tarifa_envio_extra}</p>}
                    </div>

                    <div className="md:col-span-2 flex flex-col gap-3">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 theme-element border theme-border rounded-2xl">
                            <div className="min-w-0">
                                <p className="text-sm font-bold theme-text-main">Multiplicar tarifa extra por Kilómetro</p>
                                <p className="text-xs theme-text-muted mt-0.5">Si está inactivo, se cobrará una tarifa plana.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setData('cobro_extra_por_km', !data.cobro_extra_por_km)}
                                className="gelia-switch flex-shrink-0 self-start sm:self-center"
                                data-active={data.cobro_extra_por_km}
                            >
                                <div className="gelia-switch-thumb" />
                            </button>
                        </div>

                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 theme-element border theme-border rounded-2xl">
                            <div className="min-w-0">
                                <p className="text-sm font-bold theme-text-main">Usar Google Distance Matrix API</p>
                                <p className="text-xs theme-text-muted mt-0.5">Calcula distancia por calle. Consume cuota API.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setData('usar_api_distancia', !data.usar_api_distancia)}
                                className="gelia-switch flex-shrink-0 self-start sm:self-center"
                                data-active={data.usar_api_distancia}
                            >
                                <div className="gelia-switch-thumb" />
                            </button>
                        </div>
                    </div>

                    <div className="md:col-span-2 flex flex-col gap-3 p-4 theme-element border theme-border rounded-2xl">
                        <div className="flex items-center gap-2 mb-1">
                            <Layers className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Capas del mapa</span>
                        </div>
                        <p className="text-xs theme-text-muted mb-1 ml-1">
                            Controla qué capas se dibujan en el mapa. No afectan la lógica de cotización.
                        </p>

                        {[
                            { key: 'mostrar_zonas_principales', titulo: 'Zonas principales', descripcion: 'Polígonos comerciales ZONA 1, 2 y 3.' },
                            { key: 'mostrar_zonas_restringidas', titulo: 'Zonas restringidas', descripcion: 'Áreas sin cobertura por políticas de acceso.' },
                            { key: 'mostrar_zonas_periferia', titulo: 'Zonas periferia (horarios)', descripcion: 'Capa de asignación de horarios fuera de zona comercial.' },
                            { key: 'mostrar_radio_tolerancia', titulo: 'Radio de cobertura', descripcion: 'Círculo del límite máximo de servicio desde el km 0.' },
                        ].map(({ key, titulo, descripcion }) => (
                            <div
                                key={key}
                                className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 theme-surface border theme-border rounded-xl"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-bold theme-text-main">{titulo}</p>
                                    <p className="text-xs theme-text-muted mt-0.5">{descripcion}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setData(key, !data[key])}
                                    className="gelia-switch flex-shrink-0 self-start sm:self-center"
                                    data-active={data[key]}
                                >
                                    <div className="gelia-switch-thumb" />
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="md:col-span-2 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-[1.02] active:scale-95 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-70 disabled:cursor-not-allowed"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <CheckCircle2 className="w-5 h-5" />
                            {processing ? 'Actualizando Motor...' : 'Guardar Parámetros'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
