import { useJsApiLoader } from '@react-google-maps/api';

/** ID único compartido: useJsApiLoader no permite distintos ids en la misma sesión. */
export const GOOGLE_MAPS_LOADER_ID = 'gelia-google-maps-v3';

export const GOOGLE_MAPS_LIBRARIES = ['places', 'marker'];

export function useGoogleMapsLoader(apiKey) {
    return useJsApiLoader({
        id: GOOGLE_MAPS_LOADER_ID,
        googleMapsApiKey: apiKey || '',
        libraries: GOOGLE_MAPS_LIBRARIES,
    });
}
