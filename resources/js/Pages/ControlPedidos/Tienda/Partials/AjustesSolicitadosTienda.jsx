import ResumenCambiosDisponibilidad from '../../Partials/ResumenCambiosDisponibilidad';
import PanelRespuestaDisponibilidad from '../../Partials/PanelRespuestaDisponibilidad';
import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { PackageMinus, PackagePlus } from 'lucide-react';
import SelectorProducto from '../../../../Components/Almacenes/SelectorProducto';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

export function AtencionRetiro({ tarea, ocupado, onEnviar, onDirtyChange }) {
    const pendientes = (tarea.pedido?.retiros || []).filter((retiro) => (retiro.estado || 'pendiente') === 'pendiente');
    const [producto, setProducto] = useState(null);
    const [cantidad, setCantidad] = useState('1');
    const [piezas, setPiezas] = useState([]);
    const [nota, setNota] = useState('');
    const [version, setVersion] = useState(pendientes[0]?.version || 0);
    useEffect(() => { onDirtyChange?.(Boolean(piezas.length || producto || nota)); }, [piezas, producto, nota]);
    if (!pendientes.length) return null;
    const actual = pendientes.find((retiro) => Number(retiro.version) === Number(version)) || pendientes[0];
    const documental = actual.entrada === 'documento';
    const agregarPieza = () => {
        if (!producto?.sku || !producto?.descripcion || Number(cantidad) < 1) return;
        setPiezas((lista) => {
            const indice = lista.findIndex((pieza) => String(pieza.sku) === String(producto.sku));
            const pieza = { sku: producto.sku, descripcion: producto.descripcion, cantidad: Number(cantidad) };
            if (indice < 0) return [...lista, pieza];
            return lista.map((fila, i) => (i === indice ? { ...fila, cantidad: fila.cantidad + pieza.cantidad } : fila));
        });
        setProducto(null);
        setCantidad('1');
    };
    const enviar = (decision) => {
        const datos = { version: actual.version, decision, nota, piezas: documental ? piezas : [] };
        const opciones = { preserveScroll: true };
        if (onEnviar) onEnviar(route('control_pedidos.tienda.retiro.atender', tarea.id), datos, opciones);
        else router.post(route('control_pedidos.tienda.retiro.atender', tarea.id), datos, opciones);
    };
    return <section className="registrar-tienda-panel grid gap-3" aria-label="Retiro por atender">
        <div className="registrar-tienda-seccion-titulo"><PackageMinus size={18} aria-hidden="true" /><h3>Retiro por atender</h3></div>
        {pendientes.length > 1 && <label className={THEME_LABEL}>Solicitud<select className={`${THEME_SELECT} w-full mt-1`} value={actual.version} onChange={(evento) => { setVersion(Number(evento.target.value)); setPiezas([]); setProducto(null); setNota(''); }}>{pendientes.map((retiro) => <option key={retiro.version} value={retiro.version}>Versión {retiro.version} · {retiro.motivo}</option>)}</select></label>}
        <p className="text-sm theme-text-main m-0">{actual.motivo}{actual.solicitado_por ? ` · Solicitó ${actual.solicitado_por}` : ''} · {actual.estado || 'pendiente'}</p>
        {documental ? <p className="text-sm theme-text-muted m-0">Llegaron {actual.documentos_piezas?.length || 0} archivo(s) de las piezas a retirar. Identifique productos y cantidades comprometidas. No use un SKU inventado.</p> : <ul className="m-0 pl-4">{(actual.piezas || []).map((pieza) => <li key={pieza.sku} className="text-sm theme-text-main">{pieza.descripcion} · {pieza.sku} · {pieza.cantidad}</li>)}</ul>}
        {(actual.documentos_piezas || []).length > 0 && <ul className="m-0 pl-4">{actual.documentos_piezas.map((documento) => <li key={documento.id} className="text-sm"><a className="underline" href={documento.url}>{documento.nombre || 'Captura'}</a></li>)}</ul>}
        {documental && <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_auto] sm:items-end">
            <div className="min-w-0 gelia-preparacion-selector"><label className={THEME_LABEL} htmlFor="retiro-atender-producto">Producto comprometido</label><SelectorProducto inputId="retiro-atender-producto" almacenId={tarea.almacen?.id} incluirSinInventario value={producto?.id || ''} onChange={(_, elegido) => setProducto(elegido)} disabled={ocupado} /></div>
            <div><label className={THEME_LABEL} htmlFor="retiro-atender-cantidad">Cantidad</label><input id="retiro-atender-cantidad" className={`${THEME_INPUT} w-full min-h-[44px]`} inputMode="numeric" min="1" value={cantidad} onChange={(evento) => setCantidad(evento.target.value)} /></div>
            <button type="button" className={BTN_SECONDARY} disabled={ocupado || !producto} onClick={agregarPieza}>Agregar</button>
        </div>}
        {piezas.length > 0 && <ul className="m-0 pl-4">{piezas.map((pieza) => <li key={pieza.sku} className="text-sm theme-text-main">{pieza.descripcion} · {pieza.cantidad} <button type="button" className="underline" onClick={() => setPiezas((lista) => lista.filter((fila) => fila.sku !== pieza.sku))}>Quitar</button></li>)}</ul>}
        <label className={THEME_LABEL} htmlFor="retiro-atender-nota">Nota si rechaza</label>
        <textarea id="retiro-atender-nota" className={`${THEME_TEXTAREA} w-full`} rows={2} value={nota} onChange={(evento) => setNota(evento.target.value)} />
        <div className="flex flex-wrap gap-2">
            <button type="button" className={BTN_PRIMARY} disabled={ocupado} onClick={() => enviar('aplicar')}>Aplicar retiro</button>
            <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => enviar('rechazar')}>Rechazar</button>
        </div>
    </section>;
}


