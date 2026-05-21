import React, { useState, useCallback, useEffect } from 'react';
import { GoogleMap, useJsApiLoader, MarkerF, Polygon } from '@react-google-maps/api';
import { AlertTriangle } from 'lucide-react';

const containerStyle = {
    width: '100%',
    height: '100%',
    position: 'absolute', // Fuerza al mapa a llenar el contenedor relativo
    top: 0,
    left: 0,
    borderRadius: 'inherit'
};

export default function MapaGoogle({ apiKey, coordenadas, onCoordenadasChange, configuracion, zonas = [] }) {
    // 1. Carga asíncrona de la API de Google Maps
    const { isLoaded, loadError } = useJsApiLoader({
        id: 'google-map-script',
        googleMapsApiKey: apiKey || ''
    });

    const [map, setMap] = useState(null);
    
    // El centro por defecto será la sucursal (Toyota) definida en la BD
    const [center, setCenter] = useState({
        lat: parseFloat(configuracion?.latitud_origen || 17.99300568),
        lng: parseFloat(configuracion?.longitud_origen || -92.94544775)
    });

    // 2. Sincronización del centro cuando cambian las coordenadas manuales
    useEffect(() => {
        if (coordenadas?.latitud && coordenadas?.longitud) {
            setCenter({
                lat: parseFloat(coordenadas.latitud),
                lng: parseFloat(coordenadas.longitud)
            });
        }
    }, [coordenadas]);

    // 3. Manejadores de instancia del mapa
    const onLoad = useCallback(function callback(map) {
        setMap(map);
    }, []);

    const onUnmount = useCallback(function callback() {
        setMap(null);
    }, []);

    // 4. Manejadores de interacción (Clic o arrastrar el pin)
    const handleInteraction = (e) => {
        const lat = e.latLng.lat();
        const lng = e.latLng.lng();
        onCoordenadasChange(lat, lng);
    };

    // 5. Renderizado de estados de error o carga
    if (loadError || !apiKey) {
        return (
            <div className="flex flex-col items-center justify-center h-full text-red-500 gap-3 p-6 text-center">
                <AlertTriangle className="w-10 h-10" />
                <p className="font-bold text-sm">Error de renderizado cartográfico</p>
                <p className="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {!apiKey ? 'API Key no configurada en la base de datos.' : 'Verifica la validez de la API Key o tu conexión.'}
                </p>
            </div>
        );
    }

    if (!isLoaded) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-4 opacity-50">
                <div className="w-8 h-8 border-4 border-t-transparent rounded-full animate-spin" style={{ borderColor: 'var(--color-primario)', borderTopColor: 'transparent' }}></div>
                <span className="font-black uppercase tracking-widest text-[10px] theme-text-main">
                    Inicializando Motor Cartográfico...
                </span>
            </div>
        );
    }

    return (
        <GoogleMap
            mapContainerStyle={containerStyle}
            center={center}
            zoom={13}
            onLoad={onLoad}
            onUnmount={onUnmount}
            onClick={handleInteraction}
            options={{
                disableDefaultUI: true,
                zoomControl: true,
            }}
        >
            {/* Usamos MarkerF en lugar de Marker */}
            {(coordenadas?.latitud && coordenadas?.longitud) ? (
                <MarkerF 
                    position={{ lat: parseFloat(coordenadas.latitud), lng: parseFloat(coordenadas.longitud) }}
                    draggable={true}
                    onDragEnd={handleInteraction}
                    animation={window.google.maps.Animation.DROP}
                />
            ) : (
                <MarkerF 
                    position={center}
                    draggable={true}
                    onDragEnd={handleInteraction}
                    opacity={0.6}
                />
            )}

            {/* Capa de Zonas (Polígonos) */}
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