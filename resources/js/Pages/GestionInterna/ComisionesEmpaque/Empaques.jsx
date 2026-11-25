import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Download, Search, SlidersHorizontal, X } from 'lucide-react';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { BOTON_FILTRO, ErroresFormulario, dinero, ir } from './compartido';
import Fila from './DetalleEmpaque';
import BuscarUsuario from './BuscarUsuario';

export function Acumulado({ resumen, filtros, areas = [], situaciones, acumulado, totales, puede, tipos, capturaNombre, sinAreas, paqueterias = [], cargaRevision = null, motivos = {} }) {
    const [local, setLocal] = useState(filtros);
    const [capturar, setCapturar] = useState(false);
    const [cargar, setCargar] = useState(Boolean(cargaRevision));
    const filas = acumulado?.data || [];
    const cambiarVista = (vista) => ir({ ...filtros, tab: 'acumulado', vista: vista || undefined, situacion: undefined, page: undefined });
    const vistas = [['', 'Todos'], ['revisados', 'Válidos'], ['pendientes', 'Por revisar'], ['excluidos', 'Excluidos']];
    const indicadores = [
        ['Cajas registradas', resumen?.cajas_registradas ?? 0],
        ['Cajas comisionables', resumen?.cajas_elegibles ?? 0],
        ['Cajas por revisar', resumen?.cajas_pendientes ?? 0],
        ['Cajas excluidas', resumen?.cajas_excluidas ?? 0],
        ['Comisión válida', dinero(resumen?.importe_valido || '0.00')],
        ['Por liquidar', dinero(resumen?.importe_por_cortar || '0.00')],
    ];
    const filtrado = ['cliente', 'remision', 'pedido', 'empacador', 'empacador_id', 'desde', 'hasta', 'vista', 'situacion'].some((campo) => filtros[campo] !== undefined && filtros[campo] !== null && filtros[campo] !== '');
    return <div className="min-w-0 space-y-3">
        <section className={geliaCardClass('p-3 sm:p-4')} aria-label="Resumen de empaques">
            <dl className="m-0 grid grid-cols-2 gap-x-3 gap-y-2 sm:grid-cols-3 xl:grid-cols-6">
                {indicadores.map(([nombre, valor]) => <div className="min-w-0 py-1" key={nombre}>
                    <dt className="text-xs font-medium theme-text-muted">{nombre}</dt>
                    <dd className="m-0 text-xl font-bold tabular-nums">{valor}</dd>
                </div>)}
            </dl>
            {!!resumen?.pendientes_sin_desglose && <p className="m-0 border-t theme-border pt-2 text-sm theme-text-muted">{resumen.pendientes_sin_desglose} bultos o piezas aún sin clasificar.</p>}
        </section>
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
        <form className={`${geliaCardClass('p-4 md:p-5')} flex flex-wrap items-end gap-3`} onSubmit={(e) => { e.preventDefault(); ir({ ...local, tab: 'acumulado' }); }}><label className="flex-1 min-w-48 max-w-md"><span className={THEME_LABEL}>Buscar cliente</span><input className={THEME_INPUT} value={local.cliente || ''} onChange={(e) => setLocal({ ...local, cliente: e.target.value })} placeholder="Nombre del cliente…" /></label><label className="min-w-40"><span className={THEME_LABEL}>Remisión</span><input className={THEME_INPUT} value={local.remision || ''} onChange={(e) => setLocal({ ...local, remision: e.target.value })} placeholder="Folio…" /></label><button className={BOTON_FILTRO}><Search className="size-4" aria-hidden="true" />Buscar</button><button type="button" className={BOTON_FILTRO} onClick={() => ir({ tab: 'acumulado', area_id: filtros.area_id, periodo_id: filtros.periodo_id, desde: undefined, hasta: undefined })}><X className="size-4" aria-hidden="true" />Limpiar filtros</button>
            <details className="basis-full"><summary className={`${BOTON_FILTRO} w-fit list-none [&::-webkit-details-marker]:hidden`}><SlidersHorizontal className="size-4" aria-hidden="true" />Más filtros</summary><div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 py-3">{[['desde', 'Empaque desde', 'date'], ['hasta', 'Empaque hasta', 'date'], ['pedido', 'Pedido', 'text'], ['empacador', 'Persona que empacó', 'text']].map(([k, label, type]) => <label key={k}><span className={THEME_LABEL}>{label}</span><input className={THEME_INPUT} type={type} value={local[k] || ''} disabled={!!filtros.periodo_id && ['desde', 'hasta'].includes(k)} onChange={(e) => setLocal({ ...local, [k]: e.target.value })} /></label>)}<label><span className={THEME_LABEL}>Situación</span><select className={THEME_INPUT} value={local.situacion || ''} onChange={(e) => setLocal({ ...local, situacion: e.target.value })}><option value="">Todas</option>{Object.entries(situaciones).map(([id, nombre]) => <option key={id} value={id}>{nombre}</option>)}</select></label></div></details>
        </form>
        {capturar && <Manual areas={areas} paqueterias={paqueterias} capturaNombre={capturaNombre} />}
        {cargar && <Carga revision={cargaRevision} />}
        <div className={geliaCardClass('p-3 md:p-4')}><div className="overflow-x-auto"><table className="w-full text-sm min-w-[780px]"><thead><tr className="text-left theme-text-muted">{['Fecha', 'Cliente y remisiones', 'Empacador', 'Cajas', 'Envío', 'Validación', 'Comisión', 'Acción'].map((h) => <th key={h} className={`py-3 px-2 font-medium ${h === 'Comisión' ? 'text-right' : ''}`}>{h}</th>)}</tr></thead><tbody>{!filas.length && <tr><td colSpan={8} className="p-6 theme-text-muted">{sinAreas ? 'No tiene áreas asignadas.' : filtrado ? 'No hay coincidencias. Cambie la bandeja o limpie los filtros.' : resumen?.listo ? 'No hay empaques en el área. Pulse Actualizar para consultar novedades.' : 'Actualice para consultar los empaques de Gestión de Pedidos.'}</td></tr>}{filas.map((fila) => <Fila key={fila.id} fila={fila} puede={puede} tipos={tipos} motivos={motivos} />)}</tbody></table></div>
            <div className="flex flex-wrap justify-between items-center gap-3 border-t theme-border pt-4 mt-3"><p className="text-sm theme-text-muted m-0">{totales?.registros ?? 0} {totales?.registros === 1 ? 'registro' : 'registros'} en este resultado</p></div>
        </div>
        <GeliaPaginacion paginator={acumulado} onIrAPagina={(page) => ir({ tab: 'acumulado', ...filtros, page })} />
    </div>;
}

export function Carga({ revision }) {
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

