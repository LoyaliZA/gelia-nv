import React from 'react';
import { useForm } from '@inertiajs/react';
import { X, UserPlus } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import SelectorUsuarioGelia from './SelectorUsuarioGelia';
import ActivosPortal from './ActivosPortal';
import {
    TEXTAREA_CLASS, LABEL_CLASS, BTN_ICON_CLASS, MODAL_OVERLAY_CLASS, MODAL_SHELL_CLASS, BTN_PRIMARY_CLASS,
} from './activosFormStyles';

export default function ModalAsignacion({ abierto, onCerrar, activo }) {
    const { data, setData, post, processing, errors, reset } = useForm({ user_id: '', notas: '' });

    if (!abierto || !activo) return null;

    const submit = (e) => {
        e.preventDefault();
        post(route('activos.asignar', activo.id), { onSuccess: () => { onCerrar(); reset(); } });
    };

    return (
        <ActivosPortal>
            <div className={MODAL_OVERLAY_CLASS}>
                <GeliaLoader isVisible={processing} message="Asignando_" />
                <div className={`${MODAL_SHELL_CLASS} max-w-lg`}>
                    <div className="flex items-center justify-between p-6 border-b theme-border">
                        <h2 className="text-lg font-black italic uppercase theme-text-main">
                            {activo.responsable ? 'Reasignar activo' : 'Asignar activo'}
                        </h2>
                        <button type="button" onClick={onCerrar} className={BTN_ICON_CLASS}><X className="w-5 h-5" /></button>
                    </div>
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <p className="text-sm theme-text-muted">
                            <span className="font-bold theme-text-main">{activo.folio}</span> — {activo.nombre}
                        </p>
                        {activo.responsable && (
                            <p className="text-xs theme-text-muted">Pertenece actualmente a: <strong className="theme-text-main">{activo.responsable.name}</strong></p>
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
                        <button type="submit" className={`${BTN_PRIMARY_CLASS} w-full justify-center py-3`} style={{ backgroundColor: 'var(--color-primario)' }}>
                            <UserPlus className="w-4 h-4" /> Confirmar asignación
                        </button>
                    </form>
                </div>
            </div>
        </ActivosPortal>
    );
}
