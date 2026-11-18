import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import ExpedienteConsignatario from '../ControlPedidos/Municipal/ExpedienteConsignatario';

export default function ExpedienteCliente({ cliente }) {
    return (
        <AppLayout><GeliaPageShell>
            <GeliaTituloCard eyebrow="Clientes" title="Expediente" description={cliente?.nombre || ''} />
            <p className="text-sm theme-text-muted">Los datos vigentes se usan en pedidos nuevos. Una solicitud ya guardada conserva la versión que eligió.</p>
            <Link href={route('admin.clientes')} className="text-sm font-bold theme-text-primario">Volver a clientes</Link>
            <div className="mt-4 max-w-2xl">
                <ExpedienteConsignatario clienteId={cliente.id} />
            </div>
        </GeliaPageShell></AppLayout>
    );
}
