import React from 'react';
import { Camera, ImagePlus } from 'lucide-react';
import { BTN_CAPTURA_EVIDENCIA, ICONO_CAPTURA_EVIDENCIA } from './resguardosStyles';

export default function BotonesCapturaEvidencia({
    onAgregar,
    deshabilitado = false,
    etiquetaCamara = 'Tomar foto',
    etiquetaGaleria = 'Galería',
    camaraMultiple = false,
    galeriaMultiple = true,
    className = '',
}) {
    const manejarArchivos = (archivos) => {
        onAgregar?.(archivos);
    };

    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()}>
            <label className={`${BTN_CAPTURA_EVIDENCIA} ${deshabilitado ? 'pointer-events-none opacity-50' : ''}`}>
                <Camera className={ICONO_CAPTURA_EVIDENCIA} aria-hidden />
                {etiquetaCamara}
                <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple={camaraMultiple}
                    className="sr-only"
                    disabled={deshabilitado}
                    onChange={(e) => {
                        manejarArchivos(e.target.files);
                        e.target.value = '';
                    }}
                />
            </label>
            <label className={`${BTN_CAPTURA_EVIDENCIA} ${deshabilitado ? 'pointer-events-none opacity-50' : ''}`}>
                <ImagePlus className={ICONO_CAPTURA_EVIDENCIA} aria-hidden />
                {etiquetaGaleria}
                <input
                    type="file"
                    accept="image/*"
                    multiple={galeriaMultiple}
                    className="sr-only"
                    disabled={deshabilitado}
                    onChange={(e) => {
                        manejarArchivos(e.target.files);
                        e.target.value = '';
                    }}
                />
            </label>
        </div>
    );
}
