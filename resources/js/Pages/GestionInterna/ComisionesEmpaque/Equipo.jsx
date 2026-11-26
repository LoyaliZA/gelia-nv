import { useForm } from '@inertiajs/react';
import { History, Maximize2, Pencil, Plus, UsersRound } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import BuscarUsuario from './BuscarUsuario';
import { BarraReparto, colorDisponible, colorReparto, MarcaColaborador } from './IdentidadReparto';
import { useProtegerEdicion } from './useActualizacionComisiones';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { ACCION_PRINCIPAL_COMISIONES, dinero, ErroresFormulario } from './compartido';

const secundaria = `${THEME_BTN_SECONDARY} inline-flex min-h-11 items-center gap-2 !normal-case focus-visible:!outline-2 focus-visible:outline-offset-2`;
const puntos = (porcentaje) => Math.round(Number(porcentaje || 0) * 100);
const estimar = (fondo, porcentaje) => dinero(Number(fondo) * Number(porcentaje || 0) / 100);

function datosEquipo(equipo, areaId) {
    const lineas = (equipo?.lineas || []).map((linea, indice) => ({
        fila: indice, user_id: linea.user_id, nombre: linea.nombre, porcentaje: linea.porcentaje,
        encargado: !!linea.encargado, color: colorReparto(linea, indice),
    }));
    return { area_id: areaId, reparto_id: equipo?.version || 0, motivo: '', lineas };
}

