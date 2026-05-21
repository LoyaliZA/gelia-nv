import React, { useState, useCallback, useEffect } from 'react';
import { GoogleMap, useJsApiLoader, MarkerF, Polygon } from '@react-google-maps/api';
import { AlertTriangle } from 'lucide-react';

// ----------------------------------------------------------------------
// CONSTANTES DE DISEÑO (ESTILO CLARO MONOCROMÁTICO)
// ----------------------------------------------------------------------
const ESTILO_CLARO = [
    {
        elementType: "geometry",
        stylers: [{ color: "#f5f5f5" }]
    },
    // Íconos visibles, sin color (escala de grises) y ligeramente aclarados
    {
        elementType: "labels.icon",
        stylers: [
            { visibility: "on" },
            { saturation: -100 },
            { lightness: 15 }
        ]
    },
    {
        elementType: "labels.text.fill",
        stylers: [{ color: "#616161" }]
    },
    {
        elementType: "labels.text.stroke",
        stylers: [{ color: "#f5f5f5" }]
    },
    {
        featureType: "poi",
        elementType: "geometry",
        stylers: [{ color: "#eeeeee" }]
    },
    {
        featureType: "poi",
        elementType: "labels.text.fill",
        stylers: [{ color: "#757575" }]
    },
    {
        featureType: "road",
        elementType: "geometry",
        stylers: [{ color: "#ffffff" }]
    },
    {
        featureType: "road.highway",
        elementType: "geometry",
        stylers: [{ color: "#dadada" }]
    },
    {
        featureType: "water",
        elementType: "geometry",
        stylers: [{ color: "#c9c9c9" }]
    }
];

// ----------------------------------------------------------------------
// CONSTANTES DE DISEÑO (MODO OSCURO - GOOGLE CLOUD STYLE)
// ----------------------------------------------------------------------
const ESTILO_OSCURO = [
    {
        elementType: "geometry",
        stylers: [{ color: "#212121" }]
    },
    // Íconos visibles, sin color (escala de grises) y oscurecidos para no brillar de más
    {
        elementType: "labels.icon",
        stylers: [
            { visibility: "on" }, 
            { saturation: -100 },
            { lightness: -40 }
        ]
    },
    {
        elementType: "labels.text.fill",
        stylers: [{ color: "#9e9e9e" }]
    },
    {
        elementType: "labels.text.stroke",
        stylers: [{ color: "#212121" }]
    },
    {
        featureType: "poi",
        elementType: "geometry",
        stylers: [{ color: "#181818" }]
    },
    {
        featureType: "poi",
        elementType: "labels.text.fill",
        stylers: [{ color: "#bdbdbd" }]
    },
    {
        featureType: "road",
        elementType: "geometry.fill",
        stylers: [{ color: "#2c2c2c" }]
    },
    {
        featureType: "road",
        elementType: "geometry.stroke",
        stylers: [{ color: "#5c5c5c" }, { weight: 1.5 }]
    },
    {
        featureType: "road",
        elementType: "labels.text.fill",
        stylers: [{ color: "#8a8a8a" }]
    },
    {
        featureType: "road.arterial",
        elementType: "geometry",
        stylers: [{ color: "#373737" }]
    },
    {
        featureType: "road.highway",
        elementType: "geometry",
        stylers: [{ color: "#3c3c3c" }]
    },
    {
        featureType: "road.highway",
        elementType: "geometry.stroke",
        stylers: [{ color: "#4d4d4d" }, { weight: 2 }]
    },
    {
        featureType: "water",
        elementType: "geometry",
        stylers: [{ color: "#000000" }]
    },
    {
        featureType: "water",
        elementType: "labels.text.fill",
        stylers: [{ color: "#3d3d3d" }]
    }
];

const ESTILO_CONTENEDOR = {
    width: '100%',
    height: '100%',
    position: 'absolute',
    top: 0,
    left: 0,
    borderRadius: 'inherit'
};

