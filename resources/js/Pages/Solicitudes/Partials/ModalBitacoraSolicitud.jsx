import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import React, { useState } from 'react';
import EvidenciaSolicitud from '@/Components/Solicitudes/EvidenciaSolicitud';
import { eventosHistorial, leerSnapshotSeguro, cotizacionHistorial } from './solicitudHistorial';
import { X, ShieldCheck, CheckCircle2, TrendingUp, Server, AlertOctagon, CreditCard, MessageSquare, ChevronDown, ArrowRight, Minus } from 'lucide-react';

const money = (v) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v || 0);

const ComparativaSnapshot = ({
    antes,
    despues,
    etiquetaIzq = 'Antes',
    etiquetaDer = 'Después',
    titulo = 'Comparativa de cambios en cliente',
}) => {
    if (!antes && !despues) return null;

    const filas = [
        ['Acumulado de venta', 'monto_venta', money],
        ['Lista', 'lista_nombre', value => value || 'Sin lista'],
        ['TAG (vendedora)', 'tag_vendedor_nombre', value => value || 'Sin asignar'],
        ['Tipo de cliente', 'tipo_cliente_nombre', value => value || 'Sin tipo'],
    ];
    const mostrar = (valor, formato) => valor === undefined ? 'No registrado' : valor === null ? 'Sin asignar' : formato(valor);
    return <div className="gelia-tag-comparativa mb-4">
        <p className="text-sm font-medium theme-text-main mb-3">{titulo}</p>
        <table className="w-full text-xs"><caption className="sr-only">{titulo}</caption><thead><tr><th scope="col" className="text-left theme-text-muted">Dato</th><th scope="col" className="theme-text-muted">{etiquetaIzq}</th><th scope="col"><span className="sr-only">Transición</span></th><th scope="col" className="theme-text-muted">{etiquetaDer}</th></tr></thead><tbody>
            {filas.map(([label, key, formato]) => {
                const conocido = antes?.[key] !== undefined && despues?.[key] !== undefined;
                const cambio = conocido && String(antes[key] ?? '') !== String(despues[key] ?? '');
                const Icono = conocido && !cambio ? Minus : ArrowRight;
                return <tr key={key} className="border-t theme-border" data-changed={cambio}><th scope="row" className="text-left font-medium theme-text-muted">{label}</th><td className="theme-text-main">{mostrar(antes?.[key], formato)}</td><td><Icono className="w-4 h-4 theme-text-muted" aria-hidden="true" /><span className="sr-only">{conocido && !cambio ? 'Sin cambio' : 'Pasa a'}</span></td><td className={cambio ? 'theme-text-primario font-semibold' : 'theme-text-main'}>{mostrar(despues?.[key], formato)}</td></tr>;
            })}
        </tbody></table>
    </div>;
};

