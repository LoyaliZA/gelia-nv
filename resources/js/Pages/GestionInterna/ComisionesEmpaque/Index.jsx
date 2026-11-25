import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, BarChart3, CalendarDays, CheckCircle2, Clock, Layers, Package, RefreshCw, Settings, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { emitirToastGelia } from '@/utils/geliaToast';
import { ir, momentoLegible } from './compartido';
import { Acumulado } from './Empaques';
import { Cortes, Pagos, Preparar } from './Liquidaciones';
import Periodos from './Periodos';
import Configuracion from './Configuracion';
import Productividad from './Productividad';
import useActualizacionComisiones from './useActualizacionComisiones';

export { Acumulado, Pagos, Preparar };

const LIQUIDACIONES = [['periodos', 'Periodos y liquidación'], ['pagos', 'Por pagar'], ['cortes', 'Historial']];
const SECCIONES = [['acumulado', 'Empaques', Package], ['periodos', 'Liquidaciones', Wallet], ['productividad', 'Productividad', BarChart3], ['configuracion', 'Configuración', Settings]];

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

function estadoActualizacion(sincronizacion) {
    if (sincronizacion?.estado === 'en_curso') {
        return { etiqueta: 'Actualizando', detalle: 'Incorporando empaques', aviso: false, Icono: Clock };
    }
    const fecha = sincronizacion?.finalizado_at ? momentoLegible(sincronizacion.finalizado_at) : null;
    const automatico = sincronizacion?.automatizacion_reciente;
    if (['error', 'rango_vencido'].includes(sincronizacion?.estado)) {
        return { etiqueta: sincronizacion.estado === 'error' ? 'Error recuperable' : 'Rango vencido', detalle: sincronizacion.mensaje, Icono: AlertCircle };
    }
    if (sincronizacion?.estado === 'sin_actividad') {
        return { etiqueta: 'Sin actividad automática reciente', detalle: fecha, Icono: Clock };
    }

    return {
        etiqueta: automatico ? 'Actualización automática' : fecha ? 'Actualizado' : 'Pendiente de sincronizar',
        detalle: fecha || sincronizacion?.mensaje,
        Icono: fecha || automatico ? CheckCircle2 : Clock,
    };
}

