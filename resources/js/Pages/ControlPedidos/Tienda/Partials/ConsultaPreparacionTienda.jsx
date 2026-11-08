import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, ClipboardCheck, FileText } from 'lucide-react';
import PanelRespuestaDisponibilidad from '../../Partials/PanelRespuestaDisponibilidad';
import RespuestaPreparacionTienda from '../../Partials/RespuestaPreparacionTienda';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import VigenciaApartadoTienda from '../../Partials/VigenciaApartadoTienda';
import AjustesSolicitadosTienda from './AjustesSolicitadosTienda';
import { BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import { THEME_LABEL, THEME_TEXTAREA } from '../../../../utils/geliaTheme';

/** Consulta documental: responder → esperar a Ventas → confirmar una sola separación del conjunto. */
export default function ConsultaPreparacionTienda({ tarea, auth, documentos = [], documentosTarea = [], onEnviar, bloqueado = false, onDirtyChange }) {
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [vista, setVista] = useState(null);
    const [capturaDirty, setCapturaDirty] = useState(false);
    const [ajustesDirty, setAjustesDirty] = useState(false);
    const [reportar, setReportar] = useState(false);
    const [motivo, setMotivo] = useState('');
    const pedido = tarea.pedido || {};
    const revision = pedido.revision_disponibilidad;
    const cobertura = pedido.cobertura_tienda || {};
    const permisos = auth?.user?.permissions || [];
    const admin = auth?.user?.roles?.includes('Super Admin');
    const autorizado = tarea.puede_preparar ?? (Number(tarea.responsable?.id) === Number(auth?.user?.id) && (admin || permisos.includes('control_pedidos.tienda.apartado.separar') || permisos.includes('control_pedidos.tienda.responder')));
    const enAtencion = tarea.estado === 'EN_ATENCION';
    const confirmado = ['APARTADO_CONFIRMADO', 'ENTREGADA', 'DEVUELTA_ANAQUEL'].includes(tarea.apartado?.estado);
    const actualizacion = Boolean(revision?.actualizacion_pendiente || pedido.anexos_pendientes?.length);
    const ajustes = actualizacion || (pedido.retiros || []).some(r => (r.estado || 'pendiente') === 'pendiente');
    const vigente = revision?.aceptacion_vigente !== false && revision?.vigente !== false && !revision?.sustituida_at;
    const vence = tarea.fecha_limite || tarea.apartado?.vence_at;
    const horariosActivos = pedido.horarios_activos !== false;
    const vencida = horariosActivos && Boolean(vence && new Date(vence).getTime() <= Date.now());
    const ocupado = procesando || bloqueado;
    const esperaConfirmacion = Boolean(cobertura.requiere_confirmacion_pedido && !cobertura.confirmacion_enviada_at);
    const puedeSeparar = enAtencion && autorizado && !confirmado && Boolean(revision?.estado) && vigente && !ajustes && pedido.consulta_cerrada && !esperaConfirmacion;
    const inicial = !revision?.estado && !actualizacion;
    useEffect(() => { onDirtyChange?.(capturaDirty || ajustesDirty || Boolean(motivo)); }, [capturaDirty, ajustesDirty, motivo, onDirtyChange]);

    const enviar = (url, payload) => new Promise((resolve, reject) => {
        if (ocupado) { reject(new Error('Hay una operación en curso.')); return; }
        setProcesando(true);
        setError('');
        const opciones = {
            preserveScroll: true,
            onSuccess: () => { setCapturaDirty(false); setMotivo(''); setReportar(false); resolve(); },
            onError: errors => {
                const mensaje = Object.values(errors).flat().join(' ');
                setError(mensaje);
                reject({ response: { data: { errors } } });
            },
            onFinish: () => { setProcesando(false); reject(new Error('No se pudo guardar la preparación.')); },
        };
        if (onEnviar) onEnviar(url, payload, opciones);
        else axios.post(url, payload, { headers: { Accept: 'application/json' } }).then(() => {
            opciones.onSuccess();
            router.reload({ preserveScroll: true });
        }, e => {
            opciones.onError(e.response?.data?.errors || { preparacion: e.response?.data?.message || 'No se pudo guardar la preparación.' });
        }).finally(opciones.onFinish);
    });
    const confirmar = () => enviar(route('control_pedidos.tienda.apartado.confirmar', tarea.id), { version: tarea.apartado?.version, lineas: [] }).catch(() => {});
    const reportarError = () => {
        if (!motivo.trim()) { setError('Describa qué necesita resolver Ventas.'); return; }
        enviar(route('control_pedidos.tienda.reportar_incidencia', tarea.id), { version: tarea.version, tipo_incidencia: 'producto_no_encontrado', motivo: motivo.trim(), almacen_solicitado_id: tarea.almacen?.id }).catch(() => {});
    };
    const fotosGuardadas = documentosTarea.filter(d => ['evidencia_general', 'foto_apartado'].includes(d.tipo_evidencia) && !d.pedido_bma_tarea_producto_id && (String(d.mime_type || '').startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(d.nombre_original || '')));
    const referencias = documentos.map(d => ({ ...d, url: d.url || pedido.documentos?.find(doc => doc.id === d.id)?.url }));
    const espera = ajustes ? 'Hay cambios de Ventas por responder. Revise la actualización de consulta.'
        : !pedido.consulta_cerrada ? 'Ventas está confirmando las piezas con el cliente. Espere el cierre de la consulta.'
            : esperaConfirmacion ? 'Espere a que Ventas confirme la modalidad y envíe la confirmación del pedido.'
                : !vigente ? 'La respuesta debe actualizarse antes de confirmar el apartado.' : null;

    return <section className="gelia-preparacion" aria-label="Preparación en tienda">
        {!inicial && !actualizacion && <header className="gelia-preparacion-contexto">
            <div className="min-w-0"><h3 className="text-sm font-semibold m-0">{confirmado ? 'Apartado en tienda' : 'Preparación en tienda'}</h3><dl className="gelia-preparacion-meta"><div><dt className="shrink-0">Cliente</dt><dd>{pedido.cliente_nombre || 'Sin cliente'}</dd></div><div><dt className="shrink-0">Almacén</dt><dd>{tarea.almacen?.nombre || 'No asignado'}</dd></div></dl></div>
            <button type="button" className={BTN_SECONDARY} disabled={!referencias.length} onClick={() => setVista({ documentos: referencias, indice: 0 })}><FileText size={16} aria-hidden />Ver pedido</button>
        </header>}
        {error && <p role="alert" className="gelia-preparacion-aviso">{error}</p>}
        {tarea.observaciones_solicitud && <aside className="gelia-preparacion-instrucciones"><h4>Indicaciones de Ventas</h4><p>{tarea.observaciones_solicitud}</p></aside>}
        {tarea.estado === 'PENDIENTE' && <p role="status" className="text-sm theme-text-muted px-4">Seleccione «Atender pedido» para comenzar la captura.</p>}
        {enAtencion && !autorizado && <p role="status" className="text-sm theme-text-muted px-4">Esta tarea está asignada a {tarea.responsable?.name || 'otro auxiliar'}. Solo su responsable puede responder.</p>}
        {inicial ? <PanelRespuestaDisponibilidad origen="Tienda" tomada={enAtencion} puedeResponder={autorizado && !ocupado && !reportar}
            pedido={{ ...pedido, cliente: { nombre: pedido.cliente_nombre }, almacen: tarea.almacen, documentos: pedido.documentos || referencias, solicitud_preparacion: pedido }}
            documentos={referencias} evidenciasGuardadas={fotosGuardadas} onDirtyChange={setCapturaDirty}
            onEnviar={form => enviar(route('control_pedidos.tienda.disponibilidad', tarea.id), form)} /> : <div className="grid gap-4 p-3 md:p-4">
            {!actualizacion && tarea.respuesta && <RespuestaPreparacionTienda mostrarSeguimientoEntrega={false} tarea={tarea} onVerDocumentos={(documentos, indice = 0) => setVista({ documentos, indice })} />}
            <AjustesSolicitadosTienda tarea={tarea} auth={auth} ocupado={ocupado} onEnviar={onEnviar} onDirtyChange={setAjustesDirty} confirmarConjunto={!confirmado} />
            {!confirmado && espera && <aside className="gelia-preparacion-instrucciones" role="status"><h4>{ajustes ? 'Actualización pendiente' : 'Respuesta enviada'}</h4><p>{espera}</p></aside>}
            {horariosActivos && !confirmado && cobertura.tipo === 'apartado' && <VigenciaApartadoTienda venceAt={vence} />}
            {puedeSeparar && <section className="registrar-tienda-panel grid gap-3" aria-label="Confirmación del apartado">
                <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden /><h3>Pedido aprobado por Ventas</h3></div>
                <p className="text-sm theme-text-main m-0">Separe el conjunto del pedido vigente, incluidos sus anexos y retiros ya resueltos. Confirme una sola vez cuando esté listo.</p>
                {vencida && <div role="alert" className="gelia-preparacion-aviso"><AlertTriangle size={20} className="shrink-0" aria-hidden /><div className="min-w-0"><strong>Vigencia del apartado terminada</strong><p className="m-0">Límite registrado: {new Date(vence).toLocaleString('es-MX', { timeZone: pedido.zona_horaria_operativa || 'America/Mexico_City' })}. {pedido.detalle_horario_apartado}</p></div></div>}
                <button type="button" className={`${BTN_PRIMARY} justify-self-start`} disabled={ocupado || vencida || reportar} onClick={confirmar}><CheckCircle2 size={18} aria-hidden />{procesando ? 'Guardando…' : 'Confirmar pedido separado'}</button>
            </section>}
            {confirmado && !tarea.respuesta && <p role="status" className="text-sm theme-text-main">Pedido completo conforme a la consulta aprobada · Apartado confirmado.</p>}
        </div>}
        {enAtencion && autorizado && !confirmado && (tarea.puede_reportar_incidencia ?? (admin || permisos.includes('control_pedidos.tienda.reportar_error'))) && <div className="px-3 pb-3 md:px-4">
            {!reportar ? <button type="button" className={BTN_SECONDARY} disabled={ocupado || capturaDirty || ajustesDirty} onClick={() => setReportar(true)}><AlertTriangle size={16} aria-hidden />Reportar error</button> : <section className="registrar-tienda-panel grid gap-3">
                <label htmlFor="consulta-tienda-error" className={THEME_LABEL}>Qué necesita resolver Ventas<textarea id="consulta-tienda-error" className={`${THEME_TEXTAREA} w-full mt-1`} rows={3} maxLength={2000} value={motivo} disabled={ocupado} onChange={e => setMotivo(e.target.value)} /></label>
                <div className="flex flex-wrap gap-2"><button type="button" className={BTN_PRIMARY} disabled={ocupado} onClick={reportarError}>Enviar reporte a Ventas</button><button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { setReportar(false); setMotivo(''); }}>Cancelar reporte</button></div>
            </section>}
        </div>}
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} {...vista} onClose={() => setVista(null)} />
    </section>;
}
