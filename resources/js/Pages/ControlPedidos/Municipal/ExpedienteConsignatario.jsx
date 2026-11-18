import React, { useEffect, useRef, useState } from 'react';
import { FileUp, Download, Pencil, Plus, Save, X } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT, GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';

const rutas = (clienteId, pedidoId) => {
    const base = pedidoId
        ? `/control-pedidos/municipal/clientes/${clienteId}`
        : `/admin/clientes/${clienteId}/expediente`;
    const consulta = pedidoId ? `?pedido_id=${pedidoId}` : '';
    return {
        lista: `${base}/consignatarios${consulta}`,
        crear: `${base}/consignatarios${consulta}`,
        editar: (id) => `${base}/consignatarios/${id}${consulta}`,
        documento: (id) => `${base}/consignatarios/${id}/documento${consulta}`,
        descargar: (documentoId) => pedidoId
            ? `/control-pedidos/municipal/clientes/${clienteId}/documentos/${documentoId}?pedido_id=${pedidoId}`
            : `/admin/clientes/${clienteId}/expediente/documentos/${documentoId}`,
    };
};

export default function ExpedienteConsignatario({ clienteId, pedidoId = null, seleccionado, onSeleccionar, compacto = false }) {
    const archivosRef = useRef({});
    const [subiendoId, setSubiendoId] = useState(null);
    const [tipoAviso, setTipoAviso] = useState('error');
    const [filas, setFilas] = useState([]);
    const [nombre, setNombre] = useState('');
    const [telefono, setTelefono] = useState('');
    const [aviso, setAviso] = useState('');
    const [permisos, setPermisos] = useState({});
    const [historico, setHistorico] = useState(false);
    const [editando, setEditando] = useState(null);
    const [capturando, setCapturando] = useState(!compacto);
    const [ocupado, setOcupado] = useState(false);

    const cargar = async () => {
        if (!clienteId) return;
        const { data } = await window.axios.get(rutas(clienteId, pedidoId).lista + (historico ? (pedidoId ? '&' : '?') + 'historico=1' : ''), { headers: { Accept: 'application/json' } });
        setFilas(data.consignatarios || []);
        setPermisos(data.permisos || {});
    };

    useEffect(() => { cargar().catch((error) => setAviso(error.response?.data?.message || 'No se pudo consultar el expediente.')); }, [clienteId, pedidoId, historico]);

    const crear = async (evento) => {
        evento.preventDefault();
        setAviso('');
        setTipoAviso('error');
        setOcupado(true);
        try {
            if (editando) {
                await window.axios.put(rutas(clienteId, pedidoId).editar(editando), { nombre, telefono, pedido_id: pedidoId });
            } else {
                const { data } = await window.axios.post(rutas(clienteId, pedidoId).crear, { nombre, telefono, pedido_id: pedidoId }, { headers: { Accept: 'application/json' } });
                onSeleccionar?.(data.id);
            }
            setNombre(''); setTelefono(''); setEditando(null); setCapturando(!compacto);
            await cargar();
        } catch (error) { setAviso(error.response?.data?.message || 'No se pudieron guardar los datos.'); }
        finally { setOcupado(false); }
    };

    const subir = async (id, archivo) => {
        if (!archivo || subiendoId !== null) return;
        setSubiendoId(id); setAviso(''); setTipoAviso('error');
        const cuerpo = new FormData();
        cuerpo.append('archivo', archivo);
        if (pedidoId) cuerpo.append('pedido_id', pedidoId);
        try {
            await window.axios.post(rutas(clienteId, pedidoId).documento(id), cuerpo, { headers: { Accept: 'application/json' } });
            await cargar();
            setTipoAviso('exito'); setAviso('Identificación guardada.');
        } catch (error) {
            const errores = Object.values(error.response?.data?.errors || {}).flat();
            setAviso(errores[0] || 'No se pudo guardar la identificación. Los datos del consignatario se conservan; vuelva a intentarlo.');
        } finally { setSubiendoId(null); }
    };

    const eliminar = async (url) => {
        try { setTipoAviso('error'); await window.axios.delete(url); await cargar(); }
        catch (error) { setAviso(error.response?.data?.message || 'No se pudo dar de baja.'); }
    };

    if (!clienteId) return <p className="text-sm theme-text-muted">Seleccione un cliente para consultar consignatarios.</p>;

    return (
        <section className="space-y-3">
            {!compacto && <h3 className="text-sm font-black theme-text-main m-0">Expediente del cliente</h3>}
            {!pedidoId && permisos.ver_historico && <label className="text-sm"><input type="checkbox" checked={historico} onChange={evento => setHistorico(evento.target.checked)} /> Mostrar versiones históricas</label>}
            {aviso && <p className={`text-sm m-0 break-words ${tipoAviso === 'exito' ? 'theme-text-exito' : 'theme-text-aviso'}`} role={tipoAviso === 'exito' ? 'status' : 'alert'}>{aviso}</p>}
            {compacto && onSeleccionar && <label className={THEME_LABEL}>Persona que recoge
                <select className={`${THEME_SELECT} w-full mt-1`} value={seleccionado || ''} onChange={evento => onSeleccionar(Number(evento.target.value) || '')}>
                    <option value="">Elegir consignatario</option>
                    {filas.filter(fila => fila.activo).map(fila => <option key={fila.id} value={fila.id}>{fila.version?.nombre || 'Sin datos vigentes'}</option>)}
                </select>
            </label>}
            <ul className="space-y-2 m-0 p-0 list-none">
                {filas.filter((fila) => (fila.activo || historico) && (!compacto || Number(seleccionado) === fila.id)).map((fila) => (
                    <li key={fila.id} className="theme-surface border theme-border rounded-xl p-3">
                        <label className="flex items-start gap-2">
                            {!compacto && onSeleccionar && fila.activo && <input type="radio" name="consignatario" checked={Number(seleccionado) === fila.id} onChange={() => onSeleccionar(fila.id)} />}
                            <span>
                                <span className="block font-bold theme-text-main">{fila.version?.nombre || 'Sin datos vigentes'}</span>
                                <span className="block text-sm theme-text-muted">{fila.version?.telefono}</span>
                                <span className="block text-xs theme-text-muted">{fila.documento ? `Identificación vigente, versión ${fila.documento.version}` : 'Sin identificación vigente'}</span>
                            </span>
                        </label>
                        <div className="flex flex-wrap items-center gap-2 mt-3">
                            {fila.activo && permisos.editar && <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || subiendoId !== null} onClick={() => { setAviso(''); setCapturando(true); setEditando(fila.id); setNombre(fila.version?.nombre || ''); setTelefono(fila.version?.telefono || ''); }}><Pencil size={16} aria-hidden="true" />Editar datos</button>}
                            {fila.documento && permisos.ver_identificacion && <a className={GELIA_BTN_OUTLINE} href={rutas(clienteId, pedidoId).descargar(fila.documento.id)}><Download size={16} aria-hidden="true" />Descargar identificación</a>}
                        </div>
                        {fila.activo && permisos.documentos && <div className="grid gap-2 mt-3 min-w-0">
                            <button type="button" className={`${GELIA_BTN_OUTLINE} w-full min-h-[44px]`} disabled={ocupado || subiendoId !== null} onClick={() => archivosRef.current[fila.id]?.click()}>
                                <FileUp size={18} aria-hidden="true" />
                                {subiendoId === fila.id ? 'Subiendo identificación…' : fila.documento ? 'Sustituir identificación' : 'Adjuntar identificación'}
                            </button>
                            <input ref={el => { archivosRef.current[fila.id] = el; }} className="hidden" type="file" aria-label={`Identificación de ${fila.version?.nombre || 'consignatario'}`} disabled={ocupado || subiendoId !== null} accept="image/jpeg,image/png,image/webp,application/pdf" onChange={evento => { const archivo = evento.target.files?.[0]; evento.target.value = ''; subir(fila.id, archivo); }} />
                            <p className="text-xs theme-text-muted m-0">PDF, JPG, PNG o WEBP · Hasta 10 MB.</p>
                        </div>}
                        {!pedidoId && fila.activo && permisos.eliminar && <div className="flex flex-wrap gap-2 mt-2">
                            <button type="button" className="theme-btn-secondary" onClick={() => eliminar(`/admin/clientes/${clienteId}/expediente/consignatarios/${fila.id}`)}>Dar de baja consignatario</button>
                            {fila.documento && <button type="button" className="theme-btn-secondary" onClick={() => eliminar(`/admin/clientes/${clienteId}/expediente/documentos/${fila.documento.id}`)}>Dar de baja identificación</button>}
                        </div>}
                        {historico && <details className="text-sm mt-2"><summary>Historial de datos y documentos</summary>
                            {(fila.versiones || []).map(version => <p key={version.id}>Versión {version.version}: {version.nombre} · {version.telefono}</p>)}
                            {permisos.ver_identificacion && (fila.documentos_historicos || []).map(doc => <a key={doc.id} className="block" href={rutas(clienteId, pedidoId).descargar(doc.id)}>Identificación, versión {doc.version}</a>)}
                        </details>}
                    </li>
                ))}
            </ul>
            {compacto && permisos.editar && !capturando && <button type="button" className={GELIA_BTN_OUTLINE} disabled={subiendoId !== null} onClick={() => { setAviso(''); setCapturando(true); }}><Plus size={16} aria-hidden="true" />Agregar consignatario</button>}
            {permisos.editar && capturando && <form className="grid sm:grid-cols-2 gap-3 border theme-border rounded-xl p-3" onSubmit={crear}>
                <p className="text-sm font-semibold theme-text-main m-0 sm:col-span-2">{editando ? 'Editar consignatario' : 'Nuevo consignatario'}</p>
                <label className={THEME_LABEL}>Nombre<input className={`${THEME_INPUT} w-full mt-1`} value={nombre} onChange={(evento) => setNombre(evento.target.value)} required /></label>
                <label className={THEME_LABEL}>Teléfono<input className={`${THEME_INPUT} w-full mt-1`} value={telefono} onChange={(evento) => setTelefono(evento.target.value)} required /></label>
                <button className={compacto ? GELIA_BTN_OUTLINE : THEME_BTN_PRIMARY} type="submit" disabled={ocupado}><Save size={16} aria-hidden="true" />{editando ? 'Guardar datos' : 'Guardar consignatario'}</button>
                {(editando || compacto) && <button type="button" className="theme-btn-secondary" onClick={() => { setEditando(null); setNombre(''); setTelefono(''); setCapturando(!compacto); }}><X size={16} aria-hidden="true" />{editando ? 'Cancelar edición' : 'Cancelar'}</button>}
            </form>}
        </section>
    );
}
