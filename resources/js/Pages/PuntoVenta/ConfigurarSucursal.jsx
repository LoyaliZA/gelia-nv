import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Loader2, MapPin, Store } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_SELECT } from '../../utils/geliaTheme';

export default function ConfigurarSucursal({
    auth,
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    requiere_seleccion: requiereSeleccion = false,
    sin_asignacion: sinAsignacion = false,
    destino = null,
}) {
    const [sucursalId, setSucursalId] = useState(
        sucursalActiva?.id ? String(sucursalActiva.id) : (sucursalesAsignadas[0]?.id ? String(sucursalesAsignadas[0].id) : ''),
    );
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState(null);

    const continuar = async () => {
        if (!sucursalId) {
            setError('Selecciona una sucursal para continuar.');
            return;
        }

        setGuardando(true);
        setError(null);

        try {
            await axios.put(route('punto_venta.sucursal_activa.establecer'), {
                sucursal_id: Number(sucursalId),
            });
            router.visit(destino || route('punto_venta.resguardos.index'));
        } catch (err) {
            const status = err?.response?.status;
            if (status === 403) {
                setError('No tienes permiso para operar con esa sucursal.');
            } else if (status === 422) {
                setError('La sucursal seleccionada no es válida.');
            } else {
                setError('No se pudo establecer la sucursal activa. Intenta de nuevo.');
            }
        } finally {
            setGuardando(false);
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configurar sucursal PDV" />

            <GeliaPageShell>
                <GeliaTituloCard
                    title="Punto de Venta"
                    titleHighlight="sucursal activa"
                    icon={Store}
                    className="!p-4 md:!p-6"
                />

                <div className={`${geliaCardClass()} p-6 md:p-8 space-y-6 max-w-2xl`}>
                    {sinAsignacion ? (
                        <div className="space-y-4">
                            <div className="flex items-start gap-3 p-4 rounded-2xl border border-amber-500/30 bg-amber-500/10">
                                <AlertTriangle className="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-300 mt-0.5" aria-hidden />
                                <div className="space-y-2 min-w-0">
                                    <p className="text-sm font-black uppercase tracking-tight theme-text-main m-0">
                                        Sin sucursal asignada
                                    </p>
                                    <p className="text-xs font-medium theme-text-muted m-0 leading-relaxed">
                                        Para operar en Punto de Venta necesitas al menos una sucursal asignada en tu perfil.
                                        Solicita a un administrador que configure tus sucursales en el directorio de usuarios.
                                    </p>
                                </div>
                            </div>
                            <Link
                                href="/dashboard"
                                className="inline-flex items-center justify-center px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border theme-text-main hover:text-white transition-colors"
                                onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'var(--color-primario)'; e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; e.currentTarget.style.borderColor = ''; }}
                            >
                                Volver al tablero
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            <div className="flex items-start gap-3">
                                <MapPin className="w-5 h-5 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} aria-hidden />
                                <div className="space-y-1 min-w-0">
                                    <p className="text-sm font-black uppercase tracking-tight theme-text-main m-0">
                                        {requiereSeleccion ? 'Selecciona la sucursal de trabajo' : 'Confirma la sucursal activa'}
                                    </p>
                                    <p className="text-xs font-medium theme-text-muted m-0 leading-relaxed">
                                        {requiereSeleccion
                                            ? 'Tienes varias sucursales asignadas. Elige con cuál operarás en piso antes de continuar.'
                                            : 'Esta sucursal se usará para consultar y operar resguardos en piso.'}
                                    </p>
                                </div>
                            </div>

                            {sucursalesAsignadas.length > 1 ? (
                                <label className="block space-y-2">
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                        Sucursal activa
                                    </span>
                                    <select
                                        value={sucursalId}
                                        onChange={(e) => setSucursalId(e.target.value)}
                                        disabled={guardando}
                                        className={`${THEME_SELECT} w-full !py-3 !px-4 text-sm font-bold min-h-[48px]`}
                                        aria-label="Seleccionar sucursal activa"
                                    >
                                        {sucursalesAsignadas.map(({ id, nombre }) => (
                                            <option key={id} value={String(id)}>{nombre}</option>
                                        ))}
                                    </select>
                                </label>
                            ) : (
                                <p className="text-sm font-bold theme-text-main m-0">
                                    Sucursal: <span className="text-[var(--color-primario)]">{sucursalesAsignadas[0]?.nombre}</span>
                                </p>
                            )}

                            {error && (
                                <p className="text-xs font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                            )}

                            <button
                                type="button"
                                onClick={continuar}
                                disabled={guardando || !sucursalId}
                                className={`${THEME_BTN_PRIMARY} w-full sm:w-auto min-h-[48px] gap-2`}
                            >
                                {guardando ? (
                                    <>
                                        <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                                        Configurando...
                                    </>
                                ) : (
                                    'Continuar a Punto de Venta'
                                )}
                            </button>
                        </div>
                    )}
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
