import React from 'react';
import { useForm } from '@inertiajs/react';
import { Trash2, X } from 'lucide-react';
import FacturaDialog from './FacturaDialog';
import { BTN_DANGER, BTN_SECONDARY } from './facturasStyles';

export default function ModalEliminarFactura({ factura, onClose, onExito }) {
    const { data, setData, delete: eliminar, processing, errors, isDirty } = useForm({ motivo: '' });
    return <FacturaDialog title="Eliminar solicitud de factura" onClose={onClose} busy={processing} dirty={isDirty}>
        <div className="gelia-modal-shell max-w-lg">
            <header className="gelia-workflow-header"><h2 className="m-0 theme-text-main">Eliminar solicitud</h2><button type="button" data-dialog-close className="gelia-workflow-close" aria-label="Cerrar"><X className="w-5 h-5" aria-hidden="true" /></button></header>
            <form onSubmit={(event) => { event.preventDefault(); eliminar(route('facturas.destroy', factura.id), { preserveScroll: true, onSuccess: () => { onExito?.(); onClose(); } }); }}>
                <div className="p-5 space-y-4">
                    <p className="text-sm theme-text-main m-0">Se eliminará la solicitud <strong>{factura.folio}</strong> de {factura.razon_social || 'este cliente'} y sus archivos adjuntos. Esta acción no se puede deshacer desde el módulo.</p>
                    <div><label htmlFor="factura-eliminar-motivo" className="theme-label block mb-2">Motivo de eliminación</label><textarea id="factura-eliminar-motivo" name="motivo" value={data.motivo} onChange={(event) => setData('motivo', event.target.value)} minLength={5} maxLength={500} required rows={3} className="theme-input w-full" aria-invalid={Boolean(errors.motivo)} disabled={processing} /><p className="text-xs theme-text-muted mt-2 mb-0">Describe el motivo con al menos 5 caracteres. Quedará en el historial.</p>{errors.motivo && <p role="alert" className="text-sm theme-text-peligro">{errors.motivo}</p>}</div>
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions"><button type="button" data-dialog-close disabled={processing} className={BTN_SECONDARY}>Cancelar</button><button type="submit" disabled={processing} className={BTN_DANGER}><Trash2 className="w-4 h-4" aria-hidden="true" />{processing ? 'Eliminando…' : 'Eliminar solicitud'}</button></footer>
            </form>
        </div>
    </FacturaDialog>;
}
