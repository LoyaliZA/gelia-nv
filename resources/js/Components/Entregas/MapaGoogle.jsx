import React, { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { GoogleMap, MarkerF, Polygon } from '@react-google-maps/api';
import { AlertTriangle } from 'lucide-react';

const ESTILO_CLARO = [
    { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
    { elementType: 'labels.icon', stylers: [{ visibility: 'on' }, { saturation: -100 }, { lightness: 15 }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#f5f5f5' }] },
    { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#eeeeee' }] },
    { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#dadada' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9c9c9' }] },
];

const ESTILO_OSCURO = [
    { elementType: 'geometry', stylers: [{ color: '#212121' }] },
    { elementType: 'labels.icon', stylers: [{ visibility: 'on' }, { saturation: -100 }, { lightness: -40 }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#212121' }] },
    { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#181818' }] },
    { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#bdbdbd' }] },
    { featureType: 'road', elementType: 'geometry.fill', stylers: [{ color: '#2c2c2c' }] },
    { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#5c5c5c' }, { weight: 1.5 }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#3c3c3c' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#000000' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#3d3d3d' }] },
];

const ESTILO_CONTENEDOR = {
    width: '100%',
    height: '100%',
    position: 'absolute',
    top: 0,
    left: 0,
    borderRadius: 'inherit',
};

function resolverMapId(configuracion) {
    const desdeConfig = configuracion?.google_map_id?.trim();
    if (desdeConfig) return desdeConfig;

    const desdeEnv = import.meta.env.VITE_GOOGLE_MAP_ID?.trim();
    if (desdeEnv) return desdeEnv;

    return null;
}

function obtenerPosicionMarcador(coordenadas, center) {
    if (coordenadas?.latitud && coordenadas?.longitud) {
        const lat = parseFloat(coordenadas.latitud);
        const lng = parseFloat(coordenadas.longitud);
        if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
            return { lat, lng };
        }
    }
    return center;
}

export default function MapaGoogle({
    apiKey,
    coordenadas,
    onCoordenadasChange,
    configuracion,
    zonas = [],
    zonas_restringidas = [],
    isLoaded,
    loadError,
    viewportBusqueda = null,
}) {
    const [map, setMap] = useState(null);
    const [isDarkMode, setIsDarkMode] = useState(false);
    const markerAvanzadoRef = useRef(null);

    const mapId = resolverMapId(configuracion);
    const usaMapIdCloud = Boolean(mapId);

    const [center, setCenter] = useState({
        lat: parseFloat(configuracion?.latitud_origen || 17.99300568),
        lng: parseFloat(configuracion?.longitud_origen || -92.94544775),
    });

    const posicionMarcador = useMemo(
        () => obtenerPosicionMarcador(coordenadas, center),
        [coordenadas, center]
    );

    const mapOptions = useMemo(() => {
        const base = {
            disableDefaultUI: true,
            zoomControl: true,
        };

        if (usaMapIdCloud) {
            return { ...base, mapId };
        }

        return {
            ...base,
            styles: isDarkMode ? ESTILO_OSCURO : ESTILO_CLARO,
        };
    }, [usaMapIdCloud, mapId, isDarkMode]);

    useEffect(() => {
        if (!coordenadas?.latitud || !coordenadas?.longitud) return;

        const posicion = {
            lat: parseFloat(coordenadas.latitud),
            lng: parseFloat(coordenadas.longitud),
        };

        if (Number.isNaN(posicion.lat) || Number.isNaN(posicion.lng)) return;

        setCenter(posicion);

        if (map) {
            if (viewportBusqueda) {
                map.fitBounds(viewportBusqueda);
            } else {
                map.panTo(posicion);
                const zoomActual = map.getZoom();
                if (!zoomActual || zoomActual < 14) {
                    map.setZoom(15);
                }
            }
        }
    }, [coordenadas, map, viewportBusqueda]);

    useEffect(() => {
        const checkTheme = () => setIsDarkMode(document.documentElement.classList.contains('dark'));
        checkTheme();
        const observer = new MutationObserver(checkTheme);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!map || usaMapIdCloud) return;
        map.setOptions({ styles: isDarkMode ? ESTILO_OSCURO : ESTILO_CLARO });
    }, [map, isDarkMode, usaMapIdCloud]);

    const onLoad = useCallback((mapInstance) => {
        setMap(mapInstance);
    }, []);

    const onUnmount = useCallback(() => {
        if (markerAvanzadoRef.current) {
            markerAvanzadoRef.current.map = null;
            markerAvanzadoRef.current = null;
        }
        setMap(null);
    }, []);

    const handleInteraction = useCallback(
        (lat, lng) => {
            if (typeof lat === 'number' && typeof lng === 'number') {
                onCoordenadasChange(lat, lng);
            }
        },
        [onCoordenadasChange]
    );

    const handleMapClick = useCallback(
        (e) => handleInteraction(e.latLng.lat(), e.latLng.lng()),
        [handleInteraction]
    );

    const handleMarkerDragEnd = useCallback(
        (e) => handleInteraction(e.latLng.lat(), e.latLng.lng()),
        [handleInteraction]
    );

    useEffect(() => {
        if (!usaMapIdCloud || !map || !isLoaded || !window.google?.maps?.importLibrary) return;

        const posicion = posicionMarcador;
        let activo = true;

        const sincronizarMarcadorAvanzado = async () => {
            try {
                const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker');

                if (!activo) return;

                if (!markerAvanzadoRef.current) {
                    markerAvanzadoRef.current = new AdvancedMarkerElement({
                        map,
                        position: posicion,
                        gmpDraggable: true,
                        title: 'Punto de entrega',
                    });

                    markerAvanzadoRef.current.addListener('dragend', () => {
                        const pos = markerAvanzadoRef.current?.position;
                        if (!pos) return;
                        const lat = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
                        const lng = typeof pos.lng === 'function' ? pos.lng() : pos.lng;
                        handleInteraction(lat, lng);
                    });
                } else {
                    markerAvanzadoRef.current.position = posicion;
                    markerAvanzadoRef.current.map = map;
                }
            } catch (error) {
                console.error('AdvancedMarkerElement:', error);
            }
        };

        sincronizarMarcadorAvanzado();

        return () => {
            activo = false;
        };
    }, [usaMapIdCloud, map, isLoaded, posicionMarcador, handleInteraction]);

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

    if (!isLoaded) {
        return (
            <div className="flex flex-col items-center justify-center h-full gap-4 opacity-50">
                <div
                    className="w-8 h-8 border-4 border-t-transparent rounded-full animate-spin"
                    style={{ borderColor: 'var(--color-primario)' }}
                />
            </div>
        );
    }

    const tienePin = Boolean(coordenadas?.latitud && coordenadas?.longitud);

    return (
        <GoogleMap
            key={usaMapIdCloud ? `map-cloud-${mapId}` : `map-styled-${isDarkMode ? 'dark' : 'light'}`}
            mapContainerStyle={ESTILO_CONTENEDOR}
            center={center}
            zoom={13}
            onLoad={onLoad}
            onUnmount={onUnmount}
            onClick={handleMapClick}
            options={mapOptions}
        >
            {!usaMapIdCloud && (
                <MarkerF
                    position={posicionMarcador}
                    draggable
                    onDragEnd={handleMarkerDragEnd}
                    animation={tienePin && window.google?.maps?.Animation ? window.google.maps.Animation.DROP : undefined}
                    opacity={tienePin ? 1 : 0.6}
                />
            )}

            {zonas.map((zona) => (
                <Polygon
                    key={zona.id ?? zona.nombre}
                    paths={zona.rutas_formateadas}
                    options={{
                        fillColor: zona.color_hex || '#000000',
                        fillOpacity: 0.35,
                        strokeColor: zona.color_hex || '#000000',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        clickable: false,
                    }}
                />
            ))}

            {zonas_restringidas?.map((zr) => (
                <Polygon
                    key={`restringida-${zr.id ?? zr.nombre}`}
                    paths={zr.rutas_formateadas}
                    options={{
                        fillColor: '#b91c1c',
                        fillOpacity: 0.15,
                        strokeColor: '#ef4444',
                        strokeOpacity: 0.9,
                        strokeWeight: 2,
                        clickable: false,
                    }}
                />
            ))}

            {!tienePin && (
                <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 px-4 py-2 rounded-xl theme-surface border theme-border text-[10px] font-bold theme-text-muted shadow-lg pointer-events-none">
                    Busca una dirección o haz clic en el mapa para colocar el pin
                </div>
            )}
        </GoogleMap>
    );
}
