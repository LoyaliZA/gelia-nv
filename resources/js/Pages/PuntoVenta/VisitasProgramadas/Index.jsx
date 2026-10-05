import React, { useCallback, useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { CalendarClock, RefreshCw } from 'lucide-react';
import TarjetaVisitaProgramada from '../../Comercial/VisitasProgramadas/Partials/TarjetaVisitaProgramada';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
export default function VisitasProgramadasPdvIndex({
    auth,
    bandeja: bandejaInicial,
    permisos = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
}) {
    const { flash } = usePage().props;
    const [bandeja, setBandeja] = useState(bandejaInicial);
    const [confirmando, setConfirmando] = useState(null);
    const [refrescando, setRefrescando] = useState(false);

    const puedeVer = Boolean(permisos.ver);
    const puedeConfirmar = Boolean(permisos.confirmar_llegada);

    const refrescar = useCallback(async (silencioso = false) => {
        if (!puedeVer) return;
        if (!silencioso) setRefrescando(true);
        try {
            const res = await fetch(route('punto_venta.visitas_programadas.datos'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                setBandeja(await res.json());
            }
        } finally {
            if (!silencioso) setRefrescando(false);
        }
    }, [puedeVer]);

    useEffect(() => {
        const id = setInterval(() => refrescar(true), 60_000);
        return () => clearInterval(id);
    }, [refrescar]);

    const registrarLlegada = (visitaId) => {
        setConfirmando(visitaId);
        router.post(
            route('punto_venta.visitas_programadas.llegada', visitaId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => refrescar(true),
                onFinish: () => setConfirmando(null),
            },
        );
    };

    const visitas = bandeja?.visitas ?? [];

    return (
        <AppLayout auth={auth}>
            <Head title="Visitas del día | Punto de venta" />
            <GeliaPageShell className="max-w-[960px] mx-auto w-full space-y-5" data-recepcion-movil-root>
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Visitas del"
                    titleHighlight="día"
                    description="Clientes con asistencia programada para hoy en esta sucursal."
                    icon={CalendarClock}
                    className="!p-4 md:!p-5 lg:!p-6 !gap-3 md:!gap-4 [&_h1]:!text-2xl sm:[&_h1]:!text-3xl md:[&_h1]:!text-3xl"
                >
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compacto"
                    />
                </GeliaTituloCard>

                <div className="flex justify-end">
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2`}
                        disabled={refrescando || !puedeVer}
                        onClick={() => refrescar()}
                    >
                        <RefreshCw className={`w-4 h-4 ${refrescando ? 'animate-spin' : ''}`} aria-hidden />
                        Actualizar
                    </button>
                </div>

                {flash?.success && (
                    <div className={`${geliaCardClass()} p-4 text-[11px] font-bold uppercase tracking-widest text-emerald-700`}>
                        {flash.success}
                    </div>
                )}

                {!sucursalActiva && (
                    <div className={`${geliaCardClass()} p-4 text-[11px] font-bold uppercase tracking-widest theme-text-muted`}>
                        Selecciona una sucursal activa para ver las visitas.
                    </div>
                )}

                <div className="grid grid-cols-1 gap-3">
                    {visitas.length === 0 && sucursalActiva && (
                        <div className={`${geliaCardClass()} p-6 text-center text-[11px] font-black uppercase tracking-widest theme-text-muted`}>
                            No hay visitas programadas para hoy.
                        </div>
                    )}
                    {visitas.map((visita) => (
                        <TarjetaVisitaProgramada
                            key={visita.id}
                            visita={visita}
                            variante="pdv"
                            puedeConfirmarLlegada={puedeConfirmar}
                            confirmando={confirmando === visita.id}
                            onConfirmarLlegada={registrarLlegada}
                        />
                    ))}
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
