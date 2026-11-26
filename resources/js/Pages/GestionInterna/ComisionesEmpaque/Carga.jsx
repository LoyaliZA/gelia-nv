import { useForm } from '@inertiajs/react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { useProtegerEdicion } from './useActualizacionComisiones';

export default function Carga({ revision }) {
    const form = useForm({ texto: revision?.texto || '' });
    useProtegerEdicion(form.isDirty || form.processing || Boolean(revision));
    const errores = revision?.errores || [];
    const puedeGuardar = revision && errores.length === 0 && revision.resumen?.total > 0 && form.data.texto.trim() !== '';
    function enviar(ruta) {
        form.post(ruta, { preserveScroll: true });
    }
    return (
        <form className={geliaCardClass('p-4 space-y-3')} onSubmit={(e) => { e.preventDefault(); enviar(route('gestion_interna.comisiones_empaque.carga.revisar')); }}>
            <p className="m-0 text-sm theme-text-muted">Pegue el CSV con fecha, cliente, cajas, remisión, paquetería, logística (con o sin), quién empacó, área y liquidación (pendiente o pagado fuera). Si una fila es inválida, no se guarda ninguna.</p>
            <label className="block"><span className={THEME_LABEL}>Contenido del Excel en CSV</span>
                <textarea className={THEME_INPUT} rows={6} required value={form.data.texto} onChange={(e) => form.setData('texto', e.target.value)} placeholder="fecha,cliente,cajas,remision,paqueteria,logistica,empaco,area,liquidacion" />
            </label>
            <div className="flex flex-wrap gap-2">
                <button className={THEME_BTN_SECONDARY} disabled={form.processing} type="submit">{form.processing ? 'Revisando…' : 'Revisar carga'}</button>
                <button className={THEME_BTN_PRIMARY} type="button" disabled={!puedeGuardar || form.processing} onClick={() => enviar(route('gestion_interna.comisiones_empaque.carga.store'))}>Guardar carga</button>
            </div>
            {form.errors.texto && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.texto}</p>}
            {revision && <div role="status" className="text-sm space-y-1">
                <p className="m-0">{revision.resumen?.total ?? 0} filas leídas · {revision.resumen?.pendientes ?? 0} pendientes nuevas · {revision.resumen?.pagadas ?? 0} pagadas fuera · {revision.resumen?.repetidas ?? 0} ya registradas</p>
                {errores.map((error) => <p key={`${error.fila}-${error.mensaje}`} className="theme-text-peligro m-0">{error.mensaje}</p>)}
            </div>}
        </form>
    );
}
