import React, { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef } from 'react';
import { GoogleMap, Polygon, Polyline, Circle } from '@react-google-maps/api';
import { AlertTriangle, Pencil, MousePointer2 } from 'lucide-react';
import { extraerPathsDesdePolygon } from '@/utils/poligonoGeoJson';

const ESTILO_CLARO = [
    { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9c9c9' }] },
];

const ESTILO_OSCURO = [
    { elementType: 'geometry', stylers: [{ color: '#212121' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
    { featureType: 'road', elementType: 'geometry.fill', stylers: [{ color: '#2c2c2c' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#000000' }] },
];

const CONTENEDOR = {
    width: '100%',
    height: '100%',
    position: 'absolute',
    top: 0,
    left: 0,
    borderRadius: 'inherit',
};

function estiloZona(tipoCapa, zona, seleccionada, enEdicion) {
    const base = zona.color_hex || (tipoCapa === 'restringidas' ? '#EF4444' : '#F59E0B');

    if (seleccionada && enEdicion) {
        return {
            fillColor: base,
            fillOpacity: 0.35,
            strokeColor: '#ffffff',
            strokeOpacity: 1,
            strokeWeight: 3,
        };
    }

    if (tipoCapa === 'restringidas') {
        return {
            fillColor: '#b91c1c',
            fillOpacity: seleccionada ? 0.25 : 0.1,
            strokeColor: '#ef4444',
            strokeOpacity: seleccionada ? 1 : 0.5,
            strokeWeight: seleccionada ? 2 : 1,
        };
    }

    return {
        fillColor: base,
        fillOpacity: seleccionada ? 0.4 : 0.2,
        strokeColor: base,
        strokeOpacity: seleccionada ? 0.95 : 0.45,
        strokeWeight: seleccionada ? 2 : 1,
    };
}

function MapaEditorZonas({
    configuracion,
    tipoCapa,
    zonas = [],
    modoEditor = 'ver',
    zonaSeleccionadaId = null,
    borradorPaths = null,
    onBorradorChange,
    onPathsEditados,
    isLoaded,
    loadError,
}, ref) {
    const [map, setMap] = React.useState(null);
    const [isDarkMode, setIsDarkMode] = React.useState(false);
    const polygonRefs = useRef({});

    const center = useMemo(
        () => ({
            lat: parseFloat(configuracion?.latitud_origen || 17.99300568),
            lng: parseFloat(configuracion?.longitud_origen || -92.94544775),
        }),
        [configuracion?.latitud_origen, configuracion?.longitud_origen]
    );

    const radioMetros = parseFloat(configuracion?.radio_tolerancia_km || 12) * 1000;

    const mapOptions = useMemo(
        () => ({
            disableDefaultUI: true,
            zoomControl: true,
            styles: isDarkMode ? ESTILO_OSCURO : ESTILO_CLARO,
            draggableCursor: modoEditor === 'crear' ? 'crosshair' : undefined,
        }),
        [isDarkMode, modoEditor]
    );

    useImperativeHandle(ref, () => ({
        obtenerPathsActuales() {
            if (borradorPaths?.length >= 3) {
                const borrador = polygonRefs.current.borrador;
                if (borrador) {
                    return extraerPathsDesdePolygon(borrador);
                }
                return borradorPaths;
            }

            if (modoEditor === 'editar' && zonaSeleccionadaId) {
                const polygon = polygonRefs.current[zonaSeleccionadaId];
                if (polygon) {
                    return extraerPathsDesdePolygon(polygon);
                }
                return zonas.find((z) => z.id === zonaSeleccionadaId)?.rutas_formateadas ?? null;
            }

            return borradorPaths;
        },
    }), [borradorPaths, modoEditor, zonaSeleccionadaId, zonas]);

    useEffect(() => {
        const checkTheme = () => setIsDarkMode(document.documentElement.classList.contains('dark'));
        checkTheme();
        const observer = new MutationObserver(checkTheme);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!map || !zonaSeleccionadaId || modoEditor !== 'editar') return;

        const zona = zonas.find((z) => z.id === zonaSeleccionadaId);
        if (!zona?.rutas_formateadas?.length) return;

        const bounds = new window.google.maps.LatLngBounds();
        zona.rutas_formateadas.forEach((p) => bounds.extend(p));
        map.fitBounds(bounds, 48);
    }, [map, zonaSeleccionadaId, modoEditor, zonas]);

    const onLoad = useCallback((mapInstance) => setMap(mapInstance), []);
    const onUnmount = useCallback(() => setMap(null), []);

    const handleMapClick = useCallback(
        (event) => {
            if (modoEditor !== 'crear' || !onBorradorChange) return;
            if ((borradorPaths?.length ?? 0) >= 3) return;

            const lat = event.latLng.lat();
            const lng = event.latLng.lng();
            onBorradorChange([...(borradorPaths || []), { lat, lng }]);
        },
        [modoEditor, borradorPaths, onBorradorChange]
    );

    const sincronizarPaths = useCallback(
        (refKey, callback) => {
            const polygon = polygonRefs.current[refKey];
            if (!polygon || !callback) return;
            callback(extraerPathsDesdePolygon(polygon));
        },
        []
    );

    if (loadError) {
        return (
            <div className="flex flex-col items-center justify-center h-full text-red-500 gap-3 p-6 text-center">
                <AlertTriangle className="w-10 h-10" />
                <p className="font-bold text-sm">Error al cargar Google Maps</p>
            </div>
        );
    }

    if (!isLoaded) {
        return (
            <div className="flex items-center justify-center h-full opacity-50">
                <div
                    className="w-8 h-8 border-4 border-t-transparent rounded-full animate-spin"
                    style={{ borderColor: 'var(--color-primario)' }}
                />
            </div>
        );
    }

    const pathsActivos = borradorPaths || (modoEditor === 'editar' && zonaSeleccionadaId
        ? zonas.find((z) => z.id === zonaSeleccionadaId)?.rutas_formateadas
        : null);

    const borradorCerrado = (borradorPaths?.length ?? 0) >= 3;

    return (
        <div className="relative w-full h-full">
            <GoogleMap
                mapContainerStyle={CONTENEDOR}
                center={center}
                zoom={13}
                onLoad={onLoad}
                onUnmount={onUnmount}
                onClick={handleMapClick}
                options={mapOptions}
            >
                <Circle
                    center={center}
                    radius={radioMetros}
                    options={{
                        fillColor: '#3B82F6',
                        fillOpacity: 0.05,
                        strokeColor: '#3B82F6',
                        strokeOpacity: 0.6,
                        strokeWeight: 2,
                        clickable: false,
                    }}
                />

                {zonas.map((zona) => {
                    const seleccionada = zona.id === zonaSeleccionadaId;
                    const editable = modoEditor === 'editar' && seleccionada && !borradorPaths;

                    return (
                        <Polygon
                            key={zona.id}
                            paths={zona.rutas_formateadas}
                            editable={editable}
                            draggable={false}
                            onLoad={(poly) => { polygonRefs.current[zona.id] = poly; }}
                            onUnmount={() => { delete polygonRefs.current[zona.id]; }}
                            onMouseUp={() => editable && sincronizarPaths(zona.id, onPathsEditados)}
                            onDragEnd={() => editable && sincronizarPaths(zona.id, onPathsEditados)}
                            options={{
                                ...estiloZona(tipoCapa, zona, seleccionada, editable),
                                clickable: false,
                            }}
                        />
                    );
                })}

                {modoEditor === 'crear' && borradorPaths?.length >= 2 && !borradorCerrado && (
                    <Polyline
                        path={borradorPaths}
                        options={{
                            strokeColor: '#ec4899',
                            strokeOpacity: 0.9,
                            strokeWeight: 3,
                            clickable: false,
                        }}
                    />
                )}

                {borradorCerrado && (
                    <Polygon
                        paths={borradorPaths}
                        editable
                        draggable={false}
                        onLoad={(poly) => { polygonRefs.current.borrador = poly; }}
                        onUnmount={() => { delete polygonRefs.current.borrador; }}
                        onMouseUp={() => sincronizarPaths('borrador', onBorradorChange)}
                        onDragEnd={() => sincronizarPaths('borrador', onBorradorChange)}
                        options={{
                            fillColor: '#ec4899',
                            fillOpacity: 0.25,
                            strokeColor: '#ffffff',
                            strokeOpacity: 1,
                            strokeWeight: 3,
                            clickable: false,
                        }}
                    />
                )}
            </GoogleMap>

            <div className="absolute top-3 left-3 z-10 px-3 py-2 rounded-xl theme-surface border theme-border shadow-lg text-[10px] font-black uppercase tracking-widest theme-text-main flex items-center gap-2 pointer-events-none max-w-[85%]">
                {modoEditor === 'crear' ? (
                    <>
                        <Pencil className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        Clic en el mapa para añadir vértices (mín. 3)
                    </>
                ) : modoEditor === 'editar' ? (
                    <>
                        <Pencil className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        Arrastra los vértices para ajustar
                    </>
                ) : (
                    <>
                        <MousePointer2 className="w-3.5 h-3.5 shrink-0 theme-text-muted" />
                        Modo vista
                    </>
                )}
            </div>

            {pathsActivos && (
                <div className="absolute bottom-3 right-3 z-10 px-3 py-1.5 rounded-lg bg-black/70 text-white text-[10px] font-bold pointer-events-none">
                    {pathsActivos.length} vértice(s)
                    {modoEditor === 'crear' && pathsActivos.length < 3 && ` — faltan ${3 - pathsActivos.length}`}
                </div>
            )}
        </div>
    );
}

export default forwardRef(MapaEditorZonas);
