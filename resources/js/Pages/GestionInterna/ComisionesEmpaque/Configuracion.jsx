import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import BuscarUsuario from './BuscarUsuario';
import Equipo from './Equipo';
import { Carga } from './Empaques';
import { ErroresFormulario, dinero, momentoLegible } from './compartido';

const secciones = [['tarifas', 'Tarifas'], ['equipo', 'Equipo y reparto'], ['areas', 'Áreas'], ['accesos', 'Accesos'], ['sincronizacion', 'Sincronización e históricos']];

export default function Configuracion({ areas, areaId, fechaInicio, fechaFin, tarifas, alcances, equipo, puede, fondo, repartos, sincronizacion, inicial = 'tarifas', cargaRevision, onSeccion }) {
    const seccion = cargaRevision ? 'sincronizacion' : secciones.some(([id]) => id === inicial) ? inicial : 'tarifas';
    return <div className="min-w-0 space-y-4">
        <nav className="gelia-segment p-1" aria-label="Configuración de comisiones">
            <div className="grid w-full min-w-0 grid-cols-2 gap-1 md:grid-cols-5">
                {secciones.map(([id, nombre]) => <button type="button" key={id} className={`gelia-segment-btn min-h-11 min-w-0 px-1 ${id === 'sincronizacion' ? 'col-span-2 md:col-span-1' : ''}`} aria-pressed={seccion === id} data-active={seccion === id} onClick={() => onSeccion(id)}>{nombre}</button>)}
            </div>
        </nav>
        {seccion === 'tarifas' && <Tarifas areas={areas} areaId={areaId} tarifas={tarifas} />}
        {seccion === 'equipo' && (areaId ? <Equipo equipo={equipo} areaId={areaId} puede={puede} acumulado={fondo} historial={repartos.filter((r) => String(r.area_id) === String(areaId))} /> : <p role="status">Seleccione un área para configurar su equipo.</p>)}
        {seccion === 'areas' && <Areas areas={areas} />}
        {seccion === 'accesos' && <Accesos areas={areas} alcances={alcances} />}
        {seccion === 'sincronizacion' && <Sincronizacion fechaInicio={fechaInicio} fechaFin={fechaFin} sincronizacion={sincronizacion} puedeCargar={puede.capturar} cargaRevision={cargaRevision} />}
    </div>;
}

