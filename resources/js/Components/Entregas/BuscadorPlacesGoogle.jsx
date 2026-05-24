import React, { useEffect, useRef } from 'react';

const ESTILOS_BUSCADOR = `
    .entregas-buscador-google {
        width: 100%;
        min-height: 3.25rem;
        display: flex;
        align-items: stretch;
    }
    .entregas-buscador-google__slot {
        width: 100%;
        min-width: 0;
    }
    .entregas-buscador-google__slot gmp-place-autocomplete {
        width: 100%;
        display: block;
        color-scheme: light dark;
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(input) {
        width: 100%;
        box-sizing: border-box;
        margin: 0;
        padding: 0.9rem 2.75rem 0.9rem 1rem;
        border: none;
        border-radius: 0.75rem;
        background: transparent;
        color: inherit;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.35;
        outline: none;
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(input)::placeholder {
        color: color-mix(in srgb, currentColor 45%, transparent);
        font-weight: 600;
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(input):focus {
        outline: none;
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(clear-button) {
        color: color-mix(in srgb, currentColor 55%, transparent);
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(prediction-list) {
        z-index: 200;
        margin-top: 0.35rem;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    }
    .entregas-buscador-google__slot gmp-place-autocomplete::part(prediction-item) {
        font-size: 0.8125rem;
        font-weight: 600;
    }
`;

/**
 * Restricción geográfica válida para PlaceAutocompleteElement.
 * No usar google.maps.Circle directo: la API espera LatLngBounds o locationBias plano.
 */
function construirRestriccionUbicacion(lat, lng, radioKm) {
    if (!window.google?.maps?.Circle) return {};

    const radioMetros = Math.min(Math.max(radioKm * 1000, 1), 50000);
    const center = { lat, lng };

    const circle = new window.google.maps.Circle({
        center,
        radius: radioMetros,
    });

    const bounds = circle.getBounds();

    if (bounds) {
        return { locationRestriction: bounds };
    }

    return {
        locationBias: {
            center,
            radius: radioMetros,
        },
    };
}

/**
 * PlaceAutocompleteElement (sesión de Places integrada — ahorro de tokens).
 */
export default function BuscadorPlacesGoogle({
    isLoaded,
    configuracion,
    onPlaceSelected,
    onInputChange,
    onInitError,
}) {
    const containerRef = useRef(null);

    useEffect(() => {
        if (!isLoaded || !containerRef.current) return;

        const container = containerRef.current;
        let autocompleteElement = null;

        const init = () => {
            try {
                container.innerHTML = '';

                const latOrigen = parseFloat(configuracion?.latitud_origen || 17.99300568);
                const lngOrigen = parseFloat(configuracion?.longitud_origen || -92.94544775);
                const radioKm = parseFloat(configuracion?.radio_tolerancia_km || 7.5);

                const opciones = {
                    componentRestrictions: { country: 'mx' },
                    ...construirRestriccionUbicacion(latOrigen, lngOrigen, radioKm),
                };

                autocompleteElement = new window.google.maps.places.PlaceAutocompleteElement(opciones);
                autocompleteElement.className = 'entregas-place-autocomplete';
                autocompleteElement.setAttribute('placeholder', 'Buscar dirección o punto de entrega...');
                autocompleteElement.setAttribute('aria-label', 'Búsqueda de ubicación de entrega');

                const handleSelect = async (event) => {
                    const placePrediction = event.placePrediction;
                    if (!placePrediction) return;

                    try {
                        const place = placePrediction.toPlace();
                        await place.fetchFields({
                            fields: ['location', 'displayName', 'formattedAddress', 'viewport'],
                        });

                        if (!place.location) {
                            onInitError?.('La ubicación seleccionada no tiene coordenadas válidas.');
                            return;
                        }

                        onPlaceSelected?.({
                            lat: place.location.lat(),
                            lng: place.location.lng(),
                            direccion: place.formattedAddress || place.displayName || '',
                            viewport: place.viewport ?? null,
                        });
                    } catch (error) {
                        console.error('Error fetchFields Places:', error);
                        onInitError?.('Error al obtener los detalles de la ubicación seleccionada.');
                    }
                };

                const handleInput = (event) => {
                    const valor =
                        event.target?.value ??
                        autocompleteElement?.value ??
                        '';
                    onInputChange?.(valor);
                };

                autocompleteElement.addEventListener('gmp-select', handleSelect);
                autocompleteElement.addEventListener('input', handleInput);

                container.appendChild(autocompleteElement);
            } catch (error) {
                console.error('PlaceAutocompleteElement:', error);
                onInitError?.('Error al inicializar el buscador de Google Places.');
            }
        };

        init();

        return () => {
            if (autocompleteElement) {
                autocompleteElement.remove();
            }
            container.innerHTML = '';
        };
    }, [
        isLoaded,
        configuracion?.latitud_origen,
        configuracion?.longitud_origen,
        configuracion?.radio_tolerancia_km,
        onPlaceSelected,
        onInputChange,
        onInitError,
    ]);

    if (!isLoaded) {
        return (
            <div className="entregas-buscador-google theme-element rounded-xl px-4 py-3.5 text-sm font-bold theme-text-muted opacity-60">
                Cargando buscador de direcciones...
            </div>
        );
    }

    return (
        <>
            <style>{ESTILOS_BUSCADOR}</style>
            <div className="entregas-buscador-google theme-surface border theme-border rounded-xl shadow-sm overflow-visible">
                <div ref={containerRef} className="entregas-buscador-google__slot" />
            </div>
        </>
    );
}
