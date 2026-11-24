import React from 'react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import ConsultasMunicipalesTienda from '../Tienda/Partials/ConsultasMunicipalesTienda';

export default function ConsultasTienda({ pedidos = [] }) {
    return (
        <AppLayout><GeliaPageShell>
            <GeliaTituloCard eyebrow="Municipal" title="Consulta pendiente: Tienda" description="Confirme disponibilidad o reporte las piezas con excepción y su evidencia." />
            <ConsultasMunicipalesTienda pedidos={pedidos} vacio="No hay consultas pendientes en Tienda." />
        </GeliaPageShell></AppLayout>
    );
}
