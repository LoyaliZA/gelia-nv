import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import { Eye, Plus, X } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_MODAL_SHELL, geliaCardClass } from '@/utils/geliaTheme';
import { BOTON_FILTRO, ErroresFormulario, dinero, momentoLegible } from './compartido';

const INSIGNIA = {
    info: 'border-[color-mix(in_srgb,var(--color-info)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-info)_16%,transparent)] text-[color:var(--color-info)]',
    exito: 'border-[color-mix(in_srgb,var(--color-exito)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_16%,transparent)] text-[color:var(--color-exito)]',
    aviso: 'border-[color-mix(in_srgb,var(--color-aviso)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_16%,transparent)] text-[color:var(--color-aviso)]',
    peligro: 'border-[color-mix(in_srgb,var(--color-peligro)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_16%,transparent)] text-[color:var(--color-peligro)]',
};

function logisticaTexto(logistica = {}) {
    const partes = [
        logistica.paqueteria && `Paquetería: ${logistica.paqueteria}`,
        logistica.categoria_paqueteria && `Categoría: ${logistica.categoria_paqueteria}`,
        logistica.modalidad_tarifa && `Modalidad de tarifa: ${logistica.modalidad_tarifa}`,
        logistica.tipo_guia && `Tipo de guía: ${logistica.tipo_guia}`,
        logistica.envio_tienda && `Envío tienda: ${logistica.envio_tienda}`,
        logistica.modalidad_preparacion && `Preparación: ${logistica.modalidad_preparacion}`,
        logistica.origen_solicitud && `Origen del pedido: ${logistica.origen_solicitud}`,
    ].filter(Boolean);
    return partes.length ? partes.join(' · ') : 'Sin datos de paquetería o modalidad';
}

function estadoRevision(fila) {
    if (fila.liquidacion === 'pagado_fuera' || fila.situacion === 'liquidada_fuera') return { texto: 'Pagado', tono: 'exito' };
    if (['excluida', 'rechazada'].includes(fila.situacion)) return { texto: 'No se paga', tono: 'peligro' };
    if (fila.situacion === 'en_corte') return { texto: 'En corte', tono: 'info' };
    if (fila.situacion === 'elegible') return { texto: 'Revisado', tono: 'info' };
    return { texto: fila.situacion_etiqueta || 'Pendiente', tono: 'aviso' };
}

export default function Fila({ fila, puede, tipos, motivos }) {
    const [abierta, setAbierta] = useState(false);
    const estado = estadoRevision(fila);
    return <>
        <tr className="border-t theme-border align-top">
            <td className="p-2 whitespace-nowrap">{fila.empacado_at}{fila.incorporacion_tardia && <span className="block text-xs theme-text-aviso">Incorporación tardía</span>}</td>
            <td className="p-2"><strong>{fila.cliente}</strong><span className="block text-xs theme-text-muted">{fila.remisiones?.join(', ') || fila.folio_remision || 'Sin remisión'}</span><span className="block text-xs theme-text-muted">{[fila.folio_pedido, fila.area].filter(Boolean).join(' · ')}</span></td>
            <td className="p-2">{fila.empacador}</td>
            <td className="p-2 tabular-nums">{fila.cajas ?? (fila.cantidad_origen ? `${fila.cantidad_origen} sin clasificar` : 'Sin cantidad')}{fila.bolsas > 0 && <span className="block text-xs theme-text-muted">{fila.bolsas} bolsas</span>}</td>
            <td className="p-2 max-w-52"><span>{fila.motivo_comercial || 'Sin información suficiente'}</span><span className="block text-xs theme-text-muted">{[fila.logistica?.modalidad_envio, fila.logistica?.paqueteria].filter(Boolean).join(' · ')}</span></td>
            <td className="p-2"><span className={`inline-flex rounded-lg border px-2 py-1 text-xs ${INSIGNIA[estado.tono]}`}>{estado.texto}</span></td>
            <td className="p-2 text-right whitespace-nowrap tabular-nums">{fila.importe_comisionable != null ? dinero(fila.importe_comisionable) : fila.liquidacion === 'pagado_fuera' ? dinero(fila.importe) : '—'}</td>
            <td className="p-2"><button type="button" className={BOTON_FILTRO} aria-haspopup="dialog" aria-expanded={abierta} onClick={() => setAbierta(true)}>
                {estado.tono === 'aviso' && puede.clasificar ? 'Revisar' : 'Ver detalle'}
            </button></td>
        </tr>
        {abierta && <RevisionEmpaque fila={fila} puede={puede} tipos={tipos} motivos={motivos} onCerrar={() => setAbierta(false)} />}
    </>;
}

