import React from 'react';
import FiltrosBandejaOperativa from '../../Partials/FiltrosBandejaOperativa';
export const TABS_TIENDA = [
    { id: 'TODOS', label: 'Todos' }, { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'EN_ATENCION', label: 'En atención' }, { id: 'ATENDIDOS', label: 'Atendidos' },
];
export default function FiltrosTienda(props) {
    return <FiltrosBandejaOperativa id="tienda" {...props} />;
}