export default function Equipo({ equipo, areaId, puede, acumulado = '0.00', historial = [], editarAlAbrir = false }) {
    const [editando, setEditando] = useState(editarAlAbrir && puede.configurar);
    const [error, setError] = useState(null);
    const form = useForm(datosEquipo(equipo, areaId));
    const siguienteFila = useRef(form.data.lineas.length);
    const lineas = editando ? form.data.lineas : equipo?.lineas || [];
    const total = lineas.reduce((sum, linea) => sum + puntos(linea.porcentaje), 0);
    useProtegerEdicion(editando && (form.isDirty || form.processing));

    function editar() {
        const datos = datosEquipo(equipo, areaId);
        siguienteFila.current = datos.lineas.length;
        form.setData(datos);
        setError(null);
        setEditando(true);
    }

    function cambiar(i, datos) {
        setError(null);
        form.clearErrors?.();
        form.setData('lineas', form.data.lineas.map((linea, indice) => i === indice ? { ...linea, ...datos } : linea));
    }

    function agregar() {
        const fila = siguienteFila.current++;
        setError(null);
        form.setData('lineas', [...form.data.lineas, { fila, user_id: '', nombre: '', porcentaje: '', color: colorDisponible(lineas) }]);
    }

    function guardar(evento) {
        evento.preventDefault();
        if (!lineas.length || lineas.some((linea) => !linea.user_id)) {
            setError('Seleccione una cuenta para cada colaborador del equipo.');
            return;
        }
        if (total !== 10000) {
            setError('Ajuste las participaciones hasta sumar 100%.');
            return;
        }
        form.transform((data) => ({
            ...data, lineas: data.lineas.map(({ user_id, porcentaje, encargado, color }) => ({ user_id, porcentaje: String(porcentaje), encargado: !!encargado, color })),
        }));
        form.put(route('gestion_interna.comisiones_empaque.equipo'), {
            preserveScroll: true, onSuccess: () => { setEditando(false); form.reset(); },
        });
    }

    return <section className="min-w-0 space-y-4 rounded-xl border theme-border p-3 md:p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
                <span className="grid size-11 shrink-0 place-items-center rounded-lg theme-element theme-text-primario"><UsersRound className="size-5" aria-hidden="true" /></span>
                <div>
                    <h2 className="m-0 text-base font-semibold">Equipo de {equipo?.area}</h2>
                    <p className="m-0 text-sm theme-text-muted">Comisión pendiente · <strong className="theme-text-main">{dinero(acumulado)}</strong></p>
                </div>
            </div>
            {puede.configurar && !editando && <button type="button" className={secundaria} onClick={editar}>
                <Pencil className="size-4" aria-hidden="true" />{lineas.length ? 'Ajustar equipo y porcentajes' : 'Configurar equipo'}
            </button>}
        </div>
        <BarraReparto lineas={lineas} />
        {!editando && lineas.length > 0 && <div className="rounded-lg border theme-border">
            <table className="w-full table-fixed text-sm md:table-auto">
                <thead className="theme-element"><tr className="text-left text-xs md:text-sm">
                    <th className="w-1/2 break-words px-2 py-2">Colaborador</th><th className="px-1 py-2 text-right" aria-label="Participación"><span className="md:hidden">%</span><span className="hidden md:inline">Participación</span></th><th className="break-words px-2 py-2 text-right">Estimación</th>
                </tr></thead>
                <tbody>{lineas.map((linea, indice) => <tr key={linea.user_id} className="border-t theme-border">
                    <th scope="row" className="px-2 py-2 text-left font-medium"><span className="flex items-center gap-2"><MarcaColaborador color={colorReparto(linea, indice)} /><span className="min-w-0 break-words">{linea.nombre}{linea.encargado && <small className="block text-xs theme-text-muted">Encargado</small>}</span></span></th>
                    <td className="px-1 text-right tabular-nums">{linea.porcentaje}%</td>
                    <td className="break-words px-2 text-right tabular-nums">{estimar(acumulado, linea.porcentaje)}</td>
                </tr>)}</tbody>
            </table>
        </div>}
        {!editando && !lineas.length && <p className="m-0 text-sm theme-text-muted">Agregue colaboradores y asigne su participación.</p>}
        {editando && <form onSubmit={guardar} className="space-y-4">
            {lineas.map((linea, i) => <FilaEditor key={linea.fila} linea={linea} indice={i} areaId={areaId} acumulado={acumulado}
                maximo={Math.max(0, 10000 - total + puntos(linea.porcentaje))}
                onCambiar={(datos) => cambiar(i, datos)}
                onEncargado={(encargado) => form.setData('lineas', form.data.lineas.map((l, indice) => ({ ...l, encargado: indice === i && encargado })))}
                onRetirar={() => { setError(null); form.setData('lineas', form.data.lineas.filter((_, indice) => indice !== i)); }}
                ocupado={form.processing} />)}
            <div className="flex flex-wrap items-center justify-between gap-2">
                <button type="button" className={secundaria} onClick={agregar}><Plus className="size-4" aria-hidden="true" />Agregar colaborador</button>
                <p role="status" className="m-0 text-sm theme-text-muted"><strong className="theme-text-main">{(total / 100).toFixed(2)}%</strong> asignado de 100%</p>
            </div>
            {error && <p role="alert" className="m-0 text-sm theme-text-peligro">{error}</p>}
            <label className="block"><span className={THEME_LABEL}>Motivo del cambio</span><input className={THEME_INPUT} required minLength={5} maxLength={2000}
                value={form.data.motivo} onChange={(e) => { form.clearErrors?.('motivo'); form.setData('motivo', e.target.value); }} placeholder="Por ejemplo: salida de un colaborador…" /></label>
            <p className="m-0 text-sm theme-text-muted">El nuevo reparto se aplica al fondo abierto. Cierre primero el periodo anterior si necesita conservar la participación de alguien que sale.</p>
            <ErroresFormulario form={form} />
            <div className="flex flex-wrap gap-2">
                <button className={ACCION_PRINCIPAL_COMISIONES} disabled={form.processing}>{form.processing ? 'Guardando…' : 'Guardar reparto permanente'}</button>
                <button type="button" className={secundaria} disabled={form.processing} onClick={() => { form.reset(); setError(null); setEditando(false); }}>Cancelar</button>
            </div>
        </form>}
        {historial.length > 0 && <details>
            <summary className="min-h-11 cursor-pointer py-3 text-sm font-semibold"><History className="mr-2 inline-block size-4" aria-hidden="true" />Consultar historial de reparto</summary>
            <ul className="m-0 list-none p-0 text-sm">{historial.map((r) => <li className="border-t theme-border py-2" key={r.id}>
                {r.vigente_desde?.slice(0, 10)} · {r.motivo || 'Reparto registrado'}
                <ul className="m-0 flex list-none flex-wrap gap-x-4 gap-y-1 p-0 theme-text-muted">{r.lineas?.map((l, indice) => <li key={l.id} className="inline-flex items-center gap-1.5">
                    <MarcaColaborador color={colorReparto(l, indice)} />{l.participante?.nombre} {(l.puntos_base / 100).toFixed(2)}%
                </li>)}</ul>
            </li>)}</ul>
        </details>}
    </section>;
}

