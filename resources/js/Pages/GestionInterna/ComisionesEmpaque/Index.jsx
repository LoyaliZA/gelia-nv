import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    geliaCardClass,
} from '@/utils/geliaTheme';
import BuscarUsuario from './BuscarUsuario';
import CuentasColaboradores from './CuentasColaboradores';
import Periodos from './Periodos';
import Equipo from './Equipo';
import { emitirToastGelia } from '@/utils/geliaToast';
import { AlertCircle, BadgeCheck, CheckCircle2, ClipboardList, Clock, Download, Eye, History, Layers, Package, Plus, RefreshCw, Search, Settings, SlidersHorizontal, Upload, Wallet, X } from 'lucide-react';

const TABS = [
    { id: 'acumulado', label: 'Empaques' },
    { id: 'equipo', label: 'Equipo y reparto' },
    { id: 'periodos', label: 'Periodos' },
    { id: 'pagos', label: 'Por pagar' },
    { id: 'cortes', label: 'Historial de cortes' },
];

const VISITA_MODAL = { preserveState: true, preserveScroll: true };
const BOTON_FILTRO = 'inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm font-semibold theme-text-main min-h-11 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2';
const INSIGNIA = {
    info: 'border-[color-mix(in_srgb,var(--color-info)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-info)_16%,transparent)] text-[color:var(--color-info)]',
    exito: 'border-[color-mix(in_srgb,var(--color-exito)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_16%,transparent)] text-[color:var(--color-exito)]',
    aviso: 'border-[color-mix(in_srgb,var(--color-aviso)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_16%,transparent)] text-[color:var(--color-aviso)]',
    peligro: 'border-[color-mix(in_srgb,var(--color-peligro)_45%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_16%,transparent)] text-[color:var(--color-peligro)]',
};

function avisosDePagina({ sin_areas, flash, sincronizacion, resumen }) {
    const items = [];
    if (sin_areas) items.push({ mensaje: 'No tienes áreas asignadas.', tipo: 'aviso' });
    if (flash?.success) items.push({ mensaje: flash.success, tipo: 'success' });
    if (flash?.error) items.push({ mensaje: flash.error, tipo: 'error' });
    if (flash?.warning) items.push({ mensaje: flash.warning, tipo: 'aviso' });
    if (sincronizacion?.estado === 'omitida' && sincronizacion.mensaje) {
        items.push({ mensaje: sincronizacion.mensaje, tipo: 'aviso' });
    }
    if (!resumen?.listo && resumen?.mensaje) items.push({ mensaje: resumen.mensaje, tipo: 'aviso' });

    const vistos = new Set();
    return items.filter((item) => {
        const texto = String(item.mensaje).trim();
        if (!texto || vistos.has(texto)) return false;
        vistos.add(texto);
        item.mensaje = texto;
        return true;
    });
}

function dinero(valor) {
    if (valor == null || valor === '') return '—';
    return Number(valor).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function ir(params) {
    const actual = new URL(window.location.href).searchParams.get('area_id');
    const query = { ...(actual ? { area_id: actual } : {}), ...params };
    if (!query.area_id) delete query.area_id;
    router.get(route('gestion_interna.comisiones_empaque.index'), query, { preserveState: false, preserveScroll: true });
}

function momentoLegible(valor) {
    if (!valor) return '';
    const [fecha, hora = ''] = String(valor).split(' ');
    const [anio, mes, dia] = fecha.split('-');
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const nombre = meses[Number(mes) - 1];
    if (!nombre || !dia || !anio) return String(valor);
    return `${Number(dia)} ${nombre} ${anio}${hora ? ` · ${hora}` : ''}`;
}

function estadoActualizacion(sincronizacion) {
    const fecha = momentoLegible(sincronizacion?.finalizado_at);
    if (!fecha) {
        return { etiqueta: 'Sin actualizar', detalle: 'Aún no hay empaques de Gelia', aviso: true, Icono: Clock };
    }
    if (sincronizacion?.estado === 'error') {
        return { etiqueta: 'Con error', detalle: fecha, aviso: true, Icono: AlertCircle };
    }
    if (sincronizacion?.estado === 'omitida') {
        return { etiqueta: 'Pendiente', detalle: fecha, aviso: true, Icono: Clock };
    }
    return { etiqueta: 'Actualizado', detalle: fecha, aviso: false, Icono: CheckCircle2 };
}

function logisticaTexto(logistica = {}) {
    const partes = [
        logistica.paqueteria && `Paquetería: ${logistica.paqueteria}`,
        logistica.categoria_paqueteria && `Categoría: ${logistica.categoria_paqueteria}`,
        logistica.modalidad_tarifa && `Modalidad de tarifa: ${logistica.modalidad_tarifa}`,
        logistica.tipo_guia && `Tipo de guía: ${logistica.tipo_guia}`,
        logistica.envio_tienda && `Envío tienda: ${logistica.envio_tienda}`,
        logistica.modalidad_preparacion && `Preparación: ${logistica.modalidad_preparacion}`,
        logistica.origen_solicitud && `Origen del pedido: ${logistica.origen_solicitud}`,
    ].filter(Boolean);
    return partes.length ? partes.join(' · ') : 'Sin datos de paquetería o modalidad';
}

export default function Index({ tab, puede, areas = [], fecha_inicio, fecha_fin, sincronizacion, resumen, fuentes = [], situaciones = {}, filtros = {}, acumulado, totales, preview, cortes, corte, saldos = [], participantes = [], tarifas = [], tipos_caja = [], sin_areas = false, captura_nombre = '', alcances = [], periodos = [], equipo, paqueterias = [], carga_revision = null }) {
    const { flash } = usePage().props;
    const areaId = filtros.area_id || (tab === 'acumulado' ? '' : (areas[0]?.id || ''));
    const area = areas.find((a) => String(a.id) === String(areaId));
    const [actualizando, setActualizando] = useState(false);
    const avisos = useMemo(() => avisosDePagina({ sin_areas, flash, sincronizacion, resumen }), [sin_areas, flash, sincronizacion, resumen]);
    const avisosPrevios = useRef('');
    useEffect(() => {
        const firma = avisos.map((aviso) => `${aviso.tipo}:${aviso.mensaje}`).join('\n');
        if (!firma || firma === avisosPrevios.current) return;
        avisosPrevios.current = firma;
        avisos.forEach((aviso) => emitirToastGelia(aviso.mensaje, aviso.tipo));
    }, [avisos]);
    const navegar = (params) => ir({ area_id: areaId, ...params });
    const opcionesPeriodo = periodos.filter((p) => String(p.area_id) === String(areaId));
    const actualizacion = estadoActualizacion(sincronizacion);
    const IconoActualizacion = actualizacion.Icono;
    const configuracionAbierta = ['ajustes', 'configuracion'].includes(tab);
    const [tarifasAbiertas, setTarifasAbiertas] = useState(false);
    const mostrarTarifas = puede.configurar && (tarifasAbiertas || configuracionAbierta);
    const tabVista = configuracionAbierta ? 'acumulado' : tab;
    const cerrarTarifas = () => {
        setTarifasAbiertas(false);
        if (configuracionAbierta) navegar({ tab: 'acumulado' });
    };
    return <AppLayout title="Comisiones de empaque"><Head title="Comisiones de empaque" /><GeliaPageShell className="space-y-5 pt-4 md:pt-0 pb-10">
        <section className={geliaCardClass('p-5 md:p-6 space-y-5')} aria-label="Comisiones de empaque">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 max-w-xl">
                    <h1 className="text-xl md:text-2xl font-bold theme-text-main m-0">Comisiones de empaque</h1>
                    <p className="text-sm theme-text-muted m-0 mt-1">Revise los empaques y complete los que no vinieron de Gelia.</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <div className={`inline-flex items-center gap-3 rounded-xl border px-3 py-2 min-h-11 theme-element ${actualizacion.aviso ? 'border-[color-mix(in_srgb,var(--color-aviso)_45%,transparent)]' : 'theme-border'}`}>
                        <span className={`flex size-8 shrink-0 items-center justify-center rounded-lg ${actualizacion.aviso ? 'theme-text-aviso' : 'theme-text-primario'}`}><IconoActualizacion className="size-4" aria-hidden="true" /></span>
                        <span className="leading-tight">
                            <span className="block text-[11px] font-bold uppercase tracking-wide theme-text-muted">{actualizacion.etiqueta}</span>
                            <span className="block text-sm font-semibold theme-text-main tabular-nums">{actualizacion.detalle}</span>
                        </span>
                    </div>
                    <button type="button" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`} disabled={actualizando} aria-label="Actualizar empaques de Gelia" onClick={() => router.post(route('gestion_interna.comisiones_empaque.sincronizar'), {}, { preserveScroll: true, onStart: () => setActualizando(true), onFinish: () => setActualizando(false) })}><RefreshCw className={`size-4 ${actualizando ? 'animate-spin' : ''}`} aria-hidden="true" />{actualizando ? 'Actualizando…' : 'Actualizar'}</button>
                </div>
            </header>
            <div className="flex flex-wrap items-end gap-4 border-t theme-border pt-5">
                <label className="min-w-40"><span className={THEME_LABEL}>Área</span><select className={THEME_INPUT} value={areaId} onChange={(e) => { const valor = e.target.value; if (!valor && tab !== 'acumulado') { ir({ tab: 'acumulado' }); return; } ir({ tab: tab === 'configuracion' ? 'ajustes' : tab, area_id: valor || undefined }); }}><option value="">Todas</option>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label>
                <label className="min-w-48 flex-1 max-w-md"><span className={THEME_LABEL}>Periodo del reporte</span><select className={THEME_INPUT} value={filtros.periodo_id || ''} onChange={(e) => navegar({ tab: 'acumulado', periodo_id: e.target.value || undefined })}><option value="">Todos los empaques del área</option>{opcionesPeriodo.map((p) => <option key={p.id} value={p.id}>{p.nombre} · {p.desde} al {p.hasta}{p.cerrado ? ' · cerrado' : ''}</option>)}</select></label>
                {puede.configurar && <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main min-h-11 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" aria-haspopup="dialog" aria-expanded={mostrarTarifas} onClick={() => setTarifasAbiertas(true)}><Settings className="size-4" aria-hidden="true" />Tarifas</button>}
            </div>
        </section>
        {sin_areas && <p role="alert" className="theme-text-aviso">Su cuenta no tiene áreas asignadas. Solicite acceso al administrador del módulo.</p>}
        <nav className={`${GELIA_SEGMENT_TABS_SCROLL}`} aria-label="Secciones de comisiones"><div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1`}>{TABS.map((item) => <button key={item.id} type="button" aria-current={tabVista === item.id ? 'page' : undefined} data-active={tabVista === item.id} className="gelia-segment-btn" onClick={() => navegar({ tab: item.id, ...(item.id === 'acumulado' && filtros.periodo_id ? { periodo_id: filtros.periodo_id } : {}) })}>{item.label}</button>)}</div></nav>
        {tabVista === 'acumulado' && <Acumulado resumen={resumen} filtros={filtros} areas={areas} situaciones={situaciones} acumulado={acumulado} totales={totales} puede={puede} tipos={tipos_caja} capturaNombre={captura_nombre} sinAreas={sin_areas} paqueterias={paqueterias} cargaRevision={carga_revision} />}
        {tabVista === 'equipo' && <Equipo key={areaId} equipo={equipo} areaId={areaId} puede={puede} acumulado={resumen?.importe_por_cortar || '0.00'} />}
        {tabVista === 'periodos' && <Periodos key={areaId} periodos={periodos} equipo={equipo} areaId={areaId} puede={puede} />}
        {tabVista === 'preparar' && <Preparar areas={areas} filtros={filtros} preview={preview} puede={puede} />}
        {tabVista === 'cortes' && <Cortes cortes={cortes} corte={corte} puede={puede} />}
        {tabVista === 'pagos' && <Pagos key={areaId} saldos={saldos} puede={puede} areaId={areaId} />}
        {mostrarTarifas && <Configuracion key={areaId || 'todas'} areas={areas} areaInicial={areaId} fechaInicio={fecha_inicio} fechaFin={fecha_fin} participantes={participantes} tarifas={tarifas} fuentes={fuentes} alcances={alcances} onCerrar={cerrarTarifas} />}
    </GeliaPageShell></AppLayout>;
}

