import React from 'react';
import { RotateCcw, RotateCw } from 'lucide-react';
import { THEME_BTN_SECONDARY, THEME_LABEL } from '@/utils/geliaTheme';
import { girarRotacionPublicidad, normalizarRotacionPublicidad } from '@/utils/rotacionMedioPublicidad';
import CapaMediaRotada from '@/Pages/PuntoVenta/Pantallas/Partials/CapaMediaRotada';

function MarcoEncuadre({
    etiqueta,
    ratio,
    url,
    tipo = 'imagen',
    rotacion = 0,
    ajuste = 'cover',
}) {
    const objectFit = ajuste === 'contain' ? 'contain' : 'cover';
    const marcoClase = ratio === '9:16'
        ? 'aspect-[9/16] mx-auto w-full max-w-[11rem]'
        : 'aspect-video w-full';

    return (
        <div className="min-w-0 space-y-1">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{etiqueta}</p>
            <div className={`overflow-hidden rounded-2xl bg-black ${marcoClase}`}>
                {url ? (
                    <CapaMediaRotada rotacion={rotacion}>
                        {tipo === 'video' ? (
                            <video
                                src={url}
                                muted
                                playsInline
                                loop
                                autoPlay
                                className="h-full w-full"
                                style={{ objectFit }}
                            />
                        ) : (
                            <img src={url} alt="" className="h-full w-full" style={{ objectFit }} />
                        )}
                    </CapaMediaRotada>
                ) : (
                    <div className="flex h-full min-h-[8rem] items-center justify-center px-3 text-center text-xs theme-text-muted">
                        Carga un archivo para previsualizar el encuadre.
                    </div>
                )}
            </div>
        </div>
    );
}

export default function EditorEncuadrePublicidad({
    url = null,
    tipo = 'imagen',
    rotacion = 0,
    ajuste = 'cover',
    onRotacionChange,
}) {
    const grados = normalizarRotacionPublicidad(rotacion);
    const girar = (sentido) => {
        onRotacionChange?.(girarRotacionPublicidad(grados, sentido));
    };

    return (
        <div className="space-y-3 md:col-span-2" data-pdv-publicidad-encuadre>
            <span className={THEME_LABEL}>Encuadre</span>
            <p className="text-sm theme-text-muted m-0">
                Gira la pieza si el archivo subió de lado. El archivo no se reescribe; el giro se aplica al reproducir.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                <MarcoEncuadre
                    etiqueta="Marco 16:9"
                    ratio="16:9"
                    url={url}
                    tipo={tipo}
                    rotacion={grados}
                    ajuste={ajuste}
                />
                <MarcoEncuadre
                    etiqueta="Marco 9:16"
                    ratio="9:16"
                    url={url}
                    tipo={tipo}
                    rotacion={grados}
                    ajuste={ajuste}
                />
            </div>
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex min-h-[44px] items-center justify-center gap-2`}
                    onClick={() => girar('izquierda')}
                    aria-label="Girar a la izquierda"
                >
                    <RotateCcw className="h-4 w-4" aria-hidden />
                    Girar a la izquierda
                </button>
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} inline-flex min-h-[44px] items-center justify-center gap-2`}
                    onClick={() => girar('derecha')}
                    aria-label="Girar a la derecha"
                >
                    <RotateCw className="h-4 w-4" aria-hidden />
                    Girar a la derecha
                </button>
                <p className="self-center text-xs font-bold theme-text-muted m-0">
                    Rotación: {grados}°
                </p>
            </div>
        </div>
    );
}