function RevisionEmpaque({ fila, puede, tipos, motivos, onCerrar }) {
    const [verPedido, setVerPedido] = useState(false);
    const [cambios, setCambios] = useState(false);
    const editable = puede.clasificar && !fila.corte && fila.liquidacion !== 'pagado_fuera';
    const conciliar = editable && !['descartar', 'excluir'].includes(fila.ancla_decision)
        && ['pendiente_conciliacion', 'pendiente_clasificacion', 'pendiente_tarifa', 'elegible', 'excluida'].includes(fila.situacion);
    return <SolicitudDialog title="Revisión del empaque" onClose={onCerrar} dirty={cambios}>
        <div className={`${THEME_MODAL_SHELL} max-w-3xl`}>
            <header className="flex items-start justify-between gap-3 border-b theme-border p-4">
                <div><h2 className="text-lg font-bold m-0">Revisión del empaque</h2><p className="text-sm theme-text-muted m-0">{fila.cliente}</p></div>
                <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg border theme-border theme-element" aria-label="Cerrar detalle" data-dialog-close><X className="size-4" aria-hidden="true" /></button>
            </header>
            <div className="gelia-modal-body space-y-4 p-4">
                <section className="space-y-2">
                    <h3 className="m-0 text-sm font-bold">Pedido y empaque</h3>
                    <p className="text-sm m-0">{[fila.folio_pedido, fila.area, fila.empacado_at, fila.empacador].filter(Boolean).join(' · ')}</p>
                    <p className="text-sm m-0">Remisiones {fila.remisiones?.join(', ') || fila.folio_remision || 'sin folio registrado'}</p>
                    <p className="text-sm theme-text-muted m-0">{fila.tipos?.join(', ') || 'Sin desglose físico'} · {logisticaTexto(fila.logistica)}</p>
                    {fila.logistica?.estado_envio && <p className="text-sm m-0">{fila.logistica.estado_envio}</p>}
                    {fila.pedido_bma_id && <button type="button" className={BOTON_FILTRO} aria-expanded={verPedido} onClick={() => setVerPedido(!verPedido)}><Eye className="size-4" aria-hidden="true" />{verPedido ? 'Ocultar pedido' : 'Ver pedido y documentos'}</button>}
                    {verPedido && <PedidoInformativo unidadId={fila.id} />}
                </section>
                <section className="border-t theme-border pt-3">
                    <h3 className="m-0 text-sm font-bold">Comisión</h3>
                    <p className="m-0 text-sm">{fila.situacion_etiqueta}{fila.tarifa && ` · ${dinero(fila.tarifa)} por caja`}{fila.importe_comisionable && ` · ${dinero(fila.importe_comisionable)}`}{fila.corte && ` · Corte ${fila.corte}`}</p>
                    {fila.excepcion_resguardo && <p className="text-sm theme-text-aviso m-0">Empaque relacionado con la excepción de resguardo.</p>}
                    {fila.diferencia && <p className="text-sm theme-text-aviso m-0">{fila.diferencia}</p>}
                </section>
                {editable && <Clasificar fila={fila} tipos={tipos} />}
                <section className="space-y-2 border-t theme-border pt-3">
                    <h3 className="m-0 text-sm font-bold">Conciliación comercial</h3>
                    <p className="m-0 text-sm">{fila.motivo_comercial}</p>
                    {conciliar && Object.keys(motivos).length > 0 && <Conciliar key={`${fila.huella}-${fila.fuente}`} fila={fila} motivos={motivos} onCambios={setCambios} />}
                </section>
                {(fila.conciliacion || fila.conciliacion_previa || fila.incidencias?.length > 0) && <section className="space-y-1 border-t theme-border pt-3">
                    <h3 className="m-0 text-sm font-bold">Historial de revisión</h3>
                    {[fila.conciliacion, fila.conciliacion_previa].filter(Boolean).map((decision) => <p key={decision.fecha} className="text-sm m-0">
                        {decision.responsable} · {momentoLegible(decision.fecha.replace('T', ' ').slice(0, 16))} · {motivos[decision.motivo_comercial]} · {decision.observaciones}{decision.referencia && ` · ${decision.referencia}`}
                    </p>)}
                    {fila.incidencias?.map((i) => <p key={i.id} className="text-sm m-0">{i.fecha}{i.responsable && ` · ${i.responsable}`} · {i.estado === 'resuelta' ? 'Resuelta' : 'Pendiente'} · {i.motivo}</p>)}
                </section>}
            </div>
        </div>
    </SolicitudDialog>;
}

