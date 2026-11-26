import axios from 'axios';
import { AlertCircle, Boxes, CalendarDays, Coins, Download, Package, Pencil, Wallet } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { ACCION_PRINCIPAL_COMISIONES, dinero, momentoLegible } from './compartido';
import { BarraReparto, colorReparto, MarcaColaborador } from './IdentidadReparto';

const accionSecundaria = `${THEME_BTN_SECONDARY} inline-flex min-h-11 items-center gap-2 !normal-case focus-visible:!outline-2 focus-visible:outline-offset-2`;
const numero = new Intl.NumberFormat('es-MX');
const porcentaje = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 });

export default function Reporte({ reporte, areaId, filtros, areas, periodos, puede, ocupado, onConsultar, onEmpaques, onConfigurar, onCorte }) {
    const { desde, hasta, version, grupos } = reporte;
    const [rango, setRango] = useState(null);
    const [descargando, setDescargando] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        setRango(null);
        setError(null);
    }, [areaId, filtros.periodo_id, desde, hasta]);

    async function descargar() {
        setDescargando(true);
        setError(null);
        try {
            const { data } = await axios.get(route('gestion_interna.comisiones_empaque.exportar.reporte'), {
                params: { area_id: areaId || undefined, desde, hasta, version },
                responseType: 'blob',
                headers: { Accept: 'application/json' },
            });
            const url = URL.createObjectURL(data);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = 'reporte-comisiones-empaque.xlsx';
            enlace.click();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (fallo) {
            setError(fallo.response?.status === 409
                ? 'El cálculo cambió. Actualice la consulta y vuelva a generar el reporte.'
                : 'No se pudo descargar el archivo. Vuelva a generar el reporte.');
        } finally {
            setDescargando(false);
        }
    }

    return <div className="min-w-0 space-y-4">
        <div className="grid min-w-0 grid-cols-2 gap-2">
            <label className="min-w-0 md:min-w-36">
                <span className={THEME_LABEL}><Boxes aria-hidden="true" className="mr-1.5 inline-block size-4" />Área</span>
                <select className={THEME_INPUT} value={areaId} onChange={(e) => {
                    const area = e.target.value || 'todas';
                    const mantenerFechas = !filtros.periodo_id || area === 'todas';
                    onConsultar({ area_id: area, periodo_id: undefined,
                        desde: mantenerFechas ? desde : undefined, hasta: mantenerFechas ? hasta : undefined });
                }}>
                    <option value="">Todas</option>
                    {areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                </select>
            </label>
            <label className="min-w-0">
                <span className={THEME_LABEL}><CalendarDays aria-hidden="true" className="mr-1.5 inline-block size-4" />Periodo</span>
                <select className={THEME_INPUT} value={rango ? 'personalizado' : filtros.periodo_id || 'fechas'} onChange={(e) => {
                    if (e.target.value === 'personalizado') setRango({ desde, hasta });
                    else if (e.target.value === 'fechas') setRango(null);
                    else onConsultar({ periodo_id: e.target.value, desde: undefined, hasta: undefined });
                }}>
                    <option value="fechas" hidden={Boolean(filtros.periodo_id)}>Fechas</option>
                    {periodos.map((p) => <option value={p.id} key={p.id}>{p.nombre} · {p.desde} a {p.hasta}</option>)}
                    <option value="personalizado">Elegir fechas…</option>
                </select>
            </label>
        </div>
        {rango && <form className="flex min-w-0 flex-wrap items-end gap-2" onSubmit={(e) => {
            e.preventDefault();
            onConsultar({ periodo_id: undefined, ...rango });
            setRango(null);
        }}>
            {['desde', 'hasta'].map((campo) => <label key={campo} className="min-w-0 flex-1 sm:flex-none">
                <span className={THEME_LABEL}>{campo === 'desde' ? 'Desde' : 'Hasta'}</span>
                <input type="date" required min={campo === 'hasta' ? rango.desde : undefined}
                    max={campo === 'desde' ? rango.hasta : undefined} value={rango[campo]}
                    className={THEME_INPUT} onChange={(e) => setRango({ ...rango, [campo]: e.target.value })} />
            </label>)}
            <button className={accionSecundaria} type="submit">Consultar fechas</button>
            <button className={accionSecundaria} type="button" onClick={() => setRango(null)}>Cancelar</button>
        </form>}
        <p className="m-0 text-sm theme-text-muted">{momentoLegible(desde)} – {momentoLegible(hasta)}</p>
        {error && <div className="flex flex-wrap items-center gap-2" role="alert">
            <p className="m-0 text-sm theme-text-peligro">{error}</p>
            <button type="button" className={accionSecundaria} onClick={() => onConsultar({})}>Actualizar cálculo</button>
        </div>}
        {grupos.map((grupo) => <Resultado key={`${grupo.area_id}:${grupo.corte || 'preliminar'}`} grupo={grupo}
            puede={puede} onEmpaques={onEmpaques} onConfigurar={onConfigurar} onCorte={onCorte} />)}
        <div className="flex flex-col items-center gap-1 border-t theme-border pt-3">
            {puede.exportar && grupos.length > 0 && <button type="button" className={`${ACCION_PRINCIPAL_COMISIONES} w-full`}
                disabled={ocupado || descargando || Boolean(rango)} onClick={descargar}>
                <Download aria-hidden="true" className="size-4 shrink-0" />
                {descargando ? 'Generando reporte…' : 'Generar reporte de comisiones'}
            </button>}
            <button className={accionSecundaria} type="button" onClick={() => onEmpaques()}>
                <Package aria-hidden="true" className="size-4" /> Consultar empaques
            </button>
        </div>
    </div>;
}

function Resultado({ grupo, puede, onEmpaques, onConfigurar, onCorte }) {
    const id = useId();
    const { tarifas, tarifas_referencia: referencias = [], tramos, corte, area_id: areaId } = grupo;

    return <section aria-labelledby={id} className="theme-surface theme-no-blur theme-border min-w-0 space-y-3 rounded-xl border p-3 md:p-4">
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id={id} className="m-0 text-base font-semibold theme-text-main">{grupo.area}</h2>
                <p className="m-0 text-sm theme-text-muted">{corte
                    ? `Liquidación confirmada · ${grupo.folio} · ${momentoLegible(grupo.desde)} – ${momentoLegible(grupo.hasta)}`
                    : 'Reporte preliminar'}</p>
            </div>
            {corte && <button type="button" className={accionSecundaria} onClick={() => onCorte(areaId, corte)}>Ver corte</button>}
        </div>
        <dl className="m-0 grid grid-cols-2 gap-3 border-y theme-border py-3 md:grid-cols-3">
            <Indicador etiqueta="Cajas comisionables" Icono={Package}>{numero.format(grupo.cajas)}</Indicador>
            <Indicador etiqueta="Tarifa por caja" Icono={Coins}>
                <TarifaResumen aplicadas={tarifas} referencias={referencias} />
            </Indicador>
            <Indicador etiqueta="Comisión total" Icono={Wallet} principal>{dinero(grupo.importe)}</Indicador>
        </dl>
        {!!tramos.length && <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="m-0 text-base font-semibold">Distribución del equipo</h3>
            {!corte && puede.configurar && <button className={accionSecundaria} type="button" onClick={() => onConfigurar('equipo', areaId, true)}>
                <Pencil className="size-4" aria-hidden="true" />Editar reparto
            </button>}
        </div>}
        {tramos.map((tramo, indice) => <Distribucion key={indice} tramo={tramo} multiple={tramos.length > 1} />)}
        {!grupo.cajas && !corte && <p className="m-0 text-sm theme-text-muted">Sin cajas comisionables en estas fechas.</p>}
        {grupo.reparto_incompleto && <div className="flex flex-wrap items-center gap-2">
            <p className="m-0 text-sm theme-text-main">El reparto del área está incompleto.</p>
            {puede.configurar && <button className={accionSecundaria} type="button" onClick={() => onConfigurar('equipo', areaId, true)}>Configurar reparto</button>}
        </div>}
        {!!grupo.pendientes && <Aviso cantidad={grupo.pendientes} singular="empaque requiere revisión" plural="empaques requieren revisión"
            accion={puede.clasificar ? 'Revisar empaques' : 'Ver empaques'} onClick={() => onEmpaques(areaId, 'pendientes')} />}
        {!!grupo.tardios && <Aviso cantidad={grupo.tardios} singular="empaque de un periodo cerrado" plural="empaques de periodos cerrados"
            accion="Consultar empaques" onClick={() => onEmpaques(areaId, 'tardias')} />}
        {!!grupo.excluidos && <p className="m-0 text-sm theme-text-muted">{grupo.excluidos} {grupo.excluidos === 1 ? 'empaque excluido' : 'empaques excluidos'} del cálculo.</p>}
        {grupo.cortes_parciales?.map((parcial) => <div key={parcial.id} className="flex flex-wrap items-center gap-2">
            <p className="m-0 text-sm theme-text-main">Las fechas cubren solo parte del corte {parcial.folio}.</p>
            <button type="button" className={accionSecundaria} onClick={() => onCorte(areaId, parcial.id)}>Ver corte completo</button>
        </div>)}
        {!!grupo.advertencias?.length && <details className="text-sm theme-text-muted">
            <summary className="min-h-11 cursor-pointer py-3">Ver advertencias del cálculo</summary>
            <ul className="m-0 space-y-1 pl-5">{grupo.advertencias.map((mensaje) => <li key={mensaje}>{mensaje}</li>)}</ul>
        </details>}
    </section>;
}

function TarifaResumen({ aplicadas, referencias }) {
    const hayReferencia = !aplicadas.length && referencias.length > 0;
    const tarifas = hayReferencia ? referencias : aplicadas;
    if (!tarifas.length) return '—';
    const primera = tarifas[0];
    const ultima = tarifas[tarifas.length - 1];
    return <>
        {hayReferencia || tarifas.length === 1 ? dinero(ultima.tarifa) : <span className="text-base">{dinero(primera.tarifa)} – {dinero(ultima.tarifa)}</span>}
        {hayReferencia && <span className="block text-xs font-normal theme-text-muted">
            {referencias.length > 1 ? 'Configurada al cierre del rango' : 'Configurada para el periodo'}
        </span>}
        {tarifas.length > 1 && <details className="text-sm font-semibold">
            <summary className="flex min-h-11 cursor-pointer list-none items-center gap-1 underline underline-offset-2">
                {hayReferencia ? 'Ver vigencias del periodo' : 'Tarifas variables'}
            </summary>
            <ul className="m-0 list-none space-y-1 p-0 font-normal">
                {tarifas.map((t) => <li key={t.desde || t.tarifa}><strong>{dinero(t.tarifa)}</strong> · {t.desde ? momentoLegible(t.desde) : `${numero.format(t.cajas)} cajas`}</li>)}
            </ul>
        </details>}
    </>;
}

function Indicador({ etiqueta, Icono, children, principal }) {
    return <div className={principal ? 'col-span-2 min-w-0 md:col-span-1' : 'min-w-0'}>
        <dt className="flex items-center gap-1.5 text-sm theme-text-muted"><Icono className="size-4 shrink-0 theme-text-primario" aria-hidden="true" />{etiqueta}</dt>
        <dd className={`m-0 break-words font-semibold tabular-nums ${principal ? 'text-2xl' : 'text-xl'} theme-text-main`}>{children}</dd>
    </div>;
}

function Distribucion({ tramo, multiple }) {
    return <div className="min-w-0 space-y-2">
        <BarraReparto lineas={tramo.participantes} />
        <div className="overflow-x-auto rounded-lg border theme-border">
        <table className="w-full table-fixed text-sm theme-text-main md:table-auto">
            <colgroup><col /><col className="w-[22%] md:w-auto" /><col className="w-[32%] md:w-auto" /></colgroup>
            <caption className="sr-only">{multiple ? `Distribución vigente desde ${momentoLegible(tramo.desde)}` : 'Distribución del equipo'}</caption>
            {multiple && <thead><tr><th colSpan="3" className="border-b theme-border px-2 py-2 text-left">Reparto vigente desde {momentoLegible(tramo.desde)}</th></tr></thead>}
            <thead className="theme-element text-xs md:text-sm"><tr>
                <th scope="col" className="break-words px-1.5 py-2 text-left md:px-2">Colaborador</th>
                <th scope="col" aria-label="Porcentaje" className="px-1.5 py-2 text-right md:px-2">
                    <span className="md:hidden" aria-hidden="true">%</span><span className="hidden md:inline">Porcentaje</span>
                </th>
                <th scope="col" className="break-words px-1.5 py-2 text-right md:px-2">Comisión</th>
            </tr></thead>
            <tbody>{tramo.participantes.map((persona, indice) => <tr key={persona.participante_id} className="border-t theme-border">
                <th scope="row" className="break-words px-2 py-2 text-left font-normal"><span className="flex items-center gap-1.5"><MarcaColaborador color={colorReparto(persona, indice)} /><span className="min-w-0 break-words">{persona.nombre}</span></span></th>
                <td className="break-words px-1.5 py-2 text-right md:whitespace-nowrap md:px-2 tabular-nums">{porcentaje.format(persona.porcentaje)}%</td>
                <td className="break-words px-1.5 py-2 text-right md:whitespace-nowrap md:px-2 font-medium tabular-nums">{dinero(persona.importe)}</td>
            </tr>)}</tbody>
            <tfoot><tr className="border-t theme-border font-semibold">
                <th scope="row" className="px-2 py-2 text-left">Total</th>
                <td className="px-2 py-2 text-right">100%</td>
                <td className="break-words px-1.5 py-2 text-right md:whitespace-nowrap md:px-2 tabular-nums">{dinero(tramo.importe)}</td>
            </tr></tfoot>
        </table>
        </div>
    </div>;
}

function Aviso({ cantidad, singular, plural, accion, onClick }) {
    return <div className="flex flex-wrap items-center gap-2">
        <p className="m-0 flex items-center gap-2 text-sm theme-text-main"><AlertCircle className="size-4 shrink-0 theme-text-aviso" aria-hidden="true" />{numero.format(cantidad)} {cantidad === 1 ? singular : plural}.</p>
        <button className={accionSecundaria} type="button" onClick={onClick}>{accion}</button>
    </div>;
}
