import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Clock, Loader2, X } from 'lucide-react';
import {
    THEME_BTN_ICON,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    deferModalAction,
} from '../../../../utils/geliaTheme';

const DIAS = [
    { iso: 1, etiqueta: 'Lun' },
    { iso: 2, etiqueta: 'Mar' },
    { iso: 3, etiqueta: 'Mié' },
    { iso: 4, etiqueta: 'Jue' },
    { iso: 5, etiqueta: 'Vie' },
    { iso: 6, etiqueta: 'Sáb' },
    { iso: 7, etiqueta: 'Dom' },
];

function valoresDesde(horario) {
    const dias = Array.isArray(horario?.dias_operacion) && horario.dias_operacion.length > 0
        ? horario.dias_operacion.map(Number)
        : [1, 2, 3, 4, 5, 6];

    return {
        horaApertura: horario?.hora_apertura_semana || horario?.hora_apertura || '',
        horaCierre: horario?.hora_cierre_semana || horario?.hora_cierre || '19:00',
        zonaHoraria: horario?.zona_horaria || '',
        diasOperacion: dias,
        horaAperturaSabado: horario?.hora_apertura_sabado || '',
        horaCierreSabado: horario?.hora_cierre_sabado || '',
    };
}

function toggleDia(dias, iso) {
    const actual = dias.includes(iso)
        ? dias.filter((dia) => dia !== iso)
        : [...dias, iso];

    return [...new Set(actual)].sort((a, b) => a - b);
}

export default function ModalHorarioOperativoPdv({
    abierto,
    horario,
    cargando = false,
    onCerrar,
    onGuardar,
}) {
    const [valores, setValores] = useState(() => valoresDesde(horario));

    useEffect(() => {
        if (abierto) {
            setValores(valoresDesde(horario));
        }
    }, [abierto, horario]);

    if (!abierto) return null;

    const operaSabado = valores.diasOperacion.includes(6);

    const cerrar = (event) => {
        event?.stopPropagation?.();
        deferModalAction(onCerrar);
    };

    const guardar = () => {
        onGuardar({
            hora_apertura: valores.horaApertura || null,
            hora_cierre: valores.horaCierre,
            zona_horaria: valores.zonaHoraria || null,
            dias_operacion: valores.diasOperacion,
            hora_apertura_sabado: operaSabado ? (valores.horaAperturaSabado || null) : null,
            hora_cierre_sabado: operaSabado ? (valores.horaCierreSabado || null) : null,
        });
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 15)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full max-h-[min(90vh,720px)] flex flex-col overflow-hidden modal-pop text-left`}
                onClick={(event) => event.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="pdv-horario-operativo-titulo"
            >
                <div className="flex items-start justify-between gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <div className="flex items-start gap-3 min-w-0">
                        <Clock className="w-5 h-5 shrink-0 mt-0.5 theme-text-primario" aria-hidden />
                        <div className="min-w-0">
                            <h2 id="pdv-horario-operativo-titulo" className="text-base font-black uppercase theme-text-main m-0">
                                Horario operativo
                            </h2>
                            <p className="text-xs theme-text-muted m-0 mt-1">
                                Días que opera la sucursal, horario entre semana y, si aplica, el del sábado.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className={`${THEME_BTN_ICON} theme-element border theme-border shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center`}
                        onClick={cerrar}
                        aria-label="Cerrar horario operativo"
                    >
                        <X className="w-5 h-5" aria-hidden />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-5">
                    <fieldset>
                        <legend className={`${THEME_LABEL} px-0`}>Días que opera</legend>
                        <div className="flex flex-wrap gap-2 mt-2">
                            {DIAS.map((dia) => {
                                const activo = valores.diasOperacion.includes(dia.iso);
                                return (
                                    <button
                                        key={dia.iso}
                                        type="button"
                                        aria-pressed={activo}
                                        onClick={() => setValores((actual) => ({
                                            ...actual,
                                            diasOperacion: toggleDia(actual.diasOperacion, dia.iso),
                                        }))}
                                        className={`min-h-11 min-w-11 px-3 rounded-xl border theme-border text-sm font-semibold ${activo ? 'theme-text-main' : 'theme-text-muted'}`}
                                        style={activo ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 18%, transparent)' } : undefined}
                                    >
                                        {dia.etiqueta}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label className="space-y-1">
                            <span className={THEME_LABEL}>Apertura</span>
                            <input
                                type="time"
                                className={THEME_INPUT}
                                value={valores.horaApertura}
                                onChange={(event) => setValores((actual) => ({ ...actual, horaApertura: event.target.value }))}
                            />
                        </label>
                        <label className="space-y-1">
                            <span className={THEME_LABEL}>Cierre</span>
                            <input
                                type="time"
                                className={THEME_INPUT}
                                value={valores.horaCierre}
                                onChange={(event) => setValores((actual) => ({ ...actual, horaCierre: event.target.value }))}
                            />
                        </label>
                    </div>

                    {operaSabado && (
                        <div className="space-y-3">
                            <p className="text-xs font-black uppercase tracking-widest theme-text-main m-0">Sábado</p>
                            <p className="text-xs theme-text-muted m-0">
                                Si se deja vacío, el sábado usa el mismo horario que el resto de los días.
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label className="space-y-1">
                                    <span className={THEME_LABEL}>Apertura sábado</span>
                                    <input
                                        type="time"
                                        className={THEME_INPUT}
                                        value={valores.horaAperturaSabado}
                                        onChange={(event) => setValores((actual) => ({ ...actual, horaAperturaSabado: event.target.value }))}
                                    />
                                </label>
                                <label className="space-y-1">
                                    <span className={THEME_LABEL}>Cierre sábado</span>
                                    <input
                                        type="time"
                                        className={THEME_INPUT}
                                        value={valores.horaCierreSabado}
                                        onChange={(event) => setValores((actual) => ({ ...actual, horaCierreSabado: event.target.value }))}
                                    />
                                </label>
                            </div>
                        </div>
                    )}

                    <label className="space-y-1">
                        <span className={THEME_LABEL}>Zona horaria</span>
                        <input
                            type="text"
                            className={THEME_INPUT}
                            value={valores.zonaHoraria}
                            placeholder="America/Mexico_City"
                            onChange={(event) => setValores((actual) => ({ ...actual, zonaHoraria: event.target.value }))}
                        />
                    </label>

                    {!horario?.configurado && (
                        <p className="text-xs font-semibold theme-text-aviso m-0">
                            Sin horario persistido; al guardar se usará el valor provisional. Deja apertura vacía para no restringir altas.
                        </p>
                    )}
                </div>

                <div className="flex justify-end gap-2 p-5 md:p-6 border-t theme-border shrink-0">
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando || valores.diasOperacion.length === 0 || !valores.horaCierre}
                        onClick={guardar}
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Clock className="w-4 h-4" aria-hidden />}
                        Guardar horario
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
