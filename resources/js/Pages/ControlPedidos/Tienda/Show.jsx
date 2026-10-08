import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft, Camera, CheckCircle2, AlertTriangle, Store, Package, Truck, FileText, Printer,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { claseBadgeEstadoTienda } from './Partials/tiendaUi';
import ProgresoOperacionTienda from './Partials/ProgresoOperacionTienda';
import PanelAccionesShowTienda from './Partials/PanelAccionesShowTienda';
import TablaProductosShowTienda from './Partials/TablaProductosShowTienda';
import { BTN_PRIMARY, BTN_SECONDARY, formatearFechaNegocio } from '../Partials/pedidosBmaStyles';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import AvisoOperativoPedido from '../Partials/AvisoOperativoPedido';
import ModalSesionEvidenciaTienda from './Partials/ModalSesionEvidenciaTienda';
import ModalLiberarMercancia from '../Partials/ModalLiberarMercancia';
import ApartadoFisicoTienda from './Partials/ApartadoFisicoTienda';
import EmpaqueMunicipioTienda from './Partials/EmpaqueMunicipioTienda';

const TIPOS_INCIDENCIA = [
    { value: 'almacen_incorrecto', label: 'Almacén incorrecto' },
    { value: 'datos_incorrectos', label: 'Datos incorrectos' },
    { value: 'producto_no_encontrado', label: 'Producto no encontrado' },
    { value: 'otro', label: 'Otro' },
];

function Meta({ label, children }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 opacity-70">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0 mt-0.5 break-words">{children}</p>
        </div>
    );
}

