import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, BarChart3, CalendarDays, CheckCircle2, Clock, Layers, Package, RefreshCw, Settings, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { emitirToastGelia } from '@/utils/geliaToast';
import {
    bloquearConsultas, cancelarConsulta, confirmarAntesDeConsultar, invalidarPrecargas, ir, momentoLegible,
    observarConsultas, precargarComisiones,
} from './compartido';
import { Acumulado } from './Empaques';
import { Cortes, Pagos, Preparar } from './Liquidaciones';
import Periodos from './Periodos';
import Configuracion from './Configuracion';
import Productividad from './Productividad';
import useActualizacionComisiones, { ProteccionEdiciones } from './useActualizacionComisiones';

export { Acumulado, Pagos, Preparar };

const LIQUIDACIONES = [['periodos', 'Periodos y liquidación'], ['pagos', 'Por pagar'], ['cortes', 'Historial']];
const SECCIONES = [['acumulado', 'Empaques', Package], ['periodos', 'Liquidaciones', Wallet], ['productividad', 'Productividad', BarChart3], ['configuracion', 'Configuración', Settings]];

function avisosDePagina({ sin_areas, flash }) {
    const items = [];
    if (sin_areas) items.push({ mensaje: 'No tienes áreas asignadas.', tipo: 'aviso' });
    if (flash?.success) items.push({ mensaje: flash.success, tipo: 'success' });
    if (flash?.error) items.push({ mensaje: flash.error, tipo: 'error' });
    if (flash?.warning) items.push({ mensaje: flash.warning, tipo: 'aviso' });
    const vistos = new Set();
    return items.filter((item) => {
        const texto = String(item.mensaje).trim();
        if (!texto || vistos.has(texto)) return false;
        vistos.add(texto);
        item.mensaje = texto;
        return true;
    });
}

function estadoActualizacion(sincronizacion) {
    const fecha = sincronizacion?.finalizado_at ? momentoLegible(sincronizacion.finalizado_at) : null;
    const estados = {
        en_curso: ['Procesando cambios', Clock],
        cambios_pendientes: ['Cambios pendientes', Clock],
        error: ['Error recuperable', AlertCircle],
        rango_vencido: ['Rango de incorporación vencido', AlertCircle],
        sin_actividad: ['Servicio sin actividad reciente', Clock],
        sin_acceso: ['El acceso al área cambió', AlertCircle],
        omitida: ['Sincronización sin configurar', Clock],
        pendiente: ['Pendiente de primera sincronización', Clock],
        ok: ['Datos actualizados', CheckCircle2],
    };
    const [etiqueta, Icono] = estados[sincronizacion?.estado] || estados.pendiente;
    const detalle = sincronizacion?.estado === 'ok'
        ? (sincronizacion.automatizacion_reciente ? 'Automatización activa' : fecha)
        : ['error', 'rango_vencido', 'omitida'].includes(sincronizacion?.estado) ? sincronizacion.mensaje : null;

    return { etiqueta, detalle, Icono, fecha };
}

const FILTROS_EMPAQUES = ['buscar', 'cliente', 'remision', 'pedido', 'empacador', 'empacador_id', 'empacador_nombre', 'situacion', 'vista'];

function ProgresoConsulta({ consulta }) {
    const [visible, setVisible] = useState(0);
    useEffect(() => {
        setVisible(0);
        if (!consulta?.pendiente) return undefined;
        const estado = window.setTimeout(() => setVisible(1), 100);
        const esqueleto = window.setTimeout(() => setVisible(2), 1000);
        return () => { window.clearTimeout(estado); window.clearTimeout(esqueleto); };
    }, [consulta]);
    if (consulta?.error) return <div role="alert" className="flex flex-wrap items-center gap-2 border-b theme-border pb-2">
        <p className="m-0 text-sm theme-text-peligro">No se pudo cargar la consulta.</p>
        <button type="button" className={THEME_BTN_SECONDARY} onClick={consulta.reintentar}>Reintentar</button>
    </div>;
    if (!consulta?.pendiente || !visible) return null;

    return <div role="status" className="space-y-1 border-b theme-border pb-2">
        <p className="m-0 text-sm theme-text-muted">Cargando consulta…</p>
        {visible === 2 && <div aria-hidden="true" className="grid grid-cols-3 gap-2">
            {[0, 1, 2].map((i) => <span key={i} className="theme-element h-2 rounded" />)}
        </div>}
    </div>;
}

