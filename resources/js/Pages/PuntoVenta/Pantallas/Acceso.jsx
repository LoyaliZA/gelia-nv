import React from 'react';
import { Head } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import AbrirPantallaSalaPdv from '@/Components/PuntoVenta/AbrirPantallaSalaPdv';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import PlaylistPublicidadSalaPdv from './PlaylistPublicidadSalaPdv';

export default function Acceso({
    auth,
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    return (
        <AppLayout auth={auth}>
            <Head title="Pantalla de sala | Punto de venta" />
            <GeliaPageShell>
                <GeliaTituloCard
                    title="Pantalla de sala"
                    description="Enlace permanente por sucursal para la TV de turnos, sin iniciar sesión. Activa o desactiva la pantalla sin cambiar la URL."
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

                <PlaylistPublicidadSalaPdv sucursalActiva={sucursalActiva} />
            </GeliaPageShell>
        </AppLayout>
    );
}
