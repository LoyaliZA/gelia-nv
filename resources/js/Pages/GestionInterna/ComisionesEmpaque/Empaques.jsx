import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { BadgeCheck, Box, CircleMinus, ClipboardList, Download, Hourglass, Search, SlidersHorizontal, Wallet, X } from 'lucide-react';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { BOTON_FILTRO, ErroresFormulario, dinero, ir } from './compartido';
import Fila from './DetalleEmpaque';
import BuscarUsuario from './BuscarUsuario';
import Carga from './Carga';
import { useProtegerEdicion } from './useActualizacionComisiones';

export function Acumulado({ resumen, filtros, areas = [], situaciones, acumulado, totales, puede, tipos, capturaNombre, sinAreas, paqueterias = [], cargaRevision = null, motivos = {} }) {
    const [local, setLocal] = useState(filtros);
    const filtrosServidor = JSON.stringify(filtros);
    const masFiltros = useRef(null);
    const consultaEnviada = useRef(null);
    useEffect(() => {
        const recibidos = JSON.parse(filtrosServidor);
        const enBusqueda = consultaEnviada.current === (recibidos.buscar || '');
        consultaEnviada.current = null;
        if (!enBusqueda) setLocal(recibidos);
    }, [filtrosServidor]);
    const [revisionId, setRevisionId] = useState(null);
    const [capturar, setCapturar] = useState(false);
    const [cargar, setCargar] = useState(Boolean(cargaRevision));
    const filas = acumulado?.data || [];
    const indiceActual = filas.findIndex((fila) => fila.id === revisionId);
    const siguiente = filas.slice(indiceActual + 1).find((fila) =>
        ['pendiente_revision', 'posible_duplicado', 'pendiente_clasificacion', 'pendiente_conciliacion', 'pendiente_decision', 'reempaque_revision', 'solape_revision', 'revertida_revision'].includes(fila.situacion));

    const cambiarVista = (vista) => ir({
        ...filtros, tab: 'acumulado', vista: vista || undefined, situacion: undefined, page: undefined,
        ...(vista === 'tardias' && filtros.origen !== 'simple' ? { periodo_id: undefined, desde: undefined, hasta: undefined } : {}),
    });
    const vistas = [['', 'Todos'], ['revisados', 'Válidos'], ['pendientes', 'Por revisar'], ['excluidos', 'Excluidos'], ['tardias', 'Incorporaciones tardías']];
    const buscar = (cambios, replace = false) => {
        consultaEnviada.current = replace ? cambios.buscar || '' : null;
        ir({ ...cambios, tab: 'acumulado', page: undefined }, {
            replace,
            only: ['acumulado', 'totales', 'resumen', 'filtros', 'flash'],
            onSuccess: () => {
                if (!replace && masFiltros.current) masFiltros.current.open = false;
            },
        });
    };
    const termino = (local.buscar || '').trim();
    useEffect(() => {
        if (termino === (filtros.buscar || '')) return undefined;
        const espera = window.setTimeout(() => buscar({ ...filtros, buscar: termino || undefined }, true), 350);
        return () => window.clearTimeout(espera);
    // La escritura en otros filtros solo se aplica al enviar.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [termino, filtros.buscar]);

    const indicadores = [
        ['Empaques', resumen?.total_registros ?? 0, ClipboardList, 'theme-text-muted'],
        ['Cajas identificadas', resumen?.cajas_registradas ?? 0, Box, 'theme-text-info'],
        ['Comisionables', resumen?.cajas_elegibles ?? 0, BadgeCheck, 'theme-text-exito'],
        ['Cajas por revisar', resumen?.cajas_pendientes ?? 0, Hourglass, 'theme-text-aviso'],
        ['Cajas excluidas', resumen?.cajas_excluidas ?? 0, CircleMinus, 'theme-text-muted'],
        ['Fondo abierto', dinero(resumen?.importe_por_cortar || '0.00'), Wallet, 'theme-text-primario'],
    ];
    const filtrado = ['buscar', 'cliente', 'remision', 'pedido', 'empacador', 'empacador_id', 'desde', 'hasta', 'vista', 'situacion'].some((campo) => filtros[campo] !== undefined && filtros[campo] !== null && filtros[campo] !== '');
    return <div className="min-w-0 space-y-3">
        <section className="space-y-2 border-b theme-border pb-2" aria-label="Resumen de empaques">
            <dl className="m-0 grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-3 xl:grid-cols-6">
                {indicadores.map(([nombre, valor, Icono, color]) => <div className="min-w-0 py-1" key={nombre}>
                    <dt className="flex items-center gap-1.5 text-xs font-medium theme-text-muted"><Icono className={`size-4 shrink-0 ${color}`} aria-hidden="true" />{nombre}</dt>
                    <dd className={`m-0 text-lg font-bold tabular-nums ${color === 'theme-text-primario' ? color : 'theme-text-main'}`}>{valor}</dd>
                </div>)}
            </dl>
            <p className="m-0 flex flex-wrap gap-x-4 gap-y-1 text-xs theme-text-muted">
                <span>Bolsas · {resumen?.bolsas_registradas ?? 0}</span>
                <span>Bultos sin clasificar · {resumen?.pendientes_sin_desglose ?? 0}</span>
                <span>Registros sin cantidad · {resumen?.registros_sin_cantidad ?? 0}</span>
                <span>Comisión generada · {dinero(resumen?.importe_valido || '0.00')}</span>
                <span>Constancias pagadas fuera · {resumen?.constancias_pagadas_fuera ?? 0}</span>
            </p>
        </section>
        <form className="relative flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); buscar({ ...local, buscar: termino || undefined }); }}>
            <label className="min-w-0 flex-[1_1_15rem] sm:max-w-md">
                <span className={THEME_LABEL}>Buscar empaques</span>
                <span className="relative block"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 theme-text-muted" aria-hidden="true" />
                    <input className={`${THEME_INPUT} !pl-9`} data-busqueda-live value={local.buscar || ''} onChange={(e) => setLocal({ ...local, buscar: e.target.value })} placeholder="Cliente, remisión, pedido o empacador" />
                </span>
            </label>
            <button type="button" className={BOTON_FILTRO} onClick={() => {
                const limpio = { area_id: filtros.area_id, periodo_id: filtros.periodo_id, vista: filtros.vista,
                    desde: undefined, hasta: undefined };
                setLocal(limpio);
                buscar(limpio);
            }}><X className="size-4" aria-hidden="true" />Limpiar filtros</button>
            <details ref={masFiltros}>
                <summary className={`${BOTON_FILTRO} w-fit list-none [&::-webkit-details-marker]:hidden`}><SlidersHorizontal className="size-4" aria-hidden="true" />Más filtros</summary>
                <div className="absolute inset-x-0 top-full z-10 mt-1 grid gap-2 rounded-lg border theme-border theme-element p-3 sm:max-w-lg sm:grid-cols-3">
                    {[
                        ['cliente', 'Cliente', 'text'], ['remision', 'Remisión', 'text'], ['pedido', 'Pedido', 'text'], ['empacador', 'Empacador', 'text'],
                        ['desde', 'Empaque desde', 'date'], ['hasta', 'Empaque hasta', 'date'],
                    ].map(([k, label, type]) => <label key={k}><span className={THEME_LABEL}>{label}</span>
                        <input className={THEME_INPUT} type={type} value={local[k] || ''} disabled={!!filtros.periodo_id && ['desde', 'hasta'].includes(k)} onChange={(e) => setLocal({ ...local, [k]: e.target.value })} />
                    </label>)}
                    <label><span className={THEME_LABEL}>Situación</span><select className={THEME_INPUT} value={local.situacion || ''} onChange={(e) => setLocal({ ...local, situacion: e.target.value })}>
                        <option value="">Todas</option>{Object.entries(situaciones).map(([id, nombre]) => <option key={id} value={id}>{nombre}</option>)}
                    </select></label>
                    <button className={BOTON_FILTRO} type="submit">Aplicar filtros</button>
                </div>
            </details>
        </form>
        <div className="flex flex-wrap items-start justify-between gap-2">
            <nav aria-label="Bandejas de empaques" className="flex flex-wrap items-center gap-1">
                {vistas.map(([valor, nombre]) => {
                    const activo = (filtros.vista || '') === valor;
                    return <button key={nombre} type="button" aria-label={nombre} aria-pressed={activo} onClick={() => cambiarVista(valor)}
                        className={`min-h-11 rounded-lg border px-3 text-sm font-semibold ${activo ? 'theme-element theme-border theme-text-primario' : 'border-transparent theme-text-main'}`}>
                        {nombre}
                    </button>;
                })}
            </nav>
            <div className="flex flex-wrap gap-2">
                {puede.exportar && <a className={BOTON_FILTRO} aria-label="Exportar reporte" href={route('gestion_interna.comisiones_empaque.exportar.acumulado', filtros)}><Download className="size-4" aria-hidden="true" />Exportar</a>}
                {puede.capturar && <details className="relative">
                    <summary className={`${BOTON_FILTRO} list-none [&::-webkit-details-marker]:hidden`}>Herramientas</summary>
                    <div className="absolute right-0 z-10 mt-1 grid w-48 gap-1 rounded-lg border theme-border theme-element p-1">
                        <button type="button" className={BOTON_FILTRO} aria-expanded={capturar} onClick={() => { setCapturar(!capturar); setCargar(false); }}>{capturar ? 'Ocultar captura' : 'Registrar empaque'}</button>
                        <button type="button" className={BOTON_FILTRO} aria-expanded={cargar} onClick={() => { setCargar(!cargar); setCapturar(false); }}>{cargar ? 'Ocultar carga' : 'Cargar histórico'}</button>
                    </div>
                </details>}
            </div>
        </div>
        {capturar && <Manual areas={areas} paqueterias={paqueterias} capturaNombre={capturaNombre} />}
        {cargar && <Carga revision={cargaRevision} />}
        {filtros.vista === 'tardias' && <p className="m-0 text-sm theme-text-muted">Abra el detalle para consultar el corte original y la decisión pendiente del supervisor.</p>}
        <div className={geliaCardClass('!rounded-xl p-3 md:p-4')}><div className="overflow-x-auto"><table className="w-full text-sm min-w-[780px]"><thead><tr className="text-left theme-text-muted">{['Fecha', 'Cliente y remisiones', 'Empacador', 'Cajas', 'Envío', 'Validación', 'Comisión', 'Acción'].map((h) => <th key={h} className={`py-3 px-2 font-medium ${h === 'Comisión' ? 'text-right' : ''}`}>{h}</th>)}</tr></thead><tbody>{!filas.length && <tr><td colSpan={8} className="p-6 theme-text-muted">{sinAreas ? 'No tiene áreas asignadas.' : filtrado ? 'No hay coincidencias. Cambie la bandeja o limpie los filtros.' : resumen?.listo ? 'No hay empaques en el área para este periodo.' : 'Todavía no hay empaques en este periodo.'}</td></tr>}{filas.map((fila) => <Fila key={fila.id} fila={fila} puede={puede} tipos={tipos} motivos={motivos} abierta={revisionId === fila.id} onAbrir={() => setRevisionId(fila.id)} onCerrar={() => setRevisionId(null)} onSiguiente={siguiente ? () => setRevisionId(siguiente.id) : undefined} />)}</tbody></table></div>
            {acumulado?.total > 0
                ? <GeliaPaginacion embedded paginator={acumulado} onIrAPagina={(page) => ir({ tab: 'acumulado', ...filtros, page })} />
                : <p className="m-0 border-t theme-border px-2 py-3 text-sm theme-text-muted">{totales?.registros ?? 0} registros</p>}
        </div>
    </div>;
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

    useProtegerEdicion(form.isDirty || form.processing);

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

