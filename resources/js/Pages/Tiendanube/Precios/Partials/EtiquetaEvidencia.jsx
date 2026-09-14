import React from 'react';
import { GELIA_BADGE } from '../../../../utils/geliaTheme';

const AYUDAS = {
    coincidencia_previa: 'El valor remoto ya coincidía con el aprobado. No se volvió a escribir.',
    por_verificar: 'La API no confirmó el resultado a tiempo. Hay que reconsultar el valor remoto.',
};

export default function EtiquetaEvidencia({ codigo, etiqueta, ayuda }) {
    const textoAyuda = ayuda || AYUDAS[codigo] || '';
    return (
        <span
            className={`${GELIA_BADGE} ${codigo === 'aplicado_api' ? 'border-current' : ''}`}
            title={textoAyuda}
        >
            {etiqueta || codigo}
        </span>
    );
}
