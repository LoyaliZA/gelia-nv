import React from 'react';
import { Package, Clock, PackageOpen, CheckCircle2 } from 'lucide-react';
import { IndicadoresOperacion } from '../../Partials/FiltrosBandejaOperativa';
const OPCIONES = [
    { id: 'TODOS', key: 'todos', label: 'Todos', icon: Package },
    { id: 'PENDIENTES', key: 'pendientes', label: 'Pendientes', icon: Clock },
    { id: 'EN_ATENCION', key: 'en_atencion_operativa', label: 'En atención', icon: PackageOpen },
    { id: 'ATENDIDOS', key: 'atendidos', label: 'Atendidos', icon: CheckCircle2 },
];
export default function MetricasBandejaTienda({ metricas = {}, tabActiva, onTabChange }) {
    return <IndicadoresOperacion label="Estado de atención" activo={tabActiva} onCambiar={onTabChange}
        opciones={OPCIONES.map(opcion => ({ ...opcion, valor: metricas[opcion.key] }))} />;
}
