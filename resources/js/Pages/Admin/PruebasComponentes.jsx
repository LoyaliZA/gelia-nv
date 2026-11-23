import React, { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { FlaskConical, Play } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import PantallaActualizacionGelia from '../../Components/PantallaActualizacionGelia';
import { AvisoVersionMini, AvisoVersionTarjeta } from '../../Components/AvisoVersionNueva';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../utils/geliaTheme';
import { ROTACION_ACTUALIZACION_MS, fraseActualizacion } from '../../utils/avisoVersion';

export default function PruebasComponentes() {
    const [pantalla, setPantalla] = useState(false);
    const [indice, setIndice] = useState(0);
    const [ejecucion, setEjecucion] = useState(0);

    useEffect(() => {
        if (!pantalla) {
            return undefined;
        }
        const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reducido) {
            return undefined;
        }
        const id = window.setInterval(() => setIndice((actual) => actual + 1), ROTACION_ACTUALIZACION_MS);

        return () => window.clearInterval(id);
    }, [pantalla]);

    const iniciar = () => {
        setIndice(0);
        setEjecucion((n) => n + 1);
        setPantalla(true);
    };
    const cerrar = useCallback(() => setPantalla(false), []);

    return (
        <AppLayout>
            <Head title="Pruebas de componentes" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8">
                <GeliaTituloCard
                    eyebrow="Banco de pruebas_"
                    title="PRUEBAS DE"
                    titleHighlight="COMPONENTES"
                    description="Previsualiza pantallas y avisos del sistema. Nada de aquí recarga la aplicación ni guarda datos."
                    icon={FlaskConical}
                />

                <section className={geliaCardClass('p-6 space-y-5')} aria-labelledby="prueba-actualizacion">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <h2 id="prueba-actualizacion" className="m-0 text-lg font-semibold theme-text-main">
                                Pantalla de actualización
                            </h2>
                            <p className="m-0 mt-1 text-sm theme-text-muted">
                                Dura 6 s: el logo se dispersa, gira en torbellino, se reúne y hace zoom hasta el blanco.
                                Se cierra con Escape.
                            </p>
                        </div>
                        <button type="button" className={`${THEME_BTN_PRIMARY} min-h-11 shrink-0`} onClick={iniciar}>
                            <Play className="size-4" aria-hidden="true" />
                            Reproducir pantalla
                        </button>
                    </div>
                </section>

                <section className={geliaCardClass('p-6 space-y-5')} aria-labelledby="prueba-aviso">
                    <div>
                        <h2 id="prueba-aviso" className="m-0 text-lg font-semibold theme-text-main">
                            Aviso de nueva versión
                        </h2>
                        <p className="m-0 mt-1 text-sm theme-text-muted">
                            Estado completo y estado plegado. En el sistema pasa de uno a otro a los 12 s.
                            Aquí los botones solo reproducen la pantalla de actualización.
                        </p>
                    </div>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <p className="m-0 text-xs font-semibold uppercase tracking-wide theme-text-muted">Completo</p>
                            <div className="max-w-sm"><AvisoVersionTarjeta onActivar={iniciar} /></div>
                        </div>
                        <div className="space-y-2">
                            <p className="m-0 text-xs font-semibold uppercase tracking-wide theme-text-muted">Plegado</p>
                            <div className="flex max-w-sm justify-end"><AvisoVersionMini onActivar={iniciar} /></div>
                        </div>
                    </div>
                </section>
            </div>

            <PantallaActualizacionGelia
                key={ejecucion}
                isVisible={pantalla}
                vistaPrevia
                alCerrar={cerrar}
                nota={fraseActualizacion(indice)}
            />
        </AppLayout>
    );
}
