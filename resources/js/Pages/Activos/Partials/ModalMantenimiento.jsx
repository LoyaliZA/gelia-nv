import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Wrench, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosPortal from './ActivosPortal';
import {
    INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS, BTN_ICON_CLASS,
    MODAL_OVERLAY_CLASS, MODAL_SHELL_CLASS, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS,
} from './activosFormStyles';

export default function ModalMantenimiento({ abierto, onCerrar, activo }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        tipo: 'preventivo',
        fecha_programada: new Date().toISOString().substring(0, 10),
        proveedor: '',
        costo: '',
        descripcion: '',
        notas: '',
        proximo_mantenimiento: '',
    });

    useEffect(() => {
        if (abierto) reset();
    }, [abierto]);

    if (!abierto || !activo) return null;

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.mantenimiento', activo.id), {
            onSuccess: () => { onCerrar(); reset(); },
        });
    };

    return (
        <ActivosPortal>
            <div className={MODAL_OVERLAY_CLASS}>
                <GeliaLoader isVisible={processing} message="Programando_" />
                <div className={`${MODAL_SHELL_CLASS} max-w-lg`}>
                    <div className="flex items-center justify-between p-6 border-b theme-border">
                        <h2 className="text-lg font-black italic uppercase theme-text-main flex items-center gap-2">
                            <Wrench className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            Programar mantenimiento
                        </h2>
                        <button type="button" onClick={onCerrar} className={BTN_ICON_CLASS}><X className="w-5 h-5" /></button>
                    </div>
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <p className="text-sm theme-text-muted">{activo.folio} — {activo.nombre}</p>
                        <div>
                            <label className={LABEL_CLASS}>Tipo *</label>
                            <select value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} className={SELECT_CLASS}>
                                <option value="preventivo">Preventivo</option>
                                <option value="correctivo">Correctivo</option>
                                <option value="garantia">Garantía</option>
                            </select>
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Fecha programada</label>
                            <input type="date" value={data.fecha_programada} onChange={(e) => setData('fecha_programada', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Proveedor / taller</label>
                            <input value={data.proveedor} onChange={(e) => setData('proveedor', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Costo estimado</label>
                            <input type="number" step="0.01" value={data.costo} onChange={(e) => setData('costo', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Descripción del trabajo</label>
                            <textarea value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Próximo mantenimiento</label>
                            <input type="date" value={data.proximo_mantenimiento} onChange={(e) => setData('proximo_mantenimiento', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Notas</label>
                            <textarea value={data.notas} onChange={(e) => setData('notas', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                        </div>
                        {errors.estado && <p className="text-red-500 text-xs">{errors.estado}</p>}
                        <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center py-3`} style={{ backgroundColor: 'var(--color-primario)' }}>
                            Programar mantenimiento
                        </button>
                    </form>
                </div>
            </div>
        </ActivosPortal>
    );
}
