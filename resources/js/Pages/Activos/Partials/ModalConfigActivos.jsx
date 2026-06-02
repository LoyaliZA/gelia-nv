import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Settings2, RotateCcw, Check } from 'lucide-react';
import ActivosModalShell from './ActivosModalShell';
import GeliaLoader from '../../../Components/GeliaLoader';
import { LABEL_CLASS, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS } from './activosFormStyles';

export default function ModalConfigActivos({ abierto, onCerrar, terminosCondiciones }) {
    const { data, setData, post, processing, reset, errors } = useForm({
        terminos_condiciones: terminosCondiciones || '',
    });

    useEffect(() => {
        if (abierto) {
            setData('terminos_condiciones', terminosCondiciones || '');
        }
    }, [abierto, terminosCondiciones]);

    if (!abierto) return null;

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.configuracion.guardar'), {
            preserveScroll: true,
            onSuccess: () => {
                onCerrar();
            },
        });
    };

    const restablecer = () => {
        if (window.confirm('¿Deseas restablecer los cambios al valor actual guardado?')) {
            reset();
        }
    };

    return (
        <ActivosModalShell
            title="Configuración de Términos"
            onClose={onCerrar}
            size="max-w-2xl"
            loader={<GeliaLoader isVisible={processing} message="Guardando configuración_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-5 overflow-y-auto">
                    <div className="flex items-start gap-3 p-4 rounded-xl border border-blue-100 bg-blue-50/20 dark:border-blue-900/30 dark:bg-blue-950/10">
                        <Settings2 className="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div>
                            <span className="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400 block mb-1">
                                Términos de Responsabilidad
                            </span>
                            <p className="text-xs theme-text-muted m-0 leading-relaxed">
                                Este texto legal se mostrará tanto en la ventana emergente de firma digital del colaborador como en el documento PDF responsiva impreso para el resguardo de activos.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2 flex-1">
                        <label htmlFor="terminos_condiciones" className={LABEL_CLASS}>
                            Cláusulas Legales / Términos y Condiciones *
                        </label>
                        <textarea
                            id="terminos_condiciones"
                            value={data.terminos_condiciones}
                            onChange={(e) => setData('terminos_condiciones', e.target.value)}
                            rows={12}
                            placeholder="Escribe aquí las cláusulas de responsabilidad..."
                            className="w-full p-4 theme-surface border theme-border rounded-xl theme-text-main text-xs font-medium outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all resize-none shadow-sm hover:shadow-md dark:bg-slate-900"
                            required
                        />
                        {errors.terminos_condiciones && (
                            <p className="text-xs text-red-500 m-0 mt-1">{errors.terminos_condiciones}</p>
                        )}
                    </div>
                </div>

                <div className="gelia-modal-footer p-5 md:p-6 flex gap-3 shrink-0">
                    <button
                        type="button"
                        onClick={restablecer}
                        className={`${BTN_SECONDARY_CLASS} flex-1 justify-center`}
                        disabled={processing}
                    >
                        <RotateCcw className="w-4 h-4 shrink-0" /> Restablecer
                    </button>
                    <button
                        type="submit"
                        className={`${BTN_PRIMARY_CLASS} flex-1 justify-center`}
                        disabled={processing}
                    >
                        <Check className="w-4 h-4 shrink-0" /> Guardar cambios
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
