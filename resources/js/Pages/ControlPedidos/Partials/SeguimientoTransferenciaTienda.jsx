import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import axios from 'axios';
import { Archive, Truck } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from './pedidosBmaStyles';

export function esTransferenciaT02(pedido) {
    const solicitud = pedido?.solicitud_preparacion || pedido?.solicitud_preparacion_json || {};
    return Boolean(pedido?.es_t02_documental || (solicitud.modalidad_entrega === 'tienda'
        && solicitud.cobertura_tienda?.tipo === 'transferencia'
        && (solicitud.atencion_tienda || 'no') === 'no' && !pedido?.prioridad_md));
}

export function esTransferenciaDocumental(pedido) {
    const solicitud = pedido?.solicitud_preparacion || pedido?.solicitud_preparacion_json || {};
    return Boolean(pedido?.es_t02_documental || (solicitud.modalidad_entrega === 'tienda' && solicitud.cobertura_tienda?.tipo === 'transferencia'));
}
const fechaHoraMexico = valor => valor ? new Intl.DateTimeFormat('es-MX', { timeZone: 'America/Mexico_City', dateStyle: 'short', timeStyle: 'medium' }).format(new Date(valor)) : 'Pendiente';

const ESTADOS = { pendiente_preparacion: 'Pendiente de preparación', preparada: 'Bolsas preparadas', en_traslado: 'Pendiente de recepción', recibida: 'Bolsas recibidas', en_custodia: 'En custodia', entregada: 'Pedido entregado' };
const ACCIONES = { preparar: 'Preparación', salida: 'Salida o entrega interna', recibir: 'Recepción verificada', custodia: 'Custodia identificada', entregar: 'Entrega al cliente' };
const mensajeError = (error) => error?.response?.status === 419
    ? 'La sesión cambió o venció. Recargue GELIA antes de continuar; no se confirmó la operación.'
    : Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || 'No se pudo actualizar la transferencia.';