const describirPaso = (registro) => {
    const motivo = (registro.motivo_reporte || '').toUpperCase();
    const nuevo = registro.estado_nuevo?.nombre || registro.estadoNuevo?.nombre || '';
    const anterior = registro.estado_anterior?.nombre || registro.estadoAnterior?.nombre || '';
    const esSistema = motivo.includes('SISTEMA AUTOMÁTICO') || motivo.includes('AUTOMÁTICAMENTE');
    const esCreacion = (!anterior && nuevo === 'Pendiente') || motivo.includes('CREACIÓN');
    const esPago = motivo.includes('PAGO CONFIRMADO') || motivo.includes('ALERTA DE PAGO') || motivo.includes('ALERTA DE ASCENSO');

    if (motivo.includes('PLAZO DE PAGO') || motivo.includes('PAGO RECHAZADO')) {
        return { titulo: 'Pago vencido', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : nuevo || 'Incorrecta', tono: 'error', esSistema: true, modoComparativa: 'default' };
    }
    if (motivo.includes('PAGO CONFIRMADO')) {
        return { titulo: 'Pago confirmado', detalle: 'Vendedora registró el pago', tono: 'pago', esSistema: false, modoComparativa: 'pago' };
    }
    if (motivo.includes('ALERTA DE PAGO')) {
        return { titulo: 'Alerta: pago insuficiente', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : nuevo, tono: 'alerta', esSistema: false, modoComparativa: 'pago' };
    }
    if (motivo.includes('ALERTA DE ASCENSO')) {
        return { titulo: 'Alerta: ascenso de lista', detalle: 'Pago permite subir de categoría', tono: 'alerta', esSistema: false, modoComparativa: 'pago' };
    }
    if (motivo.includes('CAMBIO DE LISTA CONFIRMADO')) {
        return { titulo: 'Ajuste de lista confirmado', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : nuevo, tono: 'ok', esSistema: false, modoComparativa: 'default' };
    }
    if (motivo.includes('CORRIGIÓ') || motivo.includes('REPAR')) {
        return { titulo: 'Solicitud reparada', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : 'Vuelve a revisión', tono: 'info', esSistema: false, modoComparativa: 'creacion' };
    }
    if (motivo.includes('REVERSIÓN CONFIRMADA') || motivo.includes('ROLLBACK')) {
        return { titulo: 'Reversión confirmada', detalle: 'Cierre por vencimiento', tono: 'error', esSistema: false, modoComparativa: 'default' };
    }
    if (motivo.includes('CANCEL')) {
        return { titulo: nuevo === 'Cancelada' ? 'Solicitud cancelada' : 'Cancelación solicitada', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : nuevo, tono: 'error', esSistema: false, modoComparativa: 'default' };
    }
    if (esSistema) {
        return { titulo: 'Acción del sistema', detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : (nuevo || 'Actualización'), tono: 'sistema', esSistema: true, modoComparativa: 'default' };
    }

    switch (nuevo) {
        case 'Pendiente':
            return {
                titulo: anterior === 'Incorrecta' ? 'Reenviada a revisión' : 'Solicitud creada',
                detalle: anterior ? `${anterior} → Pendiente` : 'Pendiente de respuesta',
                tono: 'info',
                esSistema: false,
                modoComparativa: esCreacion ? 'creacion' : 'default',
            };
        case 'Respondida':
            return { titulo: anterior === 'Incorrecta' ? 'Respuesta a corrección' : 'Proceso aprobado', detalle: `${anterior || '—'} → Respondida`, tono: 'ok', esSistema: false, modoComparativa: 'aprobacion' };
        case 'Verificada':
            return { titulo: 'Solicitud verificada', detalle: `${anterior || '—'} → Verificada`, tono: 'ok', esSistema: false, modoComparativa: 'default' };
        case 'Incorrecta':
            return { titulo: 'Error reportado', detalle: `${anterior || '—'} → Incorrecta`, tono: 'error', esSistema: false, modoComparativa: 'default' };
        case 'Cancelada':
            return { titulo: 'Solicitud cancelada', detalle: `${anterior || '—'} → Cancelada`, tono: 'error', esSistema: false, modoComparativa: 'default' };
        default:
            return {
                titulo: 'Actualización',
                detalle: anterior && nuevo ? `${anterior} → ${nuevo}` : (nuevo || 'Sin cambio de estado'),
                tono: 'info',
                esSistema: false,
                modoComparativa: esPago ? 'pago' : 'default',
            };
    }
};

const etiquetasComparativa = (modo) => {
    if (modo === 'creacion') {
        return { izq: 'Antes de solicitar', der: 'Cotizado / solicitado', titulo: 'Propuesta de ventas' };
    }
    if (modo === 'aprobacion') {
        return { izq: 'Antes de aprobar', der: 'Aplicado al aprobar', titulo: 'Cambios aplicados al cliente' };
    }
    if (modo === 'pago') {
        return { izq: 'Antes del pago', der: 'Confirmado con pago', titulo: 'Cambios tras confirmar pago' };
    }
    return { izq: 'Antes', der: 'Después', titulo: 'Comparativa de cambios en cliente' };
};

const estilosPaso = {
    ok: { iconBg: 'bg-emerald-500 text-white', border: 'border-emerald-500/30', label: 'text-emerald-600 dark:text-emerald-400', Icon: CheckCircle2 },
    error: { iconBg: 'bg-red-500 text-white', border: 'border-red-500/30', label: 'text-red-600 dark:text-red-400', Icon: AlertOctagon },
    alerta: { iconBg: 'bg-amber-500 text-white', border: 'border-amber-500/30', label: 'text-amber-600 dark:text-amber-400', Icon: TrendingUp },
    pago: { iconBg: 'bg-blue-500 text-white', border: 'border-blue-500/30', label: 'text-blue-600 dark:text-blue-400', Icon: CreditCard },
    sistema: { iconBg: 'bg-slate-500 text-white', border: 'border-slate-500/30', label: 'text-slate-500', Icon: Server },
    info: { iconBg: 'bg-purple-500 text-white', border: 'theme-border', label: 'text-purple-600 dark:text-purple-400', Icon: ShieldCheck },
};

