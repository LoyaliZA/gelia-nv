import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { GELIA_BTN_OUTLINE, GELIA_FIELDSET_LEGEND, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';
import CampoBaseRegla from './CampoBaseRegla';
import CondicionesRegla from './CondicionesRegla';
import DestinoRedondeoRegla from './DestinoRedondeoRegla';
import MuestraCalculo from './MuestraCalculo';
import OperacionRegla from './OperacionRegla';
import { definicionVacia, serializarDefinicion } from './reglaFormUtils';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function EditorCalculoPanel({
    abierto,
    onCerrar,
    seleccion,
    metadatos,
    permisos,
    reglaInicial = null,
    onSimular = null,
}) {
    const [form, setForm] = useState(() => definicionVacia(metadatos));
    const [guardarComoRegla, setGuardarComoRegla] = useState(false);
    const [nombreRegla, setNombreRegla] = useState('');
    const [descripcionRegla, setDescripcionRegla] = useState('');
    const [preview, setPreview] = useState(null);
    const [busy, setBusy] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [sucio, setSucio] = useState(false);
    const [erroresCampo, setErroresCampo] = useState({});

    const listas = metadatos?.listas || [];
    const puedeAdministrar = !!permisos?.reglas_administrar;
    const puedeVerCosto = !!permisos?.precios_ver;

    const jsonHeaders = useMemo(() => ({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    }), []);

    useEffect(() => {
        if (!abierto) {
            return;
        }
        if (reglaInicial?.version_actual?.definicion) {
            const def = reglaInicial.version_actual.definicion;
            setForm({
                ...definicionVacia(metadatos),
                ...def,
                condiciones: def.condiciones || [],
            });
            setNombreRegla(reglaInicial.nombre || '');
            setDescripcionRegla(reglaInicial.descripcion || '');
            setGuardarComoRegla(true);
        } else {
            setForm(definicionVacia(metadatos));
            setNombreRegla('');
            setDescripcionRegla('');
            setGuardarComoRegla(false);
        }
        setPreview(null);
        setMensaje(null);
        setErroresCampo({});
        setSucio(false);
    }, [abierto, reglaInicial, metadatos]);

    useEffect(() => {
        if (!abierto || !sucio) {
            return undefined;
        }
        const handler = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [abierto, sucio]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onKey = (e) => {
            if (e.key === 'Escape') cerrar();
        };
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = 'unset';
            window.removeEventListener('keydown', onKey);
        };
    }, [abierto]); // eslint-disable-line react-hooks/exhaustive-deps

    const patchForm = useCallback((patch) => {
        setForm((prev) => ({ ...prev, ...patch }));
        setSucio(true);
        setPreview(null);
    }, []);

    const cerrar = () => {
        if (sucio && !window.confirm('Hay cambios sin guardar. ¿Descartar el formulario?')) {
            return;
        }
        onCerrar();
    };

    const simularSeleccion = async () => {
        if (!seleccion?.selection_id || !onSimular) {
            setMensaje('No hay selección activa.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        setErroresCampo({});
        try {
            await onSimular({
                selection_id: seleccion.selection_id,
                selection_version: seleccion.version,
                definicion: serializarDefinicion(form),
            });
            setSucio(false);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const previsualizar = async () => {
        if (!seleccion?.selection_id) {
            setMensaje('No hay selección activa.');
            return;
        }
        setBusy(true);
        setMensaje(null);
        setErroresCampo({});
        try {
            const res = await fetch(route('tiendanube.precios.reglas.previsualizar'), {
                method: 'POST',
                headers: jsonHeaders,
                body: JSON.stringify({
                    selection_id: seleccion.selection_id,
                    definicion: serializarDefinicion(form),
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'No se pudo generar la muestra.');
            }
            setPreview(data);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    const guardar = async () => {
        if (!puedeAdministrar) {
            setMensaje('No tiene permiso para guardar reglas.');
            return;
        }
        if (guardarComoRegla && !nombreRegla.trim()) {
            setErroresCampo({ nombre: 'Indique un nombre para la regla.' });
            return;
        }
        if (!guardarComoRegla) {
            await previsualizar();
            return;
        }

        setBusy(true);
        setMensaje(null);
        setErroresCampo({});
        try {
            const payload = {
                nombre: nombreRegla.trim(),
                descripcion: descripcionRegla.trim() || null,
                definicion: serializarDefinicion(form),
            };
            const esEdicion = reglaInicial?.id;
            const url = esEdicion
                ? route('tiendanube.precios.reglas.update', reglaInicial.id)
                : route('tiendanube.precios.reglas.store');
            const res = await fetch(url, {
                method: esEdicion ? 'PUT' : 'POST',
                headers: jsonHeaders,
                body: JSON.stringify(esEdicion ? { ...payload, version: reglaInicial.version } : payload),
            });
            const data = await res.json();
            if (!res.ok) {
                if (data.codigo === 'conflicto') {
                    throw new Error('La regla fue modificada por otra persona. Recargue e intente de nuevo.');
                }
                throw new Error(data.message || 'No se pudo guardar la regla.');
            }
            setMensaje('Regla guardada correctamente.');
            setSucio(false);
        } catch (e) {
            setMensaje(e.message);
        } finally {
            setBusy(false);
        }
    };

    if (!abierto) {
        return null;
    }

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} !items-stretch !justify-end !p-0`}
            role="presentation"
            onClick={cerrar}
        >
            <aside
                className="relative h-full w-full md:max-w-2xl theme-surface border-l theme-border shadow-2xl overflow-y-auto animate-slide-in-right"
                role="dialog"
                aria-modal="true"
                aria-labelledby="tn-editor-calculo"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="sticky top-0 z-10 theme-surface border-b theme-border px-4 py-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 id="tn-editor-calculo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Configurar cálculo
                        </h2>
                        <p className="text-xs theme-text-muted m-0 mt-1">
                            {seleccion?.total_productos || 0} productos · {seleccion?.total_variantes || 0} variantes seleccionadas
                        </p>
                    </div>
                    <button type="button" onClick={cerrar} className="p-2 rounded-lg border theme-border" aria-label="Cerrar editor">
                        <X size={16} />
                    </button>
                </div>

                <div className="p-4 space-y-5">
                    <p className="text-xs theme-text-muted m-0">
                        Ejemplo: Precio normal &lt; 100 → reducir 6 % desde precio normal → precio promocional.
                    </p>

                    <CondicionesRegla
                        condiciones={form.condiciones}
                        metadatos={metadatos}
                        listas={listas}
                        onChange={(next) => patchForm({ condiciones: next })}
                    />

                    <CampoBaseRegla
                        base={form.base}
                        baseListaId={form.base_lista_id}
                        metadatos={metadatos}
                        listas={listas}
                        onChange={patchForm}
                    />

                    <OperacionRegla
                        operacion={form.operacion}
                        parametro={form.parametro}
                        metadatos={metadatos}
                        onChange={patchForm}
                    />

                    <DestinoRedondeoRegla
                        destino={form.destino}
                        redondeo={form.redondeo}
                        redondeoDireccion={form.redondeo_direccion}
                        metadatos={metadatos}
                        onChange={patchForm}
                    />

                    {puedeAdministrar && (
                        <fieldset className="space-y-2 border theme-border rounded-xl p-3">
                            <legend className={GELIA_FIELDSET_LEGEND}>
                                Biblioteca (opcional)
                            </legend>
                            <label className="flex items-center gap-2 text-sm theme-text-main">
                                <input
                                    type="checkbox"
                                    checked={guardarComoRegla}
                                    onChange={(e) => setGuardarComoRegla(e.target.checked)}
                                />
                                Guardar como regla reutilizable
                            </label>
                            {guardarComoRegla && (
                                <>
                                    <label className="block text-xs theme-text-main">
                                        Nombre
                                        <input
                                            type="text"
                                            className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                            value={nombreRegla}
                                            onChange={(e) => { setNombreRegla(e.target.value); setSucio(true); }}
                                            aria-invalid={!!erroresCampo.nombre}
                                        />
                                    </label>
                                    {erroresCampo.nombre && (
                                        <p className="text-xs text-red-600 m-0" role="alert">{erroresCampo.nombre}</p>
                                    )}
                                    <label className="block text-xs theme-text-main">
                                        Descripción (opcional)
                                        <textarea
                                            className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                                            rows={2}
                                            value={descripcionRegla}
                                            onChange={(e) => { setDescripcionRegla(e.target.value); setSucio(true); }}
                                        />
                                    </label>
                                </>
                            )}
                        </fieldset>
                    )}

                    <MuestraCalculo preview={preview} metadatos={metadatos} puedeVerCosto={puedeVerCosto} />

                    {mensaje && <p className="text-sm theme-text-main m-0" role="status">{mensaje}</p>}

                    <div className="flex flex-wrap gap-2 pb-4">
                        {onSimular && seleccion?.selection_id && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={simularSeleccion}
                                className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Simular selección
                            </button>
                        )}
                        <button
                            type="button"
                            disabled={busy}
                            onClick={previsualizar}
                            className={GELIA_BTN_OUTLINE}
                        >
                            Previsualizar muestra
                        </button>
                        {puedeAdministrar && guardarComoRegla && (
                            <button
                                type="button"
                                disabled={busy}
                                onClick={guardar}
                                className="px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Guardar regla
                            </button>
                        )}
                    </div>
                </div>
            </aside>
        </div>,
        document.body,
    );
}
