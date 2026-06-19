import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import GeliaPageShell from '@/Components/GeliaPageShell';
import EjercicioEscalonamientoPanel from '@/Components/EjercicioEscalonamiento/EjercicioEscalonamientoPanel';

export default function EjercicioEscalonamiento({ listas }) {
    return (
        <AppLayout title="Ejercicio de Escalonamiento">
            <Head title="Ejercicio de Escalonamiento" />

            <GeliaPageShell className="space-y-5 md:space-y-7 animate-fade-in">
                <EjercicioEscalonamientoPanel listas={listas} variant="page" />
            </GeliaPageShell>
        </AppLayout>
    );
}
