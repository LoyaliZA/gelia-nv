import { Boxes, Coins, History, Store, UsersRound } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { lazy, Suspense, useState } from 'react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import BuscarUsuario from './BuscarUsuario';
import Equipo from './Equipo';
import { useProtegerEdicion } from './useActualizacionComisiones';
import { ACCION_PRINCIPAL_COMISIONES, ErroresFormulario, dinero, momentoLegible } from './compartido';

const Carga = lazy(() => import('./Carga'));

const secciones = [['tarifas', 'Tarifa por caja', Coins], ['equipo', 'Equipo y porcentajes', UsersRound], ['areas', 'Áreas'], ['accesos', 'Accesos'], ['sincronizacion', 'Sincronización e históricos']];

export default function Configuracion({ areas, areaId, fechaInicio, fechaFin, tarifas, alcances, equipo, puede, fondo, repartos, sincronizacion, inicial = 'tarifas', cargaRevision, onSeccion, onAjustes, editarEquipo = false, simple = false }) {
    const seccion = cargaRevision ? 'sincronizacion' : secciones.some(([id]) => id === inicial) ? inicial : 'tarifas';
    const administracion = secciones.slice(2);
    const esAdministracion = administracion.some(([id]) => id === seccion);
    const visibles = simple && !esAdministracion ? secciones.slice(0, 2) : secciones;
    const areaActiva = areaId || (seccion === 'tarifas' ? areas.find((a) => a.activo)?.id : '') || '';
    const AreaIcono = areas.find((a) => String(a.id) === String(areaActiva))?.codigo === 'tienda' ? Store : Boxes;
    return <div className="min-w-0 space-y-4">
        <nav className="gelia-segment p-1" aria-label="Configuración de comisiones">
            <div className={`grid w-full min-w-0 grid-cols-2 gap-1 ${visibles.length === 2 ? '' : 'md:grid-cols-5'}`}>
                {visibles.map(([id, nombre, Icono]) => <button type="button" key={id} className={`gelia-segment-btn min-h-11 min-w-0 px-1 ${id === 'sincronizacion' ? 'col-span-2 md:col-span-1' : ''}`} aria-pressed={seccion === id} data-active={seccion === id} onClick={() => onSeccion(id)}>{Icono && <Icono className="size-4 shrink-0" aria-hidden="true" />}{nombre}</button>)}
            </div>
        </nav>
        {simple && !esAdministracion && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onSeccion('areas')}>Abrir administración avanzada</button>}
        {esAdministracion && <button type="button" className={THEME_BTN_SECONDARY} onClick={onAjustes}>Consultar ajustes de cortes</button>}
        {['tarifas', 'equipo'].includes(seccion) && <label className="block max-w-xs">
            <span className={THEME_LABEL}><AreaIcono aria-hidden="true" className="mr-1.5 inline-block size-4" />Área</span>
            <select className={THEME_INPUT} value={areaActiva} onChange={(e) => onSeccion(seccion, e.target.value)}>
                <option value="" disabled>Seleccione un área</option>
                {areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
            </select>
        </label>}
        {seccion === 'tarifas' && areaActiva && <Tarifas key={areaActiva} area={areas.find((a) => String(a.id) === String(areaActiva))} tarifas={tarifas} />}
        {seccion === 'equipo' && areaId && <Equipo key={areaId} equipo={equipo} areaId={areaId} puede={puede} editarAlAbrir={editarEquipo}
            acumulado={fondo} historial={repartos.filter((r) => String(r.area_id) === String(areaId))} />}
        {seccion === 'areas' && <Areas areas={areas} />}
        {seccion === 'accesos' && <Accesos areas={areas} alcances={alcances} />}
        {seccion === 'sincronizacion' && <Sincronizacion fechaInicio={fechaInicio} fechaFin={fechaFin} sincronizacion={sincronizacion} puedeCargar={puede.capturar} cargaRevision={cargaRevision} />}
    </div>;
}

