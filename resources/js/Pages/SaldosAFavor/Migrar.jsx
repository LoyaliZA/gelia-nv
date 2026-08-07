import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    FLASH_OK,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
} from './Partials/safStyles';

export default function Migrar({ auth }) {
    const { flash } = usePage().props;
    const [preview, setPreview] = useState(null);
    const form = useForm({
        archivo: null,
        canal_origen: 'bellaroma',
        confirmar: false,
    });

    const previsualizar = async () => {
        if (!form.data.archivo) return;
        const fd = new FormData();
        fd.append('archivo', form.data.archivo);
        fd.append('canal_origen', form.data.canal_origen);
        const res = await fetch(route('saldos_favor.migrar.preview'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            credentials: 'same-origin',
            body: fd,
        });
        setPreview(await res.json());
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Migrar saldos a favor" />
            <GeliaPageShell className="space-y-6">
                <div>
                    <Link href={route('saldos_favor.index')} className="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        <ArrowLeft className="w-3.5 h-3.5" /> Saldos a favor
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow="Administración"
                    title="Migración"
                    titleHighlight="histórica"
                    description="CSV conciliado · solo filas con cliente y monto válidos · excepciones se omiten"
                    icon={Upload}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}

                <div className={geliaCardClass('p-5 space-y-2 max-w-2xl')}>
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Columnas esperadas</p>
                    <code className="block text-xs theme-element border theme-border rounded-xl px-3 py-2 theme-text-main overflow-x-auto">
                        numero_cliente,monto_original,monto_aplicado,fecha_generacion,documento_origen,remision_aplicacion,motivo
                    </code>
                </div>

                <form
                    className={geliaCardClass('p-5 space-y-4 max-w-xl')}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('saldos_favor.migrar.importar'), { forceFormData: true });
                    }}
                >
                    <div>
                        <label className={THEME_LABEL}>Canal de origen</label>
                        <select className={`${THEME_SELECT} w-full mt-1`} value={form.data.canal_origen} onChange={(e) => form.setData('canal_origen', e.target.value)}>
                            <option value="bellaroma">Bellaroma</option>
                            <option value="call_center_local">Call Center local</option>
                            <option value="call_center_foraneo">Call Center foráneo</option>
                            <option value="punto_venta">Punto de Venta</option>
                        </select>
                    </div>
                    <div>
                        <label className={THEME_LABEL}>Archivo CSV</label>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            className={`${THEME_INPUT} w-full mt-1`}
                            onChange={(e) => form.setData('archivo', e.target.files?.[0] || null)}
                        />
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        <button type="button" onClick={previsualizar} className={BTN_SECONDARY}>Previsualizar</button>
                        <label className="inline-flex items-center gap-2 text-sm font-bold theme-text-main">
                            <input
                                type="checkbox"
                                className="rounded border theme-border"
                                checked={form.data.confirmar}
                                onChange={(e) => form.setData('confirmar', e.target.checked)}
                            />
                            Confirmo conciliación
                        </label>
                    </div>
                    <button type="submit" disabled={form.processing || !form.data.confirmar} className={`${BTN_PRIMARY} disabled:opacity-50`}>
                        Importar filas OK
                    </button>
                </form>

                {preview && (
                    <div className={geliaCardClass('p-5 text-sm space-y-2 max-w-2xl')}>
                        <p className="theme-text-main font-bold m-0">
                            OK: {preview.resumen?.ok} · Excepciones: {preview.resumen?.excepciones} · Monto OK: {preview.resumen?.monto_ok}
                        </p>
                        {preview.excepciones?.length > 0 && (
                            <ul className="mt-2 list-disc pl-5 text-rose-700 dark:text-rose-300 font-bold space-y-1">
                                {preview.excepciones.slice(0, 20).map((e, i) => (
                                    <li key={i}>Fila {e.fila}: {e.motivo} ({e.numero_cliente || 's/n'})</li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