function AtencionAnexo({ tarea, anexo, ocupado, onEnviar, onDirtyChange }) {
    const [producto, setProducto] = useState(null);
    const [cantidad, setCantidad] = useState('1');
    const [piezas, setPiezas] = useState([]);
    const [nota, setNota] = useState('');
    const [foto, setFoto] = useState(null);
    const [error, setError] = useState('');
    useEffect(() => { onDirtyChange?.(Boolean(piezas.length || producto || nota || foto)); }, [piezas, producto, nota, foto]);
    const agregar = () => {
        if (!producto?.id || !Number.isInteger(Number(cantidad)) || Number(cantidad) < 1) return;
        setPiezas((actuales) => [...actuales, { producto_id: producto.id, sku: producto.sku, descripcion: producto.descripcion, cantidad: Number(cantidad) }]);
        setProducto(null);
        setCantidad('1');
    };
    const enviar = (decision) => {
        if (ocupado) return;
        if (decision === 'disponible' && (!piezas.length || !foto)) {
            setError('Identifique las piezas disponibles y adjunte una foto del anexo.');
            return;
        }
        if (decision === 'rechazar' && !nota.trim()) {
            setError('Indique el faltante o detalle para que Ventas lo consulte con el cliente.');
            return;
        }
        const datos = new FormData();
        datos.append('indice', anexo.indice);
        datos.append('decision', decision);
        datos.append('nota', nota);
        piezas.forEach((pieza, i) => Object.entries(pieza).forEach(([clave, valor]) => datos.append(`piezas[${i}][${clave}]`, valor)));
        if (foto) datos.append('evidencia', foto);
        const opciones = { preserveScroll: true };
        const url = route('control_pedidos.tienda.anexo.atender', tarea.id);
        if (onEnviar) onEnviar(url, datos, opciones);
        else router.post(url, datos, opciones);
    };
    const id = `anexo-${anexo.indice}`;
    return <section className="registrar-tienda-panel grid gap-3" aria-label="Anexo por atender">
        <div className="registrar-tienda-seccion-titulo"><PackagePlus size={18} aria-hidden="true" /><h3>Anexo por atender</h3></div>
        <p className="text-sm theme-text-muted m-0">Revise el documento del anexo y responda únicamente sobre las piezas solicitadas. La respuesta no confirma separación, pago ni entrega.</p>
        {anexo.descripcion && <p className="text-sm theme-text-main m-0">{anexo.descripcion} · {anexo.cantidad} pieza(s)</p>}
        {anexo.documento?.url && <a className="underline text-sm theme-text-primario" href={anexo.documento.url} target="_blank" rel="noreferrer">Ver {anexo.documento.nombre_original || 'documento del anexo'}</a>}
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_auto] sm:items-end">
            <div className="min-w-0 gelia-preparacion-selector"><label className={THEME_LABEL} htmlFor={`${id}-producto`}>Producto disponible</label><SelectorProducto inputId={`${id}-producto`} almacenId={tarea.almacen?.id} incluirSinInventario value={producto?.id || ''} onChange={(_, elegido) => setProducto(elegido)} disabled={ocupado} /></div>
            <div><label className={THEME_LABEL} htmlFor={`${id}-cantidad`}>Cantidad</label><input id={`${id}-cantidad`} type="number" min="1" step="1" className={`${THEME_INPUT} w-full min-h-[44px]`} value={cantidad} disabled={ocupado} onChange={(evento) => setCantidad(evento.target.value)} /></div>
            <button type="button" className={BTN_SECONDARY} disabled={ocupado || !producto} onClick={agregar}>Agregar al anexo</button>
        </div>
        {piezas.length > 0 && <ul className="m-0 pl-4 grid gap-2">{piezas.map((pieza, i) => <li key={i} className="text-sm theme-text-main">{pieza.descripcion} · {pieza.cantidad} <button type="button" className="underline" disabled={ocupado} onClick={() => setPiezas((actuales) => actuales.filter((_, indice) => indice !== i))}>Quitar</button></li>)}</ul>}
        <label className={THEME_LABEL} htmlFor={`${id}-foto`}>Foto de las piezas del anexo</label>
        <input id={`${id}-foto`} type="file" accept="image/jpeg,image/png,image/webp" disabled={ocupado} onChange={(evento) => setFoto(evento.target.files?.[0] || null)} />
        <label className={THEME_LABEL} htmlFor={`${id}-nota`}>Detalle o faltante para Ventas</label>
        <textarea id={`${id}-nota`} className={`${THEME_TEXTAREA} w-full`} rows={2} value={nota} disabled={ocupado} onChange={(evento) => setNota(evento.target.value)} />
        {error && <p role="alert" className="text-sm theme-text-main m-0">{error}</p>}
        <div className="flex flex-wrap gap-2">
            <button type="button" className={BTN_PRIMARY} disabled={ocupado} onClick={() => enviar('disponible')}>Responder disponibilidad del anexo</button>
            <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => enviar('rechazar')}>Reportar faltante o detalle</button>
        </div>
    </section>;
}

