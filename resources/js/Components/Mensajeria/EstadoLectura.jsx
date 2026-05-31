import React from 'react';
import { Check, CheckCheck } from 'lucide-react';

const ETIQUETAS = {
    enviado: 'Enviado',
    entregado: 'Entregado',
    leido: 'Visto',
};

const CLASES_ESTADO = {
    enviado: 'gelia-estado-lectura--enviado',
    entregado: 'gelia-estado-lectura--entregado',
    leido: 'gelia-estado-lectura--visto',
};

export default function EstadoLectura({ estado = 'enviado', esPropio }) {
    if (!esPropio) return null;

    const etiqueta = ETIQUETAS[estado] || ETIQUETAS.enviado;
    const clase = CLASES_ESTADO[estado] || CLASES_ESTADO.enviado;
    const Icono = estado === 'enviado' ? Check : CheckCheck;

    return (
        <span
            className={`gelia-estado-lectura ${clase}`}
            aria-label={etiqueta}
            title={etiqueta}
        >
            <Icono aria-hidden />
        </span>
    );
}
