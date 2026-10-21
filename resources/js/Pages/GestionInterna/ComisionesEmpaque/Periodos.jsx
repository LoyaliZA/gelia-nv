import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';

function siguienteDia(fecha) { const dia = new Date(`${fecha}T12:00:00Z`); dia.setUTCDate(dia.getUTCDate() + 1); return dia.toISOString().slice(0, 10); }
export default function Periodos({ periodos = [], equipo, areaId, puede }) {
    const [editor, setEditor] = useState(null);
    const lista = periodos.filter((p) => String(p.area_id) === String(areaId));
    const navegar = (params) => router.get(route('gestion_interna.comisiones_empaque.index'), { area_id: areaId, ...params }, { preserveState: false });
    return <div className="space-y-4">
        <div className="flex flex-wrap justify-between items-start gap-3"><div><h2 className="text-lg font-bold m-0">Periodos de comisión</h2><p className="theme-text-muted text-sm mt-1 max-w-3xl">Elija el rango que desea acumular y cerrar. El equipo y sus porcentajes se heredan del reparto permanente del área.</p></div>{puede.configurar && !editor && <button type="button" className={THEME_BTN_PRIMARY} onClick={() => setEditor({ clave: Date.now() })}>Crear periodo</button>}</div>
        {editor && <EditorPeriodo key={editor.clave} {...editor} equipo={equipo} areaId={areaId} onCerrar={() => setEditor(null)} />}
        {!lista.length && <div className={geliaCardClass('p-5')}><p className="m-0">Todavía no hay periodos en esta área. Cree uno con las fechas que desea revisar.</p></div>}
        {lista.map((p) => <section key={p.id} className={geliaCardClass('p-4 md:p-5 space-y-3')}>
            <div className="flex flex-wrap justify-between gap-3"><div><h3 className="font-bold m-0">{p.nombre}</h3><p className="text-sm theme-text-muted m-0">{p.desde} al {p.hasta} · {p.cerrado ? `Cerrado · ${p.folio_corte}` : 'Abierto'}</p></div><div className="flex flex-wrap gap-2">
                {puede.configurar && !p.cerrado && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setEditor({ periodo: p, clave: Date.now() })}>Cambiar fechas</button>}
                {puede.configurar && p.cerrado && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setEditor({ periodo: p, siguiente: true, clave: Date.now() })}>Crear siguiente periodo</button>}
                {!p.cerrado && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar({ tab: 'acumulado', periodo_id: p.id })}>Revisar empaques</button>}
                {puede.confirmar && !p.cerrado && <button type="button" className={THEME_BTN_PRIMARY} onClick={() => navegar({ tab: 'preparar', limite_desde: p.desde, limite_hasta: p.hasta })}>Cerrar periodo</button>}
                {p.cerrado && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar({ tab: 'cortes', corte: p.corte_id })}>Ver corte</button>}
            </div></div>
            <p className="text-xs theme-text-muted m-0">{p.cerrado ? 'Reparto conservado al confirmar el corte' : 'Reparto permanente vigente · los porcentajes se administran en Equipo y reparto'}</p>
            <ul className="m-0 pl-5 text-sm">{p.lineas.map((l, i) => <li key={`${l.participante_id}-${i}`}>{l.nombre}: {l.porcentaje}%{l.importe != null && ` · ${Number(l.importe).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}`}</li>)}</ul>
            {p.motivo && <p className="text-sm m-0">Motivo: {p.motivo}</p>}
        </section>)}
    </div>;
}
function EditorPeriodo({ periodo, siguiente, equipo, areaId, onCerrar }) {
    const editando = periodo && !siguiente;
    const form = useForm({ nombre: editando ? periodo.nombre : '', area_id: areaId, desde: siguiente ? siguienteDia(periodo.hasta) : periodo?.desde || '', hasta: editando ? periodo.hasta : '', motivo: '' });
    function guardar(e) { e.preventDefault(); const options = { preserveScroll: true, onSuccess: onCerrar }; if (editando) form.put(route('gestion_interna.comisiones_empaque.periodos.update', periodo.id), options); else form.post(route('gestion_interna.comisiones_empaque.periodos.store'), options); }
    return <form className={geliaCardClass('p-5 space-y-4')} onSubmit={guardar} aria-label={editando ? 'Cambiar fechas del periodo' : 'Nuevo periodo'}>
        <h3 className="font-bold m-0">{editando ? 'Cambiar fechas del periodo' : 'Nuevo periodo'}</h3>
        <div className="grid md:grid-cols-2 gap-3"><label className="md:col-span-2"><span className={THEME_LABEL}>Nombre</span><input className={THEME_INPUT} required maxLength={255} value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} placeholder="Por ejemplo: primera quincena de octubre…" /></label><label><span className={THEME_LABEL}>Desde, incluido</span><input className={THEME_INPUT} required type="date" value={form.data.desde} onChange={(e) => form.setData('desde', e.target.value)} /></label><label><span className={THEME_LABEL}>Hasta, incluido</span><input className={THEME_INPUT} required type="date" min={form.data.desde} value={form.data.hasta} onChange={(e) => form.setData('hasta', e.target.value)} /></label></div>
        <div><h4 className="font-semibold m-0">Reparto permanente heredado</h4><ul className="pl-5 text-sm">{equipo?.lineas?.map((l) => <li key={l.user_id}>{l.nombre}: {l.porcentaje}%</li>)}</ul>{!equipo?.lineas?.length && <p className="text-sm theme-text-aviso">Configure el equipo y reparto antes de confirmar el corte.</p>}</div>
        <label className="block"><span className={THEME_LABEL}>{editando ? 'Motivo del cambio' : 'Comentario (opcional)'}</span><input className={THEME_INPUT} required={!!editando} minLength={5} maxLength={2000} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} /></label>
        {Object.entries(form.errors).map(([campo, error]) => <p key={campo} className="text-sm theme-text-peligro m-0" role="alert">{error}</p>)}
        <div className="flex gap-2 flex-wrap"><button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : editando ? 'Guardar fechas' : 'Crear periodo'}</button><button type="button" className={THEME_BTN_SECONDARY} disabled={form.processing} onClick={() => { if (!form.isDirty || window.confirm('¿Descartar los cambios del periodo?')) onCerrar(); }}>Cancelar</button></div>
    </form>;
}