function Conciliar({ fila, motivos, onCambios }) {
    const form = useForm({ decision: '', motivo_comercial: fila.motivo_comercial_codigo || '', observaciones: '', referencia: '', huella: fila.huella, fuente: fila.fuente });
    return <form className="grid gap-3 sm:grid-cols-2" onChange={() => onCambios(true)} onSubmit={(e) => {
        e.preventDefault();
        form.post(route('gestion_interna.comisiones_empaque.conciliar', fila.id), { preserveScroll: true, onSuccess: () => { form.reset(); onCambios(false); } });
    }}>
        <label><span className={THEME_LABEL}>Decisión</span><select className={THEME_INPUT} required value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}>
            <option value="">Elegir decisión</option><option value="validar">Validar empaque</option><option value="excluir">Excluir de comisión</option><option value="pendiente">Mantener pendiente</option>
        </select></label>
        <label><span className={THEME_LABEL}>Motivo comercial</span><select className={THEME_INPUT} required value={form.data.motivo_comercial} onChange={(e) => form.setData('motivo_comercial', e.target.value)}>
            <option value="">Elegir motivo</option>{Object.entries(motivos).map(([id, motivo]) => <option key={id} value={id}>{motivo}</option>)}
        </select></label>
        <label className="sm:col-span-2"><span className={THEME_LABEL}>Observaciones</span><textarea className={THEME_INPUT} required maxLength={2000} value={form.data.observaciones} onChange={(e) => form.setData('observaciones', e.target.value)} /></label>
        <label className="sm:col-span-2"><span className={THEME_LABEL}>Evidencia o referencia (opcional)</span><input className={THEME_INPUT} maxLength={500} value={form.data.referencia} onChange={(e) => form.setData('referencia', e.target.value)} /></label>
        {form.data.decision === 'excluir' && <p className="m-0 text-sm theme-text-muted sm:col-span-2">Este empaque quedará fuera del fondo y seguirá visible en el historial.</p>}
        <ErroresFormulario form={form} />
        <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar conciliación</button>
    </form>;
}

function textoCajas(cantidad) {
    const numero = Number(cantidad) || 0;
    return `${numero} ${numero === 1 ? 'caja' : 'cajas'}`;
}

