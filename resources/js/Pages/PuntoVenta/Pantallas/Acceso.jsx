import React from 'react';
import { Head } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import AbrirPantallaSalaPdv from '@/Components/PuntoVenta/AbrirPantallaSalaPdv';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';

export default function Acceso({
    auth,
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    return (
        <AppLayout auth={auth}>
            <Head title="Pantalla de sala | Punto de venta" />
            <GeliaPageShell className="space-y-4">
                <GeliaTituloCard
                    title="Pantalla de sala"
                    description="Prepara el código de la TV y abre la de turnos o la de publicidad."
                    icon={Monitor}
                >
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compacto"
                    />
                </GeliaTituloCard>

                <AbrirPantallaSalaPdv
                    sucursalActiva={sucursalActiva}
                    sucursalesAsignadas={sucursalesAsignadas}
                />
            </GeliaPageShell>
        </AppLayout>
    );
}
