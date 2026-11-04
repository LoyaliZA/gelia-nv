// Componentes reales con datos ficticios y transporte local simulado. No utiliza la base operativa.
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import axios from 'axios';
import '../../../resources/css/app.css';
import FormularioRegistrarTienda from '../../../resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda';
import ModalPreparacionTienda from '../../../resources/js/Pages/ControlPedidos/Tienda/Partials/ModalPreparacionTienda';
import PanelRespuestaDisponibilidad from '../../../resources/js/Pages/ControlPedidos/Partials/PanelRespuestaDisponibilidad';
import ModalRevisarPedido from '../../../resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido';
const params = new URLSearchParams(location.search);
const vista = params.get('vista') || 'importes';
if (params.get('tema') === 'oscuro') document.documentElement.classList.add('dark');
if (params.has('personalizado')) {
    document.documentElement.style.setProperty('--color-primario', '#259d84');
    document.documentElement.style.setProperty('--font-principal', 'Arial, sans-serif');
}
const documentoUrl = '/scripts/qa/tienda-recorrido/pedido.svg';
const documento = { id: 1, tipo: 'pdf_pedido', url: documentoUrl, nombre_original: 'Pedido de ejemplo.png', mime_type: 'image/png', activo: true };
const auth = { user: { id: 10, departamento_id: 1, name: 'Auxiliar de prueba', roles: ['Super Admin'], permissions: ['control_pedidos.tienda.responder', 'control_pedidos.tienda.apartado.separar', 'control_pedidos.auditar'] } };
const almacen = { id: 2, nombre: 'Sucursal Centro', activo: true };
const revision = { estado: 'sin_excepciones', respondida_at: '2026-11-04T16:00:00Z', aceptacion_vigente: true };
const solicitud = { version: 1, modalidad_entrega: 'tienda', caso_tienda: 'revisar_apartar', area_revision: 'preparacion_tienda', almacen_revision_id: 2, codigo_modalidad: 'RECOGE_TIENDA_TRANSFERENCIA', revision_disponibilidad: revision, cobertura_tienda: { tipo: 'transferencia' } };
if (vista === 'solicitud') { delete solicitud.revision_disponibilidad; delete solicitud.cobertura_tienda; solicitud.codigo_modalidad = 'RECOGE_TIENDA'; }
const tarea = { estado_label: 'En atención', id: 4, version: 1, estado: 'EN_ATENCION', puede_preparar: true, puede_responder: true, responsable: { id: 10 }, almacen, modalidad: { codigo: 'RECOGE_TIENDA' }, apartado: { version: 1 }, productos: [], pedido: { id: 9, folio: 'PRUEBA-9', cliente_nombre: 'Cliente de ejemplo', area_revision: 'preparacion_tienda', revision_disponibilidad: vista === 'revision' ? null : revision, consulta_cerrada: false, retiros: [], anexos_pendientes: [] } };
if (vista === 'entrega') { tarea.pedido.consulta_cerrada = true; tarea.estado = 'RESPONDIDA'; tarea.estado_label = 'Apartado confirmado'; tarea.apartado = { version: 2, estado: 'APARTADO_CONFIRMADO', estado_label: 'Apartado confirmado', cantidad: 1, pago_confirmado_at: '2026-11-04T16:00:00Z' }; }
if (vista === 'retiro') { tarea.estado = 'RESPONDIDA'; tarea.estado_label = 'Respondida'; tarea.pedido.retiros = [{ version: 1, motivo: 'El cliente ya no lo quiere', estado: 'pendiente', piezas: [{ sku: 'AROMA-1', descripcion: 'Aroma de ejemplo', cantidad: 1 }] }]; }
if (vista === 'anexo') { tarea.estado = 'RESPONDIDA'; tarea.estado_label = 'Respondida'; tarea.pedido.anexos_pendientes = [{ indice: 0, entrada: 'documento', documento }]; }
const pedido = { id: 9, revision_registro: 2, base_captura: 'a'.repeat(64), folio: 'PRUEBA-9', folio_remision: 'WIZ-9', departamento_operacion_id: 1, cliente_id: 8, cliente: { id: 8, numero_cliente: '12345', nombre: 'Cliente de ejemplo para revisión operativa de Tienda', lista_actual: 'Mayoreo' }, origen: { id: 1, nombre: 'Tienda', requiere_logistica: false }, documentos: [documento], solicitud_preparacion: solicitud, almacen, almacen_id: 2, tarea_preparacion: tarea, consulta_cerrada: vista === 'importes', puede_cerrar_consulta: true, total_mercancia: 8999, saldo_a_favor: 500, costo_envio: 0, costo_seguro: 0, total_a_cobrar: 8499, pagos_exhibicion: [], estatus: { fase_ciclo: 'BORRADOR', nombre_visual: 'Borrador' }, saf_aplicaciones: [{ saf_credito_id: 7, monto: 500 }] };
const resumen = { total_a_cubrir: 8999, total_pagado: 1000, total_a_cobrar: 8499, saldo_a_favor_aplicado: 500, pendiente: 7499, excedente_generado: 0, tolerancia_aplicada: 0.44, bloqueos: [], cobertura: 'parcial' };
const lectura = { ...pedido, resumen, pagos: [{ id: 6, numero_exhibicion: 1, monto: 1000, forma_pago: 'transferencia', activo_para_cobertura: true, estado_revision: 'pendiente' }], formas_pago: [{ codigo: 'transferencia', label: 'Transferencia', requiere_banco: true, requiere_comprobante: true }], caja_pdv: { disponible: true, nombre: 'Caja PDV', id: 4 } };
window.route = (name, id) => name === 'control_pedidos.tienda.solicitud.documento' ? documentoUrl : name ? `/__tienda_qa__/${name}/${typeof id === 'object' ? '' : id || ''}` : { current: () => '', has: () => true };
window.__qaRequests = [];
axios.defaults.adapter = async (config) => {
    window.__qaRequests.push({ method: config.method, url: config.url });
    let data = lectura;
    if (config.url.includes('control_pedidos.tienda.show')) data = { tarea, documentos_solicitud: [documento], documentos: [], requisitos: {}, config: {}, tipos_caja: [] };
    if (config.url.includes('saldo_favor')) data = { disponible: 0, creditos_usables: [] };
    if (config.url.includes('detalle')) data = { pedido, resumen_cobertura: resumen, bloqueos_pago: [] };
    if (config.url.includes('productos')) data = { data: [] };
    if (config.method !== 'get') data = { ...lectura, ok: true, message: 'Comprobación aislada: respuesta recibida.' };
    return { data, status: 200, statusText: 'OK', headers: {}, config };
};
window.fetch = async () => new Response(JSON.stringify(lectura), { status: 200, headers: { 'Content-Type': 'application/json' } });
const catalogos = { departamentos: [{ id: 1, nombre: 'Ventas' }], bancos: [{ id: 1, nombre: 'BBVA', compartido: true }], sucursales: [{ id: 2, nombre: 'Centro' }], origenes: [pedido.origen], almacenes: [almacen], zonas: [], tipos_caja: [], formas_pago: lectura.formas_pago, envios_config: {} };
function Preview() {
    return <main className="gelia-pedidos-bma theme-surface-solid theme-text-main min-h-screen p-3">
        {['importes', 'solicitud', 'respuesta'].includes(vista) && <FormularioRegistrarTienda abierto pedido={pedido} catalogos={catalogos} registrarTienda={{ almacenes_tienda: [almacen], facultad_md: true }} onClose={() => {}} />}
        {['revision', 'espera', 'anexo', 'retiro', 'entrega'].includes(vista) && <ModalPreparacionTienda tareaId={tarea.id} auth={auth} onClose={() => {}} />}
        {vista === 'cedis' && <PanelRespuestaDisponibilidad modo="dialogo" documentos={[documento]} puedeResponder tomada onEnviar={async () => {}} onClose={() => {}} />}
        {vista === 'auditar' && <ModalRevisarPedido abierto pedido={{ ...pedido, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR', nombre_visual: 'Pendiente de revisión' } }} catalogos={catalogos} onClose={() => {}} />}
    </main>;
}
createInertiaApp({ page: { component: 'Preview', props: { auth }, url: location.pathname + location.search, version: 'tienda-qa' }, resolve: () => Preview, setup({ el, App, props }) { createRoot(el).render(<App {...props} />); } });