export function Index({ tab, puede, areas = [], fecha_inicio, fecha_fin, sincronizacion, resumen, situaciones = {}, filtros = {}, acumulado, totales, preview, cortes, corte, saldos = [], tarifas = [], tipos_caja = [], sin_areas = false, captura_nombre = '', alcances = [], periodos, periodos_opciones = [], equipo, paqueterias = [], carga_revision = null, productividad = [], motivos_comerciales = {}, repartos = [] }) {
    const { flash } = usePage().props;
    const areaId = filtros.area_id || '';
    const [actualizando, setActualizando] = useState(false);
    const peticionManual = useRef(null);
    const [mutacion, setMutacion] = useState(false);
    const [consulta, setConsulta] = useState(null);
    const avisos = useMemo(() => avisosDePagina({ sin_areas, flash }), [sin_areas, flash]);
    const avisosPrevios = useRef('');
    const filtrosGuardados = useRef(new Map());
    const contexto = [areaId, filtros.periodo_id, filtros.desde, filtros.hasta].join('|');
    useEffect(() => {
        const firma = avisos.map((aviso) => `${aviso.tipo}:${aviso.mensaje}`).join('\n');
        if (!firma || firma === avisosPrevios.current) return;
        avisosPrevios.current = firma;
        avisos.forEach((aviso) => emitirToastGelia(aviso.mensaje, aviso.tipo));
    }, [avisos]);
    useEffect(() => {
        if (tab !== 'acumulado') return;
        const valores = Object.fromEntries(FILTROS_EMPAQUES.filter((clave) => filtros[clave] != null).map((clave) => [clave, filtros[clave]]));
        const memoria = filtrosGuardados.current;
        memoria.delete(contexto);
        memoria.set(contexto, valores);
        if (memoria.size > 6) memoria.delete(memoria.keys().next().value);
    }, [tab, contexto, filtros]);

    const actualizacionDiscreta = useActualizacionComisiones({ estado: sincronizacion, areaId, tab, activo: !sin_areas, ocupado: mutacion || consulta?.pendiente });
    useEffect(() => confirmarAntesDeConsultar(actualizacionDiscreta.confirmarSalida), [actualizacionDiscreta.confirmarSalida]);
    useEffect(() => {
        const quitarObservador = observarConsultas(setConsulta);
        const irAtras = () => { cancelarConsulta(); invalidarPrecargas(); };
        window.addEventListener('popstate', irAtras);
        let escribiendo;
        const quitarInicio = router.on?.('start', ({ detail: { visit } }) => {
            if (visit.method === 'get') return;
            if (!visit.url?.pathname?.startsWith(new URL(route('gestion_interna.comisiones_empaque.index'), window.location.href).pathname)) return;
            escribiendo = visit.url.href;
            bloquearConsultas(true);
            cancelarConsulta();
            invalidarPrecargas();
            setMutacion(true);
        });
        const quitarFin = router.on?.('finish', ({ detail: { visit } }) => {
            if (visit.method === 'get' || visit.url?.href !== escribiendo) return;
            escribiendo = null;
            bloquearConsultas(false);
            invalidarPrecargas();
            setMutacion(false);
        });
        return () => {
            quitarObservador();
            peticionManual.current?.abort();
            quitarInicio?.();
            quitarFin?.();
            window.removeEventListener('popstate', irAtras);
            bloquearConsultas(false);
            cancelarConsulta();
            invalidarPrecargas();
        };
    }, []);

    const observado = actualizacionDiscreta.observado;
    const actualizacion = estadoActualizacion(observado);
    const IconoActualizacion = actualizacion.Icono;
    const liquidaciones = ['periodos', 'preparar', 'cortes', 'pagos'].includes(tab);
    const configuracion = ['equipo', 'ajustes', 'configuracion'].includes(tab);
    const principal = liquidaciones ? 'periodos' : configuracion ? 'configuracion' : tab;
    const identidad = observado?.contexto && [observado.contexto, observado.revision, contexto].join('/');
    const opcionesPeriodo = periodos_opciones.filter((p) => String(p.area_id) === String(areaId));

    const parametros = (destino, cambios = {}) => ({
        area_id: areaId || 'todas', periodo_id: filtros.periodo_id, desde: filtros.desde, hasta: filtros.hasta,
        ...(destino === 'acumulado' && filtrosGuardados.current.get(contexto)),
        ...(destino === 'preparar' || filtros.retorno_corte ? {
            limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta, incluir_tardias: filtros.incluir_tardias,
        } : {}),
        retorno_corte: filtros.retorno_corte, retorno_periodo_id: filtros.retorno_periodo_id, tab: destino, ...cambios,
    });
    const navegar = (destino, cambios) => {
        if (destino === tab && !cambios) return;
        actualizacionDiscreta.cancelarRecarga();
        ir(parametros(destino, cambios), { identidad: cambios ? undefined : identidad });
    };
    const precargar = (destino) => {
        if (destino !== tab && !consulta?.pendiente && !mutacion && !actualizando && !sin_areas && !filtros.retorno_corte) precargarComisiones(parametros(destino), identidad);
    };
    useEffect(() => {
        if (!identidad || !['acumulado', 'periodos', 'productividad'].includes(tab)) return undefined;
        if (actualizando || mutacion || consulta?.pendiente || sin_areas || filtros.retorno_corte) return undefined;
        const preparar = () => {
            if (document.visibilityState !== 'visible' || !navigator.onLine) return;
            if (tab !== 'productividad') precargarComisiones(parametros('productividad'), identidad);
            if (tab !== 'periodos' && areaId) precargarComisiones(parametros('periodos'), identidad);
        };
        const espera = window.setTimeout(preparar, 700);
        return () => window.clearTimeout(espera);
    // Los parámetros de precarga están definidos por la identidad y la sección.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [identidad, tab, actualizando, mutacion, consulta?.pendiente, sin_areas, filtros.retorno_corte]);
    useEffect(() => {
        invalidarPrecargas();
    }, [contexto]);

    const actualizarManual = async () => {
        if (peticionManual.current) return;
        const peticion = new AbortController();
        peticionManual.current = peticion;
        invalidarPrecargas();
        setActualizando(true);
        try {
            const { data } = await axios.post(route('gestion_interna.comisiones_empaque.sincronizar'), { area_id: areaId || undefined }, {
                headers: { Accept: 'application/json' }, signal: peticion.signal,
            });
            emitirToastGelia(data.mensaje, data.estado === 'ok' ? 'success' : 'aviso');
        } catch {
            if (!peticion.signal.aborted) emitirToastGelia('No se pudo actualizar. Intente nuevamente.', 'error');
        } finally {
            peticionManual.current = null;
            if (!peticion.signal.aborted) {
                invalidarPrecargas();
                actualizacionDiscreta.comprobar();
                setActualizando(false);
            }
        }
    };

    return (
        <AppLayout title="Comisiones de empaque">
            <Head title="Comisiones de empaque" />
            <GeliaPageShell className="min-w-0 space-y-4 pt-4 pb-8 md:pt-0">
                <ProteccionEdiciones.Provider value={actualizacionDiscreta.registrarEdicion}>
                <div className="theme-card theme-card--solid theme-surface theme-no-blur theme-border min-w-0 space-y-3 !rounded-2xl p-3 md:p-5 [&_.theme-label]:!text-xs [&_.theme-label]:!font-semibold [&_.theme-input]:!min-h-11">
                <header className="flex flex-wrap items-end justify-between gap-x-5 gap-y-2 border-b theme-border pb-3">
                    <div className="min-w-0">
                        <h1 className="m-0 text-xl font-bold theme-text-main">Comisiones de empaque</h1>
                        <p role="status" className="m-0 mt-1 flex items-start gap-2 text-sm theme-text-muted">
                            <IconoActualizacion className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span title={actualizacion.fecha ? `Última sincronización ${actualizacion.fecha}` : undefined}>{actualizacion.etiqueta}{actualizacion.detalle && ` · ${actualizacion.detalle}`}</span>
                        </p>
                        {actualizacionDiscreta.pendiente && <p className="m-0 mt-1 text-xs theme-text-aviso" role="status">Hay datos nuevos. Se conserva tu revisión actual.</p>}
                    </div>
                    {!sin_areas && <div className="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <label className="min-w-0 sm:min-w-32"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><Layers className="size-4" aria-hidden="true" />Área</span></span><select className={THEME_INPUT} value={areaId} onChange={(e) => navegar(tab === 'preparar' ? 'periodos' : tab, { area_id: e.target.value || 'todas', periodo_id: undefined, seccion: filtros.seccion, retorno_corte: undefined, retorno_periodo_id: undefined, limite_desde: undefined, limite_hasta: undefined,
                            desde: filtros.periodo_id ? undefined : filtros.desde, hasta: filtros.periodo_id ? undefined : filtros.hasta })}>
                            <option value="">Todas</option>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                        </select></label>
                        <label className="min-w-0 max-w-full"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><CalendarDays className="size-4" aria-hidden="true" />Periodo</span></span><select className={THEME_INPUT} value={filtros.periodo_id || ''} onChange={(e) => navegar(tab, { periodo_id: e.target.value || undefined, desde: undefined, hasta: undefined, limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta })}>
                            <option value="">{filtros.desde || filtros.hasta ? [filtros.desde, filtros.hasta].filter(Boolean).join(' al ') : 'Sin periodo'}</option>
                            {opcionesPeriodo.map((p) => <option key={p.id} value={p.id}>{p.nombre} · {p.desde} a {p.hasta}</option>)}
                        </select></label>
                        <div className="group relative">
                            <button type="button" className={`${THEME_BTN_SECONDARY} size-11 !p-0`} disabled={actualizando} aria-label="Actualizar empaques de Gelia" aria-describedby="actualizar-comisiones-ayuda" onClick={actualizarManual}>
                                <RefreshCw className={`size-4 ${actualizando ? 'motion-safe:animate-spin' : ''}`} aria-hidden="true" />
                            </button>
                            <span id="actualizar-comisiones-ayuda" role="tooltip" className="pointer-events-none invisible absolute right-0 top-full z-10 mt-1 whitespace-nowrap rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">Actualizar empaques de Gelia</span>
                        </div>
                    </div>}
                </header>
                {observado?.estado === 'sin_acceso' ? <div className="space-y-2">
                    <p role="alert" className="theme-text-aviso">Consulta las áreas a las que tienes acceso.</p>
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar('acumulado', { area_id: 'todas', periodo_id: undefined, desde: undefined, hasta: undefined })}>Consultar áreas disponibles</button>
                </div> : sin_areas ? <p role="alert" className="theme-text-aviso">Solicite al administrador acceso a un área de empaque.</p> : <>
                    {!!filtros.retorno_corte && tab !== 'preparar' && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar('preparar', { periodo_id: filtros.periodo_id || filtros.retorno_periodo_id, retorno_corte: undefined, retorno_periodo_id: undefined })}>Volver a preparar el corte</button>}
                    <nav className="gelia-segment p-1" aria-label="Secciones de comisiones">
                        <div className="grid w-full min-w-0 grid-cols-2 gap-1 sm:grid-cols-4">{SECCIONES.filter(([id]) => id !== 'configuracion' || puede.configurar).map(([id, nombre, Icono]) =>
                            <button key={id} type="button" className="gelia-segment-btn min-h-11 min-w-0 px-1" aria-current={principal === id ? 'page' : undefined} data-active={principal === id} onPointerEnter={() => precargar(id)} onFocus={() => precargar(id)} onClick={() => navegar(id)}><Icono className="size-4 shrink-0" aria-hidden="true" />{nombre}</button>)}
                        </div>
                    </nav>
                    {liquidaciones && <nav aria-label="Secciones de liquidación" className="flex flex-wrap gap-2 border-b theme-border">{LIQUIDACIONES.map(([id, nombre]) =>
                        <button key={id} type="button" className="min-h-11 border-b-2 border-transparent px-2 text-sm theme-text-main aria-[current=page]:border-current aria-[current=page]:font-semibold aria-[current=page]:theme-text-primario" aria-current={(tab === id || (tab === 'preparar' && id === 'periodos')) ? 'page' : undefined} onClick={() => navegar(id)}>{nombre}</button>)}</nav>}
                    <section aria-label="Contenido de comisiones" aria-busy={consulta?.pendiente || undefined} className="min-w-0 space-y-3">
                    <ProgresoConsulta consulta={consulta} />
                    {tab === 'acumulado' && <Acumulado key={`${areaId}|${filtros.periodo_id || ''}`} resumen={resumen} filtros={filtros} areas={areas} situaciones={situaciones} acumulado={acumulado} totales={totales} puede={puede} tipos={tipos_caja} capturaNombre={captura_nombre} paqueterias={paqueterias} cargaRevision={carga_revision} motivos={motivos_comerciales} />}
                    {tab === 'periodos' && <Periodos key={areaId} periodos={periodos?.data || []} paginacion={periodos} equipo={equipo} areaId={areaId} puede={puede} />}
                    {tab === 'preparar' && <Preparar key={`${contexto}|${filtros.limite_desde || ''}|${filtros.limite_hasta || ''}`} areas={areas} filtros={filtros} preview={preview} puede={puede} />}
                    {tab === 'cortes' && <Cortes cortes={cortes} corte={corte} puede={puede} filtros={filtros} />}
                    {tab === 'pagos' && <Pagos key={areaId} saldos={saldos} puede={puede} areaId={areaId} />}
                    {tab === 'productividad' && <Productividad key={contexto} filas={productividad} filtros={filtros} puede={puede} />}
                    {configuracion && puede.configurar && <Configuracion key={areaId || 'todas'} areas={areas} areaId={areaId} fechaInicio={fecha_inicio} fechaFin={fecha_fin} tarifas={tarifas} alcances={alcances} equipo={equipo} puede={puede} fondo={resumen?.importe_por_cortar} repartos={repartos} sincronizacion={observado} cargaRevision={carga_revision} inicial={tab === 'equipo' ? 'equipo' : filtros.seccion} onSeccion={(seccion) => navegar('configuracion', { seccion })} />}
                    </section>
                </>}
                </div>
                </ProteccionEdiciones.Provider>
            </GeliaPageShell>
        </AppLayout>
    );
}

// El layout persistente conserva el espacio de trabajo también al volver en el historial.
function PaginaComisionesEmpaque() {
    return null;
}

PaginaComisionesEmpaque.layout = Index;
export default PaginaComisionesEmpaque;
