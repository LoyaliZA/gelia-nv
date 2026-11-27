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
    acciones = null,
}) {
    const objectFit = ajuste === 'contain' ? 'contain' : 'cover';
    const marcoClase = ratio === '9:16'
        ? 'aspect-[9/16] mx-auto w-full max-w-[11rem]'
        : 'aspect-video w-full';

    return (
        <div className="min-w-0 space-y-2">
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
            {acciones}
        </div>
    );
}

function BotonesGiro({ grados, onGirar, etiquetaCorta }) {
    return (
        <div className="flex flex-wrap gap-2">
            <button
                type="button"
                className={`${THEME_BTN_SECONDARY} inline-flex min-h-[44px] items-center justify-center gap-2`}
                onClick={() => onGirar('izquierda')}
                aria-label={`Girar a la izquierda${etiquetaCorta ? ` (${etiquetaCorta})` : ''}`}
            >
                <RotateCcw className="h-4 w-4" aria-hidden />
                Girar a la izquierda
            </button>
            <button
                type="button"
                className={`${THEME_BTN_SECONDARY} inline-flex min-h-[44px] items-center justify-center gap-2`}
                onClick={() => onGirar('derecha')}
                aria-label={`Girar a la derecha${etiquetaCorta ? ` (${etiquetaCorta})` : ''}`}
            >
                <RotateCw className="h-4 w-4" aria-hidden />
                Girar a la derecha
            </button>
            <p className="self-center text-xs font-bold theme-text-muted m-0">
                Rotación{etiquetaCorta ? ` ${etiquetaCorta}` : ''}: {grados}°
            </p>
        </div>
    );
}

export default function EditorEncuadrePublicidad({
    url = null,
    tipo = 'imagen',
    rotacion = 0,
    rotacionKiosko = null,
    ajuste = 'cover',
    onRotacionChange,
    onRotacionKioskoChange,
}) {
    const gradosSala = normalizarRotacionPublicidad(rotacion);
    const compartido = rotacionKiosko === null || rotacionKiosko === undefined;
    const gradosKiosko = compartido ? gradosSala : normalizarRotacionPublicidad(rotacionKiosko);

    const girarSala = (sentido) => {
        const siguiente = girarRotacionPublicidad(gradosSala, sentido);
        onRotacionChange?.(siguiente);
        if (compartido) {
            onRotacionKioskoChange?.(null);
        }
    };

    const girarKiosko = (sentido) => {
        onRotacionKioskoChange?.(girarRotacionPublicidad(gradosKiosko, sentido));
    };

    const alternarCompartido = () => {
        if (compartido) {
            onRotacionKioskoChange?.(gradosSala);
            return;
        }
        onRotacionKioskoChange?.(null);
    };

    return (
        <div className="space-y-3 md:col-span-2" data-pdv-publicidad-encuadre>
            <span className={THEME_LABEL}>Encuadre</span>
            <p className="text-sm theme-text-muted m-0">
                Gira la pieza si el archivo subió de lado. El archivo no se reescribe; el giro se aplica al reproducir.
            </p>
            <div className="flex items-start justify-between gap-3 rounded-2xl border theme-border p-3">
                <div className="space-y-1 min-w-0">
                    <p className="text-sm font-bold theme-text-main m-0">Mismo giro en TV y kiosko</p>
                    <p className="text-xs theme-text-muted m-0">
                        La TV de turnos es 16:9 y el kiosko 9:16. Desactiva esto para girar cada pantalla por separado.
                    </p>
                </div>
                <button
                    type="button"
                    className="gelia-switch shrink-0"
                    data-active={compartido}
                    aria-pressed={compartido}
                    aria-label={compartido ? 'Usar giro distinto por pantalla' : 'Usar el mismo giro en TV y kiosko'}
                    onClick={alternarCompartido}
                >
                    <span className="gelia-switch-thumb" />
                </button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <MarcoEncuadre
                    etiqueta="TV de turnos 16:9"
                    ratio="16:9"
                    url={url}
                    tipo={tipo}
                    rotacion={gradosSala}
                    ajuste={ajuste}
                    acciones={!compartido ? (
                        <BotonesGiro grados={gradosSala} onGirar={girarSala} etiquetaCorta="TV" />
                    ) : null}
                />
                <MarcoEncuadre
                    etiqueta="Pantalla de publicidad 9:16"
                    ratio="9:16"
                    url={url}
                    tipo={tipo}
                    rotacion={gradosKiosko}
                    ajuste={ajuste}
                    acciones={!compartido ? (
                        <BotonesGiro grados={gradosKiosko} onGirar={girarKiosko} etiquetaCorta="kiosko" />
                    ) : null}
                />
            </div>
            {compartido ? (
                <BotonesGiro grados={gradosSala} onGirar={girarSala} />
            ) : null}
        </div>
    );
}