export function Acumulado({ resumen, filtros, areas = [], situaciones, acumulado, totales, puede, tipos, capturaNombre, sinAreas, paqueterias = [], cargaRevision = null }) {
    const [local, setLocal] = useState(filtros);
    const [capturar, setCapturar] = useState(false);
    const [cargar, setCargar] = useState(Boolean(cargaRevision));
    const filas = acumulado?.data || [];
    const cambiarVista = (vista) => ir({ ...filtros, tab: 'acumulado', vista: vista || undefined, situacion: undefined, page: undefined });
    const vistas = [
        ['', 'Todos', resumen?.total_registros, Layers],
        ['pendientes', 'Por revisar', resumen?.por_revisar, ClipboardList],
        ['revisados', 'Revisados', resumen?.revisados, CheckCircle2],
    ];
    const indicadores = [
        ['Cajas', resumen?.cajas_elegibles ?? 0, Package],
        ['Fondo abierto', dinero(resumen?.importe_por_cortar || '0.00'), Wallet],
        ['Pagado fuera', dinero(resumen?.importe_constancia || '0.00'), BadgeCheck],
    ];
    return <div className="space-y-8">
        <section className={geliaCardClass('p-4 md:p-5 space-y-5')} aria-label="Resumen de empaques">
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
                {vistas.map(([valor, nombre, cantidad, Icono]) => {
                    const activo = filtros.vista === valor || (!filtros.vista && !valor);
                    return <button key={nombre} type="button" aria-label={nombre} aria-pressed={activo} onClick={() => cambiarVista(valor)} className={`rounded-xl border p-3 text-left min-h-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${activo ? 'theme-element theme-text-primario outline outline-2 outline-offset-2' : 'theme-element theme-border theme-text-main'}`}>
                        <span className="flex items-center gap-2 text-xs theme-text-muted"><Icono className="size-4 shrink-0" aria-hidden="true" />{nombre}</span>
                        <span className="mt-2 block text-2xl font-bold tabular-nums leading-none">{cantidad ?? 0}</span>
                    </button>;
                })}
                {indicadores.map(([nombre, valor, Icono]) => <div key={nombre} className="rounded-xl border theme-border theme-element p-3 min-h-20">
                    <span className="flex items-center gap-2 text-xs theme-text-muted"><Icono className="size-4 shrink-0" aria-hidden="true" />{nombre}</span>
                    <span className="mt-2 block text-2xl font-bold tabular-nums leading-none theme-text-main">{valor}</span>
                </div>)}
            </div>
            <div className="flex flex-wrap gap-2 border-t theme-border pt-4">
                {puede.capturar && <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" aria-expanded={capturar} aria-label={capturar ? 'Ocultar registro' : 'Registrar empaque'} onClick={() => { setCapturar(!capturar); setCargar(false); }}>{capturar ? <X className="size-4" aria-hidden="true" /> : <Plus className="size-4" aria-hidden="true" />}{capturar ? 'Cerrar' : 'Registrar'}</button>}
                {puede.capturar && <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" aria-expanded={cargar} aria-label={cargar ? 'Ocultar carga' : 'Cargar Excel'} onClick={() => { setCargar(!cargar); setCapturar(false); }}>{cargar ? <X className="size-4" aria-hidden="true" /> : <Upload className="size-4" aria-hidden="true" />}{cargar ? 'Cerrar' : 'Cargar'}</button>}
                {puede.exportar && <a className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" aria-label="Exportar reporte" href={route('gestion_interna.comisiones_empaque.exportar.acumulado', filtros)}><Download className="size-4" aria-hidden="true" />Exportar</a>}
            </div>
        </section>
        <form className={`${geliaCardClass('p-4 md:p-5')} flex flex-wrap items-end gap-3`} onSubmit={(e) => { e.preventDefault(); ir({ ...local, tab: 'acumulado' }); }}><label className="flex-1 min-w-48 max-w-md"><span className={THEME_LABEL}>Buscar cliente</span><input className={THEME_INPUT} value={local.cliente || ''} onChange={(e) => setLocal({ ...local, cliente: e.target.value })} placeholder="Nombre del cliente…" /></label><label className="min-w-40"><span className={THEME_LABEL}>Remisión</span><input className={THEME_INPUT} value={local.remision || ''} onChange={(e) => setLocal({ ...local, remision: e.target.value })} placeholder="Folio…" /></label><button className={BOTON_FILTRO}><Search className="size-4" aria-hidden="true" />Buscar</button><button type="button" className={BOTON_FILTRO} onClick={() => ir({ tab: 'acumulado', area_id: filtros.area_id, periodo_id: filtros.periodo_id })}><X className="size-4" aria-hidden="true" />Limpiar filtros</button>
            <details className="basis-full"><summary className={`${BOTON_FILTRO} w-fit list-none [&::-webkit-details-marker]:hidden`}><SlidersHorizontal className="size-4" aria-hidden="true" />Más filtros</summary><div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 py-3">{[['desde', 'Empaque desde', 'date'], ['hasta', 'Empaque hasta', 'date'], ['pedido', 'Pedido', 'text'], ['empacador', 'Persona que empacó', 'text']].map(([k, label, type]) => <label key={k}><span className={THEME_LABEL}>{label}</span><input className={THEME_INPUT} type={type} value={local[k] || ''} disabled={!!filtros.periodo_id && ['desde', 'hasta'].includes(k)} onChange={(e) => setLocal({ ...local, [k]: e.target.value })} /></label>)}<label><span className={THEME_LABEL}>Situación</span><select className={THEME_INPUT} value={local.situacion || ''} onChange={(e) => setLocal({ ...local, situacion: e.target.value })}><option value="">Todas</option>{Object.entries(situaciones).map(([id, nombre]) => <option key={id} value={id}>{nombre}</option>)}</select></label></div></details>
        </form>
        {capturar && <Manual areas={areas} paqueterias={paqueterias} capturaNombre={capturaNombre} />}
        {cargar && <Carga revision={cargaRevision} />}
        <div className={geliaCardClass('p-3 md:p-4')}><div className="overflow-x-auto"><table className="w-full text-sm min-w-[780px]"><thead><tr className="text-left theme-text-muted">{['Empaque', 'Cliente y remisión', 'Empacó', 'Cajas', 'Envío', 'Aporta al fondo', 'Liquidación', 'Revisión'].map((h) => <th key={h} className={`py-3 px-2 font-medium ${h === 'Aporta al fondo' ? 'text-right' : ''}`}>{h}</th>)}</tr></thead><tbody>{!filas.length && <tr><td colSpan={8} className="p-6 theme-text-muted">{sinAreas ? 'No tiene áreas asignadas.' : filtros.vista === 'pendientes' ? 'No hay empaques pendientes de revisión con estos filtros.' : 'No hay empaques con estos filtros. Actualice los empaques o elija otro periodo.'}</td></tr>}{filas.map((fila) => <Fila key={fila.id} fila={fila} puede={puede} tipos={tipos} />)}</tbody></table></div>
            <div className="flex flex-wrap justify-between items-center gap-3 border-t theme-border pt-4 mt-3"><p className="text-sm m-0"><strong>{dinero(resumen?.importe_por_cortar || '0.00')} fondo abierto · {dinero(resumen?.importe_constancia || '0.00')} pagado fuera</strong></p><p className="text-xs theme-text-muted m-0">{totales?.registros ?? 0} registros en este resultado · {(resumen?.por_revisar ?? 0)} por revisar</p></div>
        </div>
        <GeliaPaginacion paginator={acumulado} onIrAPagina={(page) => ir({ tab: 'acumulado', ...filtros, page })} />
    </div>;
}

function estadoRevision(fila) {
    if (fila.liquidacion === 'pagado_fuera' || fila.situacion === 'liquidada_fuera') return { texto: 'Pagado', tono: 'exito' };
    if (['excluida', 'rechazada'].includes(fila.situacion)) return { texto: 'No se paga', tono: 'peligro' };
    if (fila.situacion === 'en_corte') return { texto: 'En corte', tono: 'info' };
    if (fila.situacion === 'elegible') return { texto: 'Revisado', tono: 'info' };
    return { texto: fila.situacion_etiqueta || 'Pendiente', tono: 'aviso' };
}

function textoCajas(cantidad) {
    const numero = Number(cantidad) || 0;
    return `${numero} ${numero === 1 ? 'caja' : 'cajas'}`;
}

function Fila({ fila, puede, tipos }) {
    const [abierta, setAbierta] = useState(false);
    const pagadoFuera = fila.liquidacion === 'pagado_fuera' || fila.situacion === 'liquidada_fuera';
    const revisada = ['elegible', 'en_corte'].includes(fila.situacion);
    const estado = estadoRevision(fila);
    return <>
        <tr className="border-t theme-border align-top"><td className="p-2 whitespace-nowrap">{fila.empacado_at}{fila.incorporacion_tardia && <span className="block text-xs theme-text-aviso">Incorporación tardía</span>}</td><td className="p-2"><span className="font-semibold">{fila.cliente}</span><span className="block text-xs theme-text-muted">{fila.folio_remision || 'Sin remisión'} · {fila.folio_pedido || 'Sin pedido'}</span>{fila.area && <span className="block text-xs theme-text-muted">{fila.area}</span>}</td><td className="p-2">{fila.empacador}</td><td className="p-2 tabular-nums">{fila.cajas ?? fila.cantidad_origen}{fila.bolsas > 0 && <span className="block text-xs theme-text-muted">{fila.bolsas} bolsas excluidas</span>}</td><td className="p-2 max-w-52"><span>{fila.logistica?.paqueteria || fila.logistica?.modalidad_preparacion || 'Sin paquetería'}</span><span className="block text-xs theme-text-muted">{fila.logistica?.logistica === 'sin' ? 'Sin logística' : fila.logistica?.logistica === 'con' ? 'Con logística' : (fila.logistica?.modalidad_tarifa || fila.logistica?.tipo_guia || '')}</span></td><td className="p-2 text-right whitespace-nowrap tabular-nums">{['excluida', 'rechazada'].includes(fila.situacion) ? 'No comisiona' : (revisada || pagadoFuera) && fila.importe != null ? dinero(fila.importe) : 'Por revisar'}</td><td className="p-2 text-xs">{pagadoFuera ? 'Pagado fuera' : 'Pendiente'}</td><td className="p-2"><div className="flex flex-wrap items-center gap-4"><span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${INSIGNIA[estado.tono]}`}>{estado.texto}</span>{!pagadoFuera && <button type="button" className={revisada ? BOTON_FILTRO : `${THEME_BTN_PRIMARY} theme-btn-primary--compact`} aria-haspopup="dialog" aria-expanded={abierta} onClick={() => setAbierta(true)}>{revisada ? <Eye className="size-4" aria-hidden="true" /> : null}{revisada ? 'Ver detalle' : 'Revisar'}</button>}</div></td></tr>
        {abierta && <RevisionEmpaque fila={fila} puede={puede} tipos={tipos} onCerrar={() => setAbierta(false)} />}
    </>;
}

function RevisionEmpaque({ fila, puede, tipos, onCerrar }) {
    const [verPedido, setVerPedido] = useState(false);
    useEffect(() => {
        const alTecla = (evento) => { if (evento.key === 'Escape') onCerrar(); };
        window.addEventListener('keydown', alTecla);
        return () => window.removeEventListener('keydown', alTecla);
    }, [onCerrar]);
    const modal = <div className={THEME_MODAL_OVERLAY} onClick={onCerrar}>
        <div role="dialog" aria-modal="true" aria-labelledby={`revision-${fila.id}`} tabIndex={-1} className={`${THEME_MODAL_SHELL} max-w-3xl`} onClick={(evento) => evento.stopPropagation()}>
            <header className="flex items-start justify-between gap-4 border-b theme-border p-5 md:p-6">
                <div>
                    <h2 id={`revision-${fila.id}`} className="text-xl font-bold theme-text-main m-0">Revisión del empaque</h2>
                    <p className="text-sm theme-text-muted m-0 mt-1">{fila.cliente}</p>
                </div>
                <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg border theme-border theme-element theme-text-main" aria-label="Cerrar detalle" onClick={onCerrar}><X className="size-4" aria-hidden="true" /></button>
            </header>
            <div className="gelia-modal-body space-y-4 p-5 md:p-6">
                <p className="text-sm theme-text-muted m-0">{fila.area} · {fila.tipos?.join(', ') || 'Cajas pendientes de clasificar'} · {logisticaTexto(fila.logistica)}</p>
                {fila.pedido_bma_id ? <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main" aria-expanded={verPedido} onClick={() => setVerPedido(!verPedido)}><Eye className="size-4" aria-hidden="true" />{verPedido ? 'Ocultar pedido' : 'Ver pedido'}</button> : <p className="text-sm theme-text-muted m-0">Sin pedido de Gelia vinculado.</p>}
                {verPedido && <PedidoInformativo unidadId={fila.id} />}
                {fila.corte && <p className="text-sm m-0">Conservado en el corte {fila.corte}.</p>}
                {fila.excepcion_resguardo && <p className="text-sm theme-text-aviso m-0">Este empaque corresponde a la excepción de resguardo.</p>}
                {fila.diferencia && <p className="text-sm theme-text-aviso m-0">{fila.diferencia}</p>}
                {puede.clasificar && <Clasificar fila={fila} tipos={tipos} />}
                <p className="text-xs theme-text-muted m-0">El importe de estas cajas se suma al fondo común. El reparto entre colaboradores se calcula con los porcentajes permanentes del área.</p>
            </div>
        </div>
    </div>;
    return typeof document === 'undefined' ? modal : createPortal(modal, document.body);
}

function PedidoInformativo({ unidadId }) {
    const [estado, setEstado] = useState('cargando');
    const [pedido, setPedido] = useState(null);
    useEffect(() => {
        let activo = true;
        axios.get(route('gestion_interna.comisiones_empaque.pedido.informativo', unidadId))
            .then((respuesta) => { if (activo) { setPedido(respuesta.data); setEstado('listo'); } })
            .catch(() => { if (activo) setEstado('error'); });
        return () => { activo = false; };
    }, [unidadId]);
    if (estado === 'cargando') return <p className="text-sm theme-text-muted m-0">Consultando el pedido…</p>;
    if (estado === 'error') return <p className="text-sm theme-text-aviso m-0" role="alert">No se pudo consultar el pedido.</p>;
    if (!pedido?.vinculado) return <p className="text-sm theme-text-muted m-0">{pedido?.mensaje || 'Este empaque no está vinculado a un pedido de Gelia.'}</p>;
    const datos = [
        ['Pedido', pedido.folio_pedido || 'Sin folio'],
        ['Remisión', pedido.folio_remision || 'Sin remisión'],
        ['Cliente', pedido.cliente || 'Sin cliente'],
        ['Estatus', pedido.estatus || 'Sin estatus'],
        ['Paquetería', pedido.paqueteria || 'Sin paquetería'],
        ['Cajas declaradas', textoCajas(pedido.cajas_declaradas)],
        ['Bultos', pedido.bultos ?? 0],
        ['Empacado', pedido.empacado_at || 'Sin fecha'],
    ];
    return <section className={geliaCardClass('space-y-3 p-4')} aria-label="Pedido de origen">
        <div>
            <h3 className="text-base font-bold m-0">Pedido de origen</h3>
            <p className="text-sm theme-text-muted m-0 mt-1">Consulta informativa para clasificar las cajas. Permanece en Comisiones de empaque.</p>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {datos.map(([nombre, valor]) => <div key={nombre} className="rounded-xl border theme-border theme-element p-3">
                <span className="block text-xs theme-text-muted">{nombre}</span>
                <span className="mt-1 block text-sm font-semibold theme-text-main">{valor}</span>
            </div>)}
        </div>
        <div>
            <h4 className="text-sm font-bold m-0">Cajas registradas en el pedido</h4>
            {!pedido.cajas?.length && <p className="text-sm theme-text-muted m-0 mt-1">El pedido no tiene un desglose de cajas.</p>}
            {!!pedido.cajas?.length && <ul className="list-none m-0 mt-2 grid gap-2 p-0 sm:grid-cols-2">{pedido.cajas.map((caja, indice) => <li key={`${caja.tipo}-${indice}`} className="rounded-lg border theme-border px-3 py-2 text-sm">{textoCajas(caja.cantidad)} · {caja.tipo}</li>)}</ul>}
        </div>
    </section>;
}

function Clasificar({ fila, tipos }) {
    const form = useForm({
        lineas: fila.lineas?.length ? fila.lineas : [{ cantidad: fila.cantidad_origen || 1, es_bolsa: false, catalogo_tipo_caja_id: '' }],
        decision: 'conservar',
        motivo: '',
        revision: fila.posible_duplicado ? 'trabajo_distinto' : (fila.referencia === 'insuficiente' ? 'aceptar_sin_referencia' : 'aceptar'),
    });
    const suma = form.data.lineas.reduce((n, l) => n + Number(l.cantidad || 0), 0);

    return (
        <div className="space-y-3">
            <ErroresFormulario form={form} />
            {(fila.situacion === 'pendiente_revision' || fila.situacion === 'posible_duplicado') && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.post(route('gestion_interna.comisiones_empaque.revisar', fila.id)); }}>
                    {fila.posible_duplicado && (
                        <div className="w-full">
                            <p className="theme-text-aviso m-0">Hay una coincidencia. La aceptación normal queda bloqueada hasta reconocer un trabajo distinto, con motivo, o rechazar el registro. Un conflicto es el mismo día. Un antecedente es otra fecha y no demuestra por sí solo que sea otro trabajo.</p>
                            <ul className="m-0 pl-4">
                                {(fila.relacionados || []).map((rel) => (
                                    <li key={rel.id}>{rel.criterio === 'antecedente' ? 'Antecedente' : 'Conflicto'} · {rel.empacado_at || 'Sin fecha'} · {rel.folio_pedido || rel.clave_origen} · {rel.folio_remision || 'Sin remisión'} · {rel.cantidad} · {rel.situacion}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {fila.referencia === 'insuficiente' && !fila.posible_duplicado && (
                        <p className="theme-text-aviso m-0 w-full">Falta una referencia verificable. Vincule un pedido o indique folio o remisión. Si no existe, la persona revisora puede aceptar explícitamente esa falta.</p>
                    )}
                    <label><span className={THEME_LABEL}>Revisión del registro</span>
                        <select className={THEME_INPUT} value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)}>
                            {!fila.posible_duplicado && fila.referencia !== 'insuficiente' && <option value="aceptar">Aceptar</option>}
                            {!fila.posible_duplicado && fila.referencia === 'insuficiente' && <option value="aceptar_sin_referencia">Aceptar sin referencia verificable</option>}
                            {fila.posible_duplicado && <option value="trabajo_distinto">Reconocer trabajo distinto</option>}
                            <option value="rechazar">Rechazar</option>
                        </select>
                    </label>
                    <label className="flex-1"><span className={THEME_LABEL}>Motivo</span>
                        <input className={THEME_INPUT} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    </label>
                    <button className={THEME_BTN_PRIMARY} type="button" disabled={form.processing} onClick={() => { form.transform((data) => ({ decision: data.revision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.revisar', fila.id)); }}>Guardar revisión</button>
                </form>
            )}
            {fila.situacion !== 'en_corte' && fila.situacion !== 'rechazada' && fila.situacion !== 'posible_duplicado' && fila.situacion !== 'pendiente_revision' && (
                <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ lineas: data.lineas.map((l) => ({ ...l, catalogo_tipo_caja_id: l.catalogo_tipo_caja_id || null, es_bolsa: !!l.es_bolsa })) })); form.post(route('gestion_interna.comisiones_empaque.clasificar', fila.id)); }}>
                    <p className="m-0 text-sm">El desglose debe sumar {textoCajas(fila.cantidad_origen)}. Ahora suma {textoCajas(suma)}.</p>
                    <p className="m-0 text-xs theme-text-muted">Estas cantidades son cajas del empaque. El dinero se calcula después, con la tarifa del área.</p>
                    {form.data.lineas.map((linea, i) => (
                        <div key={i} className="flex flex-wrap items-end gap-3">
                            <label className="w-28"><span className={THEME_LABEL}>Cajas</span><input className={THEME_INPUT} type="number" min="1" aria-label="Cantidad de cajas" value={linea.cantidad} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, cantidad: e.target.value };
                                form.setData('lineas', lineas);
                            }} /></label>
                            <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" checked={!!linea.es_bolsa} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, es_bolsa: e.target.checked, catalogo_tipo_caja_id: e.target.checked ? '' : linea.catalogo_tipo_caja_id };
                                form.setData('lineas', lineas);
                            }} /> Bolsa excluida, no aporta al fondo</label>
                            {!linea.es_bolsa && (
                                <select className={THEME_INPUT} aria-label="Tipo de caja" value={linea.catalogo_tipo_caja_id || ''} onChange={(e) => {
                                    const lineas = [...form.data.lineas];
                                    lineas[i] = { ...linea, catalogo_tipo_caja_id: e.target.value };
                                    form.setData('lineas', lineas);
                                }}>
                                    <option value="">Caja sin tipo</option>
                                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                            )}
                        </div>
                    ))}
                    <p className="m-0 text-xs theme-text-muted">Agregue otra línea cuando el empaque mezcle tipos de caja o incluya una bolsa.</p>
                    <div className="flex flex-wrap gap-2">
                        <button type="button" className="inline-flex items-center gap-2 rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main" onClick={() => form.setData('lineas', [...form.data.lineas, { cantidad: 1, es_bolsa: false, catalogo_tipo_caja_id: '' }])}><Plus className="size-4" aria-hidden="true" />Agregar tipo de caja</button>
                        <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar desglose</button>
                    </div>
                </form>
            )}
            {fila.situacion === 'pendiente_decision' && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.decision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.decision', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Decisión de cancelación" value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}>
                        <option value="conservar">Conservar comisión</option>
                        <option value="excluir">Excluir comisión</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la decisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Registrar decisión'}</button>
                </form>
            )}
            {['reempaque_revision', 'solape_revision', 'revertida_revision'].includes(fila.situacion) && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.revision, motivo: data.motivo })); form.post(route('gestion_interna.comisiones_empaque.resolver', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Resolución" value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)}>
                        <option value="aceptar">Aceptar para comisión</option>
                        <option value="descartar">Descartar</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la revisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Resolver</button>
                </form>
            )}
        </div>
    );
}

function Carga({ revision }) {
    const form = useForm({ texto: revision?.texto || '' });
    const errores = revision?.errores || [];
    const puedeGuardar = revision && errores.length === 0 && revision.resumen?.total > 0 && form.data.texto.trim() !== '';
    function enviar(ruta) {
        form.post(ruta, { preserveScroll: true });
    }
    return (
        <form className={geliaCardClass('p-4 space-y-3')} onSubmit={(e) => { e.preventDefault(); enviar(route('gestion_interna.comisiones_empaque.carga.revisar')); }}>
            <p className="m-0 text-sm theme-text-muted">Pegue el CSV con fecha, cliente, cajas, remisión, paquetería, logística (con o sin), quién empacó, área y liquidación (pendiente o pagado fuera). Si una fila es inválida, no se guarda ninguna.</p>
            <label className="block"><span className={THEME_LABEL}>Contenido del Excel en CSV</span>
                <textarea className={THEME_INPUT} rows={6} required value={form.data.texto} onChange={(e) => form.setData('texto', e.target.value)} placeholder="fecha,cliente,cajas,remision,paqueteria,logistica,empaco,area,liquidacion" />
            </label>
            <div className="flex flex-wrap gap-2">
                <button className={THEME_BTN_SECONDARY} disabled={form.processing} type="submit">{form.processing ? 'Revisando…' : 'Revisar carga'}</button>
                <button className={THEME_BTN_PRIMARY} type="button" disabled={!puedeGuardar || form.processing} onClick={() => enviar(route('gestion_interna.comisiones_empaque.carga.store'))}>Guardar carga</button>
            </div>
            {form.errors.texto && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.texto}</p>}
            {revision && <div role="status" className="text-sm space-y-1">
                <p className="m-0">{revision.resumen?.total ?? 0} filas leídas · {revision.resumen?.pendientes ?? 0} pendientes nuevas · {revision.resumen?.pagadas ?? 0} pagadas fuera · {revision.resumen?.repetidas ?? 0} ya registradas</p>
                {errores.map((error) => <p key={`${error.fila}-${error.mensaje}`} className="theme-text-peligro m-0">{error.mensaje}</p>)}
            </div>}
        </form>
    );
}

function Manual({ areas = [], paqueterias = [], capturaNombre = '' }) {
    const clave = useRef(typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `cap-${Date.now()}`);
    const form = useForm({
        clave_idempotencia: clave.current,
        area_id: areas[0]?.id || '',
        empacado_at: '',
        cantidad: 1,
        cliente_nombre: '',
        folio_remision: '',
        paqueteria: '',
        logistica: 'sin',
        empacador_nombre: '',
        empacador_user_id: '',
        liquidacion: 'pendiente',
        notas: '',
    });

    function elegirPaqueteria(nombre) {
        const catalogo = paqueterias.find((item) => item.nombre === nombre);
        form.setData({
            ...form.data,
            paqueteria: nombre,
            logistica: catalogo ? (catalogo.sin_logistica ? 'sin' : 'con') : form.data.logistica,
        });
    }

    return (
        <form className={geliaCardClass('p-4 grid md:grid-cols-3 gap-3')} onSubmit={(e) => {
            e.preventDefault();
            form.transform((data) => ({ ...data, clave_idempotencia: clave.current, empacador_user_id: data.empacador_user_id || null }));
            form.post(route('gestion_interna.comisiones_empaque.manual'), {
                onSuccess: () => { clave.current = crypto.randomUUID(); form.setData('clave_idempotencia', clave.current); },
            });
        }}>
            <p className="md:col-span-3 m-0 text-sm theme-text-muted">Registre un empaque que no está en Gelia. La remisión identifica la fila. Si la liquidación es pendiente, entra al fondo solo después de la revisión. Si es pagado fuera, queda como constancia y no se vuelve a pagar. Quien captura esta pantalla: {capturaNombre}.</p>
            <label><span className={THEME_LABEL}>Fecha de empaque</span>
                <input className={THEME_INPUT} type="datetime-local" required value={form.data.empacado_at} onChange={(e) => form.setData('empacado_at', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Cliente</span>
                <input className={THEME_INPUT} required value={form.data.cliente_nombre} onChange={(e) => form.setData('cliente_nombre', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Cajas</span>
                <input className={THEME_INPUT} type="number" min="1" required value={form.data.cantidad} onChange={(e) => form.setData('cantidad', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Remisión</span>
                <input className={THEME_INPUT} required value={form.data.folio_remision} onChange={(e) => form.setData('folio_remision', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Área</span>
                <select className={THEME_INPUT} required value={form.data.area_id} onChange={(e) => form.setData('area_id', e.target.value)}>{areas.map((area) => <option key={area.id} value={area.id}>{area.nombre}</option>)}</select>
            </label>
            <label><span className={THEME_LABEL}>Paquetería</span>
                <input className={THEME_INPUT} required list="paqueterias-comision" value={form.data.paqueteria} onChange={(e) => elegirPaqueteria(e.target.value)} />
                <datalist id="paqueterias-comision">{paqueterias.map((item) => <option key={item.id} value={item.nombre} />)}</datalist>
            </label>
            <label><span className={THEME_LABEL}>Logística</span>
                <select className={THEME_INPUT} value={form.data.logistica} onChange={(e) => form.setData('logistica', e.target.value)}><option value="sin">Sin logística</option><option value="con">Con logística</option></select>
            </label>
            <label><span className={THEME_LABEL}>Quién empacó</span>
                <input className={THEME_INPUT} required value={form.data.empacador_nombre} onChange={(e) => form.setData('empacador_nombre', e.target.value)} />
            </label>
            <div><BuscarUsuario value={form.data.empacador_user_id} nombre="" etiqueta="Cuenta de Gelia, si existe" onChange={(id, nombre) => form.setData({ ...form.data, empacador_user_id: id, empacador_nombre: nombre || form.data.empacador_nombre })} /></div>
            <label><span className={THEME_LABEL}>Liquidación</span>
                <select className={THEME_INPUT} value={form.data.liquidacion} onChange={(e) => form.setData('liquidacion', e.target.value)}><option value="pendiente">Pendiente, entra al fondo al revisarse</option><option value="pagado_fuera">Pagado fuera, solo constancia</option></select>
            </label>
            <label className="md:col-span-3"><span className={THEME_LABEL}>Observaciones</span>
                <input className={THEME_INPUT} value={form.data.notas} onChange={(e) => form.setData('notas', e.target.value)} />
            </label>
            <div className="md:col-span-3 space-y-1">
                <button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar registro'}</button>
                {Object.entries(form.errors).map(([campo, mensaje]) => <p key={campo} className="theme-text-peligro text-sm m-0" role="alert">{mensaje}</p>)}
            </div>
        </form>
    );
}

export function Preparar({ areas, filtros, preview, puede }) {
    const [area, setArea] = useState(filtros.area_id || areas[0]?.id || '');
    const [desde, setDesde] = useState(filtros.limite_desde || filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.limite_hasta || filtros.hasta || '');
    const [tardias, setTardias] = useState(!!Number(filtros.incluir_tardias || 0));
    const calculoVigente = String(area) === String(filtros.area_id) && desde === filtros.limite_desde && hasta === filtros.limite_hasta && tardias === !!Number(filtros.incluir_tardias || 0);
    const form = useForm({});

    function ver(e) {
        e.preventDefault();
        ir({ tab: 'preparar', area_id: area, limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias ? 1 : 0 });
    }

    return (
        <form className="grid lg:grid-cols-[1fr_320px] gap-4" onSubmit={ver}>
            <div className="space-y-4">
                <div className={geliaCardClass('p-4 grid md:grid-cols-2 gap-3')}>
                    <p className="md:col-span-2 text-sm theme-text-muted m-0">Las fechas deben corresponder al periodo que va a cerrar. Puede elegirlo desde la sección Periodos.</p>
                    <label><span className={THEME_LABEL}>Área</span>
                        <select className={THEME_INPUT} value={area} onChange={(e) => setArea(e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select>
                    </label>
                    <label className="flex items-end gap-2 text-sm"><input type="checkbox" checked={tardias} onChange={(e) => setTardias(e.target.checked)} /> Incluir incorporaciones tardías anteriores al inicio</label>
                    <label><span className={THEME_LABEL}>Desde</span><input className={THEME_INPUT} type="date" required value={desde} onChange={(e) => setDesde(e.target.value)} /></label>
                    <label><span className={THEME_LABEL}>Hasta</span><input className={THEME_INPUT} type="date" required value={hasta} onChange={(e) => setHasta(e.target.value)} /></label>
                    <button className={THEME_BTN_SECONDARY} type="submit">Revisar cálculo</button>
                </div>
                {preview && calculoVigente && (
                    <div className={geliaCardClass('overflow-x-auto')}>
                        <table className="w-full text-sm">
                            <thead><tr className="text-left theme-text-muted"><th className="p-2">Empaque</th><th className="p-2">Pedido</th><th className="p-2">Remisión</th><th className="p-2 text-right">Cajas</th><th className="p-2 text-right">Importe</th></tr></thead>
                            <tbody>
                                {preview.unidades.length === 0 && <tr><td colSpan={5} className="p-4 theme-text-muted">No hay unidades elegibles en este intervalo.</td></tr>}
                                {preview.unidades.map((u) => (
                                    <tr key={u.id} className="border-t theme-border"><td className="p-2">{u.empacado_at}</td><td className="p-2">{u.folio_pedido}</td><td className="p-2">{u.folio_remision || 'Sin remisión'}</td><td className="p-2 text-right">{u.cantidad}</td><td className="p-2 text-right">{dinero(u.importe)}</td></tr>
                                ))}
                            </tbody>
                        </table>
                        {preview.exclusiones?.length > 0 && (
                            <div className="p-3 text-sm">
                                <p className="font-semibold m-0">Fuera de este corte</p>
                                <ul className="m-0 pl-4">{preview.exclusiones.map((e) => <li key={e.id}>{e.folio_pedido || e.id}: {e.situacion}</li>)}</ul>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <aside className={geliaCardClass('p-4 h-fit space-y-2 lg:sticky lg:top-4')}>
                <h2 className="text-base font-black m-0">Resumen</h2>
                {(!preview || !calculoVigente) && <p className="text-sm theme-text-muted m-0">Revise el cálculo para ver cantidades, reparto e importe.</p>}
                {preview && calculoVigente && (
                    <>
                        <p className="m-0 text-sm">Cajas: {preview.cajas}</p>
                        <p className="m-0 text-sm">Importe: {dinero(preview.importe)}</p>
                        {preview.tipos.map((t) => <p key={t.nombre} className="m-0 text-sm">{t.nombre}: {t.cantidad}</p>)}
                        {preview.tramos.map((tramo) => (
                            <div key={tramo.reparto_id} className="text-sm border-t theme-border pt-2">
                                {(tramo.participantes || []).map((p) => <p key={p.participante_id} className="m-0">{p.nombre} {p.porcentaje}% · {dinero(p.importe)}</p>)}
                            </div>
                        ))}
                        <p className="text-sm theme-text-muted m-0">Al confirmar se vuelven a leer las fuentes de estas unidades. Si cambiaron, hay que actualizar y revisar el cálculo. Un cambio posterior al corte no lo reescribe: queda como incidencia.</p>
                        {preview.errores.map((error) => <p key={error} className="theme-text-peligro text-sm m-0" role="alert">{error}</p>)}
                        {form.errors.corte && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.corte}</p>}
                        {puede.confirmar && preview.errores.length === 0 && preview.unidades.length > 0 && (
                            <button type="button" className={THEME_BTN_PRIMARY} disabled={form.processing} onClick={() => { form.transform(() => ({ area_id: area, limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias, hash: preview.hash })); form.post(route('gestion_interna.comisiones_empaque.confirmar')); }}>Confirmar corte</button>
                        )}
                        {form.errors.corte && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.corte}</p>}
                    </>
                )}
            </aside>
        </form>
    );
}

function Cortes({ cortes, corte, puede }) {
    return (
        <div className="space-y-4">
            <div className={geliaCardClass('overflow-x-auto')}>
                <table className="w-full text-sm min-w-[800px]">
                    <thead><tr className="text-left theme-text-muted">{['Folio', 'Área', 'Intervalo', 'Cajas', 'Total', 'Pagado', 'Pendiente', 'Estado', 'Responsable'].map((h) => <th key={h} className="p-2">{h}</th>)}</tr></thead>
                    <tbody>
                        {(cortes?.data || []).length === 0 && <tr><td colSpan={9} className="p-6 theme-text-muted">Todavía no hay cortes.</td></tr>}
                        {(cortes?.data || []).map((c) => (
                            <tr key={c.id} className="border-t theme-border">
                                <td className="p-2"><button type="button" className="theme-text-primario" onClick={() => ir({ tab: 'cortes', corte: c.id })}>{c.folio}</button>{c.provisional && <span className="block text-xs">Provisional</span>}</td>
                                <td className="p-2">{c.area}</td>
                                <td className="p-2">{c.desde} a {c.hasta}</td>
                                <td className="p-2">{c.cajas}</td>
                                <td className="p-2 text-right">{dinero(c.total)}</td>
                                <td className="p-2 text-right">{dinero(c.pagado)}</td>
                                <td className="p-2 text-right">{dinero(c.pendiente)}</td>
                                <td className="p-2">{c.estado} · {c.estado_pago}</td>
                                <td className="p-2">{c.responsable || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {corte && (
                <section className={geliaCardClass('p-4 space-y-3')}>
                    <h2 className="text-lg font-black m-0">{corte.provisional ? 'Borrador provisional' : corte.folio}</h2>
                    <p className="text-sm theme-text-muted m-0">{corte.area} · {corte.desde} a {corte.hasta} · {corte.estado_pago} · {corte.responsable || 'Sin confirmación'}</p>
                    {puede.exportar && (
                        <div className="flex gap-2">
                            <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.corte', corte.id)}>Exportar corte</a>
                            <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.reparto', corte.id)}>Exportar reparto</a>
                        </div>
                    )}
                    {(corte.unidades || []).map((u) => <p key={u.clave} className="text-sm m-0">{u.empacado_at} · {u.pedido} · {u.cantidad} · {dinero(u.importe)}{u.excepcion_resguardo ? ' · Excepción de resguardo' : ''}</p>)}
                    {(corte.asignaciones || []).map((a) => <p key={a.id} className="text-sm m-0">{a.nombre} {a.porcentaje}% · reconocido {dinero(a.reconocido)} · pagado {dinero(a.pagado)} · pendiente {dinero(a.pendiente)}</p>)}
                    {(corte.ajustes || []).map((a, i) => <p key={i} className="text-sm theme-text-aviso m-0">Ajuste {dinero(a.importe)} {a.aplicar_en_saldo ? 'aplicado al saldo' : 'solo registrado'} · {a.motivo}</p>)}
                </section>
            )}
        </div>
    );
}

export function Pagos({ saldos, puede, areaId }) {
    const [id, setId] = useState(saldos[0]?.id || '');
    const persona = useMemo(() => saldos.find((s) => String(s.id) === String(id)), [saldos, id]);
    const [seleccion, setSeleccion] = useState({});
    const nuevaClave = () => typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
    const [clave, setClave] = useState(nuevaClave);
    useEffect(() => { setClave(nuevaClave()); }, [id]);
    const form = useForm({ fecha_pago: '', referencia: '', medio: '', observaciones: '' });
    const aPagar = persona ? persona.asignaciones.filter((a) => seleccion[a.id]).reduce((n, a) => n + Number(seleccion[a.id] || 0), 0) : 0;
    const pendiente = persona ? Number(persona.pendiente) : 0;

    return (
        <div className="space-y-4">
            {puede.exportar && <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.pagos', areaId ? { area_id: areaId } : {})}>Exportar pagos y saldos</a>}
            {saldos.length === 0 && <p className="theme-text-muted">No hay cortes confirmados con saldo en esta área. Cierre un periodo para calcular las comisiones por pagar.</p>}
            <label className="block max-w-md"><span className={THEME_LABEL}>Participante</span>
                <select className={THEME_INPUT} value={id} onChange={(e) => { setId(e.target.value); setSeleccion({}); }}>
                    {saldos.map((s) => <option key={s.id} value={s.id}>{s.nombre}{s.activo ? '' : ' (inactivo)'}</option>)}
                </select>
            </label>
            {persona && (
                <div className={geliaCardClass('p-4 space-y-3')}>
                    <p className="m-0 text-sm">Pendiente actual: {dinero(persona.pendiente)} · Reconocido: {dinero(persona.reconocido)} · Pagado: {dinero(persona.pagado)}</p>
                    {persona.asignaciones.map((a) => (
                        <label key={a.id} className="flex flex-wrap gap-2 items-center text-sm">
                            <input type="checkbox" checked={seleccion[a.id] != null} onChange={(e) => setSeleccion((prev) => {
                                const next = { ...prev };
                                if (e.target.checked) next[a.id] = a.pendiente;
                                else delete next[a.id];
                                return next;
                            })} />
                            <span>{a.folio} · {a.area} · pendiente {dinero(a.pendiente)}</span>
                            {seleccion[a.id] != null && <input className={`${THEME_INPUT} w-32`} aria-label={`Importe para ${a.folio}`} value={seleccion[a.id]} onChange={(e) => setSeleccion({ ...seleccion, [a.id]: e.target.value })} />}
                        </label>
                    ))}
                    {puede.pagar && (
                        <form className="grid md:grid-cols-2 gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                participante_id: persona.id,
                                importe: aPagar.toFixed(2),
                                clave_idempotencia: clave,
                                aplicaciones: Object.entries(seleccion).map(([asignacionId, importe]) => ({ asignacion_id: Number(asignacionId), importe: String(importe) })),
                            }));
                            form.post(route('gestion_interna.comisiones_empaque.pagar'), { onSuccess: () => { setSeleccion({}); setClave(nuevaClave()); form.reset(); } });
                        }}>
                            <label><span className={THEME_LABEL}>Fecha real de pago</span><input className={THEME_INPUT} type="date" required value={form.data.fecha_pago} onChange={(e) => form.setData('fecha_pago', e.target.value)} /></label>
                            <label><span className={THEME_LABEL}>Referencia o medio</span><input className={THEME_INPUT} value={form.data.referencia} onChange={(e) => form.setData('referencia', e.target.value)} /></label>
                            <p className="md:col-span-2 text-sm m-0">Importe a pagar: {dinero(aPagar.toFixed(2))}. Saldo posterior: {dinero((pendiente - aPagar).toFixed(2))}.</p>
                            <ErroresFormulario form={form} />
                            <button className={THEME_BTN_PRIMARY} disabled={form.processing || aPagar <= 0}>Registrar pago</button>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}

function ErroresFormulario({ form }) {
    return Object.entries(form.errors).map(([campo, error]) => <p key={campo} className="theme-text-peligro text-sm m-0" role="alert">{error}</p>);
}

function recientesPorArea(tarifas) {
    const vistas = new Set();
    return tarifas
        .filter((t) => t.vigente_hasta)
        .slice()
        .sort((a, b) => String(b.vigente_hasta).localeCompare(String(a.vigente_hasta)))
        .filter((t) => {
            const clave = String(t.area_id ?? t.area);
            if (vistas.has(clave)) return false;
            vistas.add(clave);
            return true;
        });
}

function Configuracion({ areas, areaInicial = '', fechaInicio, fechaFin, participantes, tarifas = [], alcances = [], onCerrar }) {
    const [seccion, setSeccion] = useState('importacion');
    const [historial, setHistorial] = useState(false);
    const fecha = useForm({ fecha_inicio: fechaInicio || '', fecha_fin: fechaFin || '' });
    const tarifa = useForm({ area_id: areaInicial || areas[0]?.id || '', importe: '', vigente_desde: '' });
    const area = useForm({ codigo: '', nombre: '' });
    const alcance = useForm({ user_id: '', area_ids: [] });
    const secciones = [['importacion', 'Gestionar Periodo'], ['cuentas', 'Cuentas'], ['accesos', 'Accesos'], ['areas', 'Áreas']];
    const vigentes = tarifas.filter((t) => !t.vigente_hasta);
    const cerradas = tarifas.filter((t) => t.vigente_hasta).slice().sort((a, b) => String(b.vigente_hasta).localeCompare(String(a.vigente_hasta)));
    const recientes = recientesPorArea(tarifas);
    useEffect(() => {
        const alTecla = (evento) => {
            if (evento.key !== 'Escape') return;
            if (historial) { setHistorial(false); return; }
            onCerrar();
        };
        window.addEventListener('keydown', alTecla);
        return () => window.removeEventListener('keydown', alTecla);
    }, [onCerrar, historial]);
    const modal = <div className={THEME_MODAL_OVERLAY} onClick={onCerrar}>
        <div role="dialog" aria-modal="true" aria-labelledby="tarifas-titulo" tabIndex={-1} className={`${THEME_MODAL_SHELL} max-w-5xl`} onClick={(evento) => evento.stopPropagation()}>
            <header className="flex items-start justify-between gap-4 border-b theme-border p-5 md:p-6">
                <div>
                    <h2 id="tarifas-titulo" className="text-xl font-bold theme-text-main m-0">Tarifas</h2>
                    <p className="text-sm theme-text-muted m-0 mt-1">La tarifa determina cuánto aporta cada caja al fondo común. El equipo y sus porcentajes se administran en Equipo y reparto.</p>
                </div>
                <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg border theme-border theme-element theme-text-main" aria-label="Cerrar tarifas" onClick={onCerrar}><X className="size-4" aria-hidden="true" /></button>
            </header>
            <div className="gelia-modal-body space-y-5 p-5 md:p-6">
                <section className={geliaCardClass('space-y-4 p-4 md:p-5')} aria-label="Comisión por caja">
                    <div>
                        <h3 className="text-base font-bold m-0">Comisión por caja</h3>
                        <p className="text-sm theme-text-muted m-0 mt-1">Cada caja comisionable genera este importe para repartir entre el equipo. Las bolsas se excluyen. Los cortes confirmados conservan su cálculo.</p>
                    </div>
                    {!tarifas.length && <p className="text-sm theme-text-muted m-0">Todavía no hay tarifas registradas.</p>}
                    {!!tarifas.length && !vigentes.length && <p className="text-sm theme-text-muted m-0">No hay tarifas vigentes. Las anteriores están en el historial.</p>}
                    <div className="flex flex-wrap justify-center gap-3">
                        {vigentes.map((t) => <article key={t.id} className="w-full max-w-xs rounded-xl border p-4 min-h-28 theme-element theme-text-primario outline outline-2 outline-offset-2">
                            <span className="flex items-center justify-between gap-2 text-xs theme-text-muted"><span className="inline-flex items-center gap-2"><Wallet className="size-4 shrink-0" aria-hidden="true" />{t.area}</span><span>Vigente</span></span>
                            <span className="mt-3 block text-3xl font-bold tabular-nums leading-none">{dinero(t.importe)}</span>
                            <span className="mt-3 block text-xs theme-text-muted">Por caja · desde {momentoLegible(t.vigente_desde)}</span>
                        </article>)}
                    </div>
                    {cerradas.length > 0 && <div className="space-y-3 border-t theme-border pt-4">
                        <div className="flex flex-wrap justify-center gap-2">
                            {recientes.map((t) => <article key={t.id} className="w-full max-w-56 rounded-lg border theme-border theme-element px-3 py-2">
                                <span className="flex items-center justify-between gap-2 text-xs theme-text-muted"><span>{t.area}</span><span>Anterior</span></span>
                                <span className="mt-1 block text-base font-semibold tabular-nums leading-none">{dinero(t.importe)}</span>
                                <span className="mt-1 block text-xs theme-text-muted">Hasta {momentoLegible(t.vigente_hasta)}</span>
                            </article>)}
                        </div>
                        <div className="flex justify-center">
                            <button type="button" className={BOTON_FILTRO} onClick={() => setHistorial(true)}><History className="size-4" aria-hidden="true" />Historial</button>
                        </div>
                    </div>}
                    <form className="grid gap-3 border-t theme-border pt-4 md:grid-cols-4 md:items-end" onSubmit={(e) => { e.preventDefault(); tarifa.post(route('gestion_interna.comisiones_empaque.tarifas.store'), { ...VISITA_MODAL, onSuccess: () => tarifa.reset('importe', 'vigente_desde') }); }}>
                        <label><span className={THEME_LABEL}>Área</span><select className={THEME_INPUT} value={tarifa.data.area_id} onChange={(e) => tarifa.setData('area_id', e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select></label>
                        <label><span className={THEME_LABEL}>Importe por caja</span><input className={THEME_INPUT} required inputMode="decimal" value={tarifa.data.importe} onChange={(e) => tarifa.setData('importe', e.target.value)} placeholder="Por ejemplo: 10.00…" /></label>
                        <label><span className={THEME_LABEL}>Vigente desde</span><input className={THEME_INPUT} required type="datetime-local" value={tarifa.data.vigente_desde} onChange={(e) => tarifa.setData('vigente_desde', e.target.value)} /></label>
                        <button className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`} disabled={tarifa.processing}>{tarifa.processing ? 'Guardando…' : 'Registrar tarifa'}</button>
                        <ErroresFormulario form={tarifa} />
                    </form>
                </section>
                <section className={geliaCardClass('space-y-4 p-4 md:p-5')} aria-label="Administración avanzada">
                    <div>
                        <h3 className="text-base font-bold m-0">Administración avanzada</h3>
                        <p className="text-sm theme-text-muted m-0 mt-1">Accesos, periodo y cuentas. El rango de fechas es lo que permite a Actualizar traer empaques de Gelia.</p>
                    </div>
                    <nav className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1`} aria-label="Configuración de comisiones">{secciones.map(([id, nombre]) => <button type="button" key={id} className="gelia-segment-btn" aria-pressed={seccion === id} data-active={seccion === id} onClick={() => setSeccion(id)}>{nombre}</button>)}</nav>
        {seccion === 'cuentas' && <CuentasColaboradores participantes={participantes} />}
        {seccion === 'accesos' && <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); alcance.put(route('gestion_interna.comisiones_empaque.alcance'), { ...VISITA_MODAL, onSuccess: () => alcance.reset() }); }}>
            <h3 className="font-bold m-0">Quién puede consultar cada área</h3>
            <p className="text-sm theme-text-muted m-0">Estos accesos no agregan personas al reparto. La cuenta también necesita el permiso para entrar al módulo.</p>
            <ul className="pl-5 text-sm">{alcances.map((a) => <li key={`${a.user_id}-${a.area_id}`}>{a.nombre}: {a.area}</li>)}</ul>
            <BuscarUsuario value={alcance.data.user_id} onChange={(id) => alcance.setData({ user_id: id, area_ids: alcances.filter((a) => String(a.user_id) === String(id)).map((a) => String(a.area_id)) })} />
            <fieldset className="border-0 p-0 m-0 space-y-2"><legend className={THEME_LABEL}>Áreas que puede consultar</legend>{areas.map((a) => <label key={a.id} className="flex gap-2 items-center text-sm"><input type="checkbox" checked={alcance.data.area_ids.map(String).includes(String(a.id))} onChange={(e) => alcance.setData('area_ids', e.target.checked ? [...alcance.data.area_ids, String(a.id)] : alcance.data.area_ids.filter((id) => String(id) !== String(a.id)))} />{a.nombre}</label>)}</fieldset>
            <p className="text-sm theme-text-muted">Guardar reemplaza las áreas de esta cuenta. Sin ninguna seleccionada, se retira su acceso por área.</p>
            <ErroresFormulario form={alcance} /><button className={THEME_BTN_PRIMARY} disabled={alcance.processing}>{alcance.processing ? 'Guardando…' : 'Guardar accesos'}</button>
        </form>}
        {seccion === 'importacion' && <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); fecha.transform((d) => ({ ...d, fecha_fin: d.fecha_fin || null })); fecha.put(route('gestion_interna.comisiones_empaque.fecha_inicio'), VISITA_MODAL); }}>
            <h3 className="font-bold m-0">Gestionar Periodo</h3>
            <p className="text-sm theme-text-muted m-0">Define qué fechas consulta Actualizar para incorporar empaques. Para crear o cerrar un periodo de comisión, use la sección Periodos. Cambiar este rango no elimina los empaques ya importados.</p>
            <label className="block"><span className={THEME_LABEL}>Importar desde</span><input className={THEME_INPUT} required type="date" value={fecha.data.fecha_inicio} onChange={(e) => fecha.setData('fecha_inicio', e.target.value)} /></label>
            <label className="block"><span className={THEME_LABEL}>Importar hasta (opcional)</span><input className={THEME_INPUT} type="date" min={fecha.data.fecha_inicio} value={fecha.data.fecha_fin} onChange={(e) => fecha.setData('fecha_fin', e.target.value)} /></label>
            <ErroresFormulario form={fecha} /><button className={THEME_BTN_PRIMARY} disabled={fecha.processing}>{fecha.processing ? 'Guardando…' : 'Guardar rango de importación'}</button>
        </form>}
        {seccion === 'areas' && <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); area.post(route('gestion_interna.comisiones_empaque.areas.store'), { ...VISITA_MODAL, onSuccess: () => area.reset() }); }}>
            <h3 className="font-bold m-0">Áreas de empaque</h3><ul className="text-sm pl-5">{areas.map((a) => <li key={a.id}>{a.nombre} · {a.activo ? 'Activa' : 'Inactiva'}</li>)}</ul>
            <label className="block"><span className={THEME_LABEL}>Código de la nueva área</span><input className={THEME_INPUT} required value={area.data.codigo} onChange={(e) => area.setData('codigo', e.target.value)} /></label>
            <label className="block"><span className={THEME_LABEL}>Nombre</span><input className={THEME_INPUT} required value={area.data.nombre} onChange={(e) => area.setData('nombre', e.target.value)} /></label>
            <ErroresFormulario form={area} /><button className={THEME_BTN_PRIMARY} disabled={area.processing}>{area.processing ? 'Guardando…' : 'Agregar área'}</button>
        </form>}
                </section>
            </div>
        </div>
    </div>;
    const historialModal = historial && <div className={THEME_MODAL_OVERLAY} onClick={() => setHistorial(false)}>
        <div role="dialog" aria-modal="true" aria-labelledby="historial-tarifas" tabIndex={-1} className={`${THEME_MODAL_SHELL} max-w-lg`} onClick={(evento) => evento.stopPropagation()}>
            <header className="flex items-start justify-between gap-4 border-b theme-border p-5">
                <div>
                    <h2 id="historial-tarifas" className="text-lg font-bold theme-text-main m-0">Historial de tarifas</h2>
                    <p className="text-sm theme-text-muted m-0 mt-1">Tarifas que ya no están vigentes. Los cortes confirmados conservan su cálculo.</p>
                </div>
                <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg border theme-border theme-element theme-text-main" aria-label="Cerrar historial" onClick={() => setHistorial(false)}><X className="size-4" aria-hidden="true" /></button>
            </header>
            <ul className="list-none m-0 max-h-[60vh] overflow-y-auto p-5">
                {cerradas.map((t) => <li key={t.id} className="flex items-center justify-between gap-3 border-t theme-border py-3 first:border-t-0">
                    <div>
                        <p className="m-0 font-semibold">{t.area}</p>
                        <p className="m-0 text-xs theme-text-muted">Desde {momentoLegible(t.vigente_desde)} · hasta {momentoLegible(t.vigente_hasta)}</p>
                    </div>
                    <span className="font-bold tabular-nums">{dinero(t.importe)}</span>
                </li>)}
            </ul>
        </div>
    </div>;
    const contenido = <>{modal}{historialModal}</>;
    return typeof document === 'undefined' ? contenido : createPortal(contenido, document.body);
}
