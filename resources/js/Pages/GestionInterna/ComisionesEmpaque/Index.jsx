import { Head, router, useForm, usePage } from '@inertiajs/react';
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
import { emitirToastGelia } from '@/utils/geliaToast';

const TABS = [
    { id: 'acumulado', label: 'Acumulado' },
    { id: 'cortes', label: 'Cortes' },
    { id: 'pagos', label: 'Pagos' },
];

const VISITA_MODAL = { preserveState: true, preserveScroll: true };

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
    router.get(route('gestion_interna.comisiones_empaque.index'), params, { preserveState: true, preserveScroll: true });
}

function BuscarUsuario({ value, onChange, etiqueta = 'Cuenta' }) {
    const [q, setQ] = useState('');
    const [opciones, setOpciones] = useState([]);

    async function buscar(texto) {
        setQ(texto);
        if (texto.trim().length < 2) {
            setOpciones([]);
            return;
        }
        const respuesta = await fetch(`${route('gestion_interna.comisiones_empaque.usuarios')}?q=${encodeURIComponent(texto)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (respuesta.ok) setOpciones(await respuesta.json());
    }

    return (
        <div>
            <span className={THEME_LABEL}>{etiqueta}</span>
            <input className={THEME_INPUT} value={q} onChange={(e) => buscar(e.target.value)} placeholder="Escriba el nombre" aria-label={etiqueta} />
            {value && <p className="text-xs theme-text-muted m-0 mt-1">Cuenta seleccionada: {value}</p>}
            {opciones.length > 0 && (
                <ul className="mt-1 border theme-border rounded-lg overflow-hidden">
                    {opciones.map((opcion) => (
                        <li key={opcion.id}>
                            <button type="button" className="w-full text-left px-3 py-2 text-sm theme-text-main hover:bg-black/5" onClick={() => { onChange(opcion.id); setQ(opcion.name); setOpciones([]); }}>
                                {opcion.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
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

export default function Index({
    tab, puede, areas = [], fecha_inicio, fecha_fin, sincronizacion, resumen, fuentes = [], situaciones = {},
    filtros = {}, acumulado, totales, preview, cortes, corte, saldos = [],
    participantes = [], tarifas = [], repartos = [], tipos_caja = [],
    sin_areas = false, captura_nombre = '', alcances = [],
}) {
    const { flash } = usePage().props;
    const tabVisible = tab === 'configuracion' ? 'acumulado' : tab;
    const [configAbierta, setConfigAbierta] = useState(tab === 'configuracion');
    const [configSucia, setConfigSucia] = useState(false);
    const [confirmarSalida, setConfirmarSalida] = useState(false);
    const avisos = useMemo(() => avisosDePagina({ sin_areas, flash, sincronizacion, resumen }), [sin_areas, flash, sincronizacion, resumen]);
    const avisosPrevios = useRef('');

    useEffect(() => {
        const firma = avisos.map((aviso) => `${aviso.tipo}:${aviso.mensaje}`).join('\n');
        if (!firma || firma === avisosPrevios.current) return;
        avisosPrevios.current = firma;
        avisos.forEach((aviso) => emitirToastGelia(aviso.mensaje, aviso.tipo));
    }, [avisos]);

    const cerrarConfiguracion = () => {
        setConfirmarSalida(false);
        setConfigSucia(false);
        setConfigAbierta(false);
    };

    const pedirCierreConfiguracion = () => {
        if (configSucia) {
            setConfirmarSalida(true);
            return;
        }
        cerrarConfiguracion();
    };

    useEffect(() => {
        if (!configAbierta) return undefined;
        const onKey = (event) => {
            if (event.key !== 'Escape') return;
            if (confirmarSalida) {
                setConfirmarSalida(false);
                return;
            }
            if (configSucia) {
                setConfirmarSalida(true);
                return;
            }
            setConfigAbierta(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [configAbierta, configSucia, confirmarSalida]);

    return (
        <AppLayout title="Comisiones de empaque">
            <Head title="Comisiones de empaque" />
            <GeliaPageShell className="space-y-4 pb-10">
                <header className={geliaCardClass('p-4 md:p-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between')}>
                    <div>
                        <h1 className="text-xl md:text-2xl font-black theme-text-main m-0">Comisiones de empaque</h1>
                        <p className="text-sm theme-text-muted m-0 mt-1">
                            {sincronizacion?.finalizado_at
                                ? `Última actualización: ${sincronizacion.finalizado_at} · ${sincronizacion.estado}`
                                : 'Sin sincronización'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {puede.configurar && (
                            <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setConfigAbierta(true)}>Configuración</button>
                        )}
                    {tabVisible === 'acumulado' && (
                        <>
                            <button type="button" className={THEME_BTN_SECONDARY} onClick={() => router.post(route('gestion_interna.comisiones_empaque.sincronizar'))}>Actualizar</button>
                            {puede.exportar && (
                                <>
                                    <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.acumulado', filtros)}>Exportar</a>
                                    <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.tipos', filtros)}>Tipos de caja</a>
                                </>
                            )}
                            {puede.confirmar && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={() => ir({ tab: 'preparar', ...filtros })}>Preparar corte</button>
                            )}
                        </>
                    )}
                    </div>
                </header>

                {sincronizacion?.detalle?.length > 0 && (
                    <ul className="text-sm theme-text-muted m-0 pl-4">
                        {sincronizacion.detalle.map((linea) => <li key={linea}>{linea}</li>)}
                    </ul>
                )}

                <div className={GELIA_SEGMENT_TABS_SCROLL}>
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1`} role="tablist" aria-label="Secciones de comisiones de empaque">
                        {TABS.map((item) => (
                            <button key={item.id} type="button" role="tab" aria-selected={tabVisible === item.id} data-active={tabVisible === item.id} className="gelia-segment-btn" onClick={() => ir({ tab: item.id })}>
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                {tabVisible === 'acumulado' && (
                    <Acumulado resumen={resumen} filtros={filtros} areas={areas} situaciones={situaciones} acumulado={acumulado} totales={totales} puede={puede} tipos={tipos_caja} capturaNombre={captura_nombre} sinAreas={sin_areas} />
                )}
                {tab === 'preparar' && (
                    <Preparar areas={areas} filtros={filtros} preview={preview} puede={puede} />
                )}
                {tabVisible === 'cortes' && <Cortes cortes={cortes} corte={corte} puede={puede} />}
                {tabVisible === 'pagos' && <Pagos saldos={saldos} puede={puede} />}
                {configAbierta && puede.configurar && createPortal(
                    <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={pedirCierreConfiguracion}>
                        <div className={`${THEME_MODAL_SHELL} max-w-4xl`} role="dialog" aria-modal="true" aria-labelledby="config-comisiones-titulo" onClick={(e) => e.stopPropagation()}>
                            <div className="flex items-center justify-between gap-3 px-4 py-3 border-b theme-border">
                                <h2 id="config-comisiones-titulo" className="text-lg font-black theme-text-main m-0">Configuración</h2>
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={pedirCierreConfiguracion}>Cerrar</button>
                            </div>
                            <div className="overflow-y-auto p-4">
                                <Configuracion areas={areas} fechaInicio={fecha_inicio} fechaFin={fecha_fin} participantes={participantes} tarifas={tarifas} repartos={repartos} fuentes={fuentes} alcances={alcances} onSucioChange={setConfigSucia} onPrepararCorte={puede.confirmar ? (desde, hasta) => { setConfigAbierta(false); setConfigSucia(false); ir({ tab: 'preparar', limite_desde: desde || undefined, limite_hasta: hasta || undefined }); } : null} />
                            </div>
                        </div>
                    </div>,
                    document.body,
                )}
                {confirmarSalida && createPortal(
                    <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} style={{ zIndex: 'calc(var(--gelia-z-toast) + 4)' }} onClick={() => setConfirmarSalida(false)}>
                        <div className={`${THEME_MODAL_SHELL} max-w-md p-6 space-y-4`} role="dialog" aria-modal="true" aria-labelledby="salir-config-titulo" onClick={(e) => e.stopPropagation()}>
                            <h3 id="salir-config-titulo" className="text-base font-black theme-text-main m-0">Hay cambios sin guardar</h3>
                            <p className="text-sm theme-text-muted m-0">Si sale ahora, se descarta lo que aún no se aplicó.</p>
                            <div className="flex flex-col gap-2">
                                <button type="button" className="theme-btn-danger py-3 rounded-xl" onClick={cerrarConfiguracion}>Salir sin guardar</button>
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setConfirmarSalida(false)}>Seguir en configuración</button>
                            </div>
                        </div>
                    </div>,
                    document.body,
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function Resumen({ resumen }) {
    const celdas = [
        ['Cajas elegibles', resumen?.listo ? resumen.cajas_elegibles : 'Sin datos'],
        ['Importe por cortar', resumen?.listo ? dinero(resumen.importe_por_cortar) : 'Sin datos'],
        ['Confirmado por pagar', resumen?.listo ? dinero(resumen.importe_confirmado_por_pagar) : 'Sin datos'],
        ['Por revisar', resumen?.listo ? resumen.por_revisar : 'Sin datos'],
    ];
    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {celdas.map(([etiqueta, valor]) => (
                <div key={etiqueta} className={geliaCardClass('p-3')}>
                    <p className="text-xs theme-text-muted m-0">{etiqueta}</p>
                    <p className="text-lg font-black theme-text-main m-0 mt-1">{valor}</p>
                </div>
            ))}
        </div>
    );
}

function Acumulado({ resumen, filtros, areas, situaciones, acumulado, totales, puede, tipos, capturaNombre, sinAreas }) {
    const [local, setLocal] = useState(filtros);
    const filas = acumulado?.data || [];

    return (
        <div className="space-y-4">
            <Resumen resumen={resumen} />
            <form className={geliaCardClass('p-4 grid md:grid-cols-4 gap-3')} onSubmit={(e) => { e.preventDefault(); ir({ tab: 'acumulado', ...local }); }}>
                <label className="block"><span className={THEME_LABEL}>Área</span>
                    <select className={THEME_INPUT} value={local.area_id || ''} onChange={(e) => setLocal({ ...local, area_id: e.target.value })}>
                        <option value="">Todas</option>
                        {areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                    </select>
                </label>
                <label className="block"><span className={THEME_LABEL}>Empaque desde</span>
                    <input className={THEME_INPUT} type="date" value={local.desde || ''} onChange={(e) => setLocal({ ...local, desde: e.target.value })} />
                </label>
                <label className="block"><span className={THEME_LABEL}>Empaque hasta</span>
                    <input className={THEME_INPUT} type="date" value={local.hasta || ''} onChange={(e) => setLocal({ ...local, hasta: e.target.value })} />
                </label>
                <label className="block"><span className={THEME_LABEL}>Situación</span>
                    <select className={THEME_INPUT} value={local.situacion || ''} onChange={(e) => setLocal({ ...local, situacion: e.target.value })}>
                        <option value="">Todas</option>
                        {Object.entries(situaciones).map(([id, nombre]) => <option key={id} value={id}>{nombre}</option>)}
                    </select>
                </label>
                <label className="block"><span className={THEME_LABEL}>Cliente</span>
                    <input className={THEME_INPUT} value={local.cliente || ''} onChange={(e) => setLocal({ ...local, cliente: e.target.value })} />
                </label>
                <label className="block"><span className={THEME_LABEL}>Remisión</span>
                    <input className={THEME_INPUT} value={local.remision || ''} onChange={(e) => setLocal({ ...local, remision: e.target.value })} />
                </label>
                <label className="block"><span className={THEME_LABEL}>Pedido</span>
                    <input className={THEME_INPUT} value={local.pedido || ''} onChange={(e) => setLocal({ ...local, pedido: e.target.value })} />
                </label>
                <label className="block"><span className={THEME_LABEL}>Persona que empacó</span>
                    <input className={THEME_INPUT} value={local.empacador || ''} onChange={(e) => setLocal({ ...local, empacador: e.target.value })} />
                </label>
                <div className="md:col-span-4 flex gap-2">
                    <button className={THEME_BTN_PRIMARY} type="submit">Filtrar</button>
                    <button className={THEME_BTN_SECONDARY} type="button" onClick={() => { setLocal({}); ir({ tab: 'acumulado' }); }}>Limpiar</button>
                </div>
            </form>

            {puede.capturar && <Manual capturaNombre={capturaNombre} />}

            <div className={geliaCardClass('overflow-x-auto')}>
                <table className="w-full text-sm min-w-[960px]">
                    <thead className="sticky top-0 theme-surface">
                        <tr className="text-left theme-text-muted">
                            {['Empaque', 'Cliente', 'Pedido', 'Remisión', 'Área', 'Cajas', 'Empacó', 'Logística', 'Importe', 'Situación', 'Corte'].map((h) => (
                                <th key={h} className={`p-2 font-semibold ${h === 'Importe' ? 'text-right' : ''}`}>{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filas.length === 0 && (
                            <tr><td colSpan={11} className="p-6 theme-text-muted">{sinAreas ? 'No tienes áreas asignadas.' : 'No hay empaques con estos filtros. Si la fecha de inicio está vacía, defínala y pulse Actualizar.'}</td></tr>
                        )}
                        {filas.map((fila) => <Fila key={fila.id} fila={fila} puede={puede} tipos={tipos} />)}
                    </tbody>
                    {totales && (
                        <tfoot>
                            <tr className="font-semibold theme-text-main">
                                <td className="p-2" colSpan={5}>Total del resultado ({totales.registros}). {totales.sin_importe > 0 ? `${totales.sin_importe} sin importe.` : ''}</td>
                                <td className="p-2">{totales.cajas}</td>
                                <td colSpan={2} />
                                <td className="p-2 text-right">{dinero(totales.importe)}</td>
                                <td colSpan={2} />
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
            <GeliaPaginacion paginator={acumulado} onIrAPagina={(page) => ir({ tab: 'acumulado', ...filtros, page })} />
        </div>
    );
}

function Fila({ fila, puede, tipos }) {
    const origen = fila.pedido_bma_id
        ? (puede.ver_cedis ? `/control-pedidos/cedis?q=${encodeURIComponent(fila.folio_remision || fila.folio_pedido || '')}` : (puede.ver_tienda ? `/control-pedidos/tienda?q=${encodeURIComponent(fila.folio_pedido || '')}` : null))
        : null;
    return (
        <>
            <tr className="border-t theme-border align-top">
                <td className="p-2 whitespace-nowrap">{fila.empacado_at}{fila.incorporacion_tardia && <span className="block text-xs theme-text-aviso">Incorporación tardía</span>}{fila.excepcion_resguardo && <span className="block text-xs theme-text-aviso">Excepción de resguardo</span>}</td>
                <td className="p-2">{fila.cliente}</td>
                <td className="p-2">{fila.folio_pedido || '—'}</td>
                <td className="p-2">{fila.folio_remision || 'Sin remisión'}</td>
                <td className="p-2">{fila.area}</td>
                <td className="p-2">{fila.tipos?.length ? fila.tipos.join(', ') : 'Pendiente de clasificación'}</td>
                <td className="p-2">{fila.empacador}</td>
                <td className="p-2 max-w-xs">{logisticaTexto(fila.logistica)}</td>
                <td className="p-2 text-right whitespace-nowrap">{fila.importe == null ? 'Sin importe' : dinero(fila.importe)}</td>
                <td className="p-2">{fila.situacion_etiqueta}</td>
                <td className="p-2">{fila.corte || '—'}</td>
            </tr>
            <tr className="border-b theme-border">
                <td colSpan={11} className="px-2 pb-3">
                    <details>
                        <summary className="cursor-pointer text-sm theme-text-primario">Desglose y acciones</summary>
                        <div className="mt-2 space-y-2 text-sm">
                            {fila.diferencia && <p className="theme-text-aviso m-0">{fila.diferencia}</p>}
                            <p className="theme-text-muted m-0">Incorporada el {fila.incorporado_at}. Origen: {fila.cantidad_origen}. {origen ? <a className="theme-text-primario" href={origen}>Abrir origen</a> : 'Sin acceso al origen'}</p>
                            {puede.clasificar && <Clasificar fila={fila} tipos={tipos} />}
                        </div>
                    </details>
                </td>
            </tr>
        </>
    );
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
                    <button className={THEME_BTN_PRIMARY} type="button" disabled={form.processing} onClick={() => form.transform((data) => ({ decision: data.revision, motivo: data.motivo })).post(route('gestion_interna.comisiones_empaque.revisar', fila.id))}>Guardar revisión</button>
                </form>
            )}
            {fila.situacion !== 'en_corte' && fila.situacion !== 'rechazada' && fila.situacion !== 'posible_duplicado' && fila.situacion !== 'pendiente_revision' && (
                <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ lineas: data.lineas.map((l) => ({ ...l, catalogo_tipo_caja_id: l.catalogo_tipo_caja_id || null, es_bolsa: !!l.es_bolsa })) })).post(route('gestion_interna.comisiones_empaque.clasificar', fila.id)); }}>
                    <p className="m-0 theme-text-muted">El desglose debe sumar {fila.cantidad_origen}. Ahora suma {suma}.</p>
                    {form.data.lineas.map((linea, i) => (
                        <div key={i} className="flex flex-wrap gap-2">
                            <input className={`${THEME_INPUT} w-24`} type="number" min="1" aria-label="Cantidad" value={linea.cantidad} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, cantidad: e.target.value };
                                form.setData('lineas', lineas);
                            }} />
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!linea.es_bolsa} onChange={(e) => {
                                const lineas = [...form.data.lineas];
                                lineas[i] = { ...linea, es_bolsa: e.target.checked, catalogo_tipo_caja_id: e.target.checked ? '' : linea.catalogo_tipo_caja_id };
                                form.setData('lineas', lineas);
                            }} /> Bolsa excluida</label>
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
                    <div className="flex gap-2">
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={() => form.setData('lineas', [...form.data.lineas, { cantidad: 1, es_bolsa: false, catalogo_tipo_caja_id: '' }])}>Agregar renglón</button>
                        <button className={THEME_BTN_PRIMARY} disabled={form.processing}>Guardar desglose</button>
                    </div>
                    {form.errors.lineas && <p className="theme-text-peligro m-0" role="alert">{form.errors.lineas}</p>}
                </form>
            )}
            {fila.situacion === 'pendiente_decision' && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.decision, motivo: data.motivo })).post(route('gestion_interna.comisiones_empaque.decision', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Decisión de cancelación" value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}>
                        <option value="conservar">Conservar comisión</option>
                        <option value="excluir">Excluir comisión</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la decisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Registrar decisión'}</button>
                </form>
            )}
            {['reempaque_revision', 'solape_revision', 'revertida_revision'].includes(fila.situacion) && (
                <form className="flex flex-wrap gap-2 items-end" onSubmit={(e) => { e.preventDefault(); form.transform((data) => ({ decision: data.revision, motivo: data.motivo })).post(route('gestion_interna.comisiones_empaque.resolver', fila.id)); }}>
                    <select className={THEME_INPUT} aria-label="Resolución" value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)}>
                        <option value="aceptar">Aceptar para comisión</option>
                        <option value="descartar">Descartar</option>
                    </select>
                    <input className={THEME_INPUT} aria-label="Motivo de la revisión" placeholder="Motivo" value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} required />
                    <button className={THEME_BTN_PRIMARY}>Resolver</button>
                </form>
            )}
        </div>
    );
}

