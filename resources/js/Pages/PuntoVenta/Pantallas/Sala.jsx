import React, { useEffect } from 'react';
import useGeliaDeployWatch from '@/hooks/useGeliaDeployWatch';
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
    useGeliaDeployWatch();

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
                        className="flex h-dvh w-full min-w-0 flex-col gap-2 overflow-hidden p-2 theme-surface theme-text-main sm:gap-3 sm:p-3 lg:p-4 [@media(max-height:760px)]:gap-1.5 [@media(max-height:760px)]:p-2"
                        style={{ backgroundColor: 'var(--bg-app, var(--theme-surface-bg))' }}
                    >
                        <TemaSala colorHex={ctx.tema?.color_primario ?? estadoInicial?.tema?.color_primario} />
                        <DisplayHeader
                            sucursalNombre={ctx.sucursal?.nombre ?? estadoInicial?.sucursal?.nombre}
                            estadoConexion={ctx.estadoConexion}
                        />
                        <div className="grid min-h-0 min-w-0 flex-1 grid-cols-1 grid-rows-[auto_minmax(0,1fr)] gap-2 overflow-hidden min-[1100px]:grid-cols-[minmax(0,1.65fr)_minmax(16rem,0.95fr)] min-[1100px]:grid-rows-1 min-[1100px]:gap-4">
                            <AdvertisingPanel
                                items={ctx.publicidad}
                                pausado={Boolean(ctx.hablando)}
                                volumen={ctx.volumen_publicidad ?? 0.35}
                            />
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
