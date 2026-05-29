import React from 'react';
import { useForm } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ActivosModalShell from './ActivosModalShell';
import { INPUT_CLASS, SELECT_CLASS, LABEL_CLASS, BTN_PRIMARY_CLASS } from './activosFormStyles';

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
        <ActivosModalShell
            title="Transferir activo"
            onClose={onCerrar}
            loader={<GeliaLoader isVisible={processing} message="Transfiriendo_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-4">
                    <p className="text-sm theme-text-muted m-0">
                        Origen: <strong className="theme-text-main">{activo.departamento?.nombre}</strong>
                    </p>
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
                </div>
                <div className="gelia-modal-footer p-5 md:p-6">
                    <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center`}>
                        <ArrowRightLeft className="w-4 h-4 shrink-0" /> Transferir
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
