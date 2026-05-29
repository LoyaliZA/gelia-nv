import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosModalShell from './ActivosModalShell';
import { INPUT_CLASS, TEXTAREA_CLASS, LABEL_CLASS, BTN_PRIMARY_CLASS } from './activosFormStyles';

export default function ModalCambioEstado({ abierto, onCerrar, activo, estadoDestino }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        estado: 'mantenimiento', motivo: '', notas: '',
    });

    useEffect(() => {
        if (abierto && estadoDestino) setData('estado', estadoDestino);
    }, [abierto, estadoDestino]);

    if (!abierto || !activo) return null;

    const labels = { mantenimiento: 'Enviar a mantenimiento', disponible: 'Marcar disponible', baja: 'Dar de baja' };

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.estado', activo.id), { onSuccess: () => { onCerrar(); reset(); } });
    };

    return (
        <ActivosModalShell
            title={labels[data.estado] || 'Cambiar estado'}
            onClose={onCerrar}
            loader={<GeliaLoader isVisible={processing} message="Actualizando_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-4">
                    {data.estado === 'baja' && (
                        <p className="text-xs text-red-500 font-bold uppercase m-0">Esta acción dejará el activo fuera de operación.</p>
                    )}
                    <div>
                        <label className={LABEL_CLASS}>Motivo</label>
                        <input value={data.motivo} onChange={(e) => setData('motivo', e.target.value)} className={INPUT_CLASS} />
                        {errors.motivo && <p className="text-red-500 text-xs mt-1">{errors.motivo}</p>}
                    </div>
                    <div>
                        <label className={LABEL_CLASS}>Notas</label>
                        <textarea value={data.notas} onChange={(e) => setData('notas', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                    </div>
                </div>
                <div className="gelia-modal-footer p-5 md:p-6">
                    <button
                        type="submit"
                        className={`${data.estado === 'baja' ? 'theme-btn-danger' : BTN_PRIMARY_CLASS} w-full justify-center`}
                    >
                        <Settings className="w-4 h-4 shrink-0" /> Confirmar
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
