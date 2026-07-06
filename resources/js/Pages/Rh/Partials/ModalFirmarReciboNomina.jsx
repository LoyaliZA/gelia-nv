import React, { useRef, useState, useMemo, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Check, RotateCcw, X } from 'lucide-react';
import FirmaCanvas from '../../../Components/Rh/FirmaCanvas';
import FilaDesgloseExpandible from './FilaDesgloseExpandible';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import {
    THEME_BTN_ICON,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';

function combinarDeducciones(desglose) {
    if (!desglose) return [];

    return [
        ...(desglose.incidencias || []).map((item) => ({ ...item, negativo: true })),
        ...(desglose.faltas_retardos || []).map((item) => ({ ...item, negativo: true })),
        ...(desglose.prestamos_periodo || []).map((item) => ({ ...item, negativo: true })),
    ].sort((a, b) => (a.fecha || '').localeCompare(b.fecha || ''));
}

function detalleEstatico(fila, fechaInicio, fechaFin) {
    const col = fila.colaborador || {};
    const dias = fila.dias_en_rango ?? '—';

    return {
        colaborador: [
            { label: 'Folio', value: col.folio || '—' },
            { label: 'Departamento', value: col.departamento?.nombre || '—' },
            { label: 'Área', value: col.area?.nombre || '—' },
        ],
        periodo: [
            { label: 'Inicio', value: fechaInicio },
            { label: 'Fin', value: fechaFin },
            { label: 'Días en rango', value: dias },
            { label: 'Días periodo config.', value: fila.dias_periodo_config ?? '—' },
        ],
        salario: [
            { label: 'Salario diario', value: formatoMoneda(fila.salario_diario) },
            { label: 'Días aplicados', value: dias },
            { label: 'Salario mensual base', value: formatoMoneda(fila.salario_mensual) },
            { label: 'Cálculo', value: `${formatoMoneda(fila.salario_diario)} × ${dias} días` },
        ],
        bonos: [
            { label: 'Bono puntualidad diario', value: formatoMoneda(fila.bono_puntualidad_diario) },
            { label: 'Bono productividad diario', value: formatoMoneda(fila.bono_productividad_diario) },
            { label: 'Bono punt. mensual', value: formatoMoneda(fila.bono_puntualidad_mensual) },
            { label: 'Bono prod. mensual', value: formatoMoneda(fila.bono_productividad_mensual) },
            { label: 'Cálculo', value: `(${formatoMoneda(fila.bono_puntualidad_diario)} + ${formatoMoneda(fila.bono_productividad_diario)}) × ${dias} días` },
        ],
    };
}

export default function ModalFirmarReciboNomina({
    abierto,
    onCerrar,
    fila,
    fechaInicio,
    fechaFin,
    orientacion = 'portrait',
    onFirmado,
}) {
    const firmaRef = useRef(null);
    const [errorFirma, setErrorFirma] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [desglose, setDesglose] = useState(null);
    const [cargandoDesglose, setCargandoDesglose] = useState(false);

    const estatico = useMemo(
        () => (fila ? detalleEstatico(fila, fechaInicio, fechaFin) : null),
        [fila, fechaInicio, fechaFin],
    );

    useEffect(() => {
        if (!abierto || !fila?.colaborador?.id) {
            setDesglose(null);
            return;
        }

        const controller = new AbortController();
        setCargandoDesglose(true);

        const params = new URLSearchParams({
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
        });

        fetch(`${route('rh.periodo_pago.recibo_nomina.desglose', fila.colaborador.id)}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((res) => {
                if (!res.ok) throw new Error('No se pudo cargar el desglose');
                return res.json();
            })
            .then(setDesglose)
            .catch((err) => {
                if (err.name !== 'AbortError') setDesglose(null);
            })
            .finally(() => setCargandoDesglose(false));

        return () => controller.abort();
    }, [abierto, fila?.colaborador?.id, fechaInicio, fechaFin]);

    const movimientos = useMemo(() => {
        if (!desglose) return { horasExtra: [], deducciones: [], salidas: [], prestamosActivos: [], neto: [] };

        const horasExtra = (desglose.horas_extra || []).map((item) => ({
            fecha: item.fecha,
            folio: item.folio,
            concepto: item.concepto,
            monto: item.monto,
            extra: item.detalle,
            negativo: false,
        }));

        const deducciones = combinarDeducciones(desglose);

        const salidas = (desglose.salidas || []).map((item) => ({
            fecha: item.fecha,
            folio: item.folio,
            concepto: item.concepto,
            monto: item.monto,
            extra: item.minutos ? `${item.minutos} min ausente` : null,
            negativo: true,
        }));

        const prestamosActivos = (desglose.prestamos_activos || []).map((item) => ({
            fecha: '',
            folio: item.folio,
            concepto: item.concepto,
            monto: item.monto,
            extra: `Cuota · pagos ${item.pagos}`,
            negativo: false,
        }));

        const totales = desglose.totales || {};
        const neto = [
            { fecha: '', folio: '', concepto: 'Salario + bonos del periodo', monto: (fila?.salario_rango_estimado || 0) + (fila?.bonos_rango_estimado || 0), negativo: false },
            { fecha: '', folio: '', concepto: 'Horas extra', monto: totales.horas_extra || 0, negativo: false },
            { fecha: '', folio: '', concepto: 'Deducciones (incidencias, faltas, préstamos)', monto: totales.deducciones - (totales.salidas || 0), negativo: true },
            { fecha: '', folio: '', concepto: 'Salidas personales', monto: totales.salidas || 0, negativo: true },
        ].filter((item) => item.monto > 0);

        return { horasExtra, deducciones, salidas, prestamosActivos, neto };
    }, [desglose, fila]);

    if (!abierto || !fila || !estatico || typeof document === 'undefined') return null;

    const colaborador = fila.colaborador;
    const nombre = nombreCompletoColaborador(colaborador);

    const confirmar = (e) => {
        e.preventDefault();
        const firma = firmaRef.current?.getDataUrl();
        if (!firma) {
            setErrorFirma('Capture la firma del colaborador para continuar.');
            return;
        }

        setProcesando(true);
        setErrorFirma('');

        router.post(
            route('rh.periodo_pago.recibo_nomina.firmar', colaborador.id),
            {
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                orientacion,
                firma_colaborador_data: firma,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onFirmado?.(fila);
                    onCerrar();
                },
                onError: () => setErrorFirma('No se pudo guardar la firma. Intente de nuevo.'),
                onFinish: () => setProcesando(false),
            },
        );
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-3 p-5 md:p-6 border-b theme-border">
                    <h2 className="text-sm md:text-base font-black uppercase tracking-widest theme-text-main m-0">
                        Firmar recibo de nómina
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={confirmar}>
                    <div className="gelia-modal-body p-5 md:p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <p className="text-sm theme-text-muted m-0">
                            Revise el desglose del periodo (toque cada concepto para ver incidencias, salidas y horas extra con fecha) y capture la firma.
                        </p>

                        <div className="rounded-2xl border theme-border p-4">
                            <FilaDesgloseExpandible label="Colaborador" value={nombre} detalle={estatico.colaborador} />
                            <FilaDesgloseExpandible label="Periodo" value={`${fechaInicio} — ${fechaFin}`} detalle={estatico.periodo} />
                            <FilaDesgloseExpandible label="Salario estimado" value={formatoMoneda(fila.salario_rango_estimado)} detalle={estatico.salario} />
                            <FilaDesgloseExpandible label="Bonos estimados" value={formatoMoneda(fila.bonos_rango_estimado)} detalle={estatico.bonos} />
                            <FilaDesgloseExpandible
                                label="Horas extra"
                                value={formatoMoneda(fila.horas_extra_total)}
                                movimientos={movimientos.horasExtra}
                                cargando={cargandoDesglose}
                            />
                            <FilaDesgloseExpandible
                                label="Deducciones"
                                value={`-${formatoMoneda(fila.deducciones_total)}`}
                                movimientos={movimientos.deducciones}
                                cargando={cargandoDesglose}
                            />
                            <FilaDesgloseExpandible
                                label="Salidas personales"
                                value={`-${formatoMoneda(fila.salidas_deduccion_total || 0)}`}
                                movimientos={movimientos.salidas}
                                cargando={cargandoDesglose}
                            />
                            <FilaDesgloseExpandible
                                label="Préstamos activos"
                                value={formatoMoneda(fila.prestamos_activos_cuota || 0)}
                                movimientos={movimientos.prestamosActivos}
                                cargando={cargandoDesglose}
                            />
                            <FilaDesgloseExpandible
                                label="Neto estimado"
                                value={formatoMoneda(fila.neto_estimado)}
                                movimientos={movimientos.neto}
                                destacado
                            />
                        </div>

                        <div>
                            <p className={THEME_LABEL}>Firma del colaborador *</p>
                            <FirmaCanvas ref={firmaRef} label="Dibuje la firma en el recuadro" height={200} />
                            {errorFirma && <p className="text-red-500 text-[10px] font-bold mt-2 mb-0">{errorFirma}</p>}
                        </div>
                    </div>

                    <div className="gelia-modal-footer p-5 md:p-6 flex gap-3">
                        <button
                            type="button"
                            onClick={() => firmaRef.current?.clear()}
                            className={`${THEME_BTN_SECONDARY} flex-1 min-h-[44px] inline-flex items-center justify-center gap-2`}
                            disabled={procesando}
                        >
                            <RotateCcw className="w-4 h-4" /> Limpiar
                        </button>
                        <button
                            type="submit"
                            className={`${THEME_BTN_PRIMARY} flex-1 min-h-[44px] inline-flex items-center justify-center gap-2`}
                            disabled={procesando}
                        >
                            <Check className="w-4 h-4" /> {procesando ? 'Guardando…' : 'Confirmar firma'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}