function PedidoInformativo({ unidadId }) {
    const [estado, setEstado] = useState('cargando');
    const [pedido, setPedido] = useState(null);
    useEffect(() => {
        let activo = true;
        axios.get(route('gestion_interna.comisiones_empaque.pedido.informativo', unidadId))
            .then((respuesta) => { if (activo) { setPedido(respuesta.data); setEstado('listo'); } })
            .catch(() => { if (activo) setEstado('error'); });
        return () => { activo = false; };
    }, [unidadId]);
    if (estado === 'cargando') return <p className="text-sm theme-text-muted m-0">Consultando el pedido…</p>;
    if (estado === 'error') return <p className="text-sm theme-text-aviso m-0" role="alert">No se pudo consultar el pedido.</p>;
    if (!pedido?.vinculado) return <p className="text-sm theme-text-muted m-0">{pedido?.mensaje || 'Este empaque no está vinculado a un pedido de Gelia.'}</p>;
    const datos = [
        ['Pedido', pedido.folio_pedido || 'Sin folio'],
        ['Remisión', pedido.folio_remision || 'Sin remisión'],
        ['Cliente', pedido.cliente || 'Sin cliente'],
        ['Estatus', pedido.estatus || 'Sin estatus'],
        ['Paquetería', pedido.paqueteria || 'Sin paquetería'],
        ['Cajas declaradas', textoCajas(pedido.cajas_declaradas)],
        ['Bultos', pedido.bultos ?? 0],
        ['Empacado', pedido.empacado_at || 'Sin fecha'],
    ];
    return <section className={geliaCardClass('space-y-3 p-4')} aria-label="Pedido de origen">
        <div>
            <h3 className="text-base font-bold m-0">Pedido de origen</h3>

        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {datos.map(([nombre, valor]) => <div key={nombre} className="rounded-xl border theme-border theme-element p-3">
                <span className="block text-xs theme-text-muted">{nombre}</span>
                <span className="mt-1 block text-sm font-semibold theme-text-main">{valor}</span>
            </div>)}
        </div>
        <div>
            <h4 className="text-sm font-bold m-0">Cajas registradas en el pedido</h4>
            {!pedido.cajas?.length && <p className="text-sm theme-text-muted m-0 mt-1">El pedido no tiene un desglose de cajas.</p>}
            {pedido.documentos?.length > 0 && <ul className="m-0 pl-5 text-sm" aria-label="Documentos del pedido">
                {pedido.documentos.map((doc) => <li key={doc.id}><a className="inline-flex min-h-11 items-center theme-text-primario underline" href={doc.url} target="_blank" rel="noreferrer">Abrir {doc.nombre}</a></li>)}
            </ul>}
            {!!pedido.cajas?.length && <ul className="list-none m-0 mt-2 grid gap-2 p-0 sm:grid-cols-2">{pedido.cajas.map((caja, indice) => <li key={`${caja.tipo}-${indice}`} className="rounded-lg border theme-border px-3 py-2 text-sm">{textoCajas(caja.cantidad)} · {caja.tipo}</li>)}</ul>}
        </div>
    </section>;
}

