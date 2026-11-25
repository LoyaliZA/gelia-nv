import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, CheckCircle2, Clock, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { emitirToastGelia } from '@/utils/geliaToast';
import { ir, momentoLegible } from './compartido';
import { Acumulado } from './Empaques';
import { Cortes, Pagos, Preparar } from './Liquidaciones';
import Periodos from './Periodos';
import Configuracion from './Configuracion';
import Productividad from './Productividad';

export { Acumulado, Pagos, Preparar };

const LIQUIDACIONES = [['periodos', 'Periodos y liquidación'], ['pagos', 'Por pagar'], ['cortes', 'Historial']];
const SECCIONES = [['acumulado', 'Empaques'], ['periodos', 'Liquidaciones'], ['productividad', 'Productividad'], ['configuracion', 'Configuración']];

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
    const actualizacion = estadoActualizacion(sincronizacion);
    const IconoActualizacion = actualizacion.Icono;
    const liquidaciones = ['periodos', 'preparar', 'cortes', 'pagos'].includes(tab);
    const configuracion = ['equipo', 'ajustes', 'configuracion'].includes(tab);
    const principal = liquidaciones ? 'periodos' : configuracion ? 'configuracion' : tab;
    const opcionesPeriodo = periodos.filter((p) => String(p.area_id) === String(areaId));
    const navegar = (destino, cambios = {}) => ir({ area_id: areaId || 'todas', periodo_id: filtros.periodo_id, desde: filtros.desde, hasta: filtros.hasta, tab: destino, ...cambios });

    return (
        <AppLayout title="Comisiones de empaque">
            <Head title="Comisiones de empaque" />
            <GeliaPageShell className="min-w-0 space-y-4 pt-4 pb-8 md:pt-0">
                <header className={geliaCardClass('flex flex-wrap items-end justify-between gap-x-5 gap-y-3 p-4')}>
                    <div className="min-w-0">
                        <h1 className="m-0 text-xl font-bold theme-text-main">Comisiones de empaque</h1>
                        <p role="status" className="m-0 mt-1 flex items-start gap-2 text-sm theme-text-muted">
                            <IconoActualizacion className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span>{actualizacion.etiqueta} · {actualizacion.detalle}</span>
                        </p>
                    </div>
                    {!sin_areas && <div className="grid w-full min-w-0 grid-cols-2 items-end gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <label className="min-w-0 sm:min-w-32"><span className={THEME_LABEL}>Área</span><select className={THEME_INPUT} value={areaId} onChange={(e) => navegar(tab === 'preparar' ? 'periodos' : tab, { area_id: e.target.value || 'todas', periodo_id: undefined, seccion: filtros.seccion })}>
                            <option value="">Todas</option>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                        </select></label>
                        <label className="min-w-0 max-w-full"><span className={THEME_LABEL}>Periodo de consulta</span><select className={THEME_INPUT} value={filtros.periodo_id || ''} onChange={(e) => navegar(tab, { periodo_id: e.target.value || undefined, desde: undefined, hasta: undefined, limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta })}>
                            <option value="">{filtros.desde || filtros.hasta ? [filtros.desde, filtros.hasta].filter(Boolean).join(' al ') : 'Todas las fechas'}</option>
                            {opcionesPeriodo.map((p) => <option key={p.id} value={p.id}>{p.nombre} · {p.desde} a {p.hasta}</option>)}
                        </select></label>
                        <button type="button" className={`${THEME_BTN_PRIMARY} col-span-2 justify-self-start`} disabled={actualizando} aria-label="Actualizar empaques de Gelia" onClick={() => router.post(route('gestion_interna.comisiones_empaque.sincronizar'), {}, { preserveScroll: true, onStart: () => setActualizando(true), onFinish: () => setActualizando(false) })}>
                            <RefreshCw className="size-4" aria-hidden="true" />{actualizando ? 'Actualizando…' : 'Actualizar'}
                        </button>
                    </div>}
                </header>
                {sin_areas ? <p role="alert" className="theme-text-aviso">Solicite al administrador acceso a un área de empaque.</p> : <>
                    <nav className="gelia-segment p-1" aria-label="Secciones de comisiones">
                        <div className="grid w-full min-w-0 grid-cols-2 gap-1 sm:grid-cols-4">{SECCIONES.filter(([id]) => id !== 'configuracion' || puede.configurar).map(([id, nombre]) =>
                            <button key={id} type="button" className="gelia-segment-btn min-h-11 min-w-0 px-1" aria-current={principal === id ? 'page' : undefined} data-active={principal === id} onClick={() => navegar(id)}>{nombre}</button>)}
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
            </GeliaPageShell>
        </AppLayout>
    );
}
