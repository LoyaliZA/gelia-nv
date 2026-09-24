import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import ContenidoDetalleResguardo from './Partials/ContenidoDetalleResguardo';

export default function Show({ auth, resguardo, timeline = [], catalogos = {}, permisos = {}, almacenes = [] }) {
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;

    return (
        <AppLayout auth={auth}>
            <Head title={`${titulo} | Resguardos PDV`} />
            <GeliaPageShell className="max-w-[1100px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link
                        href={route('punto_venta.resguardos.index')}
                        className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                    >
                        <ArrowLeft className="w-4 h-4" /> Volver a bandejas
                    </Link>
                </div>

                <ContenidoDetalleResguardo
                    resguardo={resguardo}
                    timeline={timeline}
                    catalogos={catalogos}
                    permisos={permisos}
                    almacenes={almacenes}
                    mostrarRegresarListado
                />
            </GeliaPageShell>
        </AppLayout>
    );
}
