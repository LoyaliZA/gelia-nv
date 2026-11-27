import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import useGeliaDeployWatch from '@/hooks/useGeliaDeployWatch';
import PdvSalaProvider from '@/Components/PuntoVenta/PdvSalaProvider';
import { VOLUMEN_PUBLICIDAD_SALA_DEFECTO } from '@/utils/volumenPublicidadPdv';
import { estiloEscenarioKiosko } from '@/utils/rotacionMedioPublicidad';
import AdvertisingPanel from './Partials/AdvertisingPanel';

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

function EscenarioKiosko({ disposicion, children }) {
    const [viewport, setViewport] = useState(() => ({
        ancho: typeof window === 'undefined' ? 1920 : window.innerWidth,
        alto: typeof window === 'undefined' ? 1080 : window.innerHeight,
    }));

    useEffect(() => {
        const medir = () => setViewport({
            ancho: window.innerWidth,
            alto: window.innerHeight,
        });
        medir();
        window.addEventListener('resize', medir);
        return () => window.removeEventListener('resize', medir);
    }, []);

    return (
        <div className="h-dvh w-full overflow-hidden bg-black">
            <div
                data-pdv-kiosko-escenario
                className="h-full w-full"
                style={estiloEscenarioKiosko(disposicion, viewport.ancho, viewport.alto)}
            >
                {children}
            </div>
        </div>
    );
}

export default function Kiosko({
    estado_inicial: estadoInicial,
    sucursal_id: sucursalId,
    url_estado: urlEstado,
    disposicion = 'auto',
}) {
    useGeliaDeployWatch({ superficie: 'kiosko', politica: 'inmediata' });
    const modo = disposicion === 'vertical' ? 'vertical' : 'auto';

    return (
        <>
            <Head title={`Pantalla de publicidad | ${estadoInicial?.sucursal?.nombre ?? 'Punto de venta'}`} />
            <PdvSalaProvider
                sucursalId={sucursalId}
                estadoInicial={estadoInicial}
                urlEstado={urlEstado}
                anunciarTurnos={false}
            >
                {(ctx) => (
                    <div data-pdv-kiosko data-pdv-kiosko-disposicion={modo}>
                        <TemaSala colorHex={ctx.tema?.color_primario ?? estadoInicial?.tema?.color_primario} />
                        <EscenarioKiosko disposicion={modo}>
                            <AdvertisingPanel
                                items={ctx.publicidad}
                                pausado={false}
                                volumen={ctx.volumen_publicidad ?? VOLUMEN_PUBLICIDAD_SALA_DEFECTO}
                                variante="kiosko"
                            />
                        </EscenarioKiosko>
                    </div>
                )}
            </PdvSalaProvider>
        </>
    );
}