function Clasificar({ fila, tipos }) {
    const form = useForm({
        lineas: fila.lineas?.length ? fila.lineas : [{ cantidad: fila.cantidad_origen || 1, es_bolsa: false, catalogo_tipo_caja_id: '' }],
        decision: 'conservar',
        motivo: '',
        revision: fila.posible_duplicado ? 'trabajo_distinto' : (fila.referencia === 'insuficiente' ? 'aceptar_sin_referencia' : fila.cantidad_origen === 0 ? 'descartar' : 'aceptar'),
    });
    const suma = form.data.lineas.reduce((n, l) => n + Number(l.cantidad || 0), 0);

    return (
        <div className="space-y-3">
            <ErroresFormulario form={form} />
            {(fila.situacion === 'pendiente_revision' || fila.situacion === 'posible_duplicado') && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.post(route('gestion_interna.comisiones_empaque.revisar', fila.id)); }}>
                    {fila.posible_duplicado && (
                        <div className="w-full">
                            <p className="theme-text-aviso m-0">Hay una coincidencia. La aceptación normal queda bloqueada hasta reconocer un trabajo distinto, con motivo, o rechazar el registro. Un conflicto es el mismo día. Un antecedente es otra fecha y no demuestra por sí solo que sea otro trabajo.</p>
                            <ul className="m-0 pl-4">
                                {(fila.relacionados || []).map((rel) => (
                                    <li key={rel.id}>{rel.criterio === 'antecedente' ? 'Antecedente' : 'Conflicto'} · {rel.empacado_at || 'Sin fecha'} · {rel.folio_pedido || rel.clave_origen} · {rel.folio_remision || 'Sin remisión'} · {rel.cantidad} · {rel.situacion}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {fila.referencia === 'insuficiente' && !fila.posible_duplicado && (
                        <p className="theme-text-aviso m-0 w-full">Falta una referencia verificable. Vincule un pedido o indique folio o remisión. Si no existe, la persona revisora puede aceptar explícitamente esa falta.</p>
                    )}
                    <label><span className={THEME_LABEL}>Revisión del registro</span>
                        <select className={THEME_INPUT} value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)}>
                            {!fila.posible_duplicado && fila.referencia !== 'insuficiente' && <option value="aceptar">Aceptar</option>}
                            {!fila.posible_duplicado && fila.referencia === 'insuficiente' && <option value="aceptar_sin_referencia">Aceptar sin referencia verificable</option>}
                            {fila.posible_duplicado && <option value="trabajo_distinto">Reconocer trabajo distinto</option>}
                            <option value="rechazar">Rechazar</option>
                        </select>
                    </label>
                    <label className="flex-1"><span className={THEME_LABEL}>Motivo</span>
                        <input className={THEME_INPUT} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    </label>
                    <button className={THEME_BTN_PRIMARY} type="button" disabled={form.processing} onClick={() => { form.transform((data) => ({ decision: data.revision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.revisar', fila.id)); }}>Guardar revisión</button>
                </form>
            )}
            {fila.situacion === 'pendiente_clasificacion' && fila.cantidad_origen > 0 && (
                <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ lineas: data.lineas.map((l) => ({ ...l, catalogo_tipo_caja_id: l.catalogo_tipo_caja_id || null, es_bolsa: !!l.es_bolsa })) })); form.post(route('gestion_interna.comisiones_empaque.clasificar', fila.id)); }}>
                    <p className="m-0 text-sm">El desglose debe sumar {fila.cantidad_origen} piezas. Ahora suma {suma}.</p>

                    {form.data.lineas.map((linea, i) => (
                        <div key={i} className="flex flex-wrap items-end gap-3">
                            <label className="w-28"><span className={THEME_LABEL}>Cajas</span><input className={THEME_INPUT} type="number" min="1" aria-label="Cantidad de cajas" value={linea.cantidad} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, cantidad: e.target.value };
                                form.setData('lineas', lineas);
                            }} /></label>
                            <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={!!linea.es_bolsa} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, es_bolsa: e.target.checked, catalogo_tipo_caja_id: e.target.checked ? '' : linea.catalogo_tipo_caja_id };
                                form.setData('lineas', lineas);
                            }} /> Bolsa excluida, no aporta al fondo</label>
                            {!linea.es_bolsa && (
                                <select className={THEME_INPUT} aria-label="Tipo de caja" value={linea.catalogo_tipo_caja_id || ''} onChange={(e) => {
                                    const lineas = [...form.data.lineas];
                                    lineas[i] = { ...linea, catalogo_tipo_caja_id: e.target.value };
                                    form.setData('lineas', lineas);
                                }}>
                                    <option value="">Caja sin tipo</option>
                                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                            )}
                        </div>
                    ))}
                        <div className="flex flex-wrap gap-2">
                        <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main" onClick={() => form.setData('lineas', [...form.data.lineas, { cantidad: 1, es_bolsa: false, catalogo_tipo_caja_id: '' }])}><Plus className="size-4" aria-hidden="true" />Agregar tipo de caja</button>
                        <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar desglose</button>
                    </div>
                </form>
            )}
            {fila.situacion === 'pendiente_decision' && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.decision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.decision', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Decisión de cancelación" value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}>
                        <option value="conservar">Conservar comisión</option>
                        <option value="excluir">Excluir comisión</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la decisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Registrar decisión'}</button>
                </form>
            )}
            {['reempaque_revision', 'solape_revision', 'revertida_revision'].includes(fila.situacion) && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.revision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.resolver', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Resolución" value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)}>
                        {fila.cantidad_origen > 0 && <option value="aceptar">Aceptar para revisión comercial</option>}
                        <option value="descartar">Descartar</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la revisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Resolver</button>
                </form>
            )}
        </div>
    );
}

