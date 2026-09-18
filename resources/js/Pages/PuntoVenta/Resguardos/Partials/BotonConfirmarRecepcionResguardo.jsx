import React, { useState } from 'react';
import { CheckCircle2, Loader2, Send } from 'lucide-react';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_ACCION_RECEPCION_TARJETA } from './resguardosStyles';
import { confirmarRecepcionGerente } from './recepcionGerenteApi';

function clasesBoton({ variant, className }) {
    if (variant === 'pie') {
        return `${BTN_ACCION_RECEPCION_TARJETA} ${className}`.trim();
    }

    return `${THEME_BTN_PRIMARY} w-full inline-flex items-center justify-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest ${className}`.trim();
}

export default function BotonConfirmarRecepcionResguardo({
    resguardo,
    className = '',
    onExito,
    etiqueta = 'Confirmar recepción',
    variant = 'primary',
}) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);

    const confirmar = async () => {
        if (enviando) return;

        setEnviando(true);
        setError(null);

        const resultado = await confirmarRecepcionGerente(resguardo);

        setEnviando(false);

        if (resultado.ok) {
            onExito?.(resultado);
            return;
        }

        setError(resultado.error);
    };

    return (
        <div className="space-y-1 w-full">
            <button
                type="button"
                onClick={confirmar}
                disabled={enviando}
                className={clasesBoton({ variant, className })}
            >
                {enviando ? (
                    <Loader2 className="w-[18px] h-[18px] animate-spin shrink-0" aria-hidden />
                ) : (
                    <CheckCircle2 className="w-[18px] h-[18px] shrink-0 stroke-[1.75]" aria-hidden />
                )}
                <span>{enviando ? 'Confirmando…' : etiqueta}</span>
            </button>
            {error && <p className="text-xs text-red-600 dark:text-red-300 m-0 px-1">{error}</p>}
        </div>
    );
}

export function BotonPasarARecepcionResguardo({
    resguardo,
    className = '',
    onExito,
    variant = 'primary',
    etiqueta = 'Pasar a recepción',
}) {
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);

    const enviar = async () => {
        if (enviando) return;

        setEnviando(true);
        setError(null);

        const { pasarARecepcionGerente } = await import('./recepcionGerenteApi');
        const resultado = await pasarARecepcionGerente(resguardo);

        setEnviando(false);

        if (resultado.ok) {
            onExito?.(resultado);
            return;
        }

        setError(resultado.error);
    };

    const Icono = variant === 'pie' ? CheckCircle2 : Send;

    return (
        <div className="space-y-1 w-full">
            <button
                type="button"
                onClick={enviar}
                disabled={enviando}
                className={clasesBoton({ variant, className })}
            >
                {enviando ? (
                    <Loader2 className="w-[18px] h-[18px] animate-spin shrink-0" aria-hidden />
                ) : (
                    <Icono className="w-[18px] h-[18px] shrink-0 stroke-[1.75]" aria-hidden />
                )}
                <span>{enviando ? 'Enviando…' : etiqueta}</span>
            </button>
            {error && <p className="text-xs text-red-600 dark:text-red-300 m-0 px-1">{error}</p>}
        </div>
    );
}
