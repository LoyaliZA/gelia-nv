import React from 'react';
import { GELIA_ESTADO_VIVO_TONO } from '@/utils/geliaTheme';

export default function PdvIndicadorEstadoVivo({
    icono: Icono,
    etiqueta,
    titulo,
    tono = 'neutro',
    pulsando = false,
    clickeable = false,
    deshabilitado = false,
    onClick = null,
    dataAtributo = null,
}) {
    const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;
    const puedeInteractuar = clickeable && typeof onClick === 'function' && !deshabilitado;

    const claseBase = [
        'gelia-estado-vivo',
        tonoClass,
        pulsando ? 'gelia-estado-vivo--pulsando' : '',
        puedeInteractuar ? 'gelia-estado-vivo--clickeable' : '',
        deshabilitado ? 'gelia-estado-vivo--deshabilitado' : '',
    ].filter(Boolean).join(' ');

    const contenido = (
        <>
            <Icono className="w-4 h-4 shrink-0" aria-hidden />
            {pulsando && <span className="gelia-estado-vivo__pulso" aria-hidden />}
        </>
    );

    if (puedeInteractuar) {
        return (
            <button
                type="button"
                className={claseBase}
                onClick={onClick}
                aria-label={etiqueta}
                title={titulo || etiqueta}
                data-pdv-indicador-estado={dataAtributo}
            >
                {contenido}
            </button>
        );
    }

    return (
        <span
            className={claseBase}
            role="status"
            aria-label={etiqueta}
            title={titulo || etiqueta}
            data-pdv-indicador-estado={dataAtributo}
        >
            {contenido}
        </span>
    );
}
