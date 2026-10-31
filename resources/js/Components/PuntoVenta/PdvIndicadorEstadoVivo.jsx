import React from 'react';
import { GELIA_ESTADO_VIVO_TONO } from '@/utils/geliaTheme';
import useIconoEnControl from '@/hooks/useIconoEnControl';

export default function PdvIndicadorEstadoVivo(props) {
    if (props.animado) {
        return <IndicadorAnimado {...props} />;
    }

    return <Indicador {...props} />;
}

function IndicadorAnimado(props) {
    const { ref, triggerProps } = useIconoEnControl();

    return <Indicador {...props} iconoRef={ref} disparador={triggerProps} />;
}

function Indicador({
    icono: Icono,
    etiqueta,
    titulo,
    texto = null,
    tono = 'neutro',
    pulsando = false,
    clickeable = false,
    deshabilitado = false,
    onClick = null,
    dataAtributo = null,
    animado = false,
    iconoRef = null,
    disparador = null,
}) {
    const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;
    const puedeInteractuar = clickeable && typeof onClick === 'function' && !deshabilitado;

    const claseBase = [
        'gelia-estado-vivo',
        texto ? 'gelia-estado-vivo--etiqueta' : '',
        tonoClass,
        pulsando ? 'gelia-estado-vivo--pulsando' : '',
        puedeInteractuar ? 'gelia-estado-vivo--clickeable' : '',
        deshabilitado ? 'gelia-estado-vivo--deshabilitado' : '',
    ].filter(Boolean).join(' ');

    const icono = animado
        ? <Icono ref={iconoRef} size={14} duration={0.9} aria-hidden />
        : <Icono className="w-4 h-4 shrink-0" aria-hidden />;

    const contenido = (
        <>
            {icono}
            {texto ? <span className="whitespace-nowrap text-[10px] font-bold uppercase tracking-wide leading-none">{texto}</span> : null}
            {pulsando && <span className="gelia-estado-vivo__pulso" aria-hidden />}
        </>
    );

    if (puedeInteractuar) {
        return (
            <button
                type="button"
                className={claseBase}
                {...(animado ? disparador : null)}
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
            {...(animado ? disparador : null)}
            data-pdv-indicador-estado={dataAtributo}
        >
            {contenido}
        </span>
    );
}
