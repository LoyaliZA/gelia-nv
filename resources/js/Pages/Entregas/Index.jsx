import React, { useState, useRef, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useJsApiLoader } from '@react-google-maps/api'; 
import {
    MapPin, Navigation, Search, CheckCircle2,
    AlertTriangle, Settings2, Clock, CheckSquare
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import MapaGoogle from '@/Components/Entregas/MapaGoogle';
import ModalConfiguracionLogistica from './Partials/ModalConfiguracionLogistica';

// ----------------------------------------------------------------------
// CONSTANTES GLOBALES Y ESTILOS DEL WEB COMPONENT
// ----------------------------------------------------------------------
const LIBRERIAS_GOOGLE = ['places'];

const ESTILOS_ADICIONALES = `
    @keyframes slideUpFade { 
        0% { opacity: 0; transform: translateY(20px); } 
        100% { opacity: 1; transform: translateY(0); } 
    }
    .animate-page-reveal { 
        opacity: 0; 
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
    }
    
    /* ---------------------------------------------------------------------- */
    /* FIX: Estilización del Web Component de Google (PlaceAutocompleteElement) */
    /* ---------------------------------------------------------------------- */
    gmp-place-autocomplete {
        display: block; /* Fuerza comportamiento de bloque en el Web Component */
        width: 100%;
    }
    gmp-place-autocomplete::part(input) {
        display: block;
        width: 100%;
        box-sizing: border-box; /* Previene desbordamiento del padding */
        padding: 1rem 1rem 1rem 3.5rem; /* Ajuste para el icono de búsqueda */
        background-color: transparent;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 0.75rem; 
        color: inherit;
        font-family: inherit; /* Hereda la tipografía de la aplicación */
        font-size: 0.875rem; 
        font-weight: 700; 
        outline: none;
        transition: all 0.3s ease;
        margin: 0;
    }
    gmp-place-autocomplete::part(input):focus {
        border-color: var(--color-primario);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primario) 20%, transparent);
    }
    .dark gmp-place-autocomplete::part(input) {
        border-color: #3f3f46; 
    }
`;

// ----------------------------------------------------------------------
// COMPONENTE PRINCIPAL
// ----------------------------------------------------------------------
export default function Index({ auth, configuracion, googleApiKey, zonas, zonas_restringidas }) {
    
    // ----------------------------------------------------------------------
    // ESTADO DEL COMPONENTE
    // ----------------------------------------------------------------------
    const [coordenadas, setCoordenadas] = useState({ latitud: '', longitud: '' });
    const [direccion, setDireccion] = useState('');
    const [cargando, setCargando] = useState(false);
    const [resultado, setResultado] = useState(null);
    const [modalConfigAbierto, setModalConfigAbierto] = useState(false);
    const [errorMotor, setErrorMotor] = useState(null);
    
    const autocompleteContainerRef = useRef(null);

    // ----------------------------------------------------------------------
    // INICIALIZACIÓN Y PERMISOS
    // ----------------------------------------------------------------------
    const { isLoaded, loadError } = useJsApiLoader({
        id: 'google-map-script',
        googleMapsApiKey: googleApiKey || '',
        libraries: LIBRERIAS_GOOGLE
    });

    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const canConfigurar = can('entregas.configurar_zonas');

    // ----------------------------------------------------------------------
    // FUNCIONES DE PARSEO Y FORMATEO
    // ----------------------------------------------------------------------
    const analizarCoordenadasPgadas = (texto) => {
        if (!texto) return null;
        const cadena = texto.trim();

        try {
            // 1. Formato Decimal Directo
            const regexDecimal = /^(-?\d{1,3}\.\d+)[\s,]+(-?\d{1,3}\.\d+)$/;
            const matchDecimal = cadena.match(regexDecimal);
            if (matchDecimal) {
                return { lat: parseFloat(matchDecimal[1]), lng: parseFloat(matchDecimal[2]) };
            }

            // 2. Formato WhatsApp / GPS
            const regexDMS = /(\d+)[°\s]+(\d+)['\s]+([\d.]+)["\s]+([NS])[\s,]*(\d+)[°\s]+(\d+)['\s]+([\d.]+)["\s]+([EW])/i;
            const matchDMS = cadena.match(regexDMS);
            if (matchDMS) {
                let latitud = parseFloat(matchDMS[1]) + (parseFloat(matchDMS[2]) / 60) + (parseFloat(matchDMS[3]) / 3600);
                if (matchDMS[4].toUpperCase() === 'S') latitud = latitud * -1;

                let longitud = parseFloat(matchDMS[5]) + (parseFloat(matchDMS[6]) / 60) + (parseFloat(matchDMS[7]) / 3600);
                if (matchDMS[8].toUpperCase() === 'W') longitud = longitud * -1;

                return { lat: latitud, lng: longitud };
            }
        } catch (error) {
            console.error("Error analizando coordenadas:", error);
        }

        return null; 
    };

    const actualizarCoordenadasDesdeMapa = (lat, lng, limpiarInput = false) => {
        if (typeof lat !== 'number' || typeof lng !== 'number') return;
        
        setCoordenadas({ latitud: lat.toFixed(8), longitud: lng.toFixed(8) });
        
        // Solo limpiamos el buscador si el usuario interactuó directamente con el mapa
        if (limpiarInput) {
            setDireccion('');
            if (autocompleteContainerRef.current && autocompleteContainerRef.current.firstChild) {
                autocompleteContainerRef.current.firstChild.value = '';
            }
        }
    };
    // ----------------------------------------------------------------------
    // EFECTOS (LIFECYCLE)
    // ----------------------------------------------------------------------
    useEffect(() => {
        if (!isLoaded || !autocompleteContainerRef.current) return;

        try {
            autocompleteContainerRef.current.innerHTML = '';

            // 1. Instanciamos el componente
            const autocompleteElement = new window.google.maps.places.PlaceAutocompleteElement({
                componentRestrictions: { country: 'mx' }
            });

            // 2. FIX DE ACCESIBILIDAD Y VALIDACIÓN
            // Asignamos propiedades antes de que se renderice en el DOM
            autocompleteElement.setAttribute('placeholder', 'Buscar zona o dirección...');
            autocompleteElement.setAttribute('aria-label', 'Campo de búsqueda de ubicaciones de entrega');
            
            // Opcional: Limpiar el aria-labelledby si Google lo inyecta vacío por defecto
            autocompleteElement.removeAttribute('aria-labelledby');

            autocompleteElement.addEventListener('gmp-placeselect', async (e) => {
                const place = e.place;
                if (!place) return;

                try {
                    await place.fetchFields({ fields: ['location', 'displayName', 'formattedAddress'] });
                    
                    if (place.location) {
                        const lat = place.location.lat();
                        const lng = place.location.lng();
                        actualizarCoordenadasDesdeMapa(lat, lng);
                        setDireccion(place.formattedAddress || place.displayName);
                    }
                } catch (fetchError) {
                    setErrorMotor('Error al obtener los detalles de la ubicación seleccionada.');
                    console.error("Error fetchFields:", fetchError);
                }
            });

            autocompleteElement.addEventListener('gmp-placeselect', async (e) => {
                const place = e.place;
                if (!place) return;

                try {
                    await place.fetchFields({ fields: ['location', 'displayName', 'formattedAddress'] });
                    
                    if (place.location) {
                        const lat = place.location.lat();
                        const lng = place.location.lng();
                        // FIX: Pasamos false para NO limpiar el buscador
                        actualizarCoordenadasDesdeMapa(lat, lng, false);
                        setDireccion(place.formattedAddress || place.displayName);
                    }
                } catch (fetchError) {
                    setErrorMotor('Error al obtener los detalles de la ubicación seleccionada.');
                    console.error("Error fetchFields:", fetchError);
                }
            });

            autocompleteElement.addEventListener('input', (e) => {
                const valor = e.target.value;
                setDireccion(valor);
                const coordenadasDetectadas = analizarCoordenadasPgadas(valor);
                if (coordenadasDetectadas) {
                    // FIX: Pasamos false para NO limpiar lo que acaba de pegar
                    actualizarCoordenadasDesdeMapa(coordenadasDetectadas.lat, coordenadasDetectadas.lng, false);
                }
            });

            // 3. Montamos en el DOM
            autocompleteContainerRef.current.appendChild(autocompleteElement);

        } catch (initError) {
            setErrorMotor('Error al inicializar el motor de búsqueda inteligente.');
            console.error("Error inicializando PlaceAutocompleteElement:", initError);
        }

        return () => {
            if (autocompleteContainerRef.current) {
                autocompleteContainerRef.current.innerHTML = '';
            }
        };

    }, [isLoaded]);

    // ----------------------------------------------------------------------
    // FUNCIONES DE NEGOCIO (COTIZACIÓN)
    // ----------------------------------------------------------------------
    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setCoordenadas(prev => ({ ...prev, [name]: value }));
    };

    const solicitarCotizacion = async () => {
        if (!coordenadas.latitud || !coordenadas.longitud) {
            setErrorMotor('Por favor, ingresa las coordenadas válidas en la barra superior antes de cotizar.');
            return;
        }

        setCargando(true);
        setErrorMotor(null);
        setResultado(null);

        try {
            const response = await axios.post('/api/entregas/cotizar', coordenadas);
            if (response.data) {
                setResultado(response.data);
            }
        } catch (error) {
            console.error("Error en cotización:", error);
            if (error.response?.data?.mensaje) {
                setErrorMotor(error.response.data.mensaje);
            } else {
                setErrorMotor('Error de conexión con el motor matemático. Verifica la red o contacta a soporte.');
            }
        } finally {
            setCargando(false);
        }
    };

    // ----------------------------------------------------------------------
    // RENDERIZADO DEL DOM
    // ----------------------------------------------------------------------
    return (
        <AppLayout auth={auth}>
            <Head title="Cotización de Entregas" />
            <style>{ESTILOS_ADICIONALES}</style>

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">

                {/* ---------------------------------------------------------------------- */}
                {/* CABECERA */}
                {/* ---------------------------------------------------------------------- */}
                <header className="animate-page-reveal theme-surface rounded-3xl md:rounded-[2.5rem] p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border theme-border shadow-xl">
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel Logístico</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            COTIZACIÓN DE <span style={{ color: 'var(--color-primario)' }}>ENTREGAS</span>
                        </h1>
                    </div>
                    {canConfigurar && (
                        <button
                            onClick={() => setModalConfigAbierto(true)}
                            className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto outline-none"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Settings2 className="w-5 h-5" /> Configurar Zonas
                        </button>
                    )}
                </header>

                {/* ---------------------------------------------------------------------- */}
                {/* BARRA DE HERRAMIENTAS Y BÚSQUEDA */}
                {/* ---------------------------------------------------------------------- */}
                <div className="relative z-[50] animate-page-reveal flex flex-col lg:flex-row gap-4 items-center justify-between" style={{ animationDelay: '100ms' }}>

                    {/* FIX Z-INDEX: Agregamos z-[60] al buscador para que sus sugerencias estén sobre el mapa */}
                    <div className="relative z-[60] w-full flex-1 theme-surface rounded-xl shadow-sm border theme-border">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                        
                        {isLoaded ? (
                            <div ref={autocompleteContainerRef} className="w-full relative z-0"></div>
                        ) : (
                            <input
                                type="text"
                                disabled
                                placeholder="Cargando motor de búsqueda inteligente..."
                                className="w-full pl-14 pr-4 py-4 bg-transparent border-none rounded-xl text-gray-400 text-sm font-bold shadow-sm outline-none"
                            />
                        )}
                    </div>

                    <div className="flex flex-col md:flex-row items-center gap-4 w-full lg:w-auto">
                        <div className="flex items-center gap-2 theme-surface border theme-border rounded-xl px-4 py-4 text-sm shadow-sm w-full md:w-auto font-bold">
                            <Navigation className="w-5 h-5 theme-text-muted" />
                            <input
                                type="number"
                                name="latitud"
                                value={coordenadas.latitud}
                                onChange={handleInputChange}
                                placeholder="Latitud"
                                className="w-24 bg-transparent border-none p-0 focus:ring-0 theme-text-main outline-none placeholder:text-gray-400 text-center font-bold"
                            />
                            <span className="text-gray-300 dark:text-gray-600">|</span>
                            <input
                                type="number"
                                name="longitud"
                                value={coordenadas.longitud}
                                onChange={handleInputChange}
                                placeholder="Longitud"
                                className="w-24 bg-transparent border-none p-0 focus:ring-0 theme-text-main outline-none placeholder:text-gray-400 text-center font-bold"
                            />
                        </div>

                        <button
                            onClick={solicitarCotizacion}
                            disabled={cargando}
                            className="w-full md:w-auto px-8 py-4 rounded-xl text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-70 disabled:cursor-not-allowed"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            {cargando ? <Clock className="w-5 h-5 animate-spin" /> : <CheckCircle2 className="w-5 h-5" />}
                            {cargando ? 'Calculando_' : 'Cotizar Envío'}
                        </button>
                    </div>
                </div>

                {/* ---------------------------------------------------------------------- */}
                {/* ÁREA DE RESULTADOS Y MAPA */}
                {/* ---------------------------------------------------------------------- */}
                <div className="animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden bg-white/70 dark:bg-[#121212]/70 backdrop-blur-xl flex flex-col-reverse lg:flex-row min-h-[500px]" style={{ animationDelay: '200ms' }}>
                    <div className="w-full lg:w-[380px] border-r theme-border p-8 flex flex-col bg-slate-50/50 dark:bg-black/10">
                        <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-6 flex items-center gap-2">
                            <CheckSquare className="w-4 h-4" /> Resumen Logístico
                        </h3>

                        {!resultado && !errorMotor && (
                            <div className="flex flex-col items-center justify-center text-center h-full opacity-50 space-y-4 py-10 lg:py-0">
                                <MapPin className="w-12 h-12 theme-text-muted" />
                                <p className="text-sm font-bold theme-text-muted px-4">Ubica el pin en el mapa o ingresa coordenadas para iniciar el cálculo.</p>
                            </div>
                        )}

                        {errorMotor && (
                            <div className="p-4 rounded-2xl border bg-red-50 dark:bg-red-500/5 border-red-200 dark:border-red-500/20 shadow-sm flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <p className="text-xs font-bold text-red-600 dark:text-red-400 leading-tight">{errorMotor}</p>
                            </div>
                        )}

                        {resultado && (
                            <div className="space-y-6 animate-page-reveal">
                                <div className="theme-surface border theme-border p-6 rounded-3xl shadow-lg relative flex flex-col gap-4">
                                    <div className="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-3xl" style={{ backgroundColor: 'var(--color-primario)' }}></div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado de Área</span>
                                        <div className="mt-1 flex items-center">
                                            <span className="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 w-max bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                                <CheckCircle2 className="w-3.5 h-3.5" /> Aprobada
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Zona Asignada</span>
                                        <p className="text-base font-black theme-text-main mt-0.5">{resultado.nombre_zona}</p>
                                    </div>

                                    <div>
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Distancia Calculada</span>
                                        <p className="text-sm font-bold theme-text-main mt-0.5">{resultado.distancia_km} km</p>
                                    </div>

                                    {resultado.horarios && resultado.horarios.length > 0 && (
                                        <div className="pt-4 border-t theme-border mt-2">
                                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 mb-3">
                                                <Clock className="w-3.5 h-3.5" /> Ventanas de Entrega
                                            </span>
                                            <div className="flex flex-col gap-2">
                                                {resultado.horarios.map((horario, index) => (
                                                    <div key={index} className="flex items-center justify-between theme-element border theme-border rounded-xl px-4 py-2">
                                                        <span className="text-xs font-bold theme-text-main">
                                                            {horario.hora_inicio.substring(0, 5)} hrs — {horario.hora_fin.substring(0, 5)} hrs
                                                        </span>
                                                        <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <div className="pt-4 border-t theme-border mt-2">
                                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Costo Final</span>
                                        <div className="text-4xl font-black italic tracking-tighter mt-1" style={{ color: 'var(--color-primario)' }}>
                                            ${resultado.costo_envio} <span className="text-base font-bold opacity-50 not-italic">MXN</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex-1 relative bg-[#e5e5e5] dark:bg-[#0f0f0f] flex items-center justify-center min-h-[400px] lg:min-h-full rounded-r-[2.5rem]">
                        {!googleApiKey ? (
                            <div className="flex flex-col items-center gap-3 opacity-60 p-6 text-center">
                                <AlertTriangle className="w-12 h-12 text-red-500" />
                                <span className="font-black uppercase tracking-widest text-xs theme-text-main text-red-500">
                                    Motor Cartográfico Pausado
                                </span>
                                <p className="text-[10px] theme-text-muted mt-2 font-bold max-w-xs">
                                    Ingresa a la configuración de zonas para establecer la API Key de Google Maps.
                                </p>
                            </div>
                        ) : (
                            <MapaGoogle 
                                key={googleApiKey}
                                apiKey={googleApiKey}
                                coordenadas={coordenadas}
                                configuracion={configuracion}
                                zonas={zonas || []}
                                zonas_restringidas={zonas_restringidas || []}
                                onCoordenadasChange={actualizarCoordenadasDesdeMapa}
                                isLoaded={isLoaded}
                                loadError={loadError}
                            />
                        )}
                    </div>
                </div>
            </div>

            {/* ---------------------------------------------------------------------- */}
            {/* MODALES */}
            {/* ---------------------------------------------------------------------- */}
            {modalConfigAbierto && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-page-reveal">
                    <div className="w-full max-w-2xl theme-surface border theme-border rounded-[2rem] shadow-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto relative">
                        <ModalConfiguracionLogistica
                            configuracion={configuracion}
                            onClose={() => setModalConfigAbierto(false)}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}