export default function AjustesSolicitadosTienda({ tarea, auth, ocupado = false, onEnviar, onDirtyChange, confirmarConjunto = false }) {
    const [capturas, setCapturas] = useState({});
    const retirosPendientes = (tarea.pedido?.retiros || []).filter((retiro) => (retiro.estado || 'pendiente') === 'pendiente');
    const anexoPendienteDirty = (tarea.pedido?.anexos_pendientes || []).some((anexo) => capturas[`anexo-${anexo.indice}`]);
    const capturaPendiente = Boolean((retirosPendientes.length && capturas.retiro) || anexoPendienteDirty || capturas.consulta);
    useEffect(() => { onDirtyChange?.(capturaPendiente); }, [capturaPendiente, onDirtyChange]);
    const marcar = (clave, valor) => setCapturas((actual) => actual[clave] === valor ? actual : { ...actual, [clave]: valor });
    const permisos = auth?.user?.permissions || [];
    const autorizado = (tarea.puede_preparar || tarea.puede_responder)
        ?? (Number(tarea.responsable?.id) === Number(auth?.user?.id) && (auth?.user?.roles?.includes('Super Admin') || permisos.includes('control_pedidos.tienda.responder') || permisos.includes('control_pedidos.tienda.apartado.separar')));
    const consulta = tarea.pedido?.area_revision === 'preparacion_tienda';
    const actualizacion = consulta && Boolean(tarea.pedido?.revision_disponibilidad?.actualizacion_pendiente || tarea.pedido?.anexos_pendientes?.length);
    const resumen = <ResumenCambiosDisponibilidad solicitud={tarea.pedido || {}} documentos={tarea.pedido?.documentos || []} />;
    if (!autorizado || !['EN_ATENCION', 'RESPONDIDA'].includes(tarea.estado)) return resumen;
    return <div className="gelia-preparacion-ajustes grid gap-4">
        {!actualizacion && resumen}
        {(!consulta || tarea.pedido?.retiros_requieren_atencion_fisica) && <AtencionRetiro key={retirosPendientes.map((retiro) => retiro.version).join('-')} tarea={tarea} ocupado={ocupado} onEnviar={onEnviar} onDirtyChange={(valor) => marcar('retiro', valor)} />}
        {!confirmarConjunto && (tarea.pedido?.anexos_por_separar || []).map((anexo) => <section key={`separar-${anexo.indice}`} className="registrar-tienda-panel grid gap-3">
            <div className="registrar-tienda-seccion-titulo"><PackagePlus size={18} aria-hidden="true" /><h3>Separación del anexo</h3></div>
            <ul className="m-0 pl-4">{(anexo.piezas_respuesta || []).map((pieza, i) => <li key={i} className="text-sm theme-text-main">{pieza.descripcion} · {pieza.cantidad}</li>)}</ul>
            {tarea.pedido?.consulta_cerrada && !(tarea.pedido?.cobertura_tienda?.requiere_confirmacion_pedido && !tarea.pedido?.cobertura_tienda?.confirmacion_enviada_at) ? <button type="button" className={BTN_PRIMARY} disabled={ocupado} onClick={() => {
                const url = route('control_pedidos.tienda.anexo.atender', tarea.id);
                const datos = { indice: anexo.indice, decision: 'separar' };
                if (onEnviar) onEnviar(url, datos); else router.post(url, datos, { preserveScroll: true });
            }}>Confirmar separación del anexo</button> : <p role="status" className="text-sm theme-text-muted m-0">Disponibilidad enviada. Espere a que Ventas cierre la consulta para separar el anexo.</p>}
        </section>)}
        {actualizacion && <PanelRespuestaDisponibilidad
            key={tarea.pedido?.revision_disponibilidad?.actualizacion_id || 'actualizacion'}
            origen="Tienda" mostrarCambios={false} tomada puedeResponder={!ocupado}
            pedido={{ ...tarea.pedido, cliente: { nombre: tarea.pedido?.cliente_nombre }, almacen: tarea.almacen, solicitud_preparacion: tarea.pedido }}
            documentos={tarea.pedido?.documentos || []} revision={tarea.pedido?.revision_disponibilidad}
            onDirtyChange={valor => marcar('consulta', valor)}
            onEnviar={form => new Promise((resolve, reject) => {
                const url = route('control_pedidos.tienda.disponibilidad', tarea.id);
                const opciones = { preserveScroll: true, onSuccess: resolve, onError: errores => reject({ response: { data: { errors: errores } } }) };
                if (onEnviar) onEnviar(url, form, { ...opciones, onFinish: () => reject(new Error('No se pudo enviar la respuesta.')) });
                else axios.post(url, form, { headers: { Accept: 'application/json' } }).then(() => router.reload({ ...opciones, onFinish: resolve }), reject);
            })}
        />}
        {!consulta && (tarea.pedido?.anexos_pendientes || []).map((anexo) => <AtencionAnexo key={anexo.indice} tarea={tarea} anexo={anexo} ocupado={ocupado} onEnviar={onEnviar} onDirtyChange={(valor) => marcar(`anexo-${anexo.indice}`, valor)} />)}
    </div>;
}
