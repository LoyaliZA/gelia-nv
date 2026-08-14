import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Settings2, Save } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    BTN_BACK,
    BTN_PRIMARY,
    FLASH_ERR,
    FLASH_OK,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
} from './Partials/safStyles';

export default function Configurar({ auth, reglas }) {
    const { flash } = usePage().props;
    const form = useForm({
        monto_minimo: String(reglas?.monto_minimo ?? 10),
        vigencia_modo: reglas?.vigencia_modo || 'dias',
        vigencia_dias: String(reglas?.vigencia_dias ?? 20),
        fecha_limite: reglas?.fecha_limite || '',
    });

    return (
        <AppLayout auth={auth}>
            <Head title="Configurar saldos a favor" />
            <GeliaPageShell className="space-y-6">
                <div>
                    <Link href={route('saldos_favor.index')} className={BTN_BACK}>
                        <ArrowLeft className="w-3.5 h-3.5" /> Saldos a favor
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow="Administración"
                    title="Reglas de"
                    titleHighlight="saldos a favor"
                    description="Monto mínimo y vigencia (por días o fecha límite). Aplican a generación y reactivación."
                    icon={Settings2}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}
                {flash?.error && <div className={FLASH_ERR}>{flash.error}</div>}

                <form
                    className={geliaCardClass('p-5 space-y-4 max-w-xl')}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('saldos_favor.configurar.update'));
                    }}
                >
                    <div>
                        <label className={THEME_LABEL}>Monto mínimo para generar</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            className={`${THEME_INPUT} w-full mt-1`}
                            value={form.data.monto_minimo}
                            onChange={(e) => form.setData('monto_minimo', e.target.value)}
                        />
                        {form.errors.monto_minimo && (
                            <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.monto_minimo}</p>
                        )}
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Modo de vigencia</label>
                        <select
                            className={`${THEME_SELECT} w-full mt-1`}
                            value={form.data.vigencia_modo}
                            onChange={(e) => form.setData('vigencia_modo', e.target.value)}
                        >
                            <option value="dias">Días desde la generación</option>
                            <option value="fecha_limite">Hasta una fecha límite</option>
                        </select>
                        {form.errors.vigencia_modo && (
                            <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.vigencia_modo}</p>
                        )}
                    </div>

                    {form.data.vigencia_modo === 'dias' ? (
                        <div>
                            <label className={THEME_LABEL}>Días de vigencia</label>
                            <input
                                type="number"
                                min="1"
                                required
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={form.data.vigencia_dias}
                                onChange={(e) => form.setData('vigencia_dias', e.target.value)}
                            />
                            {form.errors.vigencia_dias && (
                                <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.vigencia_dias}</p>
                            )}
                        </div>
                    ) : (
                        <div>
                            <label className={THEME_LABEL}>Fecha límite</label>
                            <input
                                type="date"
                                required
                                className={`${THEME_INPUT} w-full mt-1`}
                                value={form.data.fecha_limite}
                                onChange={(e) => form.setData('fecha_limite', e.target.value)}
                            />
                            {form.errors.fecha_limite && (
                                <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.fecha_limite}</p>
                            )}
                            <p className="text-xs theme-text-muted mt-1 m-0">
                                Ejemplo: cierre anual. Todos los saldos nuevos vencen en esa fecha.
                            </p>
                        </div>
                    )}

                    <div className="pt-2">
                        <button type="submit" disabled={form.processing} className={`${BTN_PRIMARY} disabled:opacity-50`}>
                            <Save className="w-4 h-4" /> Guardar reglas
                        </button>
                    </div>
                </form>
            </GeliaPageShell>
        </AppLayout>
    );
}