function FilaEditor({ linea, indice, areaId, acumulado, maximo, onCambiar, onEncargado, onRetirar, ocupado }) {
    const etiquetaColor = useId();
    const campoParticipacion = useId();
    const limiteTexto = (maximo / 100).toFixed(2);
    function ajustarPorcentaje(valor) {
        onCambiar({ porcentaje: Number(valor) > maximo / 100 ? limiteTexto : valor });
    }
    return <div className="grid min-w-0 grid-cols-2 items-end gap-3 rounded-lg border theme-border p-3 lg:grid-cols-[minmax(0,1fr)_10rem_5rem_6rem_auto_auto]">
        <div className="col-span-2 min-w-0 lg:col-span-1">
            <BuscarUsuario value={linea.user_id} nombre={linea.nombre} areaEquipo={areaId} etiqueta={`Colaborador ${indice + 1}`}
                onChange={(user_id, nombre) => onCambiar({ user_id, nombre })} />
        </div>
        <div className="col-span-2 min-w-0 lg:col-span-1">
            <label htmlFor={campoParticipacion} className={THEME_LABEL}>Participación %</label>
            <div className="flex min-w-0 gap-1">
                <input id={campoParticipacion} className={`${THEME_INPUT} min-w-0 flex-1 !px-2 tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none`} required type="number" inputMode="decimal"
                    min="0.01" max={limiteTexto} step="0.01" value={linea.porcentaje} onChange={(e) => ajustarPorcentaje(e.target.value)}
                    aria-describedby={`${campoParticipacion}-maximo`} />
                <div className="group relative shrink-0">
                    <button type="button" className="grid size-11 place-items-center rounded-lg border theme-border theme-element theme-text-main focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                        aria-label={`Usar máximo disponible para ${linea.nombre || `colaborador ${indice + 1}`}`} disabled={maximo === 0 || ocupado}
                        onClick={() => ajustarPorcentaje(limiteTexto)}><Maximize2 className="size-4" aria-hidden="true" /></button>
                    <span role="tooltip" className="pointer-events-none invisible absolute right-0 top-full z-10 mt-1 w-max rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">Usar {limiteTexto}%</span>
                </div>
            </div>
            <span id={`${campoParticipacion}-maximo`} className="sr-only">Máximo disponible {limiteTexto}%</span>
        </div>
        <div>
            <label id={etiquetaColor} className={THEME_LABEL} htmlFor={`${etiquetaColor}-campo`}>Color</label>
            <input id={`${etiquetaColor}-campo`} aria-label={`Color de ${linea.nombre || `colaborador ${indice + 1}`}`} type="color"
                className="block h-11 w-full cursor-pointer rounded-lg border theme-border theme-element p-1.5 focus-visible:outline-2 focus-visible:outline-offset-2"
                value={linea.color} onChange={(e) => onCambiar({ color: e.target.value })} />
        </div>
        <div className="min-w-0"><span className="text-xs theme-text-muted">Estimación</span><p className="m-0 break-words tabular-nums">{estimar(acumulado, linea.porcentaje)}</p></div>
        <label className="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={!!linea.encargado} onChange={(e) => onEncargado(e.target.checked)} /> Encargado</label>
        <button type="button" className={secundaria} disabled={ocupado} onClick={onRetirar}>Retirar<span className="sr-only"> {linea.nombre || `colaborador ${indice + 1}`}</span></button>
    </div>;
}