export default function MapaGoogle({ apiKey, coordenadas, onCoordenadasChange, configuracion, zonas = [] }) {
    // ----------------------------------------------------------------------
    // ESTADO E INICIALIZACIÓN
    // ----------------------------------------------------------------------
    const { isLoaded, loadError } = useJsApiLoader({ id: 'google-map-script', googleMapsApiKey: apiKey || '' });
    const [map, setMap] = useState(null);
    const [isDarkMode, setIsDarkMode] = useState(false);
    
    const [center, setCenter] = useState({
        lat: parseFloat(configuracion?.latitud_origen || 17.99300568),
        lng: parseFloat(configuracion?.longitud_origen || -92.94544775)
    });

    // ----------------------------------------------------------------------
    // EFECTOS (TEMA Y COORDENADAS)
    // ----------------------------------------------------------------------
    useEffect(() => {
        if (coordenadas?.latitud && coordenadas?.longitud) {
            setCenter({
                lat: parseFloat(coordenadas.latitud),
                lng: parseFloat(coordenadas.longitud)
            });
        }
    }, [coordenadas]);

    useEffect(() => {
        // Observador para detectar cambios en el tema de Tailwind
        const checkTheme = () => setIsDarkMode(document.documentElement.classList.contains('dark'));
        checkTheme();
        
        const observer = new MutationObserver(checkTheme);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        
        return () => observer.disconnect();
    }, []);

    // ----------------------------------------------------------------------
    // MANEJADORES DE EVENTOS
    // ----------------------------------------------------------------------
    const onLoad = useCallback(function callback(map) { setMap(map); }, []);
    const onUnmount = useCallback(function callback() { setMap(null); }, []);

    const handleInteraction = (e) => {
        onCoordenadasChange(e.latLng.lat(), e.latLng.lng());
    };

    // ----------------------------------------------------------------------
    // RENDERIZADO CONDICIONAL
    // ----------------------------------------------------------------------
    if (loadError || !apiKey) {
        return (
            <div className="flex flex-col items-center justify-center h-full text-red-500 gap-3 p-6 text-center">
                <AlertTriangle className="w-10 h-10" />
                <p className="font-bold text-sm">Error de renderizado cartográfico</p>
                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {!apiKey ? 'API Key no configurada.' : 'Verifica tu conexión.'}
                </p>
            </div>
        );
    }

    if (!isLoaded) return <div className="flex flex-col items-center justify-center h-full gap-4 opacity-50"><div className="w-8 h-8 border-4 border-t-transparent rounded-full animate-spin" style={{ borderColor: 'var(--color-primario)' }}></div></div>;

    return (
        <GoogleMap
            mapContainerStyle={ESTILO_CONTENEDOR}
            center={center}
            zoom={13}
            onLoad={onLoad}
            onUnmount={onUnmount}
            onClick={handleInteraction}
            options={{
                disableDefaultUI: true,
                zoomControl: true,
                styles: isDarkMode ? ESTILO_OSCURO : ESTILO_CLARO // Inyección del estilo dinámico
            }}
        >
            <MarkerF 
                position={(coordenadas?.latitud && coordenadas?.longitud) ? { lat: parseFloat(coordenadas.latitud), lng: parseFloat(coordenadas.longitud) } : center}
                draggable={true}
                onDragEnd={handleInteraction}
                animation={(coordenadas?.latitud && coordenadas?.longitud) ? window.google.maps.Animation.DROP : null}
                opacity={(coordenadas?.latitud && coordenadas?.longitud) ? 1 : 0.6}
            />

            {zonas.map((zona, index) => (
                <Polygon 
                    key={index}
                    paths={zona.rutas_formateadas}
                    options={{
                        fillColor: zona.color_hex || '#000000',
                        fillOpacity: 0.35,
                        strokeColor: zona.color_hex || '#000000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        clickable: false
                    }}
                />
            ))}
        </GoogleMap>
    );
}