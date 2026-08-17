import React, { useEffect, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock, Save, AlertTriangle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_INPUT } from '../../../utils/geliaTheme';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';

const DIAS_SEMANA = [
    { iso: 1, label: 'Lun' },
    { iso: 2, label: 'Mar' },
    { iso: 3, label: 'Mié' },
    { iso: 4, label: 'Jue' },
    { iso: 5, label: 'Vie' },
    { iso: 6, label: 'Sáb' },
    { iso: 7, label: 'Dom' },
];

function toggleDia(dias, iso) {
    const set = new Set(dias.map(Number));
    if (set.has(iso)) set.delete(iso);
    else set.add(iso);
    return [...set].sort((a, b) => a - b);
}

export default function Index({ auth, plazos }) {
    const { flash } = usePage().props;
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });

    const form = useForm({
        activo: plazos?.activo ?? true,
        hora_corte: plazos?.hora_corte ?? '18:00',
        dias_habiles: plazos?.dias_habiles ?? [1, 2, 3, 4, 5, 6],
        temporada_alta: plazos?.temporada_alta ?? false,
        dias_extra_temporada_alta: plazos?.dias_extra_temporada_alta ?? 1,
        comercial: {
            dias_empaque: plazos?.comercial?.dias_empaque ?? 1,
            dias_recoleccion: plazos?.comercial?.dias_recoleccion ?? 1,
        },
        local_regional: {
            dias_empaque: plazos?.local_regional?.dias_empaque ?? 1,
            dias_recoleccion: plazos?.local_regional?.dias_recoleccion ?? 1,
        },
    });

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Guardado', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const guardar = (e) => {
        e.preventDefault();
        form.put(route('control_pedidos.plazos.update'), { preserveScroll: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Plazos de retraso | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-3 md:p-8`}>
                    <div className="flex items-center gap-2 mb-0.5 md:mb-2">
                        <Clock className="w-4 h-4 md:w-5 md:h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Gestión de pedidos_</span>
                    </div>
                    <h1 className="text-xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Plazos de <span style={{ color: 'var(--color-primario)' }}>retraso</span>
                    </h1>
                    <p className="hidden md:block text-sm theme-text-muted font-bold mt-2 m-0">
                        Empaque vs recolección — configurable sin hardcode. No se revelan al cliente.
                    </p>
                </header>

                <form onSubmit={guardar} className="space-y-4 max-w-3xl">
                    <div className={`${geliaCardClass()} p-5 space-y-4`}>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={Boolean(form.data.activo)}
                                onChange={(e) => form.setData('activo', e.target.checked)}
                                className="rounded border theme-border"
                            />
                            <span className="text-sm font-bold theme-text-main">Alertas de retraso activas</span>
                        </label>

                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1.5">Hora de corte</p>
                            <input
                                type="time"
                                value={form.data.hora_corte}
                                onChange={(e) => form.setData('hora_corte', e.target.value)}
                                className={`${THEME_INPUT} max-w-[10rem]`}
                            />
                            <p className="text-[11px] theme-text-muted mt-1.5 m-0">
                                Pagado antes del corte: plazo desde ese día. Después del corte: cuenta como el siguiente día hábil.
                            </p>
                            {form.errors.hora_corte && (
                                <p className="text-xs text-red-500 mt-1 m-0">{form.errors.hora_corte}</p>
                            )}
                        </div>

                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-2">Días hábiles</p>
                            <div className="flex flex-wrap gap-2">
                                {DIAS_SEMANA.map((d) => {
                                    const on = form.data.dias_habiles.map(Number).includes(d.iso);
                                    return (
                                        <button
                                            key={d.iso}
                                            type="button"
                                            onClick={() => form.setData('dias_habiles', toggleDia(form.data.dias_habiles, d.iso))}
                                            className={`px-3 py-1.5 rounded-lg text-[11px] font-black uppercase border transition-colors ${
                                                on
                                                    ? 'bg-[var(--color-primario)]/15 border-[var(--color-primario)]/40 text-[var(--color-primario)]'
                                                    : 'theme-border theme-text-muted'
                                            }`}
                                        >
                                            {d.label}
                                        </button>
                                    );
                                })}
                            </div>
                            {form.errors.dias_habiles && (
                                <p className="text-xs text-red-500 mt-1 m-0">{form.errors.dias_habiles}</p>
                            )}
                        </div>
                    </div>

                    <div className={`${geliaCardClass()} p-5 space-y-4`}>
                        <div className="flex items-start gap-2">
                            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 text-amber-500" />
                            <div>
                                <p className="text-sm font-black theme-text-main m-0">Temporada alta / venta especial</p>
                                <p className="text-[11px] theme-text-muted m-0 mt-1">
                                    Interruptor global: al activarlo, todos los pedidos ganan días extra en empaque y recolección.
                                </p>
                            </div>
                        </div>
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={Boolean(form.data.temporada_alta)}
                                onChange={(e) => form.setData('temporada_alta', e.target.checked)}
                                className="rounded border theme-border"
                            />
                            <span className="text-sm font-bold theme-text-main">Temporada alta activa</span>
                        </label>
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1.5">Días extra</p>
                            <input
                                type="number"
                                min={0}
                                max={30}
                                value={form.data.dias_extra_temporada_alta}
                                onChange={(e) => form.setData('dias_extra_temporada_alta', Number(e.target.value))}
                                className={`${THEME_INPUT} max-w-[8rem]`}
                                disabled={!form.data.temporada_alta}
                            />
                        </div>
                    </div>

                    <div className="grid md:grid-cols-2 gap-4">
                        {[
                            { key: 'comercial', titulo: 'Comercial (FedEx, Estafeta…)' },
                            { key: 'local_regional', titulo: 'Local / municipio' },
                        ].map(({ key, titulo }) => (
                            <div key={key} className={`${geliaCardClass()} p-5 space-y-3`}>
                                <p className="text-sm font-black theme-text-main m-0">{titulo}</p>
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1.5">
                                        Días hábiles · empaque
                                    </p>
                                    <input
                                        type="number"
                                        min={1}
                                        max={30}
                                        value={form.data[key].dias_empaque}
                                        onChange={(e) => form.setData(key, {
                                            ...form.data[key],
                                            dias_empaque: Number(e.target.value),
                                        })}
                                        className={THEME_INPUT}
                                    />
                                </div>
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1.5">
                                        Días hábiles · recolección
                                    </p>
                                    <input
                                        type="number"
                                        min={1}
                                        max={30}
                                        value={form.data[key].dias_recoleccion}
                                        onChange={(e) => form.setData(key, {
                                            ...form.data[key],
                                            dias_recoleccion: Number(e.target.value),
                                        })}
                                        className={THEME_INPUT}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-black uppercase tracking-wide text-white bg-[var(--color-primario)] disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            Guardar plazos
                        </button>
                    </div>
                </form>
            </GeliaPageShell>

            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta((a) => ({ ...a, abierto: false }))}
            />
        </AppLayout>
    );
}
