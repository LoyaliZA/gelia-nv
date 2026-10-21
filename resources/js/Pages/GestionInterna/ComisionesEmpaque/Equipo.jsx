import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BuscarUsuario from './BuscarUsuario';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';

const dinero = (n) => Number(n || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
export default function Equipo({ equipo, areaId, puede, acumulado = '0.00' }) {
    const [editando, setEditando] = useState(false);
    const form = useForm({ area_id: areaId, reparto_id: equipo?.version || 0, motivo: '', lineas: (equipo?.lineas || []).map((l) => ({ user_id: l.user_id, nombre: l.nombre, porcentaje: l.porcentaje })) });
    const lineas = editando ? form.data.lineas : equipo?.lineas || [];
    const total = lineas.reduce((sum, l) => sum + Math.round(Number(l.porcentaje || 0) * 100), 0);
    useEffect(() => {
        if (!editando || !form.isDirty) return undefined;
        const advertir = (e) => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', advertir);
        const salir = router.on?.('before', (event) => {
            if (event.detail.visit.method === 'get' && !window.confirm('Hay cambios en el equipo sin guardar. ¿Descartarlos?')) event.preventDefault();
        });
        return () => { window.removeEventListener('beforeunload', advertir); salir?.(); };
    }, [editando, form.isDirty]);
    function editar() {
        form.setData({ area_id: areaId, reparto_id: equipo?.version || 0, motivo: '', lineas: (equipo?.lineas || []).map((l) => ({ user_id: l.user_id, nombre: l.nombre, porcentaje: l.porcentaje })) });
        setEditando(true);
    }
    function cambiar(i, datos) { form.setData('lineas', form.data.lineas.map((l, j) => i === j ? { ...l, ...datos } : l)); }
    function guardar(e) {
        e.preventDefault();
        form.transform((data) => ({ ...data, lineas: data.lineas.map(({ user_id, porcentaje }) => ({ user_id, porcentaje: String(porcentaje) })) }));
        form.put(route('gestion_interna.comisiones_empaque.equipo'), { preserveScroll: true, onSuccess: () => { setEditando(false); form.reset(); } });
    }
    return <section className={geliaCardClass('p-4 md:p-6 space-y-4')}>
        <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold m-0">Equipo y reparto permanente</h2><p className="text-sm theme-text-muted mt-1 mb-0">{equipo?.area} · Se conserva en todas las operaciones y periodos del área.</p></div>{puede.configurar && !editando && <button type="button" className={THEME_BTN_PRIMARY} onClick={editar}>{lineas.length ? 'Ajustar equipo y porcentajes' : 'Configurar equipo'}</button>}</div>
        <p className="text-sm theme-text-muted m-0 max-w-3xl">Las cajas generan un fondo común. La encargada asigna qué porcentaje del 100% recibe cada persona, independientemente de quién empacó cada caja.</p>
        <div className="flex flex-wrap justify-between gap-3 border-y theme-border py-3"><strong>Fondo común abierto: {dinero(acumulado)}</strong><span className="text-sm theme-text-muted">Los cortes confirmados conservan sus importes.</span></div>
        <form onSubmit={guardar} className="space-y-4">
            {!lineas.length && <p className="text-sm theme-text-muted">Agregue las cuentas de los colaboradores y asigne sus porcentajes. El reparto debe sumar 100%.</p>}
            <div className="space-y-3">{lineas.map((l, i) => <div key={i} className="grid gap-3 md:grid-cols-[minmax(0,1fr)_140px_140px_auto] items-end border-b theme-border pb-3">
                {editando ? <BuscarUsuario value={l.user_id} nombre={l.nombre} etiqueta={`Colaborador ${i + 1}`} onChange={(user_id, nombre) => cambiar(i, { user_id, nombre })} /> : <div><span className="text-sm font-semibold">{l.nombre}</span><p className="text-xs theme-text-muted m-0">Participación del fondo común</p></div>}
                {editando ? <label><span className={THEME_LABEL}>Porcentaje del fondo</span><input className={THEME_INPUT} required type="number" inputMode="decimal" min="0.01" max="100" step="0.01" value={l.porcentaje} onChange={(e) => cambiar(i, { porcentaje: e.target.value })} /></label> : <span className="text-sm tabular-nums">{l.porcentaje}%</span>}
                <div><span className="text-xs theme-text-muted">Estimación actual</span><p className="m-0 tabular-nums">{dinero(Number(acumulado) * Number(l.porcentaje || 0) / 100)}</p></div>
                {editando && <button type="button" className={THEME_BTN_SECONDARY} disabled={lineas.length === 1 || form.processing} onClick={() => form.setData('lineas', form.data.lineas.filter((_, j) => j !== i))}>Retirar<span className="sr-only"> {l.nombre || `colaborador ${i + 1}`}</span></button>}
            </div>)}</div>
            <div className="flex justify-between flex-wrap items-center gap-3"><p className={`text-sm m-0 ${total === 10000 ? 'theme-text-main' : 'theme-text-aviso'}`} role="status">Total asignado: {(total / 100).toFixed(2)}% de 100%</p>{editando && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => form.setData('lineas', [...form.data.lineas, { user_id: '', nombre: '', porcentaje: '' }])}>Agregar colaborador</button>}</div>
            {editando && <><label className="block"><span className={THEME_LABEL}>Motivo del cambio</span><input className={THEME_INPUT} required minLength={5} maxLength={2000} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} placeholder="Por ejemplo: salida de un colaborador…" /></label>
                <p className="text-sm theme-text-muted m-0">Este reparto se aplicará al acumulado abierto y a los siguientes periodos. Si alguien sale, cierre primero el periodo anterior para conservar la parte que le corresponde.</p>
                {Object.entries(form.errors).map(([k, error]) => <p key={k} role="alert" className="text-sm theme-text-peligro m-0">{error}</p>)}
                <div className="flex gap-2 flex-wrap"><button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar reparto permanente'}</button><button type="button" className={THEME_BTN_SECONDARY} disabled={form.processing} onClick={() => { form.reset(); setEditando(false); }}>Cancelar</button></div>
            </>}
        </form>
    </section>;
}
