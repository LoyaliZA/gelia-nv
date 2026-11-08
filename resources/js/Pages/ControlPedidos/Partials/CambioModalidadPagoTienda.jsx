import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { LockKeyhole, Pencil, Send, ShieldCheck, X } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';

const NOMBRES = { apartado: 'Pago al recoger', transferencia: 'Transferencia', credito: 'Crédito' };
const mensaje = error => Object.values(error?.response?.data?.errors || {}).flat()[0] || error?.response?.data?.message || 'No se pudo consultar o guardar el cambio de modalidad.';

export default function CambioModalidadPagoTienda({ pedidoId, solicitud, onSolicitud, onPendiente }) {
    const [estado, setEstado] = useState(null);
    const [error, setError] = useState('');
    const [revision, setRevision] = useState(0);
    const [abierto, setAbierto] = useState(false);
    const [ocupado, setOcupado] = useState(false);
    const [cobertura, setCobertura] = useState('');
    const [mismoDia, setMismoDia] = useState(false);
    const [motivo, setMotivo] = useState('');
    const clave = useRef(null);
    const sello = JSON.stringify([solicitud?.cobertura_tienda, solicitud?.cambio_cobertura_tienda]);
    useEffect(() => {
        let vigente = true;
        setEstado(null); setError('');
        axios.get(`/control-pedidos/${pedidoId}/cambio-cobertura-tienda`, { headers: { Accept: 'application/json' } })
            .then(({ data }) => { if (vigente) setEstado(data); })
            .catch(e => { if (vigente) setError(mensaje(e)); });
        return () => { vigente = false; };
    }, [pedidoId, sello, revision]);
    useEffect(() => { onPendiente?.(abierto || ocupado); return () => onPendiente?.(false); }, [abierto, ocupado, onPendiente]);
    const pendiente = estado?.solicitud?.estado === 'pendiente' ? estado.solicitud : null;
    const enviar = async accion => {
        if (ocupado || !estado || motivo.trim().length < 5) return;
        setOcupado(true); setError('');
        if (accion === 'solicitar' && !clave.current) clave.current = uuidSolicitud();
        try {
            const { data } = await axios.post(`/control-pedidos/${pedidoId}/cambio-cobertura-tienda`, {
                accion, solicitud_id: accion === 'solicitar' ? clave.current : pendiente.id,
                motivo: motivo.trim(),
                ...(accion === 'solicitar' ? { version: estado.version, cobertura, mismo_dia: mismoDia } : {}),
            }, { headers: { Accept: 'application/json' } });
            setEstado(data.estado_cobertura); onSolicitud?.(data.solicitud_preparacion);
            setAbierto(false); setMotivo(''); clave.current = null;
        } catch (e) { setError(mensaje(e)); }
        finally { setOcupado(false); }
    };
    const abrir = () => { setAbierto(true); setCobertura(solicitud.cobertura_tienda.tipo); setMismoDia(solicitud.atencion_tienda === 'prevista'); setMotivo(''); clave.current = null; };
    const distinta = cobertura !== solicitud.cobertura_tienda.tipo || mismoDia !== (solicitud.atencion_tienda === 'prevista');
    return <div className="grid gap-3">
        <p className="flex items-start gap-2 text-sm theme-text-main m-0"><LockKeyhole size={18} className="shrink-0 mt-0.5" aria-hidden />Modalidad guardada. Los cambios requieren motivo y autorización de otra persona.</p>
        {!estado && !error && <p role="status" className="text-sm theme-text-muted m-0">Consultando control de cambios…</p>}
        {error && <div role="alert" className="grid gap-2 text-sm theme-text-main"><p className="m-0">{error}</p>{!estado && <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setRevision(n => n + 1)}>Reintentar consulta</button>}</div>}
        {estado?.bloqueo_aplicacion && <p className="text-sm theme-text-muted m-0">{estado.bloqueo_aplicacion}</p>}
        {pendiente && <div className="rounded-xl border theme-border theme-element p-3 grid gap-2" role="status">
            <p className="font-semibold text-sm theme-text-main m-0">Cambio pendiente de autorización</p>
            <p className="text-sm theme-text-main m-0">{NOMBRES[pendiente.anterior.tipo]} → {NOMBRES[pendiente.cobertura]}{pendiente.mismo_dia ? ' · Mismo día' : ''}</p>
            <p className="text-sm theme-text-main m-0 break-words">{pendiente.motivo}</p>
            <p className="text-xs theme-text-muted m-0">Solicitó {pendiente.solicitada_por}. La modalidad vigente continúa hasta aplicar el cambio.</p>
            {estado.puede_autorizar && <>
                <label className={THEME_LABEL} htmlFor={`resolucion-modalidad-${pedidoId}`}>Motivo de la resolución</label>
                <textarea id={`resolucion-modalidad-${pedidoId}`} className={THEME_TEXTAREA} maxLength={500} value={motivo} disabled={ocupado} onChange={e => setMotivo(e.target.value)} />
                <div className="flex flex-wrap gap-2">
                    <button type="button" className={THEME_BTN_PRIMARY} disabled={ocupado || motivo.trim().length < 5 || Boolean(estado.bloqueo_aplicacion)} onClick={() => enviar('autorizar')}><ShieldCheck size={16} aria-hidden />Autorizar y aplicar cambio</button>
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || motivo.trim().length < 5} onClick={() => enviar('rechazar')}><X size={16} aria-hidden />Rechazar cambio</button>
                </div>
            </>}
        </div>}
        {!pendiente && estado?.puede_solicitar && !abierto && <button type="button" className={`${GELIA_BTN_OUTLINE} registrar-tienda-guardar-modalidad justify-self-start min-h-[44px]`} onClick={abrir}><Pencil size={16} aria-hidden />Solicitar cambio de modalidad</button>}
        {abierto && <div className="grid gap-3 rounded-xl border theme-border theme-element p-3">
            <label className={THEME_LABEL} htmlFor={`nueva-modalidad-${pedidoId}`}>Modalidad solicitada</label>
            <select id={`nueva-modalidad-${pedidoId}`} className={THEME_SELECT} value={cobertura} disabled={ocupado} onChange={e => { setCobertura(e.target.value); if (e.target.value !== 'transferencia') setMismoDia(false); clave.current = null; }}>
                {Object.entries(NOMBRES).map(([valor, nombre]) => <option key={valor} value={valor}>{nombre}</option>)}
            </select>
            {cobertura === 'transferencia' && <label className="flex gap-2 text-sm theme-text-main"><input type="checkbox" className="gelia-checkbox" checked={mismoDia} disabled={ocupado} onChange={e => { setMismoDia(e.target.checked); clave.current = null; }} />Solicitar Mismo día</label>}
            <label className={THEME_LABEL} htmlFor={`motivo-modalidad-${pedidoId}`}>Motivo del cambio</label>
            <textarea id={`motivo-modalidad-${pedidoId}`} className={THEME_TEXTAREA} value={motivo} maxLength={500} disabled={ocupado} onChange={e => { setMotivo(e.target.value); clave.current = null; }} />
            <p className="text-sm theme-text-muted m-0">La solicitud no cambia pagos, documentos ni mercancía. La autorización comprueba de nuevo el estado del pedido.</p>
            <div className="flex flex-wrap gap-2"><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || !distinta || motivo.trim().length < 5} onClick={() => enviar('solicitar')}><Send size={16} aria-hidden />Enviar solicitud de cambio</button><button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado} onClick={() => { setAbierto(false); clave.current = null; }}><X size={16} aria-hidden />Cancelar</button></div>
        </div>}
        {Boolean(estado?.historial?.length) && <details className="text-sm theme-text-main"><summary className="cursor-pointer">Historial de cambios de modalidad</summary><div className="grid gap-3 mt-3">{estado.historial.map(item => <div key={item.id} className="rounded-xl border theme-border p-3"><strong>{item.estado === 'aplicada' ? 'Cambio autorizado y aplicado' : 'Cambio rechazado'}</strong><p className="my-1">{NOMBRES[item.anterior.tipo]} → {NOMBRES[item.cobertura]}{item.mismo_dia ? ' · Mismo día' : ''}</p><p className="my-1 break-words">{item.motivo}</p><p className="my-1 break-words">{item.motivo_resolucion}</p><p className="text-xs theme-text-muted m-0">{item.solicitada_por} solicitó · {item.resuelta_por} resolvió · {item.resuelta_at}</p></div>)}</div></details>}
    </div>;
}
