import React, { useEffect } from 'react';
import { Head } from '@inertiajs/react';
import PdvSalaProvider from '@/Components/PuntoVenta/PdvSalaProvider';
import DisplayHeader from './Partials/DisplayHeader';
import AdvertisingPanel from './Partials/AdvertisingPanel';
import QueuePanel from './Partials/QueuePanel';
import DisplayFooter from './Partials/DisplayFooter';

function aplicarTemaPantallaSala(colorHex) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    root.classList.remove('dark');
    root.classList.remove('glass-active');
    if (colorHex) {
        root.style.setProperty('--color-primario', colorHex);
    }
}

function TemaSala({ colorHex }) {
    useEffect(() => {
        aplicarTemaPantallaSala(colorHex);
    }, [colorHex]);

    return null;
}

export default function Sala({ estado_inicial: estadoInicial, sucursal_id: sucursalId, url_estado: urlEstado }) {
    return (
        <>
            <Head title={`Sala de espera | ${estadoInicial?.sucursal?.nombre ?? 'Punto de venta'}`} />
            <PdvSalaProvider
                sucursalId={sucursalId}
                estadoInicial={estadoInicial}
                urlEstado={urlEstado}
            >
                {(ctx) => (
                    <div
                        className="flex h-dvh min-w-[1280px] flex-col gap-3 overflow-hidden p-4 theme-surface theme-text-main"
                        style={{ backgroundColor: 'var(--bg-app, var(--theme-surface-bg))' }}
                    >
                        <TemaSala colorHex={ctx.tema?.color_primario ?? estadoInicial?.tema?.color_primario} />
                        <DisplayHeader
                            sucursalNombre={ctx.sucursal?.nombre ?? estadoInicial?.sucursal?.nombre}
                            estadoConexion={ctx.estadoConexion}
                        />
                        <div className="grid min-h-0 flex-1 grid-cols-[minmax(0,67fr)_minmax(20rem,33fr)] gap-4">
                            <AdvertisingPanel items={ctx.publicidad} />
                            <QueuePanel
                                turnoActual={ctx.turnoActual}
                                proximos={ctx.proximos}
                                anteriores={ctx.anteriores}
                            />
                        </div>
                        <DisplayFooter />
                    </div>
                )}
            </PdvSalaProvider>
        </>
    );
}
