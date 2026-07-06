import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Save } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT, THEME_SELECT, THEME_TEXTAREA, THEME_LABEL, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
import { calcularDeduccionPreview, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import RhSubNav from '../Partials/RhSubNav';

export default function Crear({ auth, colaboradores, reglasIncidencia }) {
    const { data, setData, processing, errors } = useForm({
        fecha_ocurrencia: new Date().toISOString().slice(0, 10),
        rh_colaborador_id: '',
        catalogo_regla_incidencia_id: '',
        descripcion_detallada: '',
        origen_deduccion: 'nomina',
    });

    const [reglasFiltradas, setReglasFiltradas] = useState(reglasIncidencia);

    const colaboradorSel = useMemo(
        () => colaboradores.find((c) => String(c.id) === String(data.rh_colaborador_id)),
        [colaboradores, data.rh_colaborador_id],
    );

    const reglaSel = useMemo(
        () => reglasFiltradas.find((r) => String(r.id) === String(data.catalogo_regla_incidencia_id)),
        [reglasFiltradas, data.catalogo_regla_incidencia_id],
    );

    const preview = useMemo(
        () => calcularDeduccionPreview(data, colaboradorSel, reglaSel, null),
        [data, colaboradorSel, reglaSel],
    );

    useEffect(() => {
        if (!data.rh_colaborador_id) {
            setReglasFiltradas(reglasIncidencia);
            return;
        }
        const cargar = async () => {
            try {
                const params = new URLSearchParams({ rh_colaborador_id: data.rh_colaborador_id });
                const resp = await fetch(`${route('rh.incidencias_gerente.reglas_disponibles')}?${params}`);
                if (resp.ok) {
                    const json = await resp.json();
                    setReglasFiltradas(json.reglas || []);
                }
            } catch {
                setReglasFiltradas(reglasIncidencia);
            }
        };
        cargar();
    }, [data.rh_colaborador_id, reglasIncidencia]);

    const enviar = (e) => {
        e.preventDefault();
        router.post(route('rh.incidencias_gerente.store'), data);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Nueva incidencia | RH" />
            <GeliaPageShell className="max-w-3xl space-y-6">
                <Link href={route('rh.incidencias_gerente.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted">
                    <ArrowLeft className="w-4 h-4" /> Volver
                </Link>
                <RhSubNav />

                <form onSubmit={enviar} className={geliaCardClass('p-6 md:p-8 space-y-5')}>
                    <h1 className="text-xl font-black italic uppercase m-0 flex items-center gap-2">
                        <AlertTriangle className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Registrar incidencia
                    </h1>

                    <p className="text-xs theme-text-muted m-0">
                        Las firmas del gerente y colaborador se capturan al firmar el recibo de incidencia, desde el listado o el expediente.
                    </p>

                    <div>
                        <label className={THEME_LABEL}>Colaborador</label>
                        <select value={data.rh_colaborador_id} onChange={(e) => setData('rh_colaborador_id', e.target.value)} required className={THEME_SELECT}>
                            <option value="">Selecciona colaborador...</option>
                            {colaboradores.map((c) => (
                                <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)} — {c.departamento?.nombre}</option>
                            ))}
                        </select>
                        {errors.rh_colaborador_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.rh_colaborador_id}</p>}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className={THEME_LABEL}>Fecha de ocurrencia</label>
                            <input type="date" value={data.fecha_ocurrencia} onChange={(e) => setData('fecha_ocurrencia', e.target.value)} required className={THEME_INPUT} />
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Concepto</label>
                            <select value={data.catalogo_regla_incidencia_id} onChange={(e) => setData('catalogo_regla_incidencia_id', e.target.value)} required className={THEME_SELECT}>
                                <option value="">Selecciona concepto...</option>
                                {reglasFiltradas.map((r) => (
                                    <option key={r.id} value={r.id}>{r.nombre}</option>
                                ))}
                            </select>
                            {errors.catalogo_regla_incidencia_id && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.catalogo_regla_incidencia_id}</p>}
                        </div>
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Observaciones</label>
                        <textarea value={data.descripcion_detallada} onChange={(e) => setData('descripcion_detallada', e.target.value)} rows={4} className={THEME_TEXTAREA} placeholder="Detalle de la incidencia para el recibo..." />
                    </div>

                    {preview.monto_total_final > 0 && (
                        <div className="p-4 rounded-2xl border border-red-500/20 bg-red-500/5">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">Total estimado a descontar</p>
                            <p className="text-lg font-black text-red-500 m-0 mt-1">-{formatoMoneda(preview.monto_total_final)}</p>
                        </div>
                    )}

                    <div className="flex justify-end gap-3 pt-2">
                        <Link href={route('rh.incidencias_gerente.index')} className={THEME_BTN_SECONDARY}>Cancelar</Link>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>
                            <Save className="w-4 h-4" /> Registrar incidencia
                        </button>
                    </div>
                </form>
            </GeliaPageShell>
        </AppLayout>
    );
}
