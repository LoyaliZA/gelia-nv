import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosModalShell from './ActivosModalShell';
import {
    INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS, BTN_PRIMARY_CLASS,
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
        <ActivosModalShell
            title="Programar mantenimiento"
            onClose={onCerrar}
            loader={<GeliaLoader isVisible={processing} message="Programando_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-4">
                    <p className="text-sm theme-text-muted m-0 flex items-center gap-2">
                        <Wrench className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        {activo.folio} — {activo.nombre}
                    </p>
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
                    {errors.estado && <p className="text-red-500 text-xs m-0">{errors.estado}</p>}
                </div>
                <div className="gelia-modal-footer p-5 md:p-6">
                    <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center`}>
                        Programar mantenimiento
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
