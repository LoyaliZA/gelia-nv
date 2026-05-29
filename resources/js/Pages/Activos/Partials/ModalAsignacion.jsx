import React from 'react';
import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import SelectorUsuarioGelia from './SelectorUsuarioGelia';
import ActivosModalShell from './ActivosModalShell';
import { TEXTAREA_CLASS, LABEL_CLASS, BTN_PRIMARY_CLASS } from './activosFormStyles';

export default function ModalAsignacion({ abierto, onCerrar, activo }) {
    const { data, setData, post, processing, errors, reset } = useForm({ user_id: '', notas: '' });

    if (!abierto || !activo) return null;

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.asignar', activo.id), { onSuccess: () => { onCerrar(); reset(); } });
    };

    return (
        <ActivosModalShell
            title={activo.responsable ? 'Reasignar activo' : 'Asignar activo'}
            onClose={onCerrar}
            loader={<GeliaLoader isVisible={processing} message="Asignando_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-4">
                    <p className="text-sm theme-text-muted m-0">
                        <span className="font-bold theme-text-main">{activo.folio}</span> — {activo.nombre}
                    </p>
                    {activo.responsable && (
                        <p className="text-xs theme-text-muted m-0">
                            Pertenece actualmente a: <strong className="theme-text-main">{activo.responsable.name}</strong>
                        </p>
                    )}
                    <div>
                        <label className={LABEL_CLASS}>Usuario Gelia *</label>
                        <SelectorUsuarioGelia value={data.user_id} onChange={(id) => setData('user_id', id)} departamentoId={activo.departamento_id} />
                        {errors.user_id && <p className="text-red-500 text-xs mt-1">{errors.user_id}</p>}
                    </div>
                    <div>
                        <label className={LABEL_CLASS}>Notas</label>
                        <textarea value={data.notas} onChange={(e) => setData('notas', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                    </div>
                </div>
                <div className="gelia-modal-footer p-5 md:p-6">
                    <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center`}>
                        <UserPlus className="w-4 h-4 shrink-0" /> Confirmar asignación
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
