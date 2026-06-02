import React, { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Settings, ArrowLeft, Save, Hash, Clock, AlertTriangle, Wallet } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaLoader from '../../../Components/GeliaLoader';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import RhSubNav from '../Partials/RhSubNav';
import TablaPuestos from './Partials/TablaPuestos';
import TablaBonos from './Partials/TablaBonos';
import TablaReglasIncidencia from './Partials/TablaReglasIncidencia';

export default function Index({ auth, configuracion, folioPreview: folioPreviewInicial, heFolioPreview: heFolioPreviewInicial, incFolioPreview: incFolioPreviewInicial, preFolioPreview: preFolioPreviewInicial, salFolioPreview: salFolioPreviewInicial, puestos, bonos = [], reglasIncidencia = [], departamentos = [] }) {
    const [folioPreview, setFolioPreview] = useState(folioPreviewInicial || '');
    const [heFolioPreview, setHeFolioPreview] = useState(heFolioPreviewInicial || '');
    const [incFolioPreview, setIncFolioPreview] = useState(incFolioPreviewInicial || '');
    const [preFolioPreview, setPreFolioPreview] = useState(preFolioPreviewInicial || '');
    const [salFolioPreview, setSalFolioPreview] = useState(salFolioPreviewInicial || '');

    const { data, setData, put, processing, errors } = useForm({
        folio_prefijo: configuracion.folio_prefijo || 'COL',
        folio_separador: configuracion.folio_separador || '-',
        folio_padding: configuracion.folio_padding || 6,
        folio_incluir_anio: configuracion.folio_incluir_anio || false,
        dias_periodo_pago: configuracion.dias_periodo_pago || 30,
        decimales_salario_minuto: configuracion.decimales_salario_minuto || 8,
        recalcular_salarios: false,
        he_folio_prefijo: configuracion.he_folio_prefijo || 'HE',
        he_folio_padding: configuracion.he_folio_padding || 6,
        he_multiplicador_pago: configuracion.he_multiplicador_pago || 2,
        he_minutos_minimos: configuracion.he_minutos_minimos || 30,
        he_tarifa_hora_fija: configuracion.he_tarifa_hora_fija || 39,
        he_usar_tarifa_fija: configuracion.he_usar_tarifa_fija !== false,
        he_gracia_minutos_despues_salida: configuracion.he_gracia_minutos_despues_salida || 30,
        inc_folio_prefijo: configuracion.inc_folio_prefijo || 'DED',
        inc_folio_padding: configuracion.inc_folio_padding || 6,
        pre_folio_prefijo: configuracion.pre_folio_prefijo || 'PRE',
        pre_folio_padding: configuracion.pre_folio_padding || 6,
        sal_folio_prefijo: configuracion.sal_folio_prefijo || 'SAL',
        sal_folio_padding: configuracion.sal_folio_padding || 6,
    });

    useEffect(() => {
        const timer = setTimeout(async () => {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const resp = await fetch(route('rh.configuracion.preview_folio'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        folio_prefijo: data.folio_prefijo,
                        folio_separador: data.folio_separador,
                        folio_padding: data.folio_padding,
                        folio_incluir_anio: data.folio_incluir_anio,
                    }),
                });
                if (resp.ok) {
                    const json = await resp.json();
                    setFolioPreview(json.folio_preview);
                }
            } catch {
                // ignore
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [data.folio_prefijo, data.folio_separador, data.folio_padding, data.folio_incluir_anio]);

    useEffect(() => {
        const sep = data.folio_separador || '-';
        const pad = Math.max(1, Number(data.he_folio_padding) || 6);
        const pref = String(data.he_folio_prefijo || 'HE').toUpperCase();
        setHeFolioPreview(`${pref}${sep}${'0'.repeat(pad - 1)}1`);
    }, [data.he_folio_prefijo, data.he_folio_padding, data.folio_separador]);

    useEffect(() => {
        const sep = data.folio_separador || '-';
        const pad = Math.max(1, Number(data.inc_folio_padding) || 6);
        const pref = String(data.inc_folio_prefijo || 'INC').toUpperCase();
        setIncFolioPreview(`${pref}${sep}${'0'.repeat(pad - 1)}1`);
    }, [data.inc_folio_prefijo, data.inc_folio_padding, data.folio_separador]);

    useEffect(() => {
        const sep = data.folio_separador || '-';
        const pad = Math.max(1, Number(data.pre_folio_padding) || 6);
        const pref = String(data.pre_folio_prefijo || 'PRE').toUpperCase();
        setPreFolioPreview(`${pref}${sep}${'0'.repeat(pad - 1)}1`);
    }, [data.pre_folio_prefijo, data.pre_folio_padding, data.folio_separador]);

    useEffect(() => {
        const sep = data.folio_separador || '-';
        const pad = Math.max(1, Number(data.sal_folio_padding) || 6);
        const pref = String(data.sal_folio_prefijo || 'SAL').toUpperCase();
        setSalFolioPreview(`${pref}${sep}${'0'.repeat(pad - 1)}1`);
    }, [data.sal_folio_prefijo, data.sal_folio_padding, data.folio_separador]);

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('rh.configuracion.update'), { preserveScroll: true });
    };

    const canBonos = auth.user?.permissions?.includes('rh.catalogos.bonos')
        || auth.user?.roles?.includes('Super Admin');
    const canReglasIncidencia = auth.user?.permissions?.includes('rh.catalogos.incidencias_generales')
        || auth.user?.roles?.includes('Super Admin');

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración RH" />
            <GeliaLoader isVisible={processing} message="Guardando configuración_" />

            <GeliaPageShell className="max-w-[1200px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link href={route('rh.index')} className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main">
                        <ArrowLeft className="w-4 h-4" /> Volver al dashboard
                    </Link>
                </div>

                <header className={geliaCardClass('p-6 md:p-10')}>
                    <div className="flex items-center gap-3 mb-2">
                        <Settings className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Módulo RH</p>
                    </div>
                    <h1 className="text-2xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Configuración del Módulo
                    </h1>
                </header>

                <RhSubNav />

                <form onSubmit={handleSubmit} className={geliaCardClass('p-6 md:p-8 space-y-6')}>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0">
                        <Hash className="w-4 h-4" /> Folio colaboradores
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Prefijo" error={errors.folio_prefijo}>
                            <input value={data.folio_prefijo} onChange={(e) => setData('folio_prefijo', e.target.value.toUpperCase())} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Separador" error={errors.folio_separador}>
                            <input value={data.folio_separador} onChange={(e) => setData('folio_separador', e.target.value)} maxLength={5} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Padding numérico" error={errors.folio_padding}>
                            <input type="number" min="1" max="12" value={data.folio_padding} onChange={(e) => setData('folio_padding', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <div className="flex items-end">
                            <label className="flex items-center gap-3 cursor-pointer pb-3">
                                <input type="checkbox" checked={!!data.folio_incluir_anio} onChange={(e) => setData('folio_incluir_anio', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Incluir año en folio</span>
                            </label>
                        </div>
                    </div>

                    <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa folio colaborador</p>
                        <p className="text-lg font-mono font-bold theme-text-main m-0">{folioPreview || '—'}</p>
                    </div>

                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0 pt-4 border-t theme-border">Periodo de pago y salarios</h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Días del periodo de pago" error={errors.dias_periodo_pago}>
                            <input type="number" min="1" max="365" value={data.dias_periodo_pago} onChange={(e) => setData('dias_periodo_pago', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Decimales salario por minuto" error={errors.decimales_salario_minuto}>
                            <input type="number" min="2" max="10" value={data.decimales_salario_minuto} onChange={(e) => setData('decimales_salario_minuto', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                    </div>

                    <label className="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" checked={!!data.recalcular_salarios} onChange={(e) => setData('recalcular_salarios', e.target.checked)} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Forzar recálculo de todos los colaboradores al guardar</span>
                    </label>

                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0 pt-4 border-t theme-border">
                        <Clock className="w-4 h-4" /> Horas extra
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Prefijo folio HE" error={errors.he_folio_prefijo}>
                            <input value={data.he_folio_prefijo} onChange={(e) => setData('he_folio_prefijo', e.target.value.toUpperCase())} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Padding folio HE" error={errors.he_folio_padding}>
                            <input type="number" min="1" max="12" value={data.he_folio_padding} onChange={(e) => setData('he_folio_padding', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Multiplicador de pago (ej. 2 = 200%)" error={errors.he_multiplicador_pago}>
                            <input type="number" min="1" max="10" step="0.01" value={data.he_multiplicador_pago} onChange={(e) => setData('he_multiplicador_pago', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Minutos mínimos bloque HE" error={errors.he_minutos_minimos}>
                            <input type="number" min="1" max="120" value={data.he_minutos_minimos} onChange={(e) => setData('he_minutos_minimos', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Tarifa hora fija ($)" error={errors.he_tarifa_hora_fija}>
                            <input type="number" min="0" step="0.01" value={data.he_tarifa_hora_fija} onChange={(e) => setData('he_tarifa_hora_fija', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Gracia minutos después de salida" error={errors.he_gracia_minutos_despues_salida}>
                            <input type="number" min="0" max="120" value={data.he_gracia_minutos_despues_salida} onChange={(e) => setData('he_gracia_minutos_despues_salida', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <div className="flex items-end">
                            <label className="flex items-center gap-3 cursor-pointer pb-3">
                                <input type="checkbox" checked={!!data.he_usar_tarifa_fija} onChange={(e) => setData('he_usar_tarifa_fija', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Usar tarifa fija (no calculada por salario)</span>
                            </label>
                        </div>
                    </div>

                    <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa folio horas extra</p>
                        <p className="text-lg font-mono font-bold theme-text-main m-0">{heFolioPreview || '—'}</p>
                    </div>

                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0 pt-4 border-t theme-border">
                        <AlertTriangle className="w-4 h-4" /> Deducciones (faltas, retardos, reglas operativas)
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Prefijo folio deducciones" error={errors.inc_folio_prefijo}>
                            <input value={data.inc_folio_prefijo} onChange={(e) => setData('inc_folio_prefijo', e.target.value.toUpperCase())} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Padding folio deducciones" error={errors.inc_folio_padding}>
                            <input type="number" min="1" max="12" value={data.inc_folio_padding} onChange={(e) => setData('inc_folio_padding', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                    </div>

                    <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa folio deducciones</p>
                        <p className="text-lg font-mono font-bold theme-text-main m-0">{incFolioPreview || '—'}</p>
                    </div>

                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0 pt-4 border-t theme-border">
                        <Wallet className="w-4 h-4" /> Préstamos y pagos fijos
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Prefijo folio préstamos" error={errors.pre_folio_prefijo}>
                            <input value={data.pre_folio_prefijo} onChange={(e) => setData('pre_folio_prefijo', e.target.value.toUpperCase())} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Padding folio préstamos" error={errors.pre_folio_padding}>
                            <input type="number" min="1" max="12" value={data.pre_folio_padding} onChange={(e) => setData('pre_folio_padding', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                    </div>

                    <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa folio préstamos</p>
                        <p className="text-lg font-mono font-bold theme-text-main m-0">{preFolioPreview || '—'}</p>
                    </div>

                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2 m-0 pt-4 border-t theme-border">
                        <ArrowLeft className="w-4 h-4 rotate-180 text-purple-500" /> Salidas personales
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Campo label="Prefijo folio salidas" error={errors.sal_folio_prefijo}>
                            <input value={data.sal_folio_prefijo} onChange={(e) => setData('sal_folio_prefijo', e.target.value.toUpperCase())} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                        <Campo label="Padding folio salidas" error={errors.sal_folio_padding}>
                            <input type="number" min="1" max="12" value={data.sal_folio_padding} onChange={(e) => setData('sal_folio_padding', e.target.value)} className={THEME_INPUT + ' w-full px-4 py-3 rounded-2xl text-[11px] font-bold'} />
                        </Campo>
                    </div>

                    <div className="p-4 rounded-2xl border theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1">Vista previa folio salidas</p>
                        <p className="text-lg font-mono font-bold theme-text-main m-0">{salFolioPreview || '—'}</p>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" disabled={processing} className="px-6 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Save className="w-4 h-4" /> Guardar configuración
                        </button>
                    </div>
                </form>

                {auth.user?.permissions?.includes('rh.catalogos.puestos') && (
                    <div className={geliaCardClass('overflow-hidden')}>
                        <TablaPuestos datos={puestos} bonosCatalogo={bonos} />
                    </div>
                )}

                {canBonos && (
                    <div className={geliaCardClass('overflow-hidden')}>
                        <TablaBonos datos={bonos} />
                    </div>
                )}

                {canReglasIncidencia && (
                    <div className={geliaCardClass('overflow-hidden')}>
                        <TablaReglasIncidencia datos={reglasIncidencia} bonos={bonos} departamentos={departamentos} />
                    </div>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function Campo({ label, error, children }) {
    return (
        <div className="space-y-1.5">
            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">{label}</label>
            {children}
            {error && <p className="text-red-500 text-[10px] font-bold ml-2">{error}</p>}
        </div>
    );
}
