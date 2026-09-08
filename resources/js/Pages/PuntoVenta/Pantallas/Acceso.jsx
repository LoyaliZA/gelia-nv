import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import AbrirPantallaSalaPdv from '@/Components/PuntoVenta/AbrirPantallaSalaPdv';
import SelectorSucursalActivaPdv from '@/Pages/PuntoVenta/Resguardos/Partials/SelectorSucursalActivaPdv';

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
                    titulo="Pantalla de sala"
                    subtitulo="Genera un enlace revocable para mostrar turnos en una TV sin iniciar sesión."
                />

                <div className="space-y-4">
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                    />
                    <AbrirPantallaSalaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                    />
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