export default function Index({ tab, puede, areas = [], fecha_inicio, fecha_fin, sincronizacion, resumen, situaciones = {}, filtros = {}, acumulado, totales, preview, cortes, corte, saldos = [], tarifas = [], tipos_caja = [], sin_areas = false, captura_nombre = '', alcances = [], periodos = [], equipo, paqueterias = [], carga_revision = null, productividad = [], motivos_comerciales = {}, repartos = [] }) {
    const { flash } = usePage().props;
    const areaId = filtros.area_id || '';
    const [actualizando, setActualizando] = useState(false);
    const avisos = useMemo(() => avisosDePagina({ sin_areas, flash, sincronizacion, resumen }), [sin_areas, flash, sincronizacion, resumen]);
    const avisosPrevios = useRef('');
    useEffect(() => {
        const firma = avisos.map((aviso) => `${aviso.tipo}:${aviso.mensaje}`).join('\n');
        if (!firma || firma === avisosPrevios.current) return;
        avisosPrevios.current = firma;
        avisos.forEach((aviso) => emitirToastGelia(aviso.mensaje, aviso.tipo));
    }, [avisos]);
    const actualizacionDiscreta = useActualizacionComisiones({ estado: sincronizacion, areaId, tab, activo: !sin_areas });
    const actualizacion = estadoActualizacion(actualizacionDiscreta.observado);
    const IconoActualizacion = actualizacion.Icono;
    const liquidaciones = ['periodos', 'preparar', 'cortes', 'pagos'].includes(tab);
    const configuracion = ['equipo', 'ajustes', 'configuracion'].includes(tab);
    const principal = liquidaciones ? 'periodos' : configuracion ? 'configuracion' : tab;
    const opcionesPeriodo = periodos.filter((p) => String(p.area_id) === String(areaId));
    const navegar = (destino, cambios = {}) => ir({
        area_id: areaId || 'todas', periodo_id: filtros.periodo_id, desde: filtros.desde, hasta: filtros.hasta,
        limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta, incluir_tardias: filtros.incluir_tardias,
        retorno_corte: filtros.retorno_corte, tab: destino, ...cambios,
    });

    return (
        <AppLayout title="Comisiones de empaque">
            <Head title="Comisiones de empaque" />
            <GeliaPageShell className="min-w-0 space-y-4 pt-4 pb-8 md:pt-0">
                <div className="min-w-0 space-y-3" onChangeCapture={actualizacionDiscreta.proteger} onClickCapture={actualizacionDiscreta.cancelarRecarga}>
                <header className="flex flex-wrap items-end justify-between gap-x-5 gap-y-2 border-b theme-border pb-3">
                    <div className="min-w-0">
                        <h1 className="m-0 text-xl font-bold theme-text-main">Comisiones de empaque</h1>
                        <p role="status" className="m-0 mt-1 flex items-start gap-2 text-sm theme-text-muted">
                            <IconoActualizacion className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span>{actualizacion.etiqueta}{actualizacion.detalle && ` · ${actualizacion.detalle}`}</span>
                        </p>
                        {actualizacionDiscreta.pendiente && <p className="m-0 mt-1 text-xs theme-text-aviso" role="status">Hay datos nuevos. Se conserva tu revisión actual.</p>}
                    </div>
                    {!sin_areas && <div className="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <label className="min-w-0 sm:min-w-32"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><Layers className="size-4" aria-hidden="true" />Área</span></span><select className={THEME_INPUT} value={areaId} onChange={(e) => navegar(tab === 'preparar' ? 'periodos' : tab, { area_id: e.target.value || 'todas', periodo_id: undefined, seccion: filtros.seccion, retorno_corte: undefined, limite_desde: undefined, limite_hasta: undefined })}>
                            {!['periodos', 'preparar', 'equipo'].includes(tab) && <option value="">Todas</option>}{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                        </select></label>
                        <label className="min-w-0 max-w-full"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><CalendarDays className="size-4" aria-hidden="true" />Periodo</span></span><select className={THEME_INPUT} value={filtros.periodo_id || ''} onChange={(e) => navegar(tab, { periodo_id: e.target.value || undefined, desde: undefined, hasta: undefined, limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta })}>
                            <option value="">{filtros.desde || filtros.hasta ? [filtros.desde, filtros.hasta].filter(Boolean).join(' al ') : 'Todas las fechas'}</option>
                            {opcionesPeriodo.map((p) => <option key={p.id} value={p.id}>{p.nombre} · {p.desde} a {p.hasta}</option>)}
                        </select></label>
                        <div className="group relative">
                            <button type="button" className={`${THEME_BTN_SECONDARY} size-11 !p-0`} disabled={actualizando} aria-label="Actualizar empaques de Gelia" aria-describedby="actualizar-comisiones-ayuda" onClick={() => router.post(route('gestion_interna.comisiones_empaque.sincronizar'), { area_id: areaId || undefined }, { preserveState: true, preserveScroll: true, onStart: () => setActualizando(true), onFinish: () => setActualizando(false) })}>
                                <RefreshCw className={`size-4 ${actualizando ? 'motion-safe:animate-spin' : ''}`} aria-hidden="true" />
                            </button>
                            <span id="actualizar-comisiones-ayuda" role="tooltip" className="pointer-events-none invisible absolute right-0 top-full z-10 mt-1 whitespace-nowrap rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">Actualizar empaques de Gelia</span>
                        </div>
                    </div>}
                </header>
                {sin_areas ? <p role="alert" className="theme-text-aviso">Solicite al administrador acceso a un área de empaque.</p> : <>
                    {!!filtros.retorno_corte && tab !== 'preparar' && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar('preparar', { retorno_corte: undefined })}>Volver a preparar el corte</button>}
                    <nav className="gelia-segment p-1" aria-label="Secciones de comisiones">
                        <div className="grid w-full min-w-0 grid-cols-2 gap-1 sm:grid-cols-4">{SECCIONES.filter(([id]) => id !== 'configuracion' || puede.configurar).map(([id, nombre, Icono]) =>
                            <button key={id} type="button" className="gelia-segment-btn min-h-11 min-w-0 px-1" aria-current={principal === id ? 'page' : undefined} data-active={principal === id} onClick={() => navegar(id)}><Icono className="size-4 shrink-0" aria-hidden="true" />{nombre}</button>)}
                        </div>
                    </nav>
                    {liquidaciones && <nav aria-label="Secciones de liquidación" className="flex flex-wrap gap-2 border-b theme-border">{LIQUIDACIONES.map(([id, nombre]) =>
                        <button key={id} type="button" className="min-h-11 border-b-2 border-transparent px-2 text-sm theme-text-main aria-[current=page]:border-current aria-[current=page]:font-semibold aria-[current=page]:theme-text-primario" aria-current={(tab === id || (tab === 'preparar' && id === 'periodos')) ? 'page' : undefined} onClick={() => navegar(id)}>{nombre}</button>)}</nav>}
                    {tab === 'acumulado' && <Acumulado resumen={resumen} filtros={filtros} areas={areas} situaciones={situaciones} acumulado={acumulado} totales={totales} puede={puede} tipos={tipos_caja} capturaNombre={captura_nombre} paqueterias={paqueterias} cargaRevision={carga_revision} motivos={motivos_comerciales} />}
                    {tab === 'periodos' && <Periodos key={areaId} periodos={periodos} equipo={equipo} areaId={areaId} puede={puede} />}
                    {tab === 'preparar' && <Preparar areas={areas} filtros={filtros} preview={preview} puede={puede} />}
                    {tab === 'cortes' && <Cortes cortes={cortes} corte={corte} puede={puede} />}
                    {tab === 'pagos' && <Pagos key={areaId} saldos={saldos} puede={puede} areaId={areaId} />}
                    {tab === 'productividad' && <Productividad filas={productividad} filtros={filtros} puede={puede} />}
                    {configuracion && puede.configurar && <Configuracion key={areaId || 'todas'} areas={areas} areaId={areaId} fechaInicio={fecha_inicio} fechaFin={fecha_fin} tarifas={tarifas} alcances={alcances} equipo={equipo} puede={puede} fondo={resumen?.importe_por_cortar} repartos={repartos} sincronizacion={sincronizacion} cargaRevision={carga_revision} inicial={tab === 'equipo' ? 'equipo' : filtros.seccion} onSeccion={(seccion) => navegar('configuracion', { seccion })} />}
                </>}
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