/** Una vista por pedido; las facultades y etapas se calculan en el servidor. */
export default function SeguimientoTransferenciaTienda({ pedido, sucursales = [], soloLectura = false, onActualizar, onDirtyChange }) {
    const activo = esTransferenciaDocumental(pedido);
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
    useEffect(() => () => urls.current.forEach(url => { if (typeof url === 'string' && url.startsWith('blob:')) URL.revokeObjectURL(url); }), []);
    const cargar = useCallback(async () => {
        if (!activo || !pedido?.id) return;
        try { const respuesta = await axios.get(`/control-pedidos/${pedido.id}/transferencia-tienda`); setDatos(respuesta.data); setError(''); }
        catch (fallo) { setError(mensajeError(fallo)); }
    }, [activo, pedido?.id]);
    useEffect(() => { cargar(); }, [cargar, pedido?.pago_validado_at, pedido?.numero_remision, pedido?.catalogo_estatus_pedido_id]);
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
            setDatos(respuesta.data); previews.forEach(url => { if (typeof url === 'string' && url.startsWith('blob:')) URL.revokeObjectURL(url); });
            setArchivos([]); setPreviews([]); setObservaciones(''); setUbicacion(''); setReceptor(''); setAcuerdo(''); setContinuar(false); onActualizar?.(respuesta.data);
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
    const enDestino = ['en_traslado', 'recibida', 'en_custodia', 'entregada'].includes(estado);
    const requiereFoto = datos?.puede_preparar || datos?.puede_entregar;
    const tono = datos?.pendiente_gestion ? 'var(--color-aviso)' : ['en_custodia', 'entregada'].includes(estado) ? 'var(--color-exito)' : 'var(--color-primario)';
    return <section className="registrar-tienda-panel space-y-4 min-w-0" aria-label="Transferencia y custodia en tienda">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="registrar-tienda-seccion-titulo"><Archive size={18} aria-hidden="true" /><h3>{md ? 'TIENDA MD · Transferencia' : 'Transferencia · Recoger posteriormente'}</h3></div>
            {estado && <span className="rounded-full border px-3 py-1 text-xs font-bold" style={{ color: tono, borderColor: tono, backgroundColor: `color-mix(in srgb, ${tono} 12%, transparent)` }}>{datos.pendiente_gestion ? 'Pendiente de gestión' : ESTADOS[estado]}</span>}
        </div>
        {error && <p role="alert" className="text-sm theme-text-peligro m-0">{error}</p>}
        {!datos && !error && <p role="status" className="text-sm theme-text-muted m-0">Cargando seguimiento…</p>}
        {datos && <>
            {md && <div className="gelia-callout-aviso p-3 grid gap-2 text-sm" role="status">
                <strong>{!enDestino && md.pendiente_acuerdo ? 'TIENDA MD · Pendiente de acuerdo' : 'TIENDA MD · Recolección solicitada para el mismo día'}</strong>
                {md.fecha_objetivo && <span>Fecha solicitada de recolección: {md.fecha_objetivo}.</span>}
                <span>Primer reporte aceptado: {fechaHoraMexico(md.primer_pago_at)} · Corte: 13:00, lunes a viernes, hora de México.</span>
                <span>{enDestino ? 'La mercancía pagada conserva su identificación y custodia hasta la entrega. No se libera como apartado impago si el cliente no recoge hoy.' : md.motivo}</span>
            </div>}
            <div className="grid sm:grid-cols-2 gap-3 text-sm">
                <p className="m-0"><span className="theme-text-muted">Procedencia</span><br /><strong>{datos.origen || 'Pendiente de definir'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Sucursal de recolección</span><br /><strong>{datos.sucursal || 'Seleccione el destino'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Transferencia</span><br /><strong>{datos.pago_validado ? 'Ingreso verificado por auxiliar' : 'Pendiente de verificar ingreso'}</strong></p>
                <p className="m-0"><span className="theme-text-muted">Remisión Wizerp</span><br /><strong>{datos.numero_remision || 'Pendiente'}{datos.remision_adjunta ? ' · Adjunta' : ' · Sin documento'}</strong></p>
            </div>
            {!soloLectura && sucursales.length > 0 && ['pendiente_preparacion', 'preparada'].includes(estado) && <div className="grid sm:grid-cols-[1fr_auto] gap-3 items-end">
                <label className={THEME_LABEL} htmlFor={`${id}-sucursal`}>Sucursal de recolección<select id={`${id}-sucursal`} className={`${THEME_SELECT} w-full mt-1`} value={sucursal || String(datos.sucursal_destino_id || '')} onChange={e => setSucursal(e.target.value)} disabled={ocupado}><option value="">Elegir sucursal</option>{sucursales.map(item => <option key={item.id} value={item.id}>{item.nombre}</option>)}</select></label>
                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado || !sucursal} onClick={guardarDestino}>Guardar destino</button>
            </div>}
            {!datos.consulta_cerrada && <p className="text-sm theme-text-muted m-0">El pedido espera la aceptación del cliente y el cierre de consulta de la vendedora.</p>}
            {datos.derivacion && <p role="status" className="text-sm theme-text-aviso m-0">Destino City Center: corresponde al proceso {datos.derivacion}. Continúe por esa ramificación; no se habilita la salida ordinaria T-02.</p>}
            {datos.bloqueo && <p role="status" className="text-sm theme-text-aviso m-0">{datos.bloqueo}</p>}
            {datos.limite_recoleccion && <p className="text-sm m-0 theme-text-main">Seguimiento de recolección hasta {formatearFechaNegocio(datos.limite_recoleccion)} · 20 días naturales desde la emisión de remisión.</p>}
            {datos.pendiente_gestion && <p className="text-sm m-0 theme-text-aviso">Contacte al cliente y registre el seguimiento. El vencimiento no cancela la venta ni libera la mercancía pagada.</p>}
            {datos.ubicacion && <p className="text-sm theme-text-main m-0"><strong>Ubicación de custodia:</strong> {datos.ubicacion}{datos.resguardo_id ? ` · Resguardo ${datos.resguardo_id}` : ''}</p>}
            {datos.observaciones && <p className="text-sm theme-text-main m-0"><strong>Observaciones de preparación:</strong> {datos.observaciones}</p>}
            {datos.bultos?.length > 0 && <div className="space-y-2"><p className="text-sm font-semibold m-0 theme-text-main">Bolsas identificadas · {datos.bultos.length}</p><div className="flex flex-wrap gap-2">{datos.bultos.map(bulto => <button key={bulto.id} type="button" className={THEME_BTN_SECONDARY} onClick={() => setVista({ documentos: bulto.documentos || [], indice: 0 })}>Bolsa {bulto.numero} · Ver evidencia</button>)}</div></div>}
            {!soloLectura && <fieldset disabled={ocupado} className="grid gap-4 min-w-0">
                {requiereFoto && <GaleriaEvidenciasPedido id={`${id}-evidencia`} label={datos.puede_preparar ? 'Fotografía de cada bolsa · Una imagen por bolsa' : 'Fotografía de entrega'} obligatorio soloImagenes maxArchivos={datos.puede_preparar ? 50 : 1} archivos={archivos} previews={previews} onChange={(nuevos, vistas) => { setArchivos(nuevos); setPreviews(vistas); }} onVer={(documentos, indice) => setVista({ documentos, indice })} disabled={ocupado} />}
                {(datos.puede_preparar || datos.puede_recibir || datos.puede_gestionar) && <label className={THEME_LABEL}>{datos.puede_gestionar ? 'Seguimiento con el cliente' : 'Observaciones'}<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} value={observaciones} onChange={e => setObservaciones(e.target.value)} /></label>}
                {datos.puede_gestionar && <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} onClick={() => enviar('gestionar')}>Registrar seguimiento</button>}
                {datos.puede_preparar && <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => enviar('preparar')}>Confirmar bolsas preparadas</button>}
                {datos.puede_salida && <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => enviar('salida')}><Truck size={18} aria-hidden="true" />{datos.requiere_traslado ? 'Confirmar salida a sucursal' : 'Entregar bolsas a recepción de la misma sucursal'}</button>}
                {datos.puede_recibir && <><label className="flex items-start gap-2 text-sm theme-text-main"><input type="checkbox" className="gelia-checkbox mt-0.5" checked={verificadas} onChange={e => setVerificadas(e.target.checked)} />Verifiqué todas las bolsas identificadas y sus observaciones.</label><button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => enviar('recibir')}>Confirmar recepción de bolsas</button></>}
                {datos.puede_custodia && <><label className={THEME_LABEL}>Almacén de custodia<select className={`${THEME_SELECT} w-full mt-1`} value={almacen} onChange={e => setAlmacen(e.target.value)}><option value="">Elegir almacén</option>{datos.almacenes_destino.map(item => <option key={item.id} value={item.id}>{item.nombre}</option>)}</select></label><label className={THEME_LABEL}>Ubicación e identificación del pedido<input className={`${THEME_INPUT} w-full mt-1`} maxLength={240} value={ubicacion} onChange={e => setUbicacion(e.target.value)} placeholder="Referencia de ubicación en recepción" /></label><button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => enviar('custodia')}>Confirmar custodia identificada</button></>}
                {datos.puede_entregar && <><label className={THEME_LABEL}>Quién recibe<input className={`${THEME_INPUT} w-full mt-1`} maxLength={160} value={receptor} onChange={e => setReceptor(e.target.value)} /></label><button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => enviar('entregar')}>Confirmar entrega al cliente</button></>}
            </fieldset>}
            {Object.keys(datos.historial || {}).length > 0 && <details><summary className="text-sm font-semibold theme-text-main cursor-pointer">Historial del recorrido</summary><ul className="pl-4 text-sm theme-text-muted space-y-1">{Object.entries(datos.historial).map(([accion, evento]) => <li key={accion}>{ACCIONES[accion]} · {evento.por} · {formatearFechaNegocio(evento.fecha)}</li>)}</ul></details>}
            {datos.gestiones?.length > 0 && <ul className="pl-4 space-y-1 text-sm theme-text-main">{datos.gestiones.map((gestion, indice) => <li key={indice}>{gestion.nota} · {gestion.por} · {formatearFechaNegocio(gestion.fecha)}</li>)}</ul>}
            {!soloLectura && datos.puede_continuar_posterior && <div className="border-t theme-border pt-3 grid gap-3">
                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado} aria-expanded={continuar} onClick={() => setContinuar(!continuar)}>Acordar recolección posterior · T-02</button>
                {continuar && <><label className={THEME_LABEL}>Acuerdo con el cliente<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={2000} value={acuerdo} onChange={evento => setAcuerdo(evento.target.value)} /></label><p className="text-sm theme-text-muted m-0">Al confirmar, se continúa como transferencia para recoger posteriormente. Se conservan los pagos, la remisión y la hora original del reporte.</p><button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado} onClick={() => enviar('continuar_posterior')}>Confirmar acuerdo y continuar como T-02</button></>}
            </div>}
        </>}
        <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado} onClick={cargar}>Actualizar seguimiento</button>
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} documentos={vista?.documentos || []} indice={vista?.indice || 0} onClose={() => setVista(null)} />
    </section>;
}
