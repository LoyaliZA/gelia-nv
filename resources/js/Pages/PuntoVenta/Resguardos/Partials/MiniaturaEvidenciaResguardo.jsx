import React, { useMemo, useState } from 'react';
import { FileImage } from 'lucide-react';
import LightboxFotos from '../../../Activos/Partials/LightboxFotos';

export function esImagenEvidenciaResguardo(evidencia) {
    if (!evidencia?.ruta_publica) {
        return false;
    }
    const mime = String(evidencia.mime_type || '').toLowerCase();
    if (mime.startsWith('image/')) {
        return true;
    }
    const tipo = String(evidencia.tipo || '').toLowerCase();
    return tipo === 'firma' || tipo === 'foto';
}

function urlsImagenesDesdeEvidencias(evidencias = []) {
    return evidencias
        .filter((item) => esImagenEvidenciaResguardo(item))
        .map((item) => item.ruta_publica);
}

/**
 * Miniatura con zoom al pasar el cursor o al presionar (móvil) y visor en la misma página.
 */
export default function MiniaturaEvidenciaResguardo({
    evidencia,
    etiqueta = 'Evidencia',
    className = '',
    evidenciasGrupo = [],
    tamano = 'md',
}) {
    const [lightboxIndice, setLightboxIndice] = useState(null);

    const urlsGrupo = useMemo(
        () => urlsImagenesDesdeEvidencias(evidenciasGrupo.length ? evidenciasGrupo : [evidencia]),
        [evidenciasGrupo, evidencia],
    );

    const esImagen = esImagenEvidenciaResguardo(evidencia);
    const alt = evidencia?.nombre_original || etiqueta;

    const altura = tamano === 'sm' ? 'h-20' : tamano === 'lg' ? 'h-36' : 'h-28';
    const objectFit = evidencia?.tipo === 'firma' ? 'object-contain' : 'object-cover';

    const abrirVisor = () => {
        if (!esImagen) return;
        const indice = urlsGrupo.indexOf(evidencia.ruta_publica);
        setLightboxIndice(indice >= 0 ? indice : 0);
    };

    if (!evidencia) {
        return null;
    }

    if (!esImagen) {
        return (
            <span className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest theme-text-muted">
                <FileImage className="w-3 h-3 shrink-0" />
                {evidencia.tipo === 'firma' ? 'Firma capturada' : (evidencia.nombre_original || etiqueta)}
            </span>
        );
    }

    return (
        <>
            <button
                type="button"
                onClick={abrirVisor}
                className={`group block w-full rounded-xl overflow-hidden border theme-border outline-none hover:border-[var(--color-primario)]/40 focus-visible:border-[var(--color-primario)]/50 transition-colors ${className}`}
                aria-label={`Ver ${etiqueta}`}
            >
                <div className={`${altura} w-full overflow-hidden bg-[color-mix(in_srgb,var(--theme-text-main)_4%,transparent)]`}>
                    <img
                        src={evidencia.ruta_publica}
                        alt={alt}
                        className={`w-full h-full ${objectFit} transition-transform duration-200 ease-out group-hover:scale-110 group-active:scale-110`}
                    />
                </div>
                {etiqueta ? (
                    <span className="block px-2 py-1.5 text-[9px] font-black uppercase tracking-widest theme-text-muted text-left truncate">
                        {etiqueta}
                    </span>
                ) : null}
            </button>
            {lightboxIndice != null && urlsGrupo.length > 0 && (
                <LightboxFotos
                    fotos={urlsGrupo}
                    indiceInicial={lightboxIndice}
                    onCerrar={() => setLightboxIndice(null)}
                    zIndex="calc(var(--gelia-z-modal) + 25)"
                />
            )}
        </>
    );
}

export function GaleriaEvidenciasResguardo({ evidencias = [], className = '' }) {
    const imagenes = evidencias.filter((item) => esImagenEvidenciaResguardo(item));
    if (imagenes.length === 0) {
        return null;
    }

    return (
        <ul className={`grid grid-cols-2 sm:grid-cols-3 gap-2 m-0 p-0 list-none ${className}`}>
            {imagenes.map((evidencia) => (
                <li key={evidencia.id}>
                    <MiniaturaEvidenciaResguardo
                        evidencia={evidencia}
                        etiqueta={evidencia.tipo === 'firma' ? 'Firma de entrega' : (evidencia.nombre_original || 'Evidencia')}
                        evidenciasGrupo={imagenes}
                        tamano="sm"
                    />
                </li>
            ))}
        </ul>
    );
}