export default function ModalBitacoraSolicitud({ onClose, solicitud, listas = [], tiposCliente = [], procesos = [] }) {
    const [orden, setOrden] = useState('reciente');
    if (!solicitud) return null;
    const objLista = solicitud.lista_descuento || solicitud.listaDescuento;
    const objTipo = solicitud.tipo_cliente || solicitud.tipoCliente;
    const eventos = eventosHistorial(solicitud, orden);
    const fecha = value => value && !Number.isNaN(Date.parse(value)) ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Sin fecha';
    return (
        <SolicitudDialog onClose={onClose} title={`Bitácora de FOL-${solicitud.id}`} className="gelia-tag-lista-overlay">
            <div className="gelia-modal-shell gelia-tag-bitacora w-full max-w-6xl">
                <header className="gelia-workflow-header">
                    <div><h2 className="m-0 theme-text-main">Bitácora de la solicitud</h2><p className="m-0 mt-1 text-sm theme-text-muted">FOL-{solicitud.id} · {solicitud.cliente?.nombre || 'Cliente'}</p></div>
                    <button type="button" data-dialog-close className="gelia-workflow-close" aria-label="Cerrar bitácora"><X className="w-5 h-5" aria-hidden="true" /></button>
                </header>
                <div className="gelia-modal-body gelia-tag-history-layout">
                    <aside className="gelia-tag-history-summary">
                        <h3 className="m-0 text-base theme-text-main">Resumen actual</h3>
                        <dl className="gelia-respuesta-contexto mt-4">
                            {[['Proceso', solicitud.proceso?.nombre], ['Estado', solicitud.estado?.nombre], ['Responsable', solicitud.vendedor?.name], ['Cotización', money(solicitud.monto_cotizado)], ['Tipo solicitado', objTipo?.nombre || 'Mantener actual'], ['Lista solicitada', objLista?.nombre || 'Mantener actual']].map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value || '—'}</dd></div>)}
                        </dl>
                        <div className="space-y-3 mt-5 text-sm theme-text-muted">
                            {solicitud.pago_confirmado && <p className="theme-text-exito">Pago confirmado</p>}
                            {solicitud.compra_en_tienda && <p>Compra en tienda</p>}
                            {solicitud.compra_en_tienda_solo_tag && <p>Compra realizada: solicitar TAG</p>}
                            {solicitud.monto_final_tentativo != null && <p>Pago tentativo: {money(solicitud.monto_final_tentativo)}</p>}
                            {solicitud.total_proyectado_neto != null && <p>Total neto proyectado: {money(solicitud.total_proyectado_neto)}</p>}
                            {solicitud.confirmo_informacion_escalonamiento && <p>Ventas confirmó haber informado al cliente que no alcanza el siguiente nivel.</p>}
                            {solicitud.motivo_incorrecta && <p className="theme-text-peligro">Incidencia: {solicitud.motivo_incorrecta.replaceAll('_', ' ')}</p>}
                        </div>
                        {solicitud.observaciones_vendedor && <section className="gelia-tag-message mt-5"><h3>Comentario de ventas</h3><p>{solicitud.observaciones_vendedor}</p></section>}
                        {solicitud.motivo_cancelacion && <section className="gelia-tag-message mt-5" data-tone="peligro"><h3>{solicitud.estado?.nombre === 'Cancelada' ? 'Motivo de cancelación' : 'Cancelación solicitada'}</h3><p>{solicitud.motivo_cancelacion}</p>{(solicitud.lista_rebaja || solicitud.listaRebaja)?.nombre && <p>Lista de rebaja: {(solicitud.lista_rebaja || solicitud.listaRebaja).nombre}</p>}</section>}
                        {solicitud.evidencia_path && <div className="mt-4"><EvidenciaSolicitud path={solicitud.evidencia_path} title="Evidencia de la solicitud" dialogClassName="gelia-tag-lista-overlay" /></div>}
                    </aside>
                    <section className="gelia-tag-history-events" aria-label="Cronología de la solicitud">
                        <div className="flex flex-wrap items-center justify-between gap-3 mb-5"><h3 className="m-0 text-base theme-text-main">Actividad <span className="theme-text-muted font-normal">({eventos.length})</span></h3><label className="flex items-center gap-2 text-xs theme-text-muted">Orden<select name="orden-bitacora" value={orden} onChange={event => setOrden(event.target.value)} className="theme-select text-sm"><option value="reciente">Más reciente primero</option><option value="antiguo">Desde el inicio</option></select></label></div>
                        {!eventos.length && <p role="status" className="text-sm theme-text-muted">Aún no hay actividad registrada.</p>}
                        <ol className="gelia-tag-timeline">
                            {eventos.map(evento => {
                                const registro = evento.registro;
                                const audit = evento.tipo === 'auditoria';
                                const respuesta = evento.tipo === 'respuesta';
                                const paso = audit ? describirPaso(registro) : { titulo: respuesta ? (registro.respuesta_positiva ? 'Consulta confirmada' : 'Consulta rechazada') : 'Consulta enviada', tono: respuesta ? (registro.respuesta_positiva ? 'ok' : 'error') : 'alerta', detalle: [registro.consulta_tag && 'TAG', registro.consulta_lista && 'Lista'].filter(Boolean).join(' y ') };
                                const Icono = audit ? (estilosPaso[paso.tono] || estilosPaso.info).Icon : MessageSquare;
                                const snapshot = audit ? leerSnapshotSeguro(registro.datos_snapshot) : null;
                                const autor = audit ? (paso.esSistema ? 'Sistema' : registro.usuario?.name || 'Usuario') : respuesta ? registro.encargada?.name || 'Supervisión' : registro.vendedor?.name || 'Ventas';
                                const mensaje = audit ? registro.motivo_reporte : respuesta ? registro.comentario_encargada : registro.comentario_vendedor;
                                const labels = etiquetasComparativa(paso.modoComparativa);
                                const despues = paso.modoComparativa === 'creacion' ? cotizacionHistorial(snapshot, registro, { listas, tiposCliente, procesos }) : snapshot?.despues;
                                const evidencia = audit ? snapshot?.evidencia_respuesta_path || snapshot?.evidencia_path : respuesta ? registro.evidencia_respuesta_path : null;
                                const lista = snapshot?.lista_descuento_id ? listas.find(l => String(l.id) === String(snapshot.lista_descuento_id))?.nombre : null;
                                const tipo = snapshot?.tipo_cliente_id ? tiposCliente.find(t => String(t.id) === String(snapshot.tipo_cliente_id))?.nombre : null;
                                return <li key={evento.key} className="gelia-tag-event" data-tone={paso.tono}>
                                    <span className="gelia-tag-event-icon"><Icono className="w-4 h-4" aria-hidden="true" /></span>
                                    <div className="gelia-tag-event-content"><div className="flex flex-wrap justify-between items-start gap-2"><h4 className="m-0 text-sm font-semibold theme-text-main">{paso.titulo}</h4><time dateTime={evento.fecha} className="text-xs theme-text-muted">{evento.fechaEsActualizacion ? 'Actualizada: ' : ''}{fecha(evento.fecha)}</time></div><p className="text-xs theme-text-muted mt-1 mb-3">{autor} · {paso.detalle}</p>
                                        {mensaje && <p className="gelia-tag-event-message">{mensaje}</p>}
                                        {evidencia && <div className="mt-3"><EvidenciaSolicitud path={evidencia} dialogClassName="gelia-tag-lista-overlay" /></div>}
                                        {audit && snapshot?.evidencia_path && snapshot?.evidencia_respuesta_path && snapshot.evidencia_path !== snapshot.evidencia_respuesta_path && <div className="mt-2"><EvidenciaSolicitud path={snapshot.evidencia_path} label="Ver evidencia de la solicitud" dialogClassName="gelia-tag-lista-overlay" /></div>}
                                        {snapshot && (snapshot.antes || snapshot.despues || snapshot.monto_cotizado != null || lista || tipo || snapshot.compra_en_tienda || snapshot.compra_en_tienda_solo_tag) && <details className="gelia-tag-event-details"><summary>{paso.modoComparativa === 'creacion' ? 'Ver cotización solicitada' : 'Ver datos y cambios del registro'}<ChevronDown className="w-4 h-4" aria-hidden="true" /></summary><div>
                                            {snapshot.monto_cotizado != null && <p className="text-sm theme-text-muted mb-3">Monto cotizado: <strong className="theme-text-main tabular-nums">{money(snapshot.monto_cotizado)}</strong></p>}
                                            {paso.modoComparativa === 'creacion' && <p className="text-xs theme-text-muted mb-3">Propuesta de ventas; aún no aplicada al cliente. El acumulado cotizado es una proyección.</p>}
                                            {(snapshot.antes || snapshot.despues) && <ComparativaSnapshot antes={snapshot.antes} despues={despues} etiquetaIzq={labels.izq} etiquetaDer={labels.der} titulo={labels.titulo} />}
                                            {lista && !despues && <p className="text-sm theme-text-main">Lista: {lista}</p>}{tipo && !despues && <p className="text-sm theme-text-main">Tipo de cliente: {tipo}</p>}
                                            {snapshot.compra_en_tienda && <p className="text-sm theme-text-muted">Compra en tienda</p>}{snapshot.compra_en_tienda_solo_tag && <p className="text-sm theme-text-muted">Compra realizada: solicitar TAG</p>}
                                        </div></details>}
                                    </div>
                                </li>;
                            })}
                        </ol>
                    </section>
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions"><button type="button" data-dialog-close className="theme-btn-secondary">Cerrar bitácora</button></footer>
            </div>
        </SolicitudDialog>
    );
}
