import { useRef, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Link2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
} from '../../utils/geliaTheme';

const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function dinero(valor) {
    const numero = Number(valor ?? 0);
    return Number.isFinite(numero)
        ? numero.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
        : '—';
}

export default function Conciliacion({ auth, periodo, solicitudes = [], puedeOperar = false }) {
    const { flash } = usePage().props;
    const [activa, setActiva] = useState(null);
    const [sugerencias, setSugerencias] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [errorSugerencias, setErrorSugerencias] = useState('');
    const solicitudActivaRef = useRef(null);

    const form = useForm({
        solicitud_tag_id: '',
        documento_venta_id: '',
        importe_asignado: '',
        evidencia: '',
    });

    const abrirAsignar = async (solicitud) => {
        const solicitudId = solicitud.solicitud_id;
        solicitudActivaRef.current = solicitudId;
        setActiva(solicitud);
        setSugerencias([]);
        setErrorSugerencias('');
        form.clearErrors();
        form.setData({
            solicitud_tag_id: solicitudId,
            documento_venta_id: '',
            importe_asignado: solicitud.importe_objetivo ?? '',
            evidencia: '',
        });
        setCargando(true);

        try {
            const resp = await fetch(route('escalonamiento.conciliacion.sugerencias', solicitudId), {
                headers: { Accept: 'application/json' },
            });

            if (!resp.ok) {
                throw new Error(`No se pudieron cargar las sugerencias (${resp.status}).`);
            }

            const json = await resp.json();
            if (solicitudActivaRef.current !== solicitudId) return;

            setSugerencias(Array.isArray(json.sugerencias) ? json.sugerencias : []);
            if (json.importe_objetivo != null) {
                form.setData('importe_asignado', json.importe_objetivo);
            }
        } catch (error) {
            if (solicitudActivaRef.current === solicitudId) {
                setSugerencias([]);
                setErrorSugerencias(error instanceof Error ? error.message : 'No se pudieron cargar las sugerencias.');
            }
        } finally {
            if (solicitudActivaRef.current === solicitudId) {
                setCargando(false);
            }
        }
    };

    const cerrarAsignacion = () => {
        solicitudActivaRef.current = null;
        setActiva(null);
        setSugerencias([]);
        setErrorSugerencias('');
        setCargando(false);
        form.reset();
        form.clearErrors();
    };

    const enviar = (event) => {
        event.preventDefault();
        form.post(route('escalonamiento.conciliacion.asignar'), {
            preserveScroll: true,
            onSuccess: () => {
                cerrarAsignacion();
            },
        });
    };

    const quitar = (conciliacionId) => {
        router.post(route('escalonamiento.conciliacion.quitar', conciliacionId), {}, { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Conciliación solicitudes" />
            <GeliaPageShell className="space-y-6 pb-10">
                <Link href={route('escalonamiento.index')} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}>
                    <ArrowLeft className="w-4 h-4" aria-hidden />
                    Escalonamiento
                </Link>

                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title="Conciliación de solicitudes"
                    description="Cotejo de solicitudes pagadas contra remisiones del período. No modifica el acumulado oficial ni suma venta por pago."
                    icon={Link2}
                />

                {flash?.success && <p className={`${geliaCardClass('p-4')} text-sm theme-text-exito m-0`} role="status">{flash.success}</p>}
                {flash?.error && <p className={`${geliaCardClass('p-4')} text-sm theme-text-peligro m-0`} role="alert">{flash.error}</p>}

                {!periodo ? (
                    <p className={geliaCardClass('p-6 theme-text-muted m-0')}>No hay período para conciliar.</p>
                ) : (
                    <section className={geliaCardClass('p-6 space-y-4')}>
                        <p className="text-sm theme-text-muted m-0">
                            Período: {MESES[periodo.mes - 1]} {periodo.anio}
                        </p>
                        {solicitudes.length === 0 ? (
                            <p className="theme-text-muted m-0">No hay solicitudes pagadas en este período.</p>
                        ) : (
                            <ul className="m-0 p-0 list-none space-y-4">
                                {solicitudes.map((sol) => (
                                    <li key={sol.solicitud_id} className="border-t theme-border pt-4">
                                        <div className="flex flex-wrap justify-between gap-2">
                                            <div>
                                                <p className="font-semibold theme-text-main m-0">
                                                    Solicitud #{sol.solicitud_id} · {sol.numero_cliente} {sol.nombre}
                                                </p>
                                                <p className="text-sm theme-text-muted m-0">
                                                    Objetivo {dinero(sol.importe_objetivo)} · Asignado {dinero(sol.importe_asignado)} · {sol.estado_cobertura}
                                                </p>
                                            </div>
                                            {puedeOperar && (
                                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => abrirAsignar(sol)}>
                                                    Asignar remisión
                                                </button>
                                            )}
                                        </div>
                                        {(sol.conciliaciones || []).length > 0 && (
                                            <ul className="mt-2 text-sm theme-text-muted m-0 p-0 list-none space-y-1">
                                                {sol.conciliaciones.map((c) => (
                                                    <li key={c.id} className="flex flex-wrap gap-2 items-center">
                                                        <span>
                                                            Remisión {c.folio || c.documento_venta_id}: {dinero(c.importe_asignado)}
                                                        </span>
                                                        {puedeOperar && (
                                                            <button type="button" className="underline text-xs" onClick={() => quitar(c.id)}>
                                                                Quitar
                                                            </button>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {activa && puedeOperar && (
                    <section className={geliaCardClass('p-6 space-y-4')}>
                        <h2 className="text-base font-black theme-text-main m-0">Asignar documento · solicitud #{activa.solicitud_id}</h2>
                        {errorSugerencias && (
                            <p className="theme-text-peligro m-0 text-sm" role="alert">{errorSugerencias}</p>
                        )}
                        {cargando ? (
                            <p className="theme-text-muted m-0 text-sm">Cargando sugerencias…</p>
                        ) : sugerencias.length > 0 ? (
                            <ul className="text-sm theme-text-muted m-0 p-0 list-none space-y-1">
                                {sugerencias.map((s) => (
                                    <li key={s.documento_venta_id}>
                                        <button
                                            type="button"
                                            className="underline theme-text-main"
                                            onClick={() => {
                                                form.setData('documento_venta_id', s.documento_venta_id);
                                                form.setData('importe_asignado', s.total);
                                            }}
                                        >
                                            {s.folio}
                                        </button>
                                        {' '}
                                        · {dinero(s.total)} · {s.fecha_emision}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="theme-text-muted m-0 text-sm">Sin candidatas automáticas; ingresa el ID del documento.</p>
                        )}
                        <form onSubmit={enviar} className="grid gap-3 sm:grid-cols-2">
                            <label className="space-y-1">
                                <span className={THEME_LABEL}>ID documento venta</span>
                                <input
                                    className={THEME_INPUT}
                                    value={form.data.documento_venta_id}
                                    onChange={(e) => form.setData('documento_venta_id', e.target.value)}
                                    required
                                />
                                {form.errors.documento_venta_id && <span className="block text-xs theme-text-peligro">{form.errors.documento_venta_id}</span>}
                            </label>
                            <label className="space-y-1">
                                <span className={THEME_LABEL}>Importe asignado</span>
                                <input
                                    className={THEME_INPUT}
                                    inputMode="decimal"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.importe_asignado}
                                    onChange={(e) => form.setData('importe_asignado', e.target.value)}
                                    required
                                />
                                {form.errors.importe_asignado && <span className="block text-xs theme-text-peligro">{form.errors.importe_asignado}</span>}
                            </label>
                            <label className="space-y-1 sm:col-span-2">
                                <span className={THEME_LABEL}>Evidencia (opcional)</span>
                                <input
                                    className={THEME_INPUT}
                                    value={form.data.evidencia}
                                    onChange={(e) => form.setData('evidencia', e.target.value)}
                                />
                                {form.errors.evidencia && <span className="block text-xs theme-text-peligro">{form.errors.evidencia}</span>}
                            </label>
                            <div className="sm:col-span-2 flex gap-2 justify-end">
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarAsignacion}>Cancelar</button>
                                <button type="submit" className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar</button>
                            </div>
                        </form>
                    </section>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
