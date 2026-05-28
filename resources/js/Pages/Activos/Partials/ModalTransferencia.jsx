import React from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowRightLeft, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosPortal from './ActivosPortal';
import {
    INPUT_CLASS, SELECT_CLASS, LABEL_CLASS, BTN_ICON_CLASS, MODAL_OVERLAY_CLASS, MODAL_SHELL_CLASS, BTN_PRIMARY_CLASS,
} from './activosFormStyles';

export default function ModalTransferencia({ abierto, onCerrar, activo, departamentos = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        departamento_destino_id: '', motivo: '', notas: '',
    });

    if (!abierto || !activo) return null;

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.transferir', activo.id), { onSuccess: () => { onCerrar(); reset(); } });
    };

    return (
        <ActivosPortal>
            <div className={MODAL_OVERLAY_CLASS}>
                <GeliaLoader isVisible={processing} message="Transfiriendo_" />
                <div className={`${MODAL_SHELL_CLASS} max-w-lg`}>
                    <div className="flex items-center justify-between p-6 border-b theme-border">
                        <h2 className="text-lg font-black italic uppercase theme-text-main">Transferir activo</h2>
                        <button type="button" onClick={onCerrar} className={BTN_ICON_CLASS}><X className="w-5 h-5" /></button>
                    </div>
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <p className="text-sm theme-text-muted">Origen: <strong className="theme-text-main">{activo.departamento?.nombre}</strong></p>
                        <div>
                            <label className={LABEL_CLASS}>Departamento destino *</label>
                            <select value={data.departamento_destino_id} onChange={(e) => setData('departamento_destino_id', e.target.value)} className={SELECT_CLASS}>
                                <option value="">Seleccionar...</option>
                                {departamentos.filter((d) => d.id !== activo.departamento_id).map((d) => (
                                    <option key={d.id} value={d.id}>{d.nombre}</option>
                                ))}
                            </select>
                            {errors.departamento_destino_id && <p className="text-red-500 text-xs mt-1">{errors.departamento_destino_id}</p>}
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Motivo</label>
                            <input value={data.motivo} onChange={(e) => setData('motivo', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center py-3`} style={{ backgroundColor: 'var(--color-primario)' }}>
                            <ArrowRightLeft className="w-4 h-4" /> Transferir
                        </button>
                    </form>
                </div>
            </div>
        </ActivosPortal>
    );
}
