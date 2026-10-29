import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { History, Loader2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
import NavegacionReportesPdv from './Partials/NavegacionReportesPdv';
import { TIPO_REPORTE_AUDITORIA_OPERACION, paramsFiltrosReportePdv } from './reportesPdvUtils';

function Campo({ label, children, error }) {
    return (
        <label className="flex flex-col gap-1.5 min-w-0">
            <span className="text-sm font-medium theme-text-main">{label}</span>
            {children}
            {error ? <span className="text-sm theme-text-peligro">{error}</span> : null}
        </label>
    );
}

const inputClass = 'w-full rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main';

export default function AuditoriaOperacion({
    filtros = {},
    catalogos = {},
    linea = [],
    consulta_completa = false,
    vistas_disponibles = {},
}) {
    const { errors = {} } = usePage().props;
    const [valores, setValores] = useState(filtros);
    const [cargando, setCargando] = useState(false);
    const errorClave = ['sucursal_id', 'user_id', 'desde', 'hasta'].find((campo) => errors[campo]) || '';

    useEffect(() => {
        if (!errorClave) return;
        document.getElementById(`auditoria-${errorClave}`)?.focus();
    }, [errorClave]);
    const sucursales = catalogos.sucursales || [];
    const personas = catalogos.personas || [];

    const set = (campo, value) => {
        setValores((prev) => {
            const siguiente = { ...prev, [campo]: value };
            if (campo === 'sucursal_id') {
                siguiente.user_id = '';
            }
            return siguiente;
        });
    };

    const aplicar = () => {
        setCargando(true);
        router.get(
            route('punto_venta.reportes.auditoria_operacion'),
            paramsFiltrosReportePdv(valores),
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setCargando(false),
            },
        );
    };

    const limpiar = () => {
        const vacios = { sucursal_id: '', user_id: '', desde: '', hasta: '' };
        setValores(vacios);
        setCargando(true);
        router.get(route('punto_venta.reportes.auditoria_operacion'), {}, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setCargando(false),
        });
    };

    return (
        <AppLayout>
            <Head title="Reportes PDV — Auditoría de operación" />
            <GeliaPageShell>
                <GeliaTituloCard
                    title="Reportes"
                    description="Auditoría de operación"
                    icon={History}
                />
                <div className="space-y-4 md:space-y-6 mt-4 md:mt-6">
                    <NavegacionReportesPdv
                        tipoActivo={TIPO_REPORTE_AUDITORIA_OPERACION}
                        vistasDisponibles={vistas_disponibles}
                    />

                    <form
                        className={geliaCardClass('p-4 md:p-5 space-y-4')}
                        onSubmit={(event) => {
                            event.preventDefault();
                            aplicar();
                        }}
                    >
                        <h2 className="text-sm font-semibold theme-text-main m-0">Consulta</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Campo label="Sucursal" error={errors.sucursal_id}>
                                <select
                                    id="auditoria-sucursal_id"
                                    name="sucursal_id"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={valores.sucursal_id || ''}
                                    onChange={(event) => set('sucursal_id', event.target.value)}
                                >
                                    <option value="">Elegir sucursal</option>
                                    {sucursales.map((sucursal) => (
                                        <option key={sucursal.id} value={sucursal.id}>{sucursal.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <Campo label="Persona" error={errors.user_id}>
                                <select
                                    id="auditoria-user_id"
                                    name="user_id"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={valores.user_id || ''}
                                    onChange={(event) => set('user_id', event.target.value)}
                                    disabled={personas.length === 0}
                                >
                                    <option value="">
                                        {personas.length === 0 ? 'Aplica una sucursal para ver personas' : 'Elegir persona'}
                                    </option>
                                    {personas.map((persona) => (
                                        <option key={persona.id} value={persona.id}>{persona.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <Campo label="Desde" error={errors.desde}>
                                <input
                                    id="auditoria-desde"
                                    name="desde"
                                    type="date"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={valores.desde || ''}
                                    onChange={(event) => set('desde', event.target.value)}
                                />
                            </Campo>
                            <Campo label="Hasta" error={errors.hasta}>
                                <input
                                    id="auditoria-hasta"
                                    name="hasta"
                                    type="date"
                                    autoComplete="off"
                                    className={inputClass}
                                    value={valores.hasta || ''}
                                    onChange={(event) => set('hasta', event.target.value)}
                                />
                            </Campo>
                        </div>
                        <p className="text-sm theme-text-muted m-0">
                            El rango máximo es de 31 días. Las esperas aplicadas quedan guardadas a partir de este registro; la jornada, las pausas y los turnos anteriores siguen visibles.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-2 pt-2 border-t theme-border">
                            <button type="submit" className={THEME_BTN_PRIMARY} disabled={cargando}>
                                {cargando ? 'Consultando…' : 'Consultar'}
                            </button>
                            <button type="button" className={THEME_BTN_SECONDARY} onClick={limpiar} disabled={cargando}>
                                Limpiar
                            </button>
                        </div>
                    </form>

                    <div aria-live="polite">
                    {cargando && (
                        <div className="flex items-center justify-center gap-2 py-8 theme-text-muted" role="status">
                            <Loader2 className="w-5 h-5 motion-safe:animate-spin" />
                            <span className="text-sm">Cargando la línea de tiempo…</span>
                        </div>
                    )}

                    {!cargando && !consulta_completa && (
                        <div className={geliaCardClass('p-4 md:p-5')}>
                            <p className="text-sm theme-text-main m-0 max-w-[65ch]">
                                Elige sucursal, persona y fechas para revisar si las acciones registradas coinciden con el reporte.
                            </p>
                        </div>
                    )}

                    {!cargando && consulta_completa && linea.length === 0 && (
                        <div className={geliaCardClass('p-4 md:p-5')}>
                            <p className="text-sm theme-text-main m-0">No hay acciones en ese rango.</p>
                        </div>
                    )}

                    {!cargando && linea.length > 0 && (
                        <div className={geliaCardClass('p-4 md:p-5')}>
                            <h2 className="text-sm font-semibold theme-text-main m-0 mb-3">Línea de tiempo</h2>
                            <ol className="m-0 p-0 list-none divide-y theme-border">
                                {linea.map((fila) => (
                                    <li key={fila.id} className="grid grid-cols-1 sm:grid-cols-[9.5rem_minmax(0,1fr)] gap-1 sm:gap-4 py-3 min-w-0">
                                        <time className="text-sm tabular-nums theme-text-muted" dateTime={fila.ocurrido_at}>
                                            {fila.hora}
                                        </time>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold theme-text-main m-0 break-words">{fila.titulo}</p>
                                            <p className="text-sm theme-text-muted m-0 mt-1 break-words">{fila.detalle}</p>
                                            {fila.actor ? (
                                                <p className="text-sm theme-text-main m-0 mt-1 break-words">Registrado por {fila.actor}</p>
                                            ) : null}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                    </div>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
