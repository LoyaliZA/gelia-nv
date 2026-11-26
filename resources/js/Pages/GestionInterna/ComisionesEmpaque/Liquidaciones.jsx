import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import { useProtegerEdicion } from './useActualizacionComisiones';
import { BOTON_FILTRO, ErroresFormulario, dinero, ir } from './compartido';

export function Preparar({ areas, filtros, preview: calculo, puede }) {
    const [preview, setPreview] = useState(calculo);
    const [area, setArea] = useState(filtros.area_id || areas[0]?.id || '');
    const [desde, setDesde] = useState(filtros.limite_desde || filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.limite_hasta || filtros.hasta || '');
    const tardias = false;
    const calculoVigente = String(area) === String(filtros.area_id) && desde === filtros.limite_desde && hasta === filtros.limite_hasta && tardias === !!Number(filtros.incluir_tardias || 0);
    const form = useForm({});
    useProtegerEdicion(form.processing);
    const retorno = {
        area_id: area, periodo_id: filtros.periodo_id, desde, hasta,
        limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias ? 1 : 0, retorno_corte: 1,
    };

    function ver(e) {
        e.preventDefault();
        ir({
            tab: 'preparar', area_id: area, limite_desde: desde, limite_hasta: hasta,
            periodo_id: String(area) === String(filtros.area_id) && desde === filtros.desde && hasta === filtros.hasta ? filtros.periodo_id : undefined,
        }, { onSuccess: (pagina) => { setPreview(pagina.props.preview); form.clearErrors?.(); } });
    }

    return (
        <form className="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" onSubmit={ver}>
            <div className="min-w-0 space-y-4">
                <div className={geliaCardClass('p-4 grid md:grid-cols-2 gap-3')}>
                    <p className="md:col-span-2 text-sm theme-text-muted m-0">Las fechas deben corresponder al periodo que va a cerrar. Puede elegirlo desde la sección Periodos.</p>
                    <label><span className={THEME_LABEL}>Área</span>
                        <select className={THEME_INPUT} value={area} onChange={(e) => setArea(e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select>
                    </label>
                    <button type="button" className={BOTON_FILTRO} onClick={() => ir({ ...retorno, tab: 'acumulado', vista: 'tardias', retorno_periodo_id: filtros.periodo_id, periodo_id: undefined, desde: undefined, hasta: undefined })}>Revisar incorporaciones tardías</button>
                    <label><span className={THEME_LABEL}>Desde</span><input className={THEME_INPUT} type="date" required value={desde} onChange={(e) => setDesde(e.target.value)} /></label>
                    <label><span className={THEME_LABEL}>Hasta</span><input className={THEME_INPUT} type="date" required value={hasta} onChange={(e) => setHasta(e.target.value)} /></label>
                    <button className={THEME_BTN_SECONDARY} type="submit">Revisar cálculo</button>
                </div>
                {preview && calculoVigente && (
                    <div className={geliaCardClass('overflow-x-auto')}>
                        <table className="w-full text-sm">
                            <thead><tr className="text-left theme-text-muted"><th className="p-2">Empaque</th><th className="p-2">Pedido</th><th className="p-2">Remisión</th><th className="p-2 text-right">Cajas</th><th className="p-2 text-right">Importe</th></tr></thead>
                            <tbody>
                                {preview.unidades.length === 0 && <tr><td colSpan={5} className="p-4 theme-text-muted">No hay unidades elegibles en este intervalo.</td></tr>}
                                {preview.unidades.map((u) => (
                                    <tr key={u.id} className="border-t theme-border"><td className="p-2">{u.empacado_at}</td><td className="p-2">{u.folio_pedido}</td><td className="p-2">{u.folio_remision || 'Sin remisión'}</td><td className="p-2 text-right">{u.cantidad}</td><td className="p-2 text-right">{dinero(u.importe)}</td></tr>
                                ))}
                            </tbody>
                        </table>
                        {preview.exclusiones?.length > 0 && (
                            <div className="p-3 text-sm">
                                <p className="font-semibold m-0">Fuera de este corte</p>
                                <ul className="m-0 pl-4">{preview.exclusiones.map((e) => <li key={e.id}>{e.folio_pedido || e.id}: {e.situacion}</li>)}</ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <aside className={geliaCardClass('p-4 h-fit space-y-2 lg:sticky lg:top-4')}>
                <h2 className="text-base font-semibold m-0">Resumen del corte</h2>
                {(!preview || !calculoVigente) && <p className="text-sm theme-text-muted m-0">Revise el cálculo para ver cantidades, reparto e importe.</p>}
                {preview && calculoVigente && (
                    <>
                        <dl className="m-0 grid grid-cols-2 gap-2 text-sm">
                            {[
                                ['Cajas', preview.cajas], ['Por revisar', preview.cajas_pendientes ?? 0],
                                ['Excluidas', preview.cajas_excluidas ?? 0], ['Fondo del corte', dinero(preview.importe)],
                            ].map(([nombre, valor]) => <div key={nombre}><dt className="text-xs theme-text-muted">{nombre}</dt><dd className="m-0 font-semibold tabular-nums">{valor}</dd></div>)}
                        </dl>
                        <p className="m-0 text-xs theme-text-muted">{[...preview.tipos.map((tipo) => `${tipo.nombre} · ${tipo.cantidad}`), ...(preview.tarifas || []).map((t) => `${dinero(t)} por caja`)].join(' / ')}</p>
                        {preview.tramos.length > 0 && <table className="w-full border-t theme-border text-left text-sm">
                            <caption className="sr-only">Reparto del corte</caption>
                            <thead><tr className="theme-text-muted"><th className="py-2 font-medium">Colaborador</th><th className="text-right font-medium">%</th><th className="text-right font-medium">Importe</th></tr></thead>
                            {preview.tramos.map((tramo) => <tbody key={tramo.reparto_id}>
                                {(tramo.participantes || []).map((p) => <tr key={p.participante_id} className="border-t theme-border">
                                    <th scope="row" className="py-2 pr-1 font-medium">{p.nombre}</th><td className="pr-2 text-right tabular-nums">{p.porcentaje}%</td><td className="text-right tabular-nums">{dinero(p.importe)}</td>
                                </tr>)}
                            </tbody>)}
                        </table>}
                        {(preview.errores.length > 0 || form.errors.corte) && <section className="border-t theme-border pt-2" aria-label="Impedimentos para confirmar">
                            {[...preview.errores, form.errors.corte].filter(Boolean).map((error) => <p key={error} className="theme-text-peligro text-sm m-0" role="alert">{error}</p>)}
                        </section>}
                        <div className="flex flex-wrap gap-2">
                            {!!preview.pendientes && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => ir({ ...retorno, tab: 'acumulado', vista: 'pendientes' })}>Revisar empaques pendientes</button>}
                            {puede.configurar && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => ir({ ...retorno, tab: 'equipo' })}>Revisar equipo y reparto</button>}
                        </div>
                        {puede.confirmar && !form.errors.corte && preview.errores.length === 0 && preview.unidades.length > 0 && (
                            <button type="button" className={THEME_BTN_PRIMARY} disabled={form.processing} onClick={() => { form.transform(() => ({ area_id: area, limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias, hash: preview.hash, ...(filtros.periodo_id ? { periodo_id: filtros.periodo_id } : {}) })); form.post(route('gestion_interna.comisiones_empaque.confirmar')); }}>Confirmar corte</button>
                        )}
                    </>
                )}
            </aside>
        </form>
    );
}

export function Cortes({ cortes, corte, puede, filtros }) {
    return (
        <div className="space-y-4">
            <div className={geliaCardClass('overflow-x-auto')}>
                <table className="w-full text-sm min-w-[800px]">
                    <thead><tr className="text-left theme-text-muted">{['Folio', 'Área', 'Intervalo', 'Cajas', 'Total', 'Pagado', 'Pendiente', 'Estado', 'Responsable'].map((h) => <th key={h} className="p-2">{h}</th>)}</tr></thead>
                    <tbody>
                        {(cortes?.data || []).length === 0 && <tr><td colSpan={9} className="p-6 theme-text-muted">Todavía no hay cortes.</td></tr>}
                        {(cortes?.data || []).map((c) => (
                            <tr key={c.id} className="border-t theme-border">
                                <td className="p-2"><button type="button" className="theme-text-primario" onClick={() => ir({ ...filtros, tab: 'cortes', corte: c.id, page: cortes?.current_page })}>{c.folio}</button>{c.provisional && <span className="block text-xs">Provisional</span>}</td>
                                <td className="p-2">{c.area}</td>
                                <td className="p-2">{c.desde} a {c.hasta}</td>
                                <td className="p-2">{c.cajas}</td>
                                <td className="p-2 text-right">{dinero(c.total)}</td>
                                <td className="p-2 text-right">{dinero(c.pagado)}</td>
                                <td className="p-2 text-right">{dinero(c.pendiente)}</td>
                                <td className="p-2">{c.estado} · {c.estado_pago}</td>
                                <td className="p-2">{c.responsable || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {cortes?.total > 0 && <GeliaPaginacion embedded paginator={cortes} onIrAPagina={(page) => ir({ ...filtros, tab: 'cortes', page })} />}
            </div>
            {corte && (
                <section className={geliaCardClass('p-4 space-y-3')}>
                    <h2 className="text-lg font-black m-0">{corte.provisional ? 'Borrador provisional' : corte.folio}</h2>
                    <p className="text-sm theme-text-muted m-0">{corte.area} · {corte.desde} a {corte.hasta} · {corte.estado_pago} · {corte.responsable || 'Sin confirmación'}</p>
                    {puede.exportar && (
                        <div className="flex gap-2">
                            <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.corte', corte.id)}>Exportar corte</a>
                            <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.reparto', corte.id)}>Exportar reparto</a>
                        </div>
                    )}
                    {(corte.unidades || []).map((u) => <p key={u.clave} className="text-sm m-0">{u.empacado_at} · {u.pedido} · {u.cantidad} · {dinero(u.importe)}{u.excepcion_resguardo ? ' · Excepción de resguardo' : ''}{u.conciliacion && <span className="block text-xs theme-text-muted">{u.conciliacion.responsable} · {u.conciliacion.observaciones}{u.conciliacion.referencia && ` · ${u.conciliacion.referencia}`}</span>}</p>)}
                    {(corte.asignaciones || []).map((a) => <p key={a.id} className="text-sm m-0">{a.nombre} {a.porcentaje}% · reconocido {dinero(a.reconocido)} · pagado {dinero(a.pagado)} · pendiente {dinero(a.pendiente)}</p>)}
                    {(corte.ajustes || []).map((a, i) => <p key={i} className="text-sm theme-text-aviso m-0">Ajuste {dinero(a.importe)} {a.aplicar_en_saldo ? 'aplicado al saldo' : 'solo registrado'} · {a.motivo}</p>)}
                </section>
            )}
        </div>
    );
}

export function Pagos({ saldos, puede, areaId }) {
    const [id, setId] = useState(saldos[0]?.id || '');
    const persona = useMemo(() => saldos.find((s) => String(s.id) === String(id)) || saldos[0], [saldos, id]);
    const [seleccion, setSeleccion] = useState({});
    const nuevaClave = () => typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
    const [clave, setClave] = useState(nuevaClave);
    useEffect(() => { setClave(nuevaClave()); }, [persona?.id]);
    const form = useForm({ fecha_pago: '', referencia: '', medio: '', observaciones: '' });
    useProtegerEdicion(form.isDirty || form.processing || Object.values(seleccion).some(Boolean));
    const aPagar = persona ? persona.asignaciones.filter((a) => seleccion[a.id]).reduce((n, a) => n + Number(seleccion[a.id] || 0), 0) : 0;
    const pendiente = persona ? Number(persona.pendiente) : 0;

    return (
        <div className="space-y-4">
            {puede.exportar && <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.pagos', areaId ? { area_id: areaId } : {})}>Exportar pagos y saldos</a>}
            {saldos.length === 0 && <p className="theme-text-muted">No hay cortes confirmados con saldo en esta área. Cierre un periodo para calcular las comisiones por pagar.</p>}
            <label className="block max-w-md"><span className={THEME_LABEL}>Participante</span>
                <select className={THEME_INPUT} value={persona?.id || ''} disabled={form.processing} onChange={(e) => {
                    if ((form.isDirty || Object.keys(seleccion).length) && !window.confirm('¿Descartar los datos de este pago?')) return;
                    setId(e.target.value);
                    setSeleccion({});
                    form.reset();
                    form.clearErrors?.();
                }}>
                    {saldos.map((s) => <option key={s.id} value={s.id}>{s.nombre}{s.activo ? '' : ' (inactivo)'}</option>)}
                </select>
            </label>
            {persona && (
                <div className={geliaCardClass('p-4 space-y-3')}>
                    <p className="m-0 text-sm">Saldo acumulado {areaId ? 'del área' : 'de las áreas consultadas'} · Por pagar {dinero(persona.pendiente)} · Confirmado {dinero(persona.reconocido)} · Pagado {dinero(persona.pagado)}</p>
                    {persona.asignaciones.map((a) => (
                        <label key={a.id} className="flex flex-wrap gap-2 items-center text-sm">
                            <input type="checkbox" checked={seleccion[a.id] != null} onChange={(e) => setSeleccion((prev) => {
                                const next = { ...prev };
                                if (e.target.checked) next[a.id] = a.pendiente;
                                else delete next[a.id];
                                return next;
                            })} />
                            <span>{a.folio} · {a.area} · pendiente {dinero(a.pendiente)}</span>
                            {seleccion[a.id] != null && <input className={`${THEME_INPUT} w-32`} aria-label={`Importe para ${a.folio}`} value={seleccion[a.id]} onChange={(e) => setSeleccion({ ...seleccion, [a.id]: e.target.value })} />}
                        </label>
                    ))}
                    {puede.pagar && (
                        <form className="grid md:grid-cols-2 gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                participante_id: persona.id,
                                importe: aPagar.toFixed(2),
                                clave_idempotencia: clave,
                                aplicaciones: Object.entries(seleccion).map(([asignacionId, importe]) => ({ asignacion_id: Number(asignacionId), importe: String(importe) })),
                            }));
                            form.post(route('gestion_interna.comisiones_empaque.pagar'), { onSuccess: () => { setSeleccion({}); setClave(nuevaClave()); form.reset(); } });
                        }}>
                            <label><span className={THEME_LABEL}>Fecha real de pago</span><input className={THEME_INPUT} type="date" required value={form.data.fecha_pago} onChange={(e) => form.setData('fecha_pago', e.target.value)} /></label>
                            <label><span className={THEME_LABEL}>Referencia o medio</span><input className={THEME_INPUT} value={form.data.referencia} onChange={(e) => form.setData('referencia', e.target.value)} /></label>
                            <p className="md:col-span-2 text-sm m-0">Importe a pagar: {dinero(aPagar.toFixed(2))}. Saldo posterior: {dinero((pendiente - aPagar).toFixed(2))}.</p>
                            <ErroresFormulario form={form} />
                            <button className={THEME_BTN_PRIMARY} disabled={form.processing || aPagar <= 0}>Registrar pago</button>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}

