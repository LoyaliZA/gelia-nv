import { useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import EscalonamientoModal from './EscalonamientoModal';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '../../../utils/geliaTheme';

function dinero(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    const numero = Number(valor);
    if (Number.isNaN(numero)) return '—';
    return numero.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

export default function VincularDevolucionEscalonamiento({
    pendientes = [],
    aplicaciones = [],
    puedeOperar = false,
}) {
    const [devolucion, setDevolucion] = useState(null);

    return (
        <section id="devoluciones-pendientes" className="space-y-8">
            <div className="space-y-5">
                <h2 className="text-base font-black theme-text-main m-0">Devoluciones pendientes de vínculo</h2>
                <p className="text-sm theme-text-muted m-0">
                    Cada devolución se descuenta solo cuando se confirma la remisión de la compra nueva. El sistema sugiere candidatas y no elige el vínculo.
                </p>
                {pendientes.length === 0 ? (
                    <p className="theme-text-muted m-0">No hay devoluciones pendientes en el período.</p>
                ) : (
                    <div className={geliaCardClass('overflow-hidden p-0')}>
                        <div className="overflow-x-auto px-3 py-3 sm:px-4">
                        <table className="w-full text-sm min-w-[760px]">
                            <thead>
                                <tr className="text-left theme-text-muted">
                                    <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Folio</th>
                                    <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Cliente</th>
                                    <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Total</th>
                                    <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Venta original</th>
                                    <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide">Fecha</th>
                                    {puedeOperar && <th className="py-3 px-4 text-xs font-bold uppercase tracking-wide" />}
                                </tr>
                            </thead>
                            <tbody>
                                {pendientes.map((fila) => (
                                    <tr key={fila.id} className="border-t theme-border">
                                        <td className="py-3.5 px-4 theme-text-main font-semibold">{fila.folio}</td>
                                        <td className="py-3.5 px-4 theme-text-main">
                                            {fila.numero_cliente}
                                            <span className="block theme-text-muted">{fila.nombre}</span>
                                        </td>
                                        <td className="py-3.5 px-4 theme-text-main">{dinero(fila.total)}</td>
                                        <td className="py-3.5 px-4 theme-text-main">{fila.remision_original || '—'}</td>
                                        <td className="py-3.5 px-4 theme-text-main">{fila.fecha_emision || '—'}</td>
                                        {puedeOperar && (
                                            <td className="py-3.5 px-4 text-right">
                                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setDevolucion(fila)}>
                                                    Vincular
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    </div>
                )}
            </div>

            {aplicaciones.length > 0 && (
                <div className="space-y-4">
                    <h2 className="text-base font-bold theme-text-main m-0">Devoluciones vinculadas</h2>
                    <ul className="space-y-3 m-0 p-0 list-none">
                        {aplicaciones.map((fila) => (
                            <li key={fila.id} className={`${geliaCardClass('p-4')} flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4`}>
                                <p className="theme-text-main m-0">
                                    <span className="font-semibold">{fila.folio_devolucion}</span>
                                    {' '}descuenta {dinero(fila.importe)} de la remisión {fila.folio_remision}
                                    <span className="block text-sm theme-text-muted">{fila.numero_cliente} {fila.nombre}</span>
                                </p>
                                {puedeOperar && <BotonRevertir id={fila.id} />}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <ModalVinculo devolucion={devolucion} onClose={() => setDevolucion(null)} />
        </section>
    );
}

function BotonRevertir({ id }) {
    const reverso = useForm({});

    return (
        <button
            type="button"
            className={THEME_BTN_SECONDARY}
            disabled={reverso.processing}
            onClick={() => reverso.post(route('escalonamiento.aplicaciones.revertir', id))}
        >
            Revertir vínculo
        </button>
    );
}

function ModalVinculo({ devolucion, onClose }) {
    const [candidatas, setCandidatas] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [errorCarga, setErrorCarga] = useState('');
    const [todoElMes, setTodoElMes] = useState(false);
    const vinculo = useForm({
        remision_vinculada_id: '',
        evidencia: '',
    });

    useEffect(() => {
        if (!devolucion) return undefined;
        let activo = true;
        setCandidatas([]);
        setErrorCarga('');
        setTodoElMes(false);
        setCargando(true);
        vinculo.setData({ remision_vinculada_id: '', evidencia: '' });
        window.axios.get(route('escalonamiento.devoluciones.candidatas', devolucion.id))
            .then((respuesta) => {
                if (!activo) return;
                const filas = respuesta.data?.candidatas || [];
                setCandidatas(filas);
                const mismoDia = filas.filter((fila) => fila.mismo_dia && fila.puede_recibir);
                if (mismoDia.length === 1) {
                    vinculo.setData('remision_vinculada_id', String(mismoDia[0].id));
                }
            })
            .catch((error) => {
                if (!activo) return;
                setErrorCarga(error.response?.data?.message || 'No se pudieron consultar las remisiones candidatas.');
            })
            .finally(() => {
                if (activo) setCargando(false);
            });

        return () => {
            activo = false;
        };
    }, [devolucion?.id]);

    const visibles = useMemo(() => {
        if (todoElMes) return candidatas;
        const delDia = candidatas.filter((fila) => fila.mismo_dia);
        return delDia.length > 0 ? delDia : candidatas;
    }, [candidatas, todoElMes]);

    const enviar = (event) => {
        event.preventDefault();
        vinculo.post(route('escalonamiento.devoluciones.vincular', devolucion.id), {
            onSuccess: () => onClose?.(),
        });
    };

    return (
        <EscalonamientoModal
            abierto={Boolean(devolucion)}
            onClose={onClose}
            labelledBy="titulo-vincular-devolucion"
            maxWidth="max-w-3xl"
        >
            <form onSubmit={enviar} className="p-5 sm:p-6 md:p-8 space-y-6">
                <h2 id="titulo-vincular-devolucion" className="text-xl font-black theme-text-main m-0">Confirmar vínculo</h2>
                <p className="text-sm theme-text-muted m-0">
                    Devolución {devolucion?.folio} por {dinero(devolucion?.total)}.
                    La venta original {devolucion?.remision_original || 'no indicada'} no se usa como compra nueva.
                    Hay que elegir la remisión y registrar la evidencia.
                </p>
                <label className="flex items-center gap-3 text-sm theme-text-main py-1">
                    <input type="checkbox" checked={todoElMes} onChange={(event) => setTodoElMes(event.target.checked)} />
                    Ver remisiones de otros días del mismo mes
                </label>
                {cargando && <p className="text-sm theme-text-muted m-0">Consultando candidatas…</p>}
                {errorCarga && <p className="text-sm theme-text-peligro m-0">{errorCarga}</p>}
                {!cargando && candidatas.length > 0 && !candidatas.some((fila) => fila.mismo_dia) && !todoElMes && (
                    <p className="text-sm theme-text-muted m-0">No hay candidatas del mismo día. Se muestran las remisiones del resto del mes.</p>
                )}
                {!cargando && visibles.length === 0 && (
                    <p className="text-sm theme-text-muted m-0">No hay una remisión activa con compra computable en el período.</p>
                )}
                <div className="space-y-3 max-h-72 overflow-y-auto pr-1">
                    {visibles.map((fila) => (
                        <label key={fila.id} className="flex gap-3 border theme-border theme-element rounded-xl p-4 text-sm theme-text-main cursor-pointer transition-colors hover:border-[var(--color-primario)]">
                            <input
                                type="radio"
                                name="remision_vinculada_id"
                                value={fila.id}
                                checked={String(vinculo.data.remision_vinculada_id) === String(fila.id)}
                                disabled={!fila.puede_recibir}
                                onChange={() => vinculo.setData('remision_vinculada_id', String(fila.id))}
                            />
                            <span className="min-w-0 flex-1 leading-relaxed">
                                <span className="font-semibold">{fila.folio}</span>
                                {' '}· {fila.fecha_emision}{fila.mismo_dia ? ' · mismo día' : ''}
                                <span className="block theme-text-muted">
                                    Total {dinero(fila.total)} · ya asignado {dinero(fila.suma_devoluciones_activas)}
                                    {fila.puede_recibir ? '' : ' · sin capacidad para esta devolución'}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                {vinculo.errors.remision_vinculada_id && (
                    <p className="text-sm theme-text-peligro m-0">{vinculo.errors.remision_vinculada_id}</p>
                )}
                <label className="block space-y-2">
                    <span className={THEME_LABEL}>Evidencia del vínculo</span>
                    <textarea
                        className={THEME_INPUT}
                        rows={3}
                        value={vinculo.data.evidencia}
                        onChange={(event) => vinculo.setData('evidencia', event.target.value)}
                        placeholder="Referencia o motivo de la compra que absorbe la devolución"
                    />
                </label>
                {vinculo.errors.evidencia && <p className="text-sm theme-text-peligro m-0">{vinculo.errors.evidencia}</p>}
                <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-1">
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={onClose}>Cerrar</button>
                    <button type="submit" className={THEME_BTN_PRIMARY} disabled={vinculo.processing || !vinculo.data.remision_vinculada_id}>
                        Confirmar vínculo
                    </button>
                </div>
            </form>
        </EscalonamientoModal>
    );
}
