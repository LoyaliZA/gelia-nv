import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import axios from 'axios';
import { Archive, Truck, CheckCircle2, RefreshCw, MapPin, Save, MessageSquare, CalendarClock, ArrowRightLeft, Send, Camera, ClipboardCheck, PackageCheck, HandCoins } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import ModalMovimientosTienda from './ModalMovimientosTienda';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import useActualizacionesPedidos from './useActualizacionesPedidos';

export function esTransferenciaT02(pedido) {
    const solicitud = pedido?.solicitud_preparacion || pedido?.solicitud_preparacion_json || {};
    return Boolean(pedido?.es_t02_documental || (solicitud.modalidad_entrega === 'tienda'
        && solicitud.cobertura_tienda?.tipo === 'transferencia'
        && (solicitud.atencion_tienda || 'no') === 'no' && !pedido?.prioridad_md));
}

export function esTransferenciaDocumental(pedido) {
    const solicitud = pedido?.solicitud_preparacion || pedido?.solicitud_preparacion_json || {};
    return Boolean(pedido?.es_custodia_documental || pedido?.es_t02_documental || (solicitud.modalidad_entrega === 'tienda' && ['transferencia', 'credito'].includes(solicitud.cobertura_tienda?.tipo)));
}
const fechaHoraMexico = valor => valor ? new Intl.DateTimeFormat('es-MX', { timeZone: 'America/Mexico_City', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(valor)) : 'Pendiente';

const ESTADOS = { pendiente_salida_sucursal: 'Pendiente de salida de sucursal', pendiente_preparacion: 'Pendiente de preparación', preparada: 'Bolsas preparadas', en_traslado: 'Pendiente de recepción', recibida: 'Bolsas recibidas', en_custodia: 'En custodia', entregada: 'Pedido entregado' };
const ACCIONES = { preparar: 'Preparación', salida: 'Salida o entrega interna', recibir: 'Recepción verificada', custodia: 'Custodia identificada', entregar: 'Entrega al cliente' };
const mensajeError = (error) => error?.response?.status === 419
    ? 'La sesión cambió o venció. Recargue GELIA antes de continuar; no se confirmó la operación.'
    : Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || 'No se pudo actualizar la transferencia.';

/** Una vista por pedido; las facultades y etapas se calculan en el servidor. */
export default function SeguimientoTransferenciaTienda({ pedido, sucursales = [], soloLectura = false, onActualizar, onDirtyChange }) {
    const activo = esTransferenciaDocumental(pedido);
    const [herramienta, setHerramienta] = useState('');
    const [movimiento, setMovimiento] = useState(null);
    const [datos, setDatos] = useState(null);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [vista, setVista] = useState(null);
    const [observaciones, setObservaciones] = useState('');
    const [verificadas, setVerificadas] = useState(false);
    const [ubicacion, setUbicacion] = useState('');
    const [almacen, setAlmacen] = useState('');
    const [receptor, setReceptor] = useState('');
    const [sucursal, setSucursal] = useState('');
    const [acuerdo, setAcuerdo] = useState('');
    const [continuar, setContinuar] = useState(false);
    const id = useId();
    const urls = useRef([]);
    useEffect(() => { urls.current = previews; }, [previews]);
    useEffect(() => () => urls.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const cargar = useCallback(async () => {
        if (!activo || !pedido?.id) return;
        try { const respuesta = await axios.get(`/control-pedidos/${pedido.id}/transferencia-tienda`); setDatos(respuesta.data); setError(''); }
        catch (fallo) { setError(mensajeError(fallo)); }
    }, [activo, pedido?.id]);
    useEffect(() => { cargar(); }, [cargar, pedido?.pago_validado_at, pedido?.numero_remision, pedido?.catalogo_estatus_pedido_id]);
    const capturaLocal = archivos.length > 0 || Boolean(observaciones || ubicacion || receptor || acuerdo) || ocupado;
    useActualizacionesPedidos({ activo: activo && Boolean(pedido?.id), pedidoId: pedido?.id, pausado: capturaLocal, onActualizar: () => cargar() });
    useEffect(() => { onDirtyChange?.(archivos.length > 0 || Boolean(observaciones || ubicacion || receptor || acuerdo)); }, [archivos, observaciones, ubicacion, receptor, acuerdo, onDirtyChange]);
    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);
    if (!activo) return null;

    const enviar = async (accion) => {
        if (ocupado || soloLectura) return;
        setError('');
        if ((accion === 'preparar' || accion === 'entregar') && archivos.length < 1) { setError('Adjunte la fotografía correspondiente antes de confirmar.'); return; }
        if (accion === 'recibir' && !verificadas) { setError('Confirme que verificó todas las bolsas y sus observaciones.'); return; }
        if (accion === 'custodia' && (!almacen || !ubicacion.trim())) { setError('Indique el almacén y la ubicación de custodia.'); return; }
        if (accion === 'entregar' && !receptor.trim()) { setError('Indique quién recibe el pedido.'); return; }
        if (accion === 'continuar_posterior' && !acuerdo.trim()) { setError('Describa el acuerdo con el cliente para recoger posteriormente.'); return; }
        const form = new FormData();
        form.append('version', datos.version);
        form.append('observaciones', accion === 'continuar_posterior' ? acuerdo : observaciones);
        if (accion === 'preparar') archivos.forEach((archivo, indice) => form.append(`bultos[${indice}][foto_bulto]`, archivo));
        if (accion === 'recibir') form.append('bolsas_verificadas', verificadas ? '1' : '0');
        if (accion === 'custodia') { form.append('almacen_id', almacen); form.append('ubicacion', ubicacion); }
        if (accion === 'entregar') { form.append('receptor', receptor); form.append('foto', archivos[0]); }
        setOcupado(true);
        try {
            const respuesta = await axios.post(`/control-pedidos/${pedido.id}/transferencia-tienda/${accion}`, form, { headers: { Accept: 'application/json' } });
            setDatos(respuesta.data); previews.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); });
            setArchivos([]); setPreviews([]); setObservaciones(''); setUbicacion(''); setReceptor(''); setAcuerdo(''); setContinuar(false); setHerramienta(''); onActualizar?.(respuesta.data);
        } catch (fallo) { setError(mensajeError(fallo)); } finally { setOcupado(false); }
    };
    const guardarDestino = async () => {
        if (!sucursal || ocupado) return;
        setOcupado(true); setError('');
        try { await axios.post(`/control-pedidos/${pedido.id}/sucursal-destino`, { sucursal_destino_id: Number(sucursal) }); await cargar(); onActualizar?.(); }
        catch (fallo) { setError(mensajeError(fallo)); } finally { setOcupado(false); }
    };
    const estado = datos?.estado;
    const md = datos?.atencion_md;
    const credito = datos?.cobertura === 'credito';
    const cedis = (datos?.solicitud_preparacion?.area_revision || pedido?.solicitud_preparacion?.area_revision) === 'control_pedidos';
    const resultadoCredito = datos?.credito_wizerp?.resultado;
    const creditoCedisPendiente = Boolean(credito && cedis && resultadoCredito !== 'aprobado');
    const enDestino = ['en_traslado', 'recibida', 'en_custodia', 'entregada'].includes(estado);
    const requiereFoto = datos?.puede_preparar || datos?.puede_entregar;
    const tono = datos?.pendiente_gestion ? 'var(--color-aviso)' : ['en_custodia', 'entregada'].includes(estado) ? 'var(--color-exito)' : 'var(--color-primario)';
    const siguiente = datos?.bloqueo || !datos?.consulta_cerrada ? 'Resolver la consulta de disponibilidad'
        : creditoCedisPendiente && resultadoCredito === 'vencido' ? (datos.credito_wizerp?.causa || 'El cliente tiene un crédito vencido y necesita liquidarlo.')
        : creditoCedisPendiente ? 'Pendiente de auditoría. Quien audita confirma el crédito y la remisión; después el pedido continúa en CEDIS.'
        : credito && resultadoCredito !== 'aprobado' ? 'El auxiliar debe registrar la aprobación del crédito en Wizerp'
        : !datos?.pago_validado ? (credito ? 'El auxiliar debe validar la cobertura de crédito' : 'El auxiliar debe verificar el ingreso')
        : !datos?.numero_remision || !datos?.remision_adjunta ? 'El auxiliar debe registrar la remisión'
        : ESTADOS[estado] || 'Consultar seguimiento';
    return <section className="registrar-tienda-panel space-y-4 min-w-0 tienda-transferencia" aria-label="Transferencia y custodia en tienda">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="registrar-tienda-seccion-titulo"><Archive size={20} aria-hidden="true" /><h3>{credito && cedis ? 'Crédito · CEDIS' : credito ? 'Crédito · Recoger en tienda' : md ? 'Tienda · Recolección el mismo día' : 'Transferencia · Recoger posteriormente'}</h3></div>
            <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={cargar}><RefreshCw size={16} />Actualizar seguimiento</button>
        </div>
        {error && <div role="alert" className="tienda-alerta-disponibilidad rounded-xl border p-3 text-sm">{error}</div>}
        {!datos && !error && <p role="status" className="text-sm theme-text-muted m-0">Cargando seguimiento…</p>}
        {datos && <>
            <div className="rounded-xl border theme-border theme-element p-4 grid gap-2" role="status">
                <span className="text-xs theme-text-muted">{creditoCedisPendiente ? (resultadoCredito === 'vencido' ? 'Devuelto por crédito vencido' : 'Pendiente de auditoría') : datos.pendiente_gestion ? 'Pendiente de gestión' : ESTADOS[estado]}</span>
                <strong className="text-base theme-text-main">{siguiente}</strong>
                <span className="text-sm theme-text-main"><MapPin size={16} className="inline mr-1" />{datos.sucursal || 'Sucursal de destino pendiente'}{md?.fecha_objetivo ? ` · ${md.fecha_objetivo}` : ''}</span>
            </div>
            {md && !enDestino && <AvisoOperativoPedido label={md.pendiente_acuerdo ? 'Mismo día · Pendiente de acuerdo' : 'Solicitud para el mismo día'} tono={md.pendiente_acuerdo ? 'warning' : 'info'} icon={CalendarClock}>{md.motivo}</AvisoOperativoPedido>}
            {datos.bloqueo && <div role="alert" className="tienda-alerta-disponibilidad border rounded-xl p-4 text-sm"><strong>Revisión de disponibilidad pendiente</strong><p className="m-0 mt-1">{datos.bloqueo}</p></div>}
            {datos.bloqueo_destino && <AvisoOperativoPedido tono="warning" label="Custodia en destino" icon={MapPin}>{datos.bloqueo_destino}</AvisoOperativoPedido>}
            <details className="border theme-border rounded-xl p-3">
                <summary className="text-sm font-semibold theme-text-main cursor-pointer">Datos y documentos del recorrido</summary>
                <div className="grid sm:grid-cols-2 gap-3 mt-3 text-sm theme-text-main">
                    <p className="m-0"><span className="theme-text-muted">Procedencia</span><br /><strong>{datos.origen || 'Pendiente de definir'}</strong></p>
                    <p className="m-0"><span className="theme-text-muted">{credito ? 'Crédito Wizerp' : 'Transferencia'}</span><br /><strong>{credito ? (datos.credito_wizerp?.resultado || 'Pendiente de consultar Wizerp') : datos.pago_validado ? 'Ingreso verificado por auxiliar' : 'Pendiente de verificar ingreso'}</strong></p>
                    <p className="m-0"><span className="theme-text-muted">Remisión Wizerp</span><br /><strong>{datos.numero_remision || 'Pendiente'}{datos.remision_adjunta ? ' · Adjunta' : ' · Sin documento'}</strong></p>
                    {md && <p className="m-0"><span className="theme-text-muted">Primer reporte aceptado</span><br /><strong>{fechaHoraMexico(md.primer_pago_at)}</strong></p>}
                </div>
                {datos.limite_recoleccion && <p className="text-sm mt-3 mb-0 theme-text-muted">Seguimiento hasta {formatearFechaNegocio(datos.limite_recoleccion)} · {datos.plazo_recoleccion_dias} días desde remisión.</p>}
                {datos.observaciones && <p className="text-sm theme-text-main mt-3 mb-0">{datos.observaciones}</p>}
            </details>
            {datos.pendiente_gestion && <p className="text-sm m-0 theme-text-aviso">Contacte al cliente y registre el seguimiento. El vencimiento no cancela la venta ni libera la mercancía pagada.</p>}
            {datos.ubicacion && <p className="text-sm theme-text-main m-0"><MapPin size={16} className="inline mr-1" /><strong>Custodia:</strong> {datos.ubicacion}</p>}
            {enDestino && md && <p className="text-sm theme-text-muted m-0">Mercancía pagada en custodia hasta su entrega.</p>}
            {datos.bultos?.length > 0 && <div className="flex flex-wrap gap-2">{datos.bultos.map(b => <button key={b.id} type="button" className={GELIA_BTN_OUTLINE} onClick={() => setVista({ documentos: b.documentos || [], indice: 0 })}><Camera size={16} />Bolsa {b.numero} · Ver evidencia</button>)}</div>}
            {!soloLectura && <fieldset disabled={ocupado} className="grid gap-4 min-w-0">
                {requiereFoto && <GaleriaEvidenciasPedido id={`${id}-evidencia`} label={datos.puede_preparar ? 'Fotografía de cada bolsa · Una imagen por bolsa' : 'Fotografía de entrega'} obligatorio soloImagenes maxArchivos={datos.puede_preparar ? 50 : 1} archivos={archivos} previews={previews} onChange={(a,p) => { setArchivos(a); setPreviews(p); }} onVer={(documentos, indice) => setVista({ documentos, indice })} disabled={ocupado} />}
                {(datos.puede_preparar || datos.puede_recibir) && <label className={THEME_LABEL}>Observaciones<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} value={observaciones} onChange={e => setObservaciones(e.target.value)} /></label>}
                {datos.puede_preparar && <button type="button" className={THEME_BTN_PRIMARY} onClick={() => enviar('preparar')}><PackageCheck size={18} />Confirmar bolsas preparadas</button>}
                {datos.puede_salida && <button type="button" className={THEME_BTN_PRIMARY} onClick={() => enviar('salida')}><Truck size={18} />{datos.requiere_traslado ? 'Confirmar salida a sucursal' : 'Entregar bolsas a recepción de la misma sucursal'}</button>}
                {datos.puede_recibir && <><label className="flex items-start gap-2 text-sm theme-text-main"><input type="checkbox" className="gelia-checkbox mt-0.5" checked={verificadas} onChange={e => setVerificadas(e.target.checked)} />Verifiqué todas las bolsas identificadas y sus observaciones.</label><button type="button" className={THEME_BTN_PRIMARY} onClick={() => enviar('recibir')}><ClipboardCheck size={18} />Confirmar recepción de bolsas</button></>}
                {datos.puede_custodia && <><label className={THEME_LABEL}>Almacén de custodia<select className={`${THEME_SELECT} w-full mt-1`} value={almacen} onChange={e => setAlmacen(e.target.value)}><option value="">Elegir almacén</option>{datos.almacenes_destino.map(a => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label><label className={THEME_LABEL}>Ubicación e identificación del pedido<input className={`${THEME_INPUT} w-full mt-1`} maxLength={240} value={ubicacion} onChange={e => setUbicacion(e.target.value)} /></label><button type="button" className={THEME_BTN_PRIMARY} onClick={() => enviar('custodia')}><Archive size={18} />Confirmar custodia identificada</button></>}
                {datos.puede_entregar && <><label className={THEME_LABEL}>Quién recibe<input className={`${THEME_INPUT} w-full mt-1`} maxLength={160} value={receptor} onChange={e => setReceptor(e.target.value)} /></label><button type="button" className={THEME_BTN_PRIMARY} onClick={() => enviar('entregar')}><CheckCircle2 size={18} />Confirmar entrega al cliente</button></>}
            </fieldset>}
            {!soloLectura && !creditoCedisPendiente && <div className="flex flex-wrap gap-2 border-t theme-border pt-3">
                {sucursales.length > 0 && ['pendiente_preparacion','preparada'].includes(estado) && <button type="button" className={GELIA_BTN_OUTLINE} aria-expanded={herramienta === 'destino'} disabled={ocupado} onClick={() => setHerramienta(herramienta === 'destino' ? '' : 'destino')}><MapPin size={16} />Cambiar destino</button>}
                {datos.puede_gestionar && <button type="button" className={GELIA_BTN_OUTLINE} aria-expanded={herramienta === 'seguimiento'} disabled={ocupado} onClick={() => setHerramienta(herramienta === 'seguimiento' ? '' : 'seguimiento')}><MessageSquare size={16} />Seguimiento con el cliente</button>}
                {datos.puede_continuar_posterior && <button type="button" className={GELIA_BTN_OUTLINE} aria-expanded={continuar} disabled={ocupado} onClick={() => { setContinuar(!continuar); setHerramienta(''); }}><CalendarClock size={16} />Acordar recolección posterior</button>}
                {['en_custodia','pendiente_salida_sucursal'].includes(estado) && <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => setMovimiento('traslado')}><ArrowRightLeft size={16} />Traslado entre sucursales</button>}
                {estado === 'en_custodia' && <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => setMovimiento('modalidad')}><Truck size={16} />Cambiar modalidad</button>}
            </div>}
            {!soloLectura && herramienta === 'destino' && <div className="grid gap-3"><label className={THEME_LABEL}>Sucursal de destino<select className={`${THEME_SELECT} w-full mt-1`} value={sucursal || String(datos.sucursal_destino_id || '')} onChange={e => setSucursal(e.target.value)} disabled={ocupado}><option value="">Elegir sucursal</option>{sucursales.map(a => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label><button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={ocupado || !sucursal} onClick={guardarDestino}><Save size={16} />Guardar destino</button></div>}
            {!soloLectura && herramienta === 'seguimiento' && <div className="grid gap-3"><label className={THEME_LABEL}>Seguimiento con el cliente<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} value={observaciones} onChange={e => setObservaciones(e.target.value)} /></label><button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={ocupado || !observaciones.trim()} onClick={() => enviar('gestionar')}><Send size={16} />Registrar seguimiento</button></div>}
            {!soloLectura && continuar && <div className="border theme-border rounded-xl p-3 grid gap-3"><label className={THEME_LABEL}>Acuerdo con el cliente<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} value={acuerdo} onChange={e => setAcuerdo(e.target.value)} /></label><p className="text-sm theme-text-muted m-0">Se conservan los pagos, la remisión y la hora original del reporte.</p><button type="button" className={THEME_BTN_PRIMARY} disabled={ocupado} onClick={() => enviar('continuar_posterior')}><CheckCircle2 size={18} />Confirmar recolección y resguardo en tienda</button></div>}
            {(Object.keys(datos.historial || {}).length > 0 || datos.gestiones?.length > 0) && <details><summary className="text-sm font-semibold theme-text-main cursor-pointer">Historial y seguimiento</summary><ul className="pl-4 text-sm theme-text-muted space-y-1">{Object.entries(datos.historial || {}).map(([a,e]) => <li key={a}>{ACCIONES[a] || 'Seguimiento'} · {e.por} · {formatearFechaNegocio(e.fecha)}</li>)}{(datos.gestiones || []).map((g,i) => <li key={`gestion-${i}`}>{g.nota} · {g.por} · {formatearFechaNegocio(g.fecha)}</li>)}</ul></details>}
        </>}
        <ModalMovimientosTienda abierto={Boolean(movimiento)} pedido={pedido} modo={movimiento || 'traslado'} onClose={() => setMovimiento(null)} onActualizar={() => { cargar(); onActualizar?.(); }} />
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} documentos={vista?.documentos || []} indice={vista?.indice || 0} onClose={() => setVista(null)} />
    </section>;
}
