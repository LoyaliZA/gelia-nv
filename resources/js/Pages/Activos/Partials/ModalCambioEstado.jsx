import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Settings, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosPortal from './ActivosPortal';
import {
    INPUT_CLASS, TEXTAREA_CLASS, LABEL_CLASS, BTN_ICON_CLASS, MODAL_OVERLAY_CLASS, MODAL_SHELL_CLASS, BTN_PRIMARY_CLASS,
} from './activosFormStyles';

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
        <ActivosPortal>
            <div className={MODAL_OVERLAY_CLASS}>
                <GeliaLoader isVisible={processing} message="Actualizando_" />
                <div className={`${MODAL_SHELL_CLASS} max-w-lg`}>
                    <div className="flex items-center justify-between p-6 border-b theme-border">
                        <h2 className="text-lg font-black italic uppercase theme-text-main">{labels[data.estado] || 'Cambiar estado'}</h2>
                        <button type="button" onClick={onCerrar} className={BTN_ICON_CLASS}><X className="w-5 h-5" /></button>
                    </div>
                    <form onSubmit={submit} className="p-6 space-y-4">
                        {data.estado === 'baja' && (
                            <p className="text-xs text-red-500 font-bold uppercase">Esta acción dejará el activo fuera de operación.</p>
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
                        <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center py-3`} style={{ backgroundColor: data.estado === 'baja' ? '#dc2626' : 'var(--color-primario)' }}>
                            <Settings className="w-4 h-4" /> Confirmar
                        </button>
                    </form>
                </div>
            </div>
        </ActivosPortal>
    );
}
