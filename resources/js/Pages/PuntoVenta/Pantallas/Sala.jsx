import React, { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Expand, Gem, Monitor } from 'lucide-react';
import PdvSalaProvider from '@/Components/PuntoVenta/PdvSalaProvider';
import { etiquetaServicioSala } from '@/utils/pantallaSalaUtils';

function TarjetaLlamadoActual({ llamado }) {
    if (!llamado) {
        return (
            <div
                className="rounded-3xl border px-8 py-16 text-center theme-surface"
                style={{ borderColor: 'color-mix(in srgb, var(--color-texto) 12%, transparent)' }}
                data-pdv-sala-sin-llamado
            >
                <p className="text-2xl font-semibold opacity-70">Esperando llamado…</p>
            </div>
        );
    }

    return (
        <article
            className="rounded-3xl border px-6 py-10 sm:px-10 sm:py-14 theme-surface space-y-6"
            style={{ borderColor: 'color-mix(in srgb, var(--color-primario) 35%, transparent)' }}
            data-pdv-sala-llamado-actual
            aria-live="polite"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-black uppercase tracking-[0.2em] opacity-70">
                    Turno en llamado
                </p>
                {llamado.prioridad_diamante && (
                    <span
                        className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wider"
                        style={{
                            borderColor: 'color-mix(in srgb, var(--color-primario) 40%, transparent)',
                            color: 'var(--color-primario)',
                        }}
                        data-pdv-sala-diamante
                    >
                        <Gem className="w-3.5 h-3.5" aria-hidden />
                        Lista Diamante
                    </span>
                )}
            </div>

            <div className="space-y-2">
                <p className="text-5xl sm:text-7xl font-black tracking-tight" data-pdv-sala-folio>
                    {llamado.folio}
                </p>
                <p className="text-xl sm:text-3xl font-semibold" data-pdv-sala-nombre>
                    {llamado.snapshot_nombre_llamado}
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <p className="text-xs uppercase tracking-widest opacity-60">Servicio</p>
                    <p className="text-lg font-semibold" data-pdv-sala-servicio>
                        {etiquetaServicioSala(llamado.servicio)}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase tracking-widest opacity-60">Mostrador</p>
                    <p className="text-lg font-semibold" data-pdv-sala-mostrador>
                        {llamado.atencion_primer_nombre || '—'}
                    </p>
                </div>
            </div>
        </article>
    );
}

function ListaLlamadosRecientes({ llamados, llamadoActual }) {
    const recientes = llamados.filter((item) => item.turno_id !== llamadoActual?.turno_id);

    if (!recientes.length) return null;

    return (
        <section className="space-y-3" data-pdv-sala-lista-llamados aria-label="Llamados recientes">
            <h2 className="text-sm font-black uppercase tracking-[0.18em] opacity-70">
                Llamados recientes
            </h2>
            <ul className="grid gap-3 sm:grid-cols-2">
                {recientes.map((llamado) => (
                    <li
                        key={llamado.turno_id}
                        className="rounded-2xl border px-4 py-3 theme-surface flex items-center justify-between gap-3"
                        style={{ borderColor: 'color-mix(in srgb, var(--color-texto) 10%, transparent)' }}
                    >
                        <div className="min-w-0">
                            <p className="font-bold">{llamado.folio}</p>
                            <p className="text-sm truncate opacity-80">{llamado.snapshot_nombre_llamado}</p>
                        </div>
                        <p className="text-sm font-semibold shrink-0">{llamado.atencion_primer_nombre || '—'}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Sala({ estado_inicial: estadoInicial, sucursal_id: sucursalId, url_estado: urlEstado }) {
    const [pantallaCompleta, setPantallaCompleta] = useState(false);

    const alternarPantallaCompleta = useCallback(async () => {
        if (typeof document === 'undefined') return;

        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
                setPantallaCompleta(true);
            } else {
                await document.exitFullscreen();
                setPantallaCompleta(false);
            }
        } catch {
            setPantallaCompleta(Boolean(document.fullscreenElement));
        }
    }, []);

    return (
        <>
            <Head title={`Sala de espera | ${estadoInicial?.sucursal?.nombre ?? 'Punto de venta'}`} />
            <div className="min-h-screen theme-surface text-[var(--color-texto)]">
                <div className="mx-auto flex min-h-screen max-w-6xl flex-col gap-6 px-4 py-6 sm:px-8 sm:py-10">
                    <header className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-2">
                            <div className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] opacity-70">
                                <Monitor className="w-4 h-4" aria-hidden />
                                Sala de espera
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-black">
                                {estadoInicial?.sucursal?.nombre ?? 'Sucursal'}
                            </h1>
                        </div>
                        <button
                            type="button"
                            onClick={alternarPantallaCompleta}
                            className="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-bold theme-surface"
                            style={{ borderColor: 'color-mix(in srgb, var(--color-texto) 12%, transparent)' }}
                            data-pdv-sala-pantalla-completa
                            aria-pressed={pantallaCompleta}
                        >
                            <Expand className="w-4 h-4" aria-hidden />
                            {pantallaCompleta ? 'Salir de pantalla completa' : 'Pantalla completa'}
                        </button>
                    </header>

                    <PdvSalaProvider
                        sucursalId={sucursalId}
                        estadoInicial={estadoInicial}
                        urlEstado={urlEstado}
                    >
                        {(ctx) => (
                            <main className="flex flex-1 flex-col gap-8">
                                <TarjetaLlamadoActual llamado={ctx.llamadoActual} />
                                <ListaLlamadosRecientes
                                    llamados={ctx.llamados}
                                    llamadoActual={ctx.llamadoActual}
                                />
                            </main>
                        )}
                    </PdvSalaProvider>
                </div>
            </div>
        </>
    );
}
