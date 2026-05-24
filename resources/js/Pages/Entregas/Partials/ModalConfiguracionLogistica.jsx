import React from 'react';
import { useForm } from '@inertiajs/react';
import { 
    Settings2, X, Key, Map, 
    DollarSign, Route, CheckCircle2 
} from 'lucide-react';

export default function ModalConfiguracionLogistica({ configuracion, onClose }) {
    /*
     * Inicialización del formulario reactivo con Inertia.
     * Se cargan los datos existentes o los valores por defecto del sistema.
     */
    const { data, setData, put, processing, errors } = useForm({
        latitud_origen: configuracion?.latitud_origen || '',
        longitud_origen: configuracion?.longitud_origen || '',
        radio_tolerancia_km: configuracion?.radio_tolerancia_km || 12,
        tarifa_envio_extra: configuracion?.tarifa_envio_extra || 60,
        cobro_extra_por_km: !!configuracion?.cobro_extra_por_km,
        usar_api_distancia: !!configuracion?.usar_api_distancia,
        api_key_google: '', // Se mantiene vacío por seguridad; solo se envía si el usuario desea cambiarla
        google_map_id: configuracion?.google_map_id || '',
    });

    /*
     * Función controladora del envío del formulario.
     */
    const handleSubmit = (e) => {
        e.preventDefault();
        put('/entregas/configuracion', {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                // Forzamos un hard-reload para purgar la caché de scripts de Google en el navegador
                window.location.reload();
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            
            {/* Encabezado del Modal */}
            <div className="flex justify-between items-center border-b theme-border pb-4">
                <h2 className="text-xl font-black theme-text-main flex items-center gap-2 uppercase tracking-tight">
                    <Settings2 className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                    Parámetros del Motor
                </h2>
                <button 
                    type="button" 
                    onClick={onClose} 
                    className="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-[#222] transition-colors outline-none"
                >
                    <X className="w-5 h-5 theme-text-main" />
                </button>
            </div>

            {/* Contenedor de Campos */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {/* API Key (Fila Completa) */}
                <div className="md:col-span-2">
                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                        API Key - Google Maps Cartography
                    </label>
                    <div className="flex items-center theme-element border theme-border rounded-xl px-4 py-1 focus-within:ring-2 focus-within:ring-[var(--color-primario)] transition-all">
                        <Key className="w-4 h-4 theme-text-muted" />
                        <input 
                            type="password" 
                            value={data.api_key_google} 
                            onChange={e => setData('api_key_google', e.target.value)} 
                            className="w-full bg-transparent border-none p-3 text-sm font-bold theme-text-main outline-none focus:ring-0 placeholder:text-gray-400 placeholder:font-normal" 
                            placeholder="Dejar en blanco para conservar la clave actual..." 
                        />
                    </div>
                    {errors.api_key_google && <p className="text-xs text-red-500 mt-1 font-bold">{errors.api_key_google}</p>}
                </div>

                <div className="md:col-span-2">
                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                        Map ID — Marcadores avanzados (Google Cloud)
                    </label>
                    <div className="flex items-center theme-element border theme-border rounded-xl px-4 py-1 focus-within:ring-2 focus-within:ring-[var(--color-primario)] transition-all">
                        <Map className="w-4 h-4 theme-text-muted shrink-0" />
                        <input
                            type="text"
                            value={data.google_map_id}
                            onChange={(e) => setData('google_map_id', e.target.value)}
                            className="w-full bg-transparent border-none p-3 text-sm font-bold theme-text-main outline-none focus:ring-0 placeholder:text-gray-400 placeholder:font-normal"
                            placeholder="ej. a1b2c3d4e5f6g7h8 (Map ID vectorial)"
                        />
                    </div>
                    <p className="text-[10px] theme-text-muted mt-1.5 font-medium leading-relaxed">
                        Opcional. Si lo dejas vacío, el mapa usa el estilo monocromático integrado de la app.
                        Si lo configuras, el estilo se define en Google Cloud (Map Management → Map ID) y se activa el marcador avanzado.
                    </p>
                    {errors.google_map_id && <p className="text-xs text-red-500 mt-1 font-bold">{errors.google_map_id}</p>}
                </div>

                {/* Coordenadas Punto Central */}
                <div className="md:col-span-2 grid grid-cols-2 gap-4 p-4 theme-element border theme-border rounded-2xl">
                    <div className="col-span-2 flex items-center gap-2 mb-2">
                        <Map className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Punto Central Logístico (Km 0)</span>
                    </div>
                    <div>
                        <label className="block text-xs font-bold theme-text-muted mb-1">Latitud</label>
                        <input 
                            type="number" 
                            step="any"
                            value={data.latitud_origen} 
                            onChange={e => setData('latitud_origen', e.target.value)} 
                            className="w-full theme-surface border theme-border rounded-lg p-2.5 text-sm theme-text-main outline-none focus:ring-1 focus:ring-[var(--color-primario)]" 
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold theme-text-muted mb-1">Longitud</label>
                        <input 
                            type="number" 
                            step="any"
                            value={data.longitud_origen} 
                            onChange={e => setData('longitud_origen', e.target.value)} 
                            className="w-full theme-surface border theme-border rounded-lg p-2.5 text-sm theme-text-main outline-none focus:ring-1 focus:ring-[var(--color-primario)]" 
                        />
                    </div>
                </div>

                {/* Configuración de Tolerancia */}
                <div>
                    <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                        <Route className="w-4 h-4" /> Tolerancia Máxima (Km)
                    </label>
                    <input 
                        type="number" 
                        step="0.01"
                        value={data.radio_tolerancia_km} 
                        onChange={e => setData('radio_tolerancia_km', e.target.value)} 
                        className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]" 
                    />
                    {errors.radio_tolerancia_km && <p className="text-xs text-red-500 mt-1 font-bold">{errors.radio_tolerancia_km}</p>}
                </div>

                {/* Configuración de Tarifa Base Extra */}
                <div>
                    <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                        <DollarSign className="w-4 h-4" /> Tarifa Extra Fuera de Zona
                    </label>
                    <input 
                        type="number" 
                        step="0.01"
                        value={data.tarifa_envio_extra} 
                        onChange={e => setData('tarifa_envio_extra', e.target.value)} 
                        className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]" 
                    />
                    {errors.tarifa_envio_extra && <p className="text-xs text-red-500 mt-1 font-bold">{errors.tarifa_envio_extra}</p>}
                </div>

                {/* Interruptores Lógicos */}
                <div className="md:col-span-2 flex flex-col gap-4 mt-2">
                    <div className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl">
                        <div>
                            <p className="text-sm font-bold theme-text-main">Multiplicar tarifa extra por Kilómetro</p>
                            <p className="text-xs theme-text-muted mt-0.5">Si está inactivo, se cobrará una tarifa plana ($60).</p>
                        </div>
                        <button 
                            type="button"
                            onClick={() => setData('cobro_extra_por_km', !data.cobro_extra_por_km)}
                            className="gelia-switch flex-shrink-0"
                            data-active={data.cobro_extra_por_km}
                        >
                            <div className="gelia-switch-thumb" />
                        </button>
                    </div>

                    <div className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl">
                        <div>
                            <p className="text-sm font-bold theme-text-main">Usar Google Distance Matrix API</p>
                            <p className="text-xs theme-text-muted mt-0.5">Calcula la distancia por calle en lugar de línea recta. (Consume cuota API).</p>
                        </div>
                        <button 
                            type="button"
                            onClick={() => setData('usar_api_distancia', !data.usar_api_distancia)}
                            className="gelia-switch flex-shrink-0"
                            data-active={data.usar_api_distancia}
                        >
                            <div className="gelia-switch-thumb" />
                        </button>
                    </div>
                </div>

            </div>

            {/* Acciones del Modal */}
            <div className="mt-4 pt-4 border-t theme-border">
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
    );
}