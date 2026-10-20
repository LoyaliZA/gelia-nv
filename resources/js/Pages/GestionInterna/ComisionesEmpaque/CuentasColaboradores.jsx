import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { GELIA_ESTADO_VIVO_TONO, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';

const VISITA_MODAL = { preserveState: true, preserveScroll: true };
const SIN_DEPARTAMENTO = 'Sin departamento';
const SIN_AREA = 'Sin área';

function ordenarEtiqueta(a, b, vacio) {
    if (a === vacio) return 1;
    if (b === vacio) return -1;
    return a.localeCompare(b, 'es');
}

function agrupar(items, departamentoDe, areaDe) {
    const departamentos = new Map();
    items.forEach((item) => {
        const departamento = departamentoDe(item) || SIN_DEPARTAMENTO;
        const area = areaDe(item) || SIN_AREA;
        if (!departamentos.has(departamento)) departamentos.set(departamento, new Map());
        const areas = departamentos.get(departamento);
        if (!areas.has(area)) areas.set(area, []);
        areas.get(area).push(item);
    });

    return [...departamentos.entries()]
        .sort(([a], [b]) => ordenarEtiqueta(a, b, SIN_DEPARTAMENTO))
        .map(([departamento, areas]) => [
            departamento,
            [...areas.entries()].sort(([a], [b]) => ordenarEtiqueta(a, b, SIN_AREA)),
        ]);
}

function Errores({ errors, omitir = [] }) {
    return Object.entries(errors)
        .filter(([campo]) => !omitir.some((prefijo) => campo.startsWith(prefijo)))
        .map(([campo, error]) => <p key={campo} className="theme-text-peligro text-sm m-0" role="alert">{error}</p>);
}

export default function CuentasColaboradores({ participantes = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [cuentas, setCuentas] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [errorBusqueda, setErrorBusqueda] = useState('');
    const [recortado, setRecortado] = useState(false);
    const [seleccion, setSeleccion] = useState([]);
    const [edicion, setEdicion] = useState(null);
    const form = useForm({ id: '', user_id: '', nombre: '', activo: true, colaboradores: [] });
    const registrados = useMemo(() => new Set(participantes.map((p) => String(p.user_id))), [participantes]);
    const grupos = useMemo(() => agrupar(cuentas, (c) => c.departamento, (c) => c.area), [cuentas]);
    const equipo = useMemo(
        () => agrupar([...participantes].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')), (p) => p.departamento, (p) => p.area),
        [participantes],
    );

    useEffect(() => {
        const control = new AbortController();
        const espera = window.setTimeout(async () => {
            setCargando(true);
            setErrorBusqueda('');
            try {
                const params = new URLSearchParams({ catalogo: '1' });
                const texto = busqueda.trim();
                if (texto) params.set('q', texto);
                const respuesta = await fetch(`${route('gestion_interna.comisiones_empaque.usuarios')}?${params}`, {
                    signal: control.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!respuesta.ok) throw new Error('busqueda');
                const datos = await respuesta.json();
                if (control.signal.aborted) return;
                const lista = Array.isArray(datos) ? datos : [];
                setCuentas(lista);
                setRecortado(lista.length >= (texto ? 80 : 400));
            } catch (error) {
                if (error.name !== 'AbortError') setErrorBusqueda('No se pudo cargar el catálogo. Escriba de nuevo para reintentar.');
            } finally {
                if (!control.signal.aborted) setCargando(false);
            }
        }, busqueda.trim() ? 250 : 0);

        return () => {
            window.clearTimeout(espera);
            control.abort();
        };
    }, [busqueda]);

    function alternar(cuenta) {
        if (registrados.has(String(cuenta.id))) return;
        setSeleccion((prev) => {
            const existe = prev.some((s) => String(s.user_id) === String(cuenta.id));
            if (existe) return prev.filter((s) => String(s.user_id) !== String(cuenta.id));
            return [...prev, {
                user_id: cuenta.id,
                cuenta: cuenta.name,
                username: cuenta.username || '',
                departamento: cuenta.departamento || '',
                area: cuenta.area || '',
                nombre: '',
                activo: true,
            }];
        });
    }

    function actualizar(userId, campo, valor) {
        setSeleccion((prev) => prev.map((s) => (String(s.user_id) === String(userId) ? { ...s, [campo]: valor } : s)));
    }

    function editar(participante) {
        setSeleccion([]);
        setEdicion(participante);
        form.clearErrors();
        form.setData({
            id: participante.id,
            user_id: participante.user_id,
            nombre: participante.nombre || '',
            activo: !!participante.activo,
            colaboradores: [],
        });
    }

    function limpiar() {
        setSeleccion([]);
        setEdicion(null);
        form.reset();
        form.clearErrors();
    }

    function enviar(evento) {
        evento.preventDefault();
        if (edicion) {
            form.transform((data) => ({ id: data.id, user_id: data.user_id, nombre: data.nombre, activo: !!data.activo }));
        } else {
            form.transform(() => ({
                colaboradores: seleccion.map((s) => ({ user_id: s.user_id, nombre: s.nombre, activo: !!s.activo })),
            }));
        }
        form.post(route('gestion_interna.comisiones_empaque.participantes.store'), { ...VISITA_MODAL, onSuccess: limpiar });
    }

    return (
        <section className="space-y-4">
            <h3 className="text-base font-bold m-0">Colaboradores que reciben comisión</h3>
            <p className="text-sm theme-text-muted m-0">Estas cuentas conservan la identidad de los saldos históricos. Seleccione una o varias, indique el nombre para mostrar de cada una y después asigne porcentajes en Equipo y reparto.</p>
            {participantes.length === 0 && <p className="text-sm m-0">Todavía no hay colaboradores. Seleccione una o varias cuentas para agregar el primero.</p>}

            <form className={geliaCardClass('p-4 space-y-4')} onSubmit={enviar}>
                <h4 className="font-bold m-0">{edicion ? 'Editar colaborador' : 'Agregar colaboradores'}</h4>
                {edicion ? (
                    <div className="rounded-lg border theme-border px-3 py-3 text-sm">
                        <p className="m-0 font-semibold">{edicion.cuenta || edicion.nombre}</p>
                        <p className="m-0 theme-text-muted">{[edicion.departamento || SIN_DEPARTAMENTO, edicion.area || SIN_AREA].join(' · ')}{edicion.username ? ` · ${edicion.username}` : ''}</p>
                        <p className="m-0 mt-1 text-xs theme-text-muted">La cuenta vinculada se conserva para proteger cortes y saldos.</p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        <label className="block">
                            <span className={THEME_LABEL}>Cuentas</span>
                            <input className={THEME_INPUT} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar por nombre, usuario, departamento o área…" autoComplete="off" aria-controls="catalogo-cuentas" />
                        </label>
                        <p className="text-xs theme-text-muted m-0" role="status">{cargando ? 'Cargando cuentas…' : errorBusqueda || 'Marque las cuentas que entrarán al equipo. Puede elegir varias a la vez.'}</p>
                        <div id="catalogo-cuentas" className="max-h-72 overflow-y-auto rounded-lg border theme-border" aria-busy={cargando}>
                            {!cargando && grupos.length === 0 && <p className="m-0 px-3 py-3 text-sm theme-text-muted">No hay cuentas con ese criterio.</p>}
                            {grupos.map(([departamento, areas]) => (
                                <details key={departamento} open className="border-t theme-border first:border-t-0">
                                    <summary className="cursor-pointer px-3 py-2 text-xs font-black uppercase tracking-widest theme-text-muted">{departamento}</summary>
                                    {areas.map(([area, lista]) => (
                                        <div key={`${departamento}-${area}`} className="px-3 pb-2">
                                            <p className="m-0 px-1 py-1 text-sm font-semibold">{area}</p>
                                            <ul className="m-0 list-none p-0">
                                                {lista.map((cuenta) => {
                                                    const yaRegistrada = registrados.has(String(cuenta.id));
                                                    const marcada = seleccion.some((s) => String(s.user_id) === String(cuenta.id));
                                                    return (
                                                        <li key={cuenta.id}>
                                                            <label className={`flex items-start gap-3 rounded-lg px-2 py-2 text-sm ${yaRegistrada ? 'opacity-60' : 'cursor-pointer hover:bg-black/5 dark:hover:bg-white/5'}`}>
                                                                <input type="checkbox" className="mt-1" checked={yaRegistrada || marcada} disabled={yaRegistrada} onChange={() => alternar(cuenta)} />
                                                                <span>
                                                                    <span className="block font-semibold">{cuenta.name}</span>
                                                                    <span className="block text-xs theme-text-muted">{cuenta.username || 'Sin usuario'}{yaRegistrada ? ' · Ya en el equipo' : ''}</span>
                                                                </span>
                                                            </label>
                                                        </li>
                                                    );
                                                })}
                                            </ul>
                                        </div>
                                    ))}
                                </details>
                            ))}
                        </div>
                        {recortado && <p className="text-xs theme-text-muted m-0">Hay más cuentas. Escriba para acotar la búsqueda.</p>}
                    </div>
                )}

                {!edicion && seleccion.length > 0 && (
                    <div className="space-y-3">
                        <h5 className="m-0 text-sm font-bold">Nombre para mostrar</h5>
                        <p className="m-0 text-xs theme-text-muted">Si lo deja vacío, se usa el nombre de la cuenta.</p>
                        <ul className="m-0 list-none space-y-3 p-0">
                            {seleccion.map((s, indice) => (
                                <li key={s.user_id} className="rounded-lg border theme-border p-3 space-y-2">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="m-0 text-sm font-semibold">{s.cuenta}</p>
                                            <p className="m-0 text-xs theme-text-muted">{[s.departamento || SIN_DEPARTAMENTO, s.area || SIN_AREA].join(' · ')}{s.username ? ` · ${s.username}` : ''}</p>
                                        </div>
                                        <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setSeleccion((prev) => prev.filter((fila) => String(fila.user_id) !== String(s.user_id)))}>Quitar<span className="sr-only"> {s.cuenta}</span></button>
                                    </div>
                                    <label className="block">
                                        <span className={THEME_LABEL}>Nombre para mostrar (opcional)</span>
                                        <input className={THEME_INPUT} value={s.nombre} onChange={(e) => actualizar(s.user_id, 'nombre', e.target.value)} placeholder="Se usará el nombre de la cuenta…" />
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={s.activo} onChange={(e) => actualizar(s.user_id, 'activo', e.target.checked)} />
                                        Colaborador activo
                                    </label>
                                    {form.errors[`colaboradores.${indice}.user_id`] && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors[`colaboradores.${indice}.user_id`]}</p>}
                                    {form.errors[`colaboradores.${indice}.nombre`] && <p className="theme-text-peligro text-sm m-0" role="alert">{form.errors[`colaboradores.${indice}.nombre`]}</p>}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {edicion && (
                    <>
                        <label className="block">
                            <span className={THEME_LABEL}>Nombre para mostrar (opcional)</span>
                            <input className={THEME_INPUT} value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} placeholder="Se usará el nombre de la cuenta…" />
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={!!form.data.activo} onChange={(e) => form.setData('activo', e.target.checked)} />
                            Colaborador activo
                        </label>
                    </>
                )}

                <Errores errors={form.errors} omitir={['colaboradores.']} />
                <div className="flex flex-wrap gap-2">
                    <button className={THEME_BTN_PRIMARY} disabled={form.processing || (!edicion && seleccion.length === 0)}>
                        {form.processing ? 'Guardando…' : edicion ? 'Guardar colaborador' : seleccion.length > 1 ? `Agregar ${seleccion.length} al equipo` : 'Agregar al equipo'}
                    </button>
                    {edicion && <button type="button" className={THEME_BTN_SECONDARY} onClick={limpiar}>Cancelar edición</button>}
                </div>
            </form>

            <h4 className="font-bold m-0">Equipo registrado</h4>
            {equipo.length === 0 && <p className="text-sm theme-text-muted m-0">El equipo aparecerá aquí, separado por departamento y área.</p>}
            <div className="space-y-5">
                {equipo.map(([departamento, areas]) => (
                    <section key={departamento} className="space-y-3">
                        <h5 className="m-0 text-xs font-black uppercase tracking-widest theme-text-muted">{departamento}</h5>
                        {areas.map(([area, lista]) => (
                            <div key={`${departamento}-${area}`}>
                                <h6 className="m-0 text-sm font-semibold">{area}</h6>
                                <ul className="m-0 list-none p-0">
                                    {lista.map((p) => (
                                        <li key={p.id} className="grid gap-3 border-t theme-border py-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <strong className="text-sm">{p.nombre}</strong>
                                                    <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex text-[10px] font-semibold ${p.activo ? GELIA_ESTADO_VIVO_TONO.exito : GELIA_ESTADO_VIVO_TONO.neutro}`}>{p.activo ? 'Activo' : 'Inactivo'}</span>
                                                </div>
                                                <dl className="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                                    <div><dt className="text-xs theme-text-muted">Cuenta</dt><dd className="m-0">{p.cuenta || 'Sin cuenta vinculada'}</dd></div>
                                                    <div><dt className="text-xs theme-text-muted">Usuario</dt><dd className="m-0">{p.username || 'Sin usuario'}</dd></div>
                                                    <div><dt className="text-xs theme-text-muted">Departamento</dt><dd className="m-0">{p.departamento || SIN_DEPARTAMENTO}</dd></div>
                                                    <div><dt className="text-xs theme-text-muted">Área</dt><dd className="m-0">{p.area || SIN_AREA}</dd></div>
                                                </dl>
                                            </div>
                                            <button type="button" className={THEME_BTN_SECONDARY} onClick={() => editar(p)}>Editar<span className="sr-only"> {p.nombre}</span></button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </section>
    );
}
