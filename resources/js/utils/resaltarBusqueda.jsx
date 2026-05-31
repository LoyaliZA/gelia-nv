import React from 'react';

export function resaltarTexto(texto, termino) {
    if (!texto || !termino || termino.length < 2) {
        return texto;
    }

    const regex = new RegExp(`(${termino.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    const partes = String(texto).split(regex);

    return partes.map((parte, i) => {
        if (parte.toLowerCase() === termino.toLowerCase()) {
            return (
                <mark key={i} className="gelia-busqueda-mark">
                    {parte}
                </mark>
            );
        }

        return <React.Fragment key={i}>{parte}</React.Fragment>;
    });
}