export default function Show({
    auth,
    tarea,
    requisitos = {},
    estados_fisicos = {},
    documentos = [],
    historial = [],
    almacenes = [],
    tipos_caja = [],
    traspaso = null,
    eventos_apartado = [],
    conciliacion_grupo = null,
    discrepancias_traspaso = [],
    config = {},
}) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const [productos, setProductos] = useState(() => (tarea.productos || []).map((p) => ({
        id: p.id,
        cantidad_encontrada: p.cantidad_encontrada ?? p.cantidad_solicitada ?? 0,
        estado_fisico: p.estado_fisico || 'bueno',
        observacion: p.observacion || '',
    })));
    const [observaciones, setObservaciones] = useState(tarea.observaciones_respuesta || '');
    const [pesoReal, setPesoReal] = useState(tarea.peso_real_kg ?? '');
    const [pesoVol, setPesoVol] = useState(tarea.peso_volumetrico_kg ?? '');
    const [obsFisicas, setObsFisicas] = useState(tarea.observaciones_fisicas || '');
    const [evidencias, setEvidencias] = useState([]);
    const [evidenciasProducto, setEvidenciasProducto] = useState({});
    const [tipoCajaId, setTipoCajaId] = useState(tarea.catalogo_tipo_caja_id ? String(tarea.catalogo_tipo_caja_id) : '');
    const [modalQr, setModalQr] = useState(false);
    const [modoIncidencia, setModoIncidencia] = useState(false);
    const [motivoRegeneracion, setMotivoRegeneracion] = useState('');
    const [mostrarRegenerar, setMostrarRegenerar] = useState(false);
    const [incidencia, setIncidencia] = useState({
        tipo_incidencia: 'almacen_incorrecto',
        motivo: '',
        almacen_solicitado_id: tarea.almacen?.id || '',
        almacen_aparente_id: '',
        observacion: '',
    });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [modalLiberar, setModalLiberar] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    const puedeTomar = permisos.includes('control_pedidos.tienda.tomar');
    const puedeResponder = permisos.includes('control_pedidos.tienda.responder');
    const puedeIncidencia = permisos.includes('control_pedidos.tienda.reportar_error');
    const puedeLiberar = permisos.includes('control_pedidos.tienda.liberar');
    const puedeEvidencias = permisos.includes('control_pedidos.tienda.evidencias');
    const puedeTrasladar = permisos.includes('control_pedidos.tienda.trasladar');
    const puedeGenerarCaratula = permisos.includes('control_pedidos.tienda.generar_caratula');
    const puedeImprimirCaratula = permisos.includes('control_pedidos.tienda.imprimir_caratula');
    const puedeRegenerarCaratula = permisos.includes('control_pedidos.tienda.regenerar_caratula');
    const puedeConfirmarCaratula = permisos.includes('control_pedidos.tienda.confirmar_caratula');
    const puedeCargarId = permisos.includes('control_pedidos.tienda.cargar_identificacion');
    const esMunicipio = Boolean(tarea.modalidad?.es_envio_municipio || tarea.modalidad?.codigo === 'ENVIO_MUNICIPIO');
    const listaCaratula = tarea.estado === 'LISTA_PARA_CARATULA';
    const entrega = tarea.entrega_municipal;
    const caratula = tarea.caratula;

    const faltantes = useMemo(() => {
        const f = [];
        productos.forEach((p, i) => {
            const orig = tarea.productos[i];
            if (p.cantidad_encontrada === '' || p.cantidad_encontrada == null) f.push(`Cantidad de «${orig?.descripcion_snapshot}»`);
            if (!p.estado_fisico) f.push(`Estado físico de «${orig?.descripcion_snapshot}»`);
        });
        if (requisitos.evidencia_general_obligatoria && documentos.filter((d) => !d.inmutable).length + evidencias.length < 1) {
            f.push('Al menos una evidencia general');
        }
        if (requisitos.peso_real_obligatorio && !(Number(pesoReal) > 0)) f.push('Peso real (kg)');
        if (requisitos.peso_volumetrico_obligatorio && !(Number(pesoVol) > 0)) f.push('Peso volumétrico (kg)');
        if (requisitos.observaciones_fisicas_obligatorias && !String(obsFisicas || '').trim()) f.push('Observaciones físicas');
        if (requisitos.caja_obligatoria && !tipoCajaId) f.push('Tipo de caja');
        if (requisitos.evidencia_por_producto) {
            productos.forEach((p, i) => {
                if (!(Number(p.cantidad_encontrada) > 0)) return;
                const orig = tarea.productos[i];
                const ya = documentos.some((d) => d.tipo_evidencia === 'evidencia_producto' && Number(d.pedido_bma_tarea_producto_id) === Number(p.id));
                if (!ya && !evidenciasProducto[p.id]) f.push(`Evidencia de «${orig?.descripcion_snapshot || 'producto'}»`);
            });
        }
        return f;
    }, [productos, tarea.productos, requisitos, documentos, evidencias, evidenciasProducto, pesoReal, pesoVol, obsFisicas, tipoCajaId]);

    const tomar = () => {
        router.post(route('control_pedidos.tienda.tomar', tarea.id), { version: tarea.version });
    };

    const responder = () => {
        const form = new FormData();
        productos.forEach((p, i) => {
            form.append(`productos[${i}][id]`, p.id);
            form.append(`productos[${i}][cantidad_encontrada]`, p.cantidad_encontrada);
            form.append(`productos[${i}][estado_fisico]`, p.estado_fisico);
            if (p.observacion) form.append(`productos[${i}][observacion]`, p.observacion);
        });
        form.append('observaciones_respuesta', observaciones);
        form.append('version', tarea.version);
        if (pesoReal !== '') form.append('peso_real_kg', pesoReal);
        if (pesoVol !== '') form.append('peso_volumetrico_kg', pesoVol);
        if (tipoCajaId) form.append('catalogo_tipo_caja_id', tipoCajaId);
        if (obsFisicas) form.append('observaciones_fisicas', obsFisicas);
        evidencias.forEach((f, i) => form.append(`evidencias[${i}]`, f));
        Object.entries(evidenciasProducto).forEach(([id, file]) => {
            if (file) form.append(`evidencias_producto[${id}]`, file);
        });
        router.post(route('control_pedidos.tienda.responder', tarea.id), form, { forceFormData: true });
    };

    const confirmarSalida = () => {
        if (!window.confirm('¿Confirma que la mercancía salió hacia CEDIS?')) return;
        router.post(route('control_pedidos.tienda.confirmar_salida', tarea.id), { version: tarea.version });
    };

    const generarCaratula = () => {
        router.post(route('control_pedidos.tienda.caratula.generar', tarea.id), { version: tarea.version });
    };

    const regenerarCaratula = () => {
        if (!motivoRegeneracion.trim()) return;
        router.post(route('control_pedidos.tienda.caratula.regenerar', tarea.id), {
            version: tarea.version,
            motivo_regeneracion: motivoRegeneracion,
        }, { onSuccess: () => { setMostrarRegenerar(false); setMotivoRegeneracion(''); } });
    };

    const confirmarCaratula = () => {
        if (!window.confirm('¿Confirma que la carátula está colocada en el paquete?')) return;
        router.post(route('control_pedidos.tienda.caratula.confirmar', tarea.id), {
            version: tarea.version,
            caratula_id: caratula?.id,
        });
    };

    const subirDocMunicipal = (tipo, file) => {
        if (!file) return;
        const form = new FormData();
        form.append('tipo', tipo);
        form.append('archivo', file);
        router.post(route('control_pedidos.tienda.documento_municipal.store', tarea.id), form, { forceFormData: true });
    };

    const reportarIncidencia = () => {
        const form = new FormData();
        Object.entries(incidencia).forEach(([k, v]) => { if (v !== '' && v != null) form.append(k, v); });
        form.append('version', tarea.version);
        router.post(route('control_pedidos.tienda.reportar_incidencia', tarea.id), form, { forceFormData: true });
    };

    const liberar = () => setModalLiberar(true);

    const editable = tarea.estado === 'EN_ATENCION' && puedeResponder;
    const enPendiente = tarea.estado === 'PENDIENTE' && puedeTomar;
    const listaTraslado = tarea.estado === 'LISTA_PARA_TRASLADO';
    const traspasoInfo = traspaso || tarea.solicitud_traspaso;
    const folio = tarea.pedido?.folio_visible || tarea.pedido?.folio_remision || tarea.pedido?.folio || `Tarea #${tarea.id}`;
    const folioEtiqueta = tarea.pedido?.folio_etiqueta || 'Folio';
    const badgeEstado = claseBadgeEstadoTienda(tarea.estado, tarea.estado_label);
    const progreso = tarea.progreso_traslado || [];
    const progresoCaratula = tarea.progreso_caratula || [];

    const avisoPrincipal = () => {
        if (tarea.estado === 'RECHAZADA_CEDIS' || tarea.estado === 'CON_INCIDENCIA') {
            return {
                label: tarea.estado === 'RECHAZADA_CEDIS' ? 'Rechazo CEDIS' : 'Incidencia',
                tono: 'danger',
                icon: AlertTriangle,
                texto: tarea.motivo_rechazo_cedis || 'Revise el motivo y continúe según indique Ventas.',
            };
        }
        if (listaTraslado) {
            return {
                label: 'Traslado a CEDIS',
                tono: 'info',
                icon: Truck,
                texto: 'Mercancía lista. Confirme la salida cuando el traslado salga de tienda.',
            };
        }
        if (listaCaratula) {
            return {
                label: 'Carátula municipal',
                tono: 'info',
                icon: FileText,
                texto: 'Genere, imprima y confirme la colocación de la carátula en el paquete.',
            };
        }
        if (tarea.estado === 'EN_TRASLADO') {
            return {
                label: 'En camino',
                tono: 'blue',
                icon: Truck,
                texto: 'Esperando confirmación de recepción en CEDIS.',
            };
        }
        if (enPendiente) {
            return {
                label: 'Nueva solicitud',
                tono: 'warning',
                icon: Package,
                texto: tarea.requiere_traslado_cedis
                    ? 'Tome la tarea, localice piezas y prepare el traslado a CEDIS.'
                    : (esMunicipio
                        ? 'Tome la tarea, localice piezas y prepare el envío municipal.'
                        : 'Tome la tarea, localice piezas y responda con evidencia.'),
            };
        }
        if (editable) {
            return {
                label: 'En atención',
                tono: 'info',
                icon: Package,
                texto: esMunicipio
                    ? 'Capture cantidades y evidencia; luego marque lista para carátula.'
                    : (tarea.requiere_traslado_cedis
                        ? 'Capture cantidades, evidencia y peso; luego marque lista para traslado.'
                        : 'Capture cantidades y evidencia; luego responda la preparación.'),
            };
        }
        return null;
    };

    const aviso = avisoPrincipal();

    return (
        <AppLayout auth={auth}>
            <Head title={`Preparación Tienda — ${folio}`} />
            <GeliaPageShell className="gelia-tienda-op space-y-4 md:space-y-6">
                <GeliaTituloCard
                    eyebrow="Preparación tienda"
                    title={folio}
                    description={`${folioEtiqueta}${tarea.pedido?.folio && tarea.pedido.folio !== folio ? ` · Interno ${tarea.pedido.folio}` : ''}${tarea.pedido?.prioridad_md ? ' · Mismo día' : ''} · ${tarea.modalidad?.nombre || '—'}${tarea.almacen?.nombre ? ` · ${tarea.almacen.nombre}` : ''}`}
                    icon={Store}
                    aside={(
                        <div className="flex flex-col items-stretch sm:items-end gap-2">
                            <span className={badgeEstado.className}>{badgeEstado.label}</span>
                            <Link href={route('control_pedidos.tienda.index')} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-2 min-h-[44px]`}>
                                <ArrowLeft className="w-4 h-4" /> Volver al listado
                            </Link>
                        </div>
                    )}
                />

                <div className="grid lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2 space-y-4">
                        {aviso && (
                            <AvisoOperativoPedido label={aviso.label} tono={aviso.tono} icon={aviso.icon}>
                                {aviso.texto}
                            </AvisoOperativoPedido>
                        )}

                        {entrega && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="text-sm font-bold theme-text-main m-0">Destino y cobro</h3>
                                <div className="grid sm:grid-cols-2 gap-3">
                                    <Meta label="Municipio">{entrega.municipio_destino || '—'}</Meta>
                                    <Meta label="Destinatario">{entrega.destinatario_nombre || '—'}</Meta>
                                    <Meta label="Teléfono">{entrega.destinatario_telefono || '—'}</Meta>
                                    <Meta label="Cobro">{entrega.modalidad_cobro || '—'}</Meta>
                                    <Meta label="Transporte">{entrega.transporte?.nombre || '—'}</Meta>
                                    {entrega.direccion_referencia && <Meta label="Referencia">{entrega.direccion_referencia}</Meta>}
                                </div>
                            </section>
                        )}

                        <ApartadoFisicoTienda
                            tarea={tarea}
                            auth={auth}
                            config={config}
                            eventos={eventos_apartado}
                            requisitos={requisitos}
                        />

                        {esMunicipio && (
                            <EmpaqueMunicipioTienda
                                tarea={tarea}
                                apartado={tarea.apartado}
                                auth={auth}
                                requisitos={requisitos}
                                documentos={documentos}
                            />
                        )}

                        <TablaProductosShowTienda
                            productos={tarea.productos || []}
                            productosState={productos}
                            estados_fisicos={estados_fisicos}
                            editable={editable}
                            requisitos={requisitos}
                            evidenciasProducto={evidenciasProducto}
                            setProductos={setProductos}
                            setEvidenciasProducto={setEvidenciasProducto}
                        />

                        {editable && (requisitos.peso_real_obligatorio || requisitos.peso_volumetrico_obligatorio || requisitos.observaciones_fisicas_obligatorias || requisitos.caja_obligatoria || tarea.requiere_traslado_cedis) && (
                            <section className={`${geliaCardClass()} p-4 space-y-3`}>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Peso / empaque</h3>
                                <div className="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label className={THEME_LABEL}>Peso real (kg){requisitos.peso_real_obligatorio ? ' *' : ''}</label>
                                        <input type="number" step="0.001" min="0" className={THEME_INPUT} value={pesoReal} onChange={(e) => setPesoReal(e.target.value)} />
                                    </div>
                                    {requisitos.peso_volumetrico_obligatorio && (
                                        <div>
                                            <label className={THEME_LABEL}>Peso volumétrico (kg) *</label>
                                            <input type="number" step="0.001" min="0" className={THEME_INPUT} value={pesoVol} onChange={(e) => setPesoVol(e.target.value)} />
                                        </div>
                                    )}
                                    {requisitos.caja_obligatoria && (
                                        <div className="sm:col-span-2">
                                            <label className={THEME_LABEL}>Tipo de caja *</label>
                                            <select className={THEME_SELECT} value={tipoCajaId} onChange={(e) => setTipoCajaId(e.target.value)}>
                                                <option value="">Seleccionar...</option>
                                                {tipos_caja.map((c) => (
                                                    <option key={c.id} value={String(c.id)}>{c.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                    )}
                                    <div className="sm:col-span-2">
                                        <label className={THEME_LABEL}>Observaciones físicas{requisitos.observaciones_fisicas_obligatorias ? ' *' : ''}</label>
                                        <textarea className={THEME_TEXTAREA} rows={2} value={obsFisicas} onChange={(e) => setObsFisicas(e.target.value)} />
                                    </div>
                                </div>
                            </section>
                        )}

                        {editable && (
                            <section className={`${geliaCardClass()} p-4 space-y-3`}>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Evidencia general</h3>
                                <div className="flex flex-wrap gap-2">
                                    <label className={`${BTN_SECONDARY} cursor-pointer inline-flex items-center gap-2`}>
                                        <Camera className="w-4 h-4" /> Subir archivo
                                        <input
                                            type="file"
                                            accept="image/*,application/pdf"
                                            className="hidden"
                                            multiple
                                            onChange={(e) => setEvidencias(Array.from(e.target.files || []))}
                                        />
                                    </label>
                                    {puedeEvidencias && (
                                        <button type="button" className={BTN_SECONDARY} onClick={() => setModalQr(true)}>QR celular</button>
                                    )}
                                </div>
                                <ul className="text-xs theme-text-muted m-0 space-y-1">
                                    {documentos.map((d) => <li key={d.id}>{d.nombre_original}</li>)}
                                    {evidencias.map((f, i) => <li key={`p-${i}`}>{f.name} (pendiente)</li>)}
                                </ul>
                                <div>
                                    <label className={THEME_LABEL}>Observaciones de respuesta</label>
                                    <textarea className={THEME_TEXTAREA} rows={3} value={observaciones} onChange={(e) => setObservaciones(e.target.value)} />
                                </div>
                            </section>
                        )}

                        {esMunicipio && (editable || listaCaratula || tarea.estado === 'RESPONDIDA') && (
                            <section className={`${geliaCardClass()} p-4 space-y-3`}>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Documentos municipales</h3>
                                <ul className="text-xs theme-text-muted m-0 space-y-1">
                                    {documentos.filter((d) => ['identificacion', 'remision'].includes(d.tipo_evidencia)).map((d) => (
                                        <li key={d.id}>
                                            {d.tipo_evidencia}: {d.nombre_original}
                                            {permisos.includes('control_pedidos.tienda.ver_identificacion') || d.tipo_evidencia !== 'identificacion' ? (
                                                <a className="ml-2 underline" href={route('control_pedidos.tienda.evidencia.descargar', [tarea.id, d.id])}>Ver</a>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                                {puedeCargarId && (
                                    <div className="flex flex-wrap gap-2">
                                        <label className={`${BTN_SECONDARY} cursor-pointer inline-flex items-center gap-2`}>
                                            <FileText className="w-4 h-4" /> Identificación
                                            <input type="file" accept="image/*,application/pdf" className="hidden"
                                                onChange={(e) => subirDocMunicipal('identificacion', e.target.files?.[0])} />
                                        </label>
                                        <label className={`${BTN_SECONDARY} cursor-pointer inline-flex items-center gap-2`}>
                                            <FileText className="w-4 h-4" /> Remisión
                                            <input type="file" accept="image/*,application/pdf" className="hidden"
                                                onChange={(e) => subirDocMunicipal('remision', e.target.files?.[0])} />
                                        </label>
                                    </div>
                                )}
                                <p className="text-[10px] font-bold theme-text-muted m-0">
                                    Checklist carátula: {[
                                        requisitos.requiere_identificacion && 'Identificación',
                                        requisitos.caja_obligatoria && 'Caja',
                                        requisitos.peso_real_obligatorio && 'Peso',
                                        requisitos.evidencia_general_obligatoria && 'Evidencia',
                                    ].filter(Boolean).join(' · ') || 'Según transporte'}
                                    {requisitos.causa_remision_salida && (
                                        <span className="block mt-1">Salida: {requisitos.causa_remision_salida}</span>
                                    )}
                                </p>
                            </section>
                        )}

                        {(listaCaratula || caratula) && esMunicipio && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="text-sm font-bold theme-text-main m-0">Carátula</h3>
                                <p className="text-sm font-bold m-0">
                                    Estado: {caratula?.estado || 'PENDIENTE'}
                                    {caratula?.version ? ` · v${caratula.version}` : ''}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {listaCaratula && puedeGenerarCaratula && !caratula && (
                                        <button type="button" className={BTN_PRIMARY} onClick={generarCaratula}>
                                            <FileText className="w-4 h-4 inline mr-1" /> Generar carátula
                                        </button>
                                    )}
                                    {caratula && puedeImprimirCaratula && (
                                        <a
                                            href={route('control_pedidos.tienda.caratula.pdf', [tarea.id, caratula.id])}
                                            target="_blank"
                                            rel="noreferrer"
                                            className={`${BTN_SECONDARY} inline-flex items-center gap-1`}
                                        >
                                            <Printer className="w-4 h-4" /> Previsualizar / Imprimir
                                        </a>
                                    )}
                                    {listaCaratula && caratula?.estado === 'GENERADA' && puedeRegenerarCaratula && (
                                        <button type="button" className={BTN_SECONDARY} onClick={() => setMostrarRegenerar((v) => !v)}>
                                            Regenerar
                                        </button>
                                    )}
                                    {listaCaratula && caratula?.estado === 'GENERADA' && puedeConfirmarCaratula && (
                                        <button type="button" className={BTN_PRIMARY} onClick={confirmarCaratula}>
                                            <CheckCircle2 className="w-4 h-4 inline mr-1" /> Carátula colocada
                                        </button>
                                    )}
                                </div>
                                {mostrarRegenerar && (
                                    <div className="space-y-2">
                                        <label className={THEME_LABEL}>Motivo de regeneración</label>
                                        <textarea className={THEME_TEXTAREA} rows={2} value={motivoRegeneracion}
                                            onChange={(e) => setMotivoRegeneracion(e.target.value)} />
                                        <button type="button" className={BTN_PRIMARY} disabled={motivoRegeneracion.trim().length < 5} onClick={regenerarCaratula}>
                                            Confirmar regeneración
                                        </button>
                                    </div>
                                )}
                            </section>
                        )}

                        {progresoCaratula.length > 0 && (
                            <section className={`${geliaCardClass()} p-4`}>
                                <ProgresoOperacionTienda pasos={progresoCaratula} titulo="Progreso municipal" />
                            </section>
                        )}

                        {conciliacion_grupo?.lineas?.length > 0 && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="text-sm font-bold theme-text-main m-0">
                                    Grupo complemento ({conciliacion_grupo.folio_raiz})
                                </h3>
                                <p className="text-xs theme-text-muted font-bold m-0">
                                    Cada línea conserva el expediente de origen (caso 9+1).
                                </p>
                                <ul className="m-0 p-0 list-none space-y-2 text-sm">
                                    {conciliacion_grupo.lineas.map((ln, idx) => (
                                        <li key={`${ln.tarea_id}-${ln.sku}-${idx}`} className="theme-element border theme-border rounded-lg p-2">
                                            <span className="font-black">{ln.sku}</span>
                                            <span className="theme-text-muted"> · {ln.folio_expediente}</span>
                                            <span className="block text-[11px] theme-text-muted font-bold">
                                                Encontradas {ln.cantidad_encontrada} · {ln.almacen || '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {discrepancias_traspaso?.length > 0 && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="text-sm font-bold theme-text-main m-0">
                                    Discrepancias Tienda / CEDIS
                                </h3>
                                <ul className="m-0 p-0 list-none space-y-2 text-sm">
                                    {discrepancias_traspaso.map((d) => (
                                        <li key={`${d.sku}-${d.pedido_bma_origen_id}`} className="font-bold">
                                            {d.sku}: enviadas {d.piezas_tienda}, recibidas OK {d.piezas_cedis}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {traspasoInfo && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="text-sm font-bold theme-text-main m-0">Traspaso vinculado</h3>
                                <div className="grid sm:grid-cols-2 gap-3">
                                    <Meta label="Folio">{traspasoInfo.folio || '—'}</Meta>
                                    {traspasoInfo.folio_traspaso && <Meta label="Folio CEDIS">{traspasoInfo.folio_traspaso}</Meta>}
                                    {traspasoInfo.estado && <Meta label="Estado">{traspasoInfo.estado}</Meta>}
                                </div>
                                <a
                                    href={traspasoInfo.url || `/traspasos?q=${encodeURIComponent(traspasoInfo.folio || '')}`}
                                    className={`${BTN_SECONDARY} inline-flex`}
                                >
                                    Abrir traspaso
                                </a>
                            </section>
                        )}

                        {progreso.length > 0 && (
                            <section className={`${geliaCardClass()} p-4 space-y-3`}>
                                <ProgresoOperacionTienda pasos={progreso} titulo="Progreso de traslado" />
                                <ul className="m-0 p-0 list-none space-y-1 text-xs theme-text-muted">
                                    {progreso.filter((p) => p.en).map((paso) => (
                                        <li key={`${paso.clave}-meta`}>
                                            {paso.label}: {formatearFechaNegocio(paso.en)}
                                            {paso.por ? ` · ${paso.por}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {modoIncidencia && puedeIncidencia && (
                            <section className={`${geliaCardClass()} p-4 space-y-3 theme-element border theme-border`}>
                                <h3 className="font-black text-sm flex items-center gap-2 m-0">
                                    <AlertTriangle className="w-4 h-4" /> Reportar incidencia
                                </h3>
                                <select
                                    className={THEME_SELECT}
                                    value={incidencia.tipo_incidencia}
                                    onChange={(e) => setIncidencia((s) => ({ ...s, tipo_incidencia: e.target.value }))}
                                >
                                    {TIPOS_INCIDENCIA.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                                <textarea
                                    className={THEME_TEXTAREA}
                                    placeholder="Motivo obligatorio"
                                    rows={3}
                                    value={incidencia.motivo}
                                    onChange={(e) => setIncidencia((s) => ({ ...s, motivo: e.target.value }))}
                                />
                                {almacenes?.length > 0 && (
                                    <div>
                                        <label className={THEME_LABEL}>Almacén aparente (opcional)</label>
                                        <select
                                            className={THEME_SELECT}
                                            value={incidencia.almacen_aparente_id}
                                            onChange={(e) => setIncidencia((s) => ({ ...s, almacen_aparente_id: e.target.value }))}
                                        >
                                            <option value="">—</option>
                                            {almacenes.map((a) => (
                                                <option key={a.id} value={a.id}>{a.nombre}</option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                <button type="button" className={BTN_PRIMARY} onClick={reportarIncidencia}>Enviar incidencia a Ventas</button>
                            </section>
                        )}

                        {historial.length > 0 && (
                            <section className={`${geliaCardClass()} p-4 space-y-3`}>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Historial</h3>
                                <ul className="space-y-2 m-0 p-0 list-none">
                                    {historial.map((h) => (
                                        <li key={h.id} className="text-sm border-b theme-border pb-2 last:border-0 last:pb-0">
                                            <p className="font-bold theme-text-main m-0">
                                                {h.accion || `${h.estado_anterior || '—'} → ${h.estado_nuevo || '—'}`}
                                            </p>
                                            {h.comentario && <p className="text-xs theme-text-muted m-0 mt-0.5">{h.comentario}</p>}
                                            <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">
                                                {h.usuario || 'Sistema'}
                                                {h.created_at ? ` · ${formatearFechaNegocio(h.created_at)}` : ''}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>

                    <PanelAccionesShowTienda
                        tarea={tarea}
                        faltantes={faltantes}
                        editable={editable}
                        enPendiente={enPendiente}
                        listaTraslado={listaTraslado}
                        puedeTomar={puedeTomar}
                        puedeResponder={puedeResponder}
                        puedeTrasladar={puedeTrasladar}
                        puedeLiberar={puedeLiberar}
                        esMunicipio={esMunicipio}
                        modoIncidencia={modoIncidencia}
                        onTomar={tomar}
                        onResponder={responder}
                        onConfirmarSalida={confirmarSalida}
                        onToggleIncidencia={() => setModoIncidencia((v) => !v)}
                        onLiberar={liberar}
                    />
                </div>
            </GeliaPageShell>

            <ModalSesionEvidenciaTienda abierto={modalQr} onCerrar={() => setModalQr(false)} tareaId={tarea.id} />
            <ModalLiberarMercancia
                abierto={modalLiberar}
                onClose={() => setModalLiberar(false)}
                tarea={tarea}
                routeName="control_pedidos.tienda.liberar"
            />
            <ModalAlertaPedido {...alerta} onCerrar={() => setAlerta((a) => ({ ...a, abierto: false }))} />
        </AppLayout>
    );
}
