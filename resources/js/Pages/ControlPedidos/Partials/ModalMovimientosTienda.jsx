import React, { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { ArrowRightLeft, Package, Store, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA, THEME_INPUT, GELIA_ICON_BOX } from '../../../utils/geliaTheme';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';

const mensaje = error => error?.response?.status === 419 ? 'La sesión venció. Recargue GELIA antes de continuar.' : Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || 'No se pudo actualizar el movimiento.';
export const ETAPAS_TRASLADO = { pendiente: 'Pendiente de autorización', rechazada: 'Solicitud rechazada', autorizada: 'Pendiente de salida de sucursal', en_camino: 'En traslado', recibido: 'Recibido en custodia' };

export default function ModalMovimientosTienda({ abierto = false, pedido, modo = 'traslado', onClose, onActualizar }) {
    const [datos, setDatos] = useState(null);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [destino, setDestino] = useState('');
    const [motivo, setMotivo] = useState('');
    const [captura, setCaptura] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [vista, setVista] = useState(null);
    const [recorrido, setRecorrido] = useState('');
    const [almacen, setAlmacen] = useState('');
    const [ubicacion, setUbicacion] = useState('');
    const [verificado, setVerificado] = useState(false);
    const [confirmar, setConfirmar] = useState(null);
    const [exito, setExito] = useState('');
    const id = useId();
    const dialogo = useRef(null);
    const urls = useRef([]);
    const cambios = Boolean(destino || motivo || archivos.length || recorrido || almacen || ubicacion || captura || verificado);
    useEffect(() => { urls.current = previews; }, [previews]);
    useEffect(() => {
        if (!abierto || !pedido?.id) return;
        let vigente = true;
        setDatos(null); setError(''); setDestino(''); setMotivo(''); setCaptura(false); setArchivos([]); setPreviews([]); setRecorrido(''); setAlmacen(''); setUbicacion(''); setVerificado(false); setConfirmar(null); setExito('');
        axios.get(`/control-pedidos/${pedido.id}/movimientos-tienda`).then(r => { if (vigente) setDatos(r.data); }).catch(e => { if (vigente) setError(mensaje(e)); });
        const anterior = document.activeElement;
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogo.current?.focus();
        return () => { vigente = false; document.body.style.overflow = overflow; anterior?.focus?.(); urls.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }); };
    }, [abierto, pedido?.id, modo]);
    if (!abierto || !pedido) return null;
    const cerrar = () => { if (ocupado || vista) return; if (cambios && !exito) setConfirmar('cancelar'); else onClose?.(); };
    const teclado = e => {
        if (vista) return;
        if (e.key === 'Escape') { e.stopPropagation(); cerrar(); }
        if (e.key === 'Tab') {
            const items = [...dialogo.current.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')];
            const primero = items[0], ultimo = items.at(-1);
            if (e.shiftKey && (document.activeElement === primero || document.activeElement === dialogo.current)) { e.preventDefault(); ultimo?.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero?.focus(); }
        }
    };
    const preparar = accion => {
        setError('');
        if (['solicitar', 'modalidad'].includes(accion) && (!destino || !motivo.trim())) { setError('Seleccione el destino y registre el motivo.'); return; }
        if (accion === 'solicitar' && !captura && !archivos.length) { setError('Adjunte la petición del cliente o notifique un error de captura.'); return; }
        if (accion === 'autorizar' && !recorrido.trim()) { setError('Defina el recorrido físico del traslado.'); return; }
        if (accion === 'recepcion' && (!verificado || !almacen || !ubicacion.trim())) { setError('Verifique las bolsas e identifique su almacén y ubicación en destino.'); return; }
        setConfirmar(accion);
    };
    const enviar = async () => {
        const accion = confirmar;
        if (ocupado) return;
        setOcupado(true); setError('');
        const form = new FormData();
        form.append('version', datos.version);
        if (accion === 'solicitar') { form.append('sucursal_destino_id', destino); form.append('error_captura', captura ? '1' : '0'); archivos.forEach(a => form.append('evidencias[]', a)); }
        if (accion === 'modalidad') form.append('modalidad', destino);
        if (motivo) form.append('motivo', motivo);
        if (recorrido) form.append('recorrido', recorrido);
        if (accion === 'recepcion') { form.append('bolsas_verificadas', verificado ? '1' : '0'); form.append('almacen_id', almacen); form.append('ubicacion', ubicacion); }
        try {
            const respuesta = await axios.post(`/control-pedidos/${pedido.id}/movimientos-tienda/${accion}`, form, { headers: { Accept: 'application/json' } });
            setDatos(respuesta.data); setConfirmar(null); setExito(accion === 'solicitar' ? 'Traslado solicitado. Pendiente de autorización de gerencia; el pedido permanece en la sucursal actual.' : accion === 'modalidad' ? 'Solicitud de cambio registrada. Pendiente de conectar el envío; se conservan la custodia, la compra y el pago.' : accion === 'salida' ? 'Salida registrada. Falta confirmar la recepción en destino.' : accion === 'recepcion' ? 'Recepción y custodia confirmadas en la sucursal autorizada.' : accion === 'autorizar' ? 'Traslado autorizado. Falta registrar la salida y la recepción.' : 'Solicitud rechazada. Se conserva el destino original.');
            onActualizar?.();
        } catch (e) { setError(mensaje(e)); setConfirmar(null); }
        finally { setOcupado(false); }
    };
    const traslado = datos?.traslado;
    const etapa = traslado?.recepcion_confirmada ? 'recibido' : traslado?.movimiento_estado === 'en_camino' ? 'en_camino' : traslado?.estado;
    const titulo = modo === 'modalidad' ? 'Cambiar modalidad' : 'Trasladar a otra sucursal';
    const Icono = modo === 'modalidad' ? Package : ArrowRightLeft;
    const contenido = confirmar ? <div className="grid gap-4" role="status">
        <h3 className="text-lg font-bold theme-text-main m-0">{confirmar === 'cancelar' ? '¿Descartar lo capturado?' : 'Confirmar operación'}</h3>
        <p className="text-sm theme-text-main m-0">{confirmar === 'cancelar' ? 'Los datos que aún no enviaste se perderán.' : confirmar === 'solicitar' ? `Se solicitará autorización para trasladar a ${datos.sucursales.find(s => String(s.id) === destino)?.nombre}. La mercancía permanece en custodia hasta la salida.` : confirmar === 'modalidad' ? `Se registrará la petición de envío ${destino}, pendiente de conectar su proceso. La modalidad actual se conserva.` : confirmar === 'autorizar' ? `Se autorizará el recorrido: ${recorrido}. La autorización no confirma el movimiento físico.` : confirmar === 'salida' ? 'Confirma solo si las bolsas salieron físicamente de la sucursal de origen.' : confirmar === 'recepcion' ? `Confirma la recepción de todas las bolsas y su custodia en ${ubicacion}.` : 'Se rechazará la solicitud; se conserva el destino original.'}</p>
        <div className="flex flex-wrap gap-2 justify-end"><button type="button" className={THEME_BTN_SECONDARY} disabled={ocupado} onClick={() => setConfirmar(null)}>Seguir editando</button><button type="button" className={THEME_BTN_PRIMARY} disabled={ocupado} onClick={confirmar === 'cancelar' ? onClose : enviar}>{ocupado ? 'Guardando…' : confirmar === 'cancelar' ? 'Descartar y cerrar' : 'Sí, confirmar'}</button></div>
    </div> : exito ? <div className="grid gap-4"><p role="status" className="text-sm theme-text-main m-0">{exito}</p><button type="button" className={THEME_BTN_PRIMARY} onClick={onClose}>Aceptar</button></div> : <>
        {datos && <div className="registrar-tienda-panel grid gap-3 text-sm">
            <div className="registrar-tienda-seccion-titulo"><Store size={18} /><h3>Sucursal y movimiento</h3></div>
            <p className="m-0 theme-text-main">Sucursal actual: <strong>{etapa === 'recibido' ? datos.sucursal_destino : datos.sucursal_actual}</strong>{etapa === 'en_camino' ? ' · Mercancía en traslado' : ''}</p>
            {traslado && <><p className="m-0 theme-text-main">Destino solicitado: <strong>{datos.sucursal_destino}</strong></p><p className="m-0 font-semibold theme-text-main" role="status">{ETAPAS_TRASLADO[etapa]}</p>{traslado.motivo && <p className="m-0 theme-text-main">Motivo: {traslado.motivo}</p>}{traslado.anotacion && <p className="m-0 theme-text-muted">{traslado.anotacion}</p>}{traslado.recorrido && <p className="m-0 theme-text-main">Recorrido autorizado: {traslado.recorrido}</p>}{datos.evidencias?.length > 0 && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setVista({ documentos: datos.evidencias, indice: 0 })}>Ver petición del cliente</button>}</>}
            {datos.modalidad && <p className="m-0 theme-text-main">Envío {datos.modalidad.destino} solicitado · Pendiente de conexión.</p>}
        </div>}
        {datos?.bloqueo_destino && <p role="status" className="text-sm theme-text-aviso m-0">{datos.bloqueo_destino}</p>}
        {datos?.puede_solicitar && <fieldset disabled={ocupado} className="grid gap-4 min-w-0">
            <label className={THEME_LABEL} htmlFor={`${id}-destino`}>{modo === 'modalidad' ? 'Nueva modalidad' : 'Nueva sucursal'}<select id={`${id}-destino`} className={`${THEME_SELECT} w-full mt-1`} value={destino} onChange={e => setDestino(e.target.value)}><option value="">Seleccionar</option>{modo === 'modalidad' ? <><option value="municipal">Envío municipal</option><option value="nacional">Envío nacional</option></> : datos.sucursales.map(s => <option key={s.id} value={s.id}>{s.nombre}</option>)}</select></label>
            <label className={THEME_LABEL} htmlFor={`${id}-motivo`}>Motivo de la solicitud<textarea id={`${id}-motivo`} className={`${THEME_TEXTAREA} w-full mt-1`} rows={3} maxLength={200} value={motivo} onChange={e => setMotivo(e.target.value)} /><span className="block text-right text-xs theme-text-muted">{motivo.length} / 200</span></label>
            {modo !== 'modalidad' ? <><GaleriaEvidenciasPedido id={`${id}-evidencias`} label="Evidencia petición cliente" obligatorio={!captura} maxArchivos={8} disabled={captura || ocupado} archivos={archivos} previews={previews} onChange={(a, p) => { setArchivos(a); setPreviews(p); }} onVer={(documentos, indice) => setVista({ documentos, indice })} /><label className="flex gap-2 items-start text-sm theme-text-main"><input type="checkbox" className="gelia-checkbox" checked={captura} onChange={e => { setCaptura(e.target.checked); }} />Notificar un error de captura</label><p className="text-xs theme-text-muted m-0">{captura ? 'Se registrará: Se notificó un error de captura. La evidencia deja de ser obligatoria; el traslado requiere autorización de gerencia.' : 'Adjunta la solicitud del cliente. El traslado requiere autorización de gerencia.'}</p></> : <p className="text-sm theme-text-muted m-0">Se registra la petición para continuar después con el envío. La mercancía permanece en custodia hasta conectar y confirmar esa operación.</p>}
            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={() => preparar(modo === 'modalidad' ? 'modalidad' : 'solicitar')}>{modo === 'modalidad' ? 'Registrar solicitud de cambio' : 'Solicitar traslado'}</button>
        </fieldset>}
        {datos?.puede_resolver && <div className="grid gap-3"><label className={THEME_LABEL}>Recorrido físico autorizado<textarea className={`${THEME_TEXTAREA} w-full mt-1`} maxLength={500} value={recorrido} onChange={e => setRecorrido(e.target.value)} /></label><p className="text-xs theme-text-muted m-0">Define origen, destino y pasos intermedios. El cliente recoge en sucursal.</p><div className="flex gap-2 flex-wrap"><button type="button" className={THEME_BTN_PRIMARY} onClick={() => preparar('autorizar')}>Autorizar traslado</button><button type="button" className={THEME_BTN_SECONDARY} onClick={() => preparar('rechazar')}>Rechazar solicitud</button></div></div>}
        {datos?.puede_salida && <button type="button" className={THEME_BTN_PRIMARY} onClick={() => preparar('salida')}>Registrar salida de sucursal</button>}
        {datos?.puede_recibir && <div className="grid gap-3"><label className="flex gap-2 text-sm theme-text-main"><input type="checkbox" className="gelia-checkbox" checked={verificado} onChange={e => setVerificado(e.target.checked)} />Verifiqué todas las bolsas y sus observaciones.</label><label className={THEME_LABEL}>Almacén de custodia<select className={`${THEME_SELECT} w-full mt-1`} value={almacen} onChange={e => setAlmacen(e.target.value)}><option value="">Elegir almacén</option>{datos.almacenes.map(a => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label><label className={THEME_LABEL}>Ubicación de custodia<input className={`${THEME_INPUT} w-full mt-1`} maxLength={240} value={ubicacion} onChange={e => setUbicacion(e.target.value)} /></label><button type="button" className={THEME_BTN_PRIMARY} onClick={() => preparar('recepcion')}>Confirmar recepción y custodia</button></div>}
        {datos && !datos.puede_solicitar && !traslado && !datos.modalidad && <p className="text-sm theme-text-muted m-0">La solicitud corresponde a la vendedora del pedido cuando la mercancía está recibida y en custodia en sucursal.</p>}
    </>;
    return createPortal(<div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4`} style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }} onClick={cerrar}>
        <div ref={dialogo} role="dialog" aria-modal="true" aria-labelledby={`${id}-titulo`} tabIndex={-1} onKeyDown={teclado} onClick={e => e.stopPropagation()} className={`${THEME_MODAL_SHELL} w-full max-w-2xl flex flex-col outline-none`} style={{ maxHeight: 'calc(100dvh - 2rem)' }}>
            <header className="p-5 border-b theme-border flex items-start gap-3 shrink-0"><span className={`${GELIA_ICON_BOX} p-3`}><Icono size={24} /></span><div className="min-w-0 flex-1"><h2 id={`${id}-titulo`} className="text-lg font-black uppercase italic theme-text-main m-0">{titulo}</h2><p className="text-sm theme-text-muted m-0 break-words">{pedido.cliente?.nombre || 'Pedido'} · {pedido.folio_remision || pedido.folio}</p></div><button type="button" aria-label="Cerrar movimiento" className="theme-btn-icon shrink-0" disabled={ocupado} onClick={cerrar}><X size={20} /></button></header>
            <div className="gelia-modal-body p-5 grid gap-4 min-w-0">{error && <p role="alert" className="theme-text-peligro text-sm m-0">{error}</p>}<button type="button" className={THEME_BTN_SECONDARY} disabled={ocupado} onClick={() => axios.get(`/control-pedidos/${pedido.id}/movimientos-tienda`).then(r => { setDatos(r.data); setError(''); }).catch(e => setError(mensaje(e)))}>Actualizar seguimiento</button>{!datos && !error && <p role="status" className="text-sm theme-text-muted">Cargando movimiento…</p>}{contenido}</div>
        </div>
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} documentos={vista?.documentos || []} indice={vista?.indice || 0} onClose={() => setVista(null)} />
    </div>, document.body);
}