function Manual({ capturaNombre }) {
    const clave = useRef(typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `cap-${Date.now()}`);
    const form = useForm({
        clave_idempotencia: clave.current,
        empacado_at: '',
        cantidad: 1,
        pedido_bma_id: '',
        cliente_nombre: '',
        folio_pedido: '',
        folio_remision: '',
        notas: '',
    });

    async function tomarPedido() {
        const params = new URLSearchParams();
        if (form.data.pedido_bma_id) params.set('id', form.data.pedido_bma_id);
        else if (form.data.folio_pedido) params.set('folio', form.data.folio_pedido);
        else return;
        const respuesta = await fetch(`${route('gestion_interna.comisiones_empaque.pedido')}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const cuerpo = await respuesta.json();
        if (!respuesta.ok) {
            form.setError('pedido_bma_id', cuerpo.message || 'No se encontró el pedido.');
            return;
        }
        form.clearErrors('pedido_bma_id');
        form.setData({
            ...form.data,
            pedido_bma_id: cuerpo.id,
            cliente_nombre: cuerpo.cliente_nombre || '',
            folio_pedido: cuerpo.folio_pedido || '',
            folio_remision: cuerpo.folio_remision || '',
        });
    }

    return (
        <form className={geliaCardClass('p-4 grid md:grid-cols-3 gap-3')} onSubmit={(e) => {
            e.preventDefault();
            form.transform((data) => ({ ...data, clave_idempotencia: clave.current })).post(route('gestion_interna.comisiones_empaque.manual'), {
                onSuccess: () => { clave.current = crypto.randomUUID(); form.setData('clave_idempotencia', clave.current); },
            });
        }}>
            <p className="md:col-span-3 m-0 text-sm theme-text-muted">Captura de Tienda. Quien empaca es la cuenta en sesión. Queda pendiente de revisión y no entra al importe por cortar hasta aceptarse. Vincule un pedido existente. Si no hay pedido, indique folio o remisión para contrastar la captura. Sin una referencia verificable se guarda pendiente y la aceptación normal queda bloqueada.</p>
            <p className="m-0 text-sm"><span className={THEME_LABEL}>Área</span><span className="block">Tienda</span></p>
            <p className="m-0 text-sm"><span className={THEME_LABEL}>Persona que empacó</span><span className="block">{capturaNombre}</span></p>
            <label><span className={THEME_LABEL}>Fecha y hora reales del empaque</span>
                <input className={THEME_INPUT} type="datetime-local" required value={form.data.empacado_at} onChange={(e) => form.setData('empacado_at', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Cantidad de cajas</span>
                <input className={THEME_INPUT} type="number" min="1" required value={form.data.cantidad} onChange={(e) => form.setData('cantidad', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Pedido vinculado</span>
                <input className={THEME_INPUT} inputMode="numeric" value={form.data.pedido_bma_id} onChange={(e) => form.setData('pedido_bma_id', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Folio del pedido</span>
                <input className={THEME_INPUT} value={form.data.folio_pedido} onChange={(e) => form.setData('folio_pedido', e.target.value)} />
            </label>
            <div className="flex items-end"><button type="button" className={THEME_BTN_SECONDARY} onClick={tomarPedido}>Tomar datos del pedido</button></div>
            <label><span className={THEME_LABEL}>Cliente</span>
                <input className={THEME_INPUT} value={form.data.cliente_nombre} onChange={(e) => form.setData('cliente_nombre', e.target.value)} />
            </label>
            <label><span className={THEME_LABEL}>Remisión</span>
                <input className={THEME_INPUT} value={form.data.folio_remision} onChange={(e) => form.setData('folio_remision', e.target.value)} />
            </label>
            <label className="md:col-span-3"><span className={THEME_LABEL}>Observaciones</span>
                <input className={THEME_INPUT} value={form.data.notas} onChange={(e) => form.setData('notas', e.target.value)} />
            </label>
            <div className="md:col-span-3 space-y-1">
                <button className={THEME_BTN_PRIMARY} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar registro manual'}</button>
                {form.errors.clave_idempotencia && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.clave_idempotencia}</p>}
                {form.errors.pedido_bma_id && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.pedido_bma_id}</p>}
                {form.errors.cantidad && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.cantidad}</p>}
            </div>
        </form>
    );
}

function Preparar({ areas, filtros, preview, puede }) {
    const [area, setArea] = useState(filtros.area_id || areas[0]?.id || '');
    const [desde, setDesde] = useState(filtros.limite_desde || filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.limite_hasta || filtros.hasta || '');
    const [tardias, setTardias] = useState(!!filtros.incluir_tardias);
    const form = useForm({});

    function ver(e) {
        e.preventDefault();
        ir({ tab: 'preparar', area_id: area, limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias ? 1 : 0 });
    }

    return (
        <form className="grid lg:grid-cols-[1fr_320px] gap-4" onSubmit={ver}>
            <div className="space-y-4">
                <div className={geliaCardClass('p-4 grid md:grid-cols-2 gap-3')}>
                    <p className="md:col-span-2 text-sm theme-text-muted m-0">Los días habituales de pago son 15 y 30. El intervalo se captura con fechas explícitas.</p>
                    <label><span className={THEME_LABEL}>Área</span>
                        <select className={THEME_INPUT} value={area} onChange={(e) => setArea(e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select>
                    </label>
                    <label className="flex items-end gap-2 text-sm"><input type="checkbox" checked={tardias} onChange={(e) => setTardias(e.target.checked)} /> Incluir incorporaciones tardías anteriores al inicio</label>
                    <label><span className={THEME_LABEL}>Desde</span><input className={THEME_INPUT} type="date" required value={desde} onChange={(e) => setDesde(e.target.value)} /></label>
                    <label><span className={THEME_LABEL}>Hasta</span><input className={THEME_INPUT} type="date" required value={hasta} onChange={(e) => setHasta(e.target.value)} /></label>
                    <button className={THEME_BTN_SECONDARY} type="submit">Revisar cálculo</button>
                </div>
                {preview && (
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
                {!preview && <p className="text-sm theme-text-muted m-0">Revise el cálculo para ver cantidades, reparto e importe.</p>}
                {preview && (
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
                            <button type="button" className={THEME_BTN_PRIMARY} disabled={form.processing} onClick={() => form.transform(() => ({ area_id: area, limite_desde: desde, limite_hasta: hasta, incluir_tardias: tardias, hash: preview.hash })).post(route('gestion_interna.comisiones_empaque.confirmar'))}>Confirmar corte</button>
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

function Pagos({ saldos, puede }) {
    const [id, setId] = useState(saldos[0]?.id || '');
    const persona = useMemo(() => saldos.find((s) => String(s.id) === String(id)), [saldos, id]);
    const [seleccion, setSeleccion] = useState({});
    const clave = useMemo(() => (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : String(Date.now())), [id]);
    const form = useForm({ fecha_pago: '', referencia: '', medio: '', observaciones: '' });
    const aPagar = persona ? persona.asignaciones.filter((a) => seleccion[a.id]).reduce((n, a) => n + Number(seleccion[a.id] || 0), 0) : 0;
    const pendiente = persona ? Number(persona.pendiente) : 0;

    return (
        <div className="space-y-4">
            {puede.exportar && <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.pagos')}>Exportar pagos y saldos</a>}
            {saldos.length === 0 && <p className="theme-text-muted">No hay participantes. Regístrelos en Configuración, cada uno con su cuenta.</p>}
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
                            })).post(route('gestion_interna.comisiones_empaque.pagar'));
                        }}>
                            <label><span className={THEME_LABEL}>Fecha real de pago</span><input className={THEME_INPUT} type="date" required value={form.data.fecha_pago} onChange={(e) => form.setData('fecha_pago', e.target.value)} /></label>
                            <label><span className={THEME_LABEL}>Referencia o medio</span><input className={THEME_INPUT} value={form.data.referencia} onChange={(e) => form.setData('referencia', e.target.value)} /></label>
                            <p className="md:col-span-2 text-sm m-0">Importe a pagar: {dinero(aPagar.toFixed(2))}. Saldo posterior: {dinero((pendiente - aPagar).toFixed(2))}.</p>
                            {form.errors.importe && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.importe}</p>}
                            {form.errors.aplicaciones && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors.aplicaciones}</p>}
                            <button className={THEME_BTN_PRIMARY} disabled={form.processing || aPagar <= 0}>Registrar pago</button>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}

function Configuracion({ areas, fechaInicio, fechaFin, participantes, tarifas, repartos, fuentes, alcances = [], onSucioChange, onPrepararCorte }) {
    const fecha = useForm({ fecha_inicio: fechaInicio || '', fecha_fin: fechaFin || '' });
    const tarifa = useForm({ area_id: areas[0]?.id || '', importe: '', vigente_desde: '' });
    const [lineas, setLineas] = useState([{ participante_id: '', porcentaje: '' }]);
    const reparto = useForm({ area_id: areas[0]?.id || '', vigente_desde: '', lineas });
    const participante = useForm({ nombre: '', user_id: '', referencia_interna: '', observaciones: '', activo: true });
    const area = useForm({ codigo: '', nombre: '', observaciones: '' });
    const alcance = useForm({ user_id: '', area_ids: [] });
    const suma = lineas.reduce((n, l) => n + Number(l.porcentaje || 0), 0);
    const sucio = fecha.data.fecha_inicio !== (fechaInicio || '')
        || fecha.data.fecha_fin !== (fechaFin || '')
        || area.data.codigo !== '' || area.data.nombre !== '' || area.data.observaciones !== ''
        || alcance.data.user_id !== '' || alcance.data.area_ids.length > 0
        || participante.data.nombre !== '' || participante.data.user_id !== '' || participante.data.referencia_interna !== '' || participante.data.observaciones !== ''
        || String(tarifa.data.importe || '') !== '' || tarifa.data.vigente_desde !== ''
        || reparto.data.vigente_desde !== ''
        || lineas.length > 1
        || lineas.some((linea) => linea.participante_id || linea.porcentaje);

    useEffect(() => {
        onSucioChange?.(sucio);
    }, [sucio, onSucioChange]);

    function aplicarPeriodo(e) {
        e.preventDefault();
        fecha.transform((data) => ({ ...data, fecha_fin: data.fecha_fin || null })).put(route('gestion_interna.comisiones_empaque.fecha_inicio'), {
            ...VISITA_MODAL,
            onSuccess: () => router.post(route('gestion_interna.comisiones_empaque.sincronizar'), {}, VISITA_MODAL),
        });
    }

    return (
        <div className="space-y-4">
            <form className={geliaCardClass('p-4 flex flex-wrap gap-3 items-end')} onSubmit={aplicarPeriodo}>
                <label><span className={THEME_LABEL}>Inicio del periodo</span><input className={THEME_INPUT} type="date" required value={fecha.data.fecha_inicio} onChange={(e) => fecha.setData('fecha_inicio', e.target.value)} /></label>
                <label><span className={THEME_LABEL}>Fin del periodo</span><input className={THEME_INPUT} type="date" value={fecha.data.fecha_fin} min={fecha.data.fecha_inicio || undefined} onChange={(e) => fecha.setData('fecha_fin', e.target.value)} /></label>
                <button className={THEME_BTN_PRIMARY} disabled={fecha.processing}>Guardar y aplicar periodo</button>
                {onPrepararCorte && (
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => onPrepararCorte(fecha.data.fecha_inicio, fecha.data.fecha_fin)}>Preparar corte</button>
                )}
                <p className="text-sm theme-text-muted m-0 basis-full">El rango incorpora los empaques de esas fechas. Puede aplicar otro cuando alguien entra o sale. El fin puede quedar vacío si el periodo sigue abierto. El corte usa sus propias fechas: puede cerrarse antes del fin o extenderse después.</p>
                {fecha.errors.fecha_fin && <p className="theme-text-peligro text-sm m-0 basis-full" role="alert">{fecha.errors.fecha_fin}</p>}
            </form>
            <section className={geliaCardClass('p-4 space-y-2')}>
                <h2 className="text-base font-black theme-text-main m-0">Fuentes</h2>
                {fuentes.map((f) => <p key={f.flujo} className="text-sm theme-text-main m-0"><strong>{f.flujo}.</strong> {f.prevalece}</p>)}
            </section>
            <form className={geliaCardClass('p-4 grid md:grid-cols-3 gap-3')} onSubmit={(e) => { e.preventDefault(); area.post(route('gestion_interna.comisiones_empaque.areas.store'), { ...VISITA_MODAL, onSuccess: () => area.reset() }); }}>
                <h2 className="md:col-span-3 text-base font-black theme-text-main m-0">Áreas</h2>
                {areas.map((a) => <p key={a.id} className="m-0 text-sm theme-text-main">{a.nombre} · {a.activo ? 'Activa' : 'Inactiva'}</p>)}
                <input className={THEME_INPUT} placeholder="Código" aria-label="Código del área" value={area.data.codigo} onChange={(e) => area.setData('codigo', e.target.value)} />
                <input className={THEME_INPUT} placeholder="Nombre" aria-label="Nombre del área" value={area.data.nombre} onChange={(e) => area.setData('nombre', e.target.value)} />
                <button className={THEME_BTN_PRIMARY}>Agregar área</button>
                {area.errors.codigo && <p className="theme-text-peligro text-sm m-0">{area.errors.codigo}</p>}
            </form>
            <form className={geliaCardClass('p-4 grid md:grid-cols-2 gap-3')} onSubmit={(e) => { e.preventDefault(); alcance.put(route('gestion_interna.comisiones_empaque.alcance'), { ...VISITA_MODAL, onSuccess: () => alcance.reset() }); }}>
                <h2 className="md:col-span-2 text-base font-black theme-text-main m-0">Alcance por área</h2>
                <p className="md:col-span-2 text-sm theme-text-muted m-0">Sin áreas asignadas no hay acceso. Consultar el módulo no abre todas las áreas. El acceso global usa el permiso específico.</p>
                {alcances.map((fila) => <p key={`${fila.user_id}-${fila.area_id}`} className="m-0 text-sm theme-text-main">{fila.nombre}: {fila.area}</p>)}
                <BuscarUsuario etiqueta="Cuenta" value={alcance.data.user_id} onChange={(id) => alcance.setData('user_id', id)} />
                <fieldset className="border-0 p-0 m-0">
                    <legend className={THEME_LABEL}>Áreas que puede consultar</legend>
                    <div className="flex flex-wrap gap-3">
                        {areas.map((a) => (
                            <label key={a.id} className="flex items-center gap-2 text-sm theme-text-main">
                                <input type="checkbox" checked={alcance.data.area_ids.includes(a.id) || alcance.data.area_ids.includes(String(a.id))} onChange={(e) => {
                                    const actual = alcance.data.area_ids.map(String);
                                    const id = String(a.id);
                                    alcance.setData('area_ids', e.target.checked ? [...actual, id] : actual.filter((item) => item !== id));
                                }} />
                                {a.nombre}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <button className={THEME_BTN_PRIMARY}>Guardar alcance</button>
                {alcance.errors.user_id && <p className="theme-text-peligro text-sm m-0" role="alert">{alcance.errors.user_id}</p>}
            </form>
            <form className={geliaCardClass('p-4 grid md:grid-cols-2 gap-3')} onSubmit={(e) => { e.preventDefault(); participante.post(route('gestion_interna.comisiones_empaque.participantes.store'), { ...VISITA_MODAL, onSuccess: () => participante.reset() }); }}>
                <h2 className="md:col-span-2 text-base font-black theme-text-main m-0">Participantes</h2>
                {participantes.map((p) => <p key={p.id} className="m-0 text-sm theme-text-main">{p.nombre} · {p.activo ? 'Activo' : 'Inactivo'} · cuenta {p.user_id}</p>)}
                <input className={THEME_INPUT} placeholder="Nombre" aria-label="Nombre del participante" value={participante.data.nombre} onChange={(e) => participante.setData('nombre', e.target.value)} />
                <BuscarUsuario etiqueta="Cuenta de usuario" value={participante.data.user_id} onChange={(id) => participante.setData('user_id', id)} />
                <button className={THEME_BTN_PRIMARY}>Guardar participante</button>
                {participante.errors.user_id && <p className="theme-text-peligro text-sm m-0" role="alert">{participante.errors.user_id}</p>}
            </form>
            <form className={geliaCardClass('p-4 grid md:grid-cols-3 gap-3')} onSubmit={(e) => { e.preventDefault(); tarifa.post(route('gestion_interna.comisiones_empaque.tarifas.store'), { ...VISITA_MODAL, onSuccess: () => tarifa.reset('importe', 'vigente_desde') }); }}>
                <h2 className="md:col-span-3 text-base font-black theme-text-main m-0">Tarifa plana por área</h2>
                {tarifas.map((t) => <p key={t.id} className="m-0 text-sm theme-text-main">{t.area}: {dinero(t.importe)} desde {t.vigente_desde}{t.vigente_hasta ? ` hasta ${t.vigente_hasta}` : ' (vigente)'}</p>)}
                <select className={THEME_INPUT} aria-label="Área de la tarifa" value={tarifa.data.area_id} onChange={(e) => tarifa.setData('area_id', e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select>
                <input className={THEME_INPUT} placeholder="10.00" aria-label="Importe" value={tarifa.data.importe} onChange={(e) => tarifa.setData('importe', e.target.value)} />
                <input className={THEME_INPUT} type="datetime-local" aria-label="Vigente desde" value={tarifa.data.vigente_desde} onChange={(e) => tarifa.setData('vigente_desde', e.target.value)} />
                <button className={THEME_BTN_PRIMARY}>Registrar tarifa</button>
                {tarifa.errors.vigente_desde && <p className="theme-text-peligro text-sm m-0" role="alert">{tarifa.errors.vigente_desde}</p>}
            </form>
            <form className={geliaCardClass('p-4 space-y-3')} onSubmit={(e) => { e.preventDefault(); reparto.transform((data) => ({ ...data, lineas })).post(route('gestion_interna.comisiones_empaque.repartos.store'), { ...VISITA_MODAL, onSuccess: () => { reparto.reset('vigente_desde'); setLineas([{ participante_id: '', porcentaje: '' }]); } }); }}>
                <h2 className="text-base font-black theme-text-main m-0">Repartos</h2>
                {repartos.map((r) => <p key={r.id} className="text-sm theme-text-main m-0">{r.area?.nombre} desde {r.vigente_desde} · {(r.lineas || []).map((l) => `${l.participante?.nombre} ${l.puntos_base / 100}%`).join(', ')}</p>)}
                <div className="grid md:grid-cols-2 gap-3">
                    <select className={THEME_INPUT} aria-label="Área del reparto" value={reparto.data.area_id} onChange={(e) => reparto.setData('area_id', e.target.value)}>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}</select>
                    <input className={THEME_INPUT} type="datetime-local" aria-label="Inicio del reparto" value={reparto.data.vigente_desde} onChange={(e) => reparto.setData('vigente_desde', e.target.value)} />
                </div>
                {lineas.map((linea, i) => (
                    <div key={i} className="flex gap-2">
                        <select className={THEME_INPUT} aria-label="Participante" value={linea.participante_id} onChange={(e) => {
                            const next = [...lineas]; next[i] = { ...linea, participante_id: e.target.value }; setLineas(next);
                        }}>{participantes.filter((p) => p.activo).map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}<option value="">Elegir</option></select>
                        <input className={`${THEME_INPUT} w-32`} aria-label="Porcentaje" value={linea.porcentaje} onChange={(e) => {
                            const next = [...lineas]; next[i] = { ...linea, porcentaje: e.target.value }; setLineas(next);
                        }} />
                    </div>
                ))}
                <p className="text-sm theme-text-main m-0">Suma: {suma.toFixed(2)}%. Debe ser 100%.</p>
                {reparto.errors.lineas && <p className="theme-text-peligro text-sm m-0" role="alert">{reparto.errors.lineas}</p>}
                <div className="flex gap-2">
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setLineas([...lineas, { participante_id: '', porcentaje: '' }])}>Agregar participante</button>
                    <button className={THEME_BTN_PRIMARY}>Guardar reparto</button>
                </div>
            </form>
        </div>
    );
}