function Tarifas({ areas, areaId, tarifas }) {
    const form = useForm({ area_id: areaId || areas[0]?.id || '', importe: '', vigente_desde: '' });
    const seleccionadas = tarifas.filter((t) => !areaId || String(t.area_id) === String(areaId));
    return <section className={geliaCardClass('space-y-4 p-4')}>
        <h2 className="m-0 text-base font-bold">Tarifas por caja</h2>
        {!seleccionadas.length && <p className="m-0 text-sm theme-text-muted">Registre la primera tarifa del área.</p>}
        <ul className="m-0 list-none p-0 text-sm">
            {seleccionadas.map((t) => <li className="flex flex-wrap items-center justify-between gap-2 border-b theme-border py-2" key={t.id}>
                <span><strong>{t.area}</strong> · {momentoLegible(t.vigente_desde)}{t.vigente_hasta ? ` al ${momentoLegible(t.vigente_hasta)}` : ''}</span>
                <span>{dinero(t.importe)} · {t.estado}</span>
            </li>)}
        </ul>
        <form className="grid gap-3 md:grid-cols-4 md:items-end" onSubmit={(e) => { e.preventDefault(); form.post(route('gestion_interna.comisiones_empaque.tarifas.store'), { preserveScroll: true, onSuccess: () => form.reset('importe', 'vigente_desde') }); }}>
            <label><span className={THEME_LABEL}>Área</span><select className={THEME_INPUT} value={form.data.area_id} onChange={(e) => form.setData('area_id', e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label>
            <label><span className={THEME_LABEL}>Importe por caja</span><input className={THEME_INPUT} required inputMode="decimal" value={form.data.importe} onChange={(e) => form.setData('importe', e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Vigente desde</span><input className={THEME_INPUT} required type="datetime-local" value={form.data.vigente_desde} onChange={(e) => form.setData('vigente_desde', e.target.value)} /></label>
            <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Registrar tarifa</button>
        </form>
        <ErroresFormulario form={form} />
    </section>;
}

function Areas({ areas }) {
    const form = useForm({ codigo: '', nombre: '' });
    return <section className={geliaCardClass('space-y-4 p-4')}>
        <h2 className="m-0 text-base font-bold">Áreas de empaque</h2>
        <ul className="m-0 list-none p-0">{areas.map((area) => <Area key={area.id} area={area} />)}</ul>
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); form.post(route('gestion_interna.comisiones_empaque.areas.store'), { preserveScroll: true, onSuccess: () => form.reset() }); }}>
            <label><span className={THEME_LABEL}>Código</span><input className={THEME_INPUT} required value={form.data.codigo} onChange={(e) => form.setData('codigo', e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Nombre</span><input className={THEME_INPUT} required value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} /></label>
            <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Agregar área</button>
        </form>
        <ErroresFormulario form={form} />
    </section>;
}

function Area({ area }) {
    const form = useForm({ nombre: area.nombre, activo: !area.activo });
    function cambiar() {
        form.transform(() => ({ nombre: area.nombre, activo: !area.activo }));
        form.put(route('gestion_interna.comisiones_empaque.areas.update', area.id), { preserveScroll: true });
    }
    return <li className="flex flex-wrap items-center justify-between gap-2 border-b theme-border py-2 text-sm">
        <div><strong>{area.nombre}</strong> · {area.activo ? 'Activa' : 'Inactiva'}<p className="m-0 theme-text-muted">{area.sistema ? 'Fuente de Gestión de Pedidos' : 'Captura y carga autorizadas'}</p></div>
        {!area.sistema && <button type="button" className={THEME_BTN_SECONDARY} disabled={form.processing} onClick={cambiar}>{area.activo ? 'Desactivar' : 'Activar'}</button>}
        <ErroresFormulario form={form} />
    </li>;
}

function Accesos({ areas, alcances }) {
    const form = useForm({ user_id: '', area_ids: [] });
    const [cuenta, setCuenta] = useState(null);
    return <form className={geliaCardClass('space-y-3 p-4')} onSubmit={(e) => { e.preventDefault(); form.put(route('gestion_interna.comisiones_empaque.alcance'), { preserveScroll: true }); }}>
        <h2 className="m-0 text-base font-bold">Acceso de operación</h2>
        <BuscarUsuario value={form.data.user_id} onChange={(id, nombre, datos) => {
            setCuenta(datos || null);
            form.setData({ user_id: id, area_ids: alcances.filter((a) => String(a.user_id) === String(id)).map((a) => String(a.area_id)) });
        }} />
        {cuenta?.facultades && <p className="text-sm theme-text-muted">{cuenta.facultades.length ? cuenta.facultades.join(' · ') : 'Sin permisos en Comisiones de empaque'}</p>}
        <fieldset className="border-0 p-0"><legend className={THEME_LABEL}>Áreas autorizadas</legend>{areas.map((a) => <label key={a.id} className="flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" checked={form.data.area_ids.includes(String(a.id))} onChange={(e) => form.setData('area_ids', e.target.checked ? [...form.data.area_ids, String(a.id)] : form.data.area_ids.filter((id) => id !== String(a.id)))} />{a.nombre}
        </label>)}</fieldset>
        <ErroresFormulario form={form} />
        <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar accesos</button>
        <details><summary className="min-h-11 cursor-pointer py-2 text-sm font-semibold">Consultar accesos asignados</summary>
            <ul className="m-0 pl-5 text-sm">{alcances.map((a) => <li key={`${a.user_id}-${a.area_id}`}>{a.nombre} · {a.area}</li>)}</ul>
        </details>
    </form>;
}

function Sincronizacion({ fechaInicio, fechaFin, sincronizacion, puedeCargar, cargaRevision }) {
    const form = useForm({ fecha_inicio: fechaInicio || '', fecha_fin: fechaFin || '' });
    const [cargar, setCargar] = useState(Boolean(cargaRevision));
    return <section className={geliaCardClass('space-y-4 p-4')}>
        <h2 className="m-0 text-base font-bold">Rango autorizado de sincronización</h2>
        {sincronizacion && <div className="text-sm theme-text-muted">
            <p className="m-0">{momentoLegible(sincronizacion.finalizado_at)} · {sincronizacion.mensaje}</p>
            {sincronizacion.estado === 'ok' && <p className="m-0">{sincronizacion.nuevas} nuevos · {sincronizacion.actualizadas} actualizados · {sincronizacion.diferencias} diferencias</p>}
        </div>}
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); form.transform((datos) => ({ ...datos, fecha_fin: datos.fecha_fin || null })); form.put(route('gestion_interna.comisiones_empaque.fecha_inicio'), { preserveScroll: true }); }}>
            <label><span className={THEME_LABEL}>Importar desde</span><input className={THEME_INPUT} required type="date" value={form.data.fecha_inicio} onChange={(e) => form.setData('fecha_inicio', e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Importar hasta (opcional)</span><input className={THEME_INPUT} min={form.data.fecha_inicio} type="date" value={form.data.fecha_fin} onChange={(e) => form.setData('fecha_fin', e.target.value)} /></label>
            <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar rango</button>
        </form>
        <ErroresFormulario form={form} />
        {puedeCargar && <button type="button" className={THEME_BTN_SECONDARY} aria-expanded={cargar} onClick={() => setCargar(!cargar)}>{cargar ? 'Ocultar carga histórica' : 'Cargar histórico'}</button>}
        {puedeCargar && cargar && <Carga revision={cargaRevision} />}
    </section>;
}