function Tarifas({ area, tarifas }) {
    const form = useForm({ area_id: area.id, importe: '', vigente_desde: '' });
    useProtegerEdicion(form.isDirty || form.processing);
    const seleccionadas = tarifas.filter((t) => String(t.area_id) === String(area.id));
    const vigente = seleccionadas.find((t) => t.estado === 'Vigente');
    const otras = seleccionadas.filter((t) => t.id !== vigente?.id);
    return <section className="space-y-4 rounded-xl border theme-border p-3 md:p-4">
        <div className="flex flex-wrap items-center gap-3 border-b theme-border pb-3">
            <span className="grid size-11 place-items-center rounded-lg theme-element theme-text-primario"><Coins className="size-5" aria-hidden="true" /></span>
            <div className="min-w-0">
                <h2 className="m-0 text-sm font-semibold theme-text-muted">Tarifa vigente en {area.nombre}</h2>
                <p className="m-0 text-2xl font-semibold tabular-nums">{vigente ? dinero(vigente.importe) : 'Sin tarifa vigente'}</p>
                {vigente && <p className="m-0 text-sm theme-text-muted">Desde {momentoLegible(vigente.vigente_desde)}</p>}
            </div>
        </div>
        {otras.length > 0 && <details>
            <summary className="min-h-11 cursor-pointer py-3 text-sm font-semibold"><History className="mr-2 inline-block size-4" aria-hidden="true" />Ver otras vigencias</summary>
            <ul className="m-0 list-none p-0 text-sm">
                {otras.map((t) => <li className="flex flex-wrap items-center justify-between gap-2 border-b theme-border py-2" key={t.id}>
                    <span>{momentoLegible(t.vigente_desde)}{t.vigente_hasta ? ` al ${momentoLegible(t.vigente_hasta)}` : ''}</span>
                    <span><strong className="tabular-nums">{dinero(t.importe)}</strong> · {t.estado}</span>
                </li>)}
            </ul>
        </details>}
        <form className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end" onSubmit={(e) => {
            e.preventDefault();
            form.post(route('gestion_interna.comisiones_empaque.tarifas.store'), { preserveScroll: true, onSuccess: () => form.reset('importe', 'vigente_desde') });
        }}>
            <label className="min-w-0"><span className={THEME_LABEL}>Importe por caja</span><input className={THEME_INPUT} required inputMode="decimal" value={form.data.importe} onChange={(e) => { form.clearErrors?.('importe'); form.setData('importe', e.target.value); }} /></label>
            <label className="min-w-0"><span className={THEME_LABEL}>Vigente desde</span><input className={`${THEME_INPUT} min-w-0 max-w-full`} required type="datetime-local" value={form.data.vigente_desde} onChange={(e) => { form.clearErrors?.('vigente_desde'); form.setData('vigente_desde', e.target.value); }} /></label>
            <button className={ACCION_PRINCIPAL_COMISIONES} disabled={form.processing}><Coins className="size-4" aria-hidden="true" />{form.processing ? 'Registrando…' : 'Registrar tarifa'}</button>
            <div className="md:col-span-3"><ErroresFormulario form={form} /></div>
        </form>
    </section>;
}

function Areas({ areas }) {
    const form = useForm({ codigo: '', nombre: '' });
    useProtegerEdicion(form.isDirty || form.processing);
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
    useProtegerEdicion(form.isDirty || form.processing);
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
    useProtegerEdicion(form.isDirty || form.processing);
    return <section className={geliaCardClass('space-y-4 p-4')}>
        <h2 className="m-0 text-base font-bold">Rango autorizado de sincronización</h2>
        {sincronizacion && <div className="text-sm theme-text-muted">
            {sincronizacion.mensaje && <p className="m-0">{sincronizacion.mensaje}</p>}
            {sincronizacion.areas?.map((area) => <p key={area.id} className="m-0">
                {area.nombre} · Última ejecución {area.ultima_at ? momentoLegible(area.ultima_at) : 'pendiente'} · Último éxito {area.ultima_exitosa_at ? momentoLegible(area.ultima_exitosa_at) : 'pendiente'}
            </p>)}
        </div>}
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); form.transform((datos) => ({ ...datos, fecha_fin: datos.fecha_fin || null })); form.put(route('gestion_interna.comisiones_empaque.fecha_inicio'), { preserveScroll: true }); }}>
            <label><span className={THEME_LABEL}>Importar desde</span><input className={THEME_INPUT} required type="date" value={form.data.fecha_inicio} onChange={(e) => form.setData('fecha_inicio', e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Importar hasta (opcional)</span><input className={THEME_INPUT} min={form.data.fecha_inicio} type="date" value={form.data.fecha_fin} onChange={(e) => form.setData('fecha_fin', e.target.value)} /></label>
            <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar rango</button>
        </form>
        <ErroresFormulario form={form} />
        {puedeCargar && <button type="button" className={THEME_BTN_SECONDARY} aria-expanded={cargar} onClick={() => setCargar(!cargar)}>{cargar ? 'Ocultar carga histórica' : 'Cargar histórico'}</button>}
        {puedeCargar && cargar && <Suspense fallback={<p role="status" className="m-0 text-sm theme-text-muted">Cargando formulario…</p>}>
            <Carga revision={cargaRevision} />
        </Suspense>}
    </section>;
}
