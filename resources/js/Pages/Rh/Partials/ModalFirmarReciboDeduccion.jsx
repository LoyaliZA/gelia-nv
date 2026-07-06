import React, { useRef, useState, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Check, RotateCcw, X } from 'lucide-react';
import FirmaCanvas from '../../../Components/Rh/FirmaCanvas';
import FilaDesgloseExpandible from './FilaDesgloseExpandible';
import { formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';
import { ESTADO_DEDUCCION_LABELS, ORIGEN_DEDUCCION_LABELS } from '../Deducciones/Partials/deduccionesStyles';
import {
    THEME_BTN_ICON,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';

function detalleDeduccion(registro) {
    const col = registro.colaborador || {};
    const monto = registro.monto_total_final ?? registro.total_deduccion;

    return {
        folio: [
            { label: 'UUID', value: registro.uuid || '—' },
            { label: 'Estado', value: ESTADO_DEDUCCION_LABELS[registro.estado_deduccion] || registro.estado_deduccion || '—' },
            { label: 'Origen', value: ORIGEN_DEDUCCION_LABELS[registro.origen_deduccion] || registro.origen_deduccion || '—' },
        ],
        colaborador: [
            { label: 'Folio colaborador', value: col.folio || '—' },
            { label: 'Departamento', value: registro.departamento_snapshot || col.departamento?.nombre || '—' },
            { label: 'Área', value: registro.area_snapshot || col.area?.nombre || '—' },
        ],
        concepto: [
            { label: 'Regla', value: registro.regla_nombre_snapshot || registro.regla_incidencia?.nombre || '—' },
            { label: 'Comportamiento', value: registro.regla_comportamiento_snapshot || '—' },
            { label: 'Descripción', value: registro.descripcion_detallada || '—' },
            { label: 'SKU producto', value: registro.producto_sku_snapshot || '—' },
        ],
        fecha: [
            { label: 'Fecha del evento', value: registro.fecha_ocurrencia?.slice?.(0, 10) || registro.fecha_ocurrencia || '—' },
            { label: 'Fecha aplicación', value: registro.fecha_aplicacion_deduccion?.slice?.(0, 10) || 'Pendiente' },
            { label: 'Fecha deducción nómina', value: registro.fecha_deduccion_nomina?.slice?.(0, 10) || '—' },
        ],
        salarioBase: [
            { label: 'Salario diario (snapshot)', value: formatoMoneda(registro.salario_diario_snapshot) },
            { label: 'Monto base deducción', value: formatoMoneda(registro.monto_deduccion_base) },
            { label: 'Factor multiplicador', value: `×${registro.factor_multiplicador ?? 1}` },
            { label: 'Aplica ded. salario', value: registro.aplica_deduccion_salario_snapshot ? 'Sí' : 'No' },
        ],
        bonoPuntualidad: [
            { label: 'Bono diario (snapshot)', value: formatoMoneda(registro.bono_puntualidad_diario_snapshot) },
            { label: 'Factor puntualidad', value: registro.factor_puntualidad_snapshot ?? '—' },
            { label: 'Monto descontado', value: formatoMoneda(registro.deduccion_bono_puntualidad) },
        ],
        bonoProductividad: [
            { label: 'Bono diario (snapshot)', value: formatoMoneda(registro.bono_productividad_diario_snapshot) },
            { label: 'Factor productividad', value: registro.factor_productividad_snapshot ?? '—' },
            { label: 'Monto descontado', value: formatoMoneda(registro.deduccion_bono_productividad) },
        ],
        total: [
            { label: 'Subtotal parcial', value: formatoMoneda(registro.total_parcial) },
            { label: 'Salario base', value: formatoMoneda(registro.deduccion_salario_base) },
            { label: 'Bono puntualidad', value: formatoMoneda(registro.deduccion_bono_puntualidad) },
            { label: 'Bono productividad', value: formatoMoneda(registro.deduccion_bono_productividad) },
            { label: 'Total final', value: `-${formatoMoneda(monto)}` },
        ],
    };
}

export default function ModalFirmarReciboDeduccion({
    abierto,
    onCerrar,
    registro,
    firmarUrl,
    onFirmado,
    requiereFirmaGerente = false,
}) {
    const firmaGerenteRef = useRef(null);
    const firmaColaboradorRef = useRef(null);
    const [errorFirma, setErrorFirma] = useState('');
    const [procesando, setProcesando] = useState(false);

    const detalle = useMemo(() => (registro ? detalleDeduccion(registro) : null), [registro]);

    if (!abierto || !registro || !firmarUrl || !detalle || typeof document === 'undefined') return null;

    const faltaGerente = requiereFirmaGerente && !registro.firma_gerente_path;
    const faltaColaborador = !registro.firma_colaborador_path;
    const monto = registro.monto_total_final ?? registro.total_deduccion;

    const confirmar = (e) => {
        e.preventDefault();
        const payload = {};

        if (faltaGerente) {
            const firmaGerente = firmaGerenteRef.current?.getDataUrl();
            if (!firmaGerente) {
                setErrorFirma('Capture la firma del gerente.');
                return;
            }
            payload.firma_gerente_data = firmaGerente;
        }

        if (faltaColaborador) {
            const firmaColaborador = firmaColaboradorRef.current?.getDataUrl();
            if (!firmaColaborador) {
                setErrorFirma('Capture la firma del colaborador.');
                return;
            }
            payload.firma_colaborador_data = firmaColaborador;
        }

        setProcesando(true);
        setErrorFirma('');

        router.post(firmarUrl, payload, {
            preserveScroll: true,
            onSuccess: () => {
                onFirmado?.(registro);
                onCerrar();
            },
            onError: () => setErrorFirma('No se pudo guardar la firma. Intente de nuevo.'),
            onFinish: () => setProcesando(false),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full modal-pop text-left`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-3 p-5 md:p-6 border-b theme-border">
                    <h2 className="text-sm md:text-base font-black uppercase tracking-widest theme-text-main m-0">
                        Firmar recibo de incidencia
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={confirmar}>
                    <div className="gelia-modal-body p-5 md:p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <p className="text-sm theme-text-muted m-0">
                            Revise el desglose de la deducción (toque cada concepto para ampliar) y capture las firmas pendientes.
                        </p>

                        <div className="rounded-2xl border theme-border p-4">
                            <FilaDesgloseExpandible label="Folio" value={registro.folio} detalle={detalle.folio} />
                            <FilaDesgloseExpandible label="Colaborador" value={nombreCompletoColaborador(registro.colaborador)} detalle={detalle.colaborador} />
                            <FilaDesgloseExpandible label="Concepto" value={registro.regla_nombre_snapshot || '—'} detalle={detalle.concepto} />
                            <FilaDesgloseExpandible
                                label="Fecha"
                                value={registro.fecha_ocurrencia?.slice?.(0, 10) || registro.fecha_ocurrencia || '—'}
                                detalle={detalle.fecha}
                            />
                            <FilaDesgloseExpandible label="Salario base" value={formatoMoneda(registro.deduccion_salario_base)} detalle={detalle.salarioBase} />
                            <FilaDesgloseExpandible label="Bono puntualidad" value={formatoMoneda(registro.deduccion_bono_puntualidad)} detalle={detalle.bonoPuntualidad} />
                            <FilaDesgloseExpandible label="Bono productividad" value={formatoMoneda(registro.deduccion_bono_productividad)} detalle={detalle.bonoProductividad} />
                            <FilaDesgloseExpandible label="Total a descontar" value={`-${formatoMoneda(monto)}`} detalle={detalle.total} destacado />
                        </div>

                        {faltaGerente && (
                            <div>
                                <p className={THEME_LABEL}>Firma del gerente *</p>
                                <FirmaCanvas ref={firmaGerenteRef} label="Dibuje la firma del gerente" height={180} />
                            </div>
                        )}

                        {faltaColaborador && (
                            <div>
                                <p className={THEME_LABEL}>Firma del colaborador *</p>
                                <FirmaCanvas ref={firmaColaboradorRef} label="Dibuje la firma del colaborador" height={180} />
                            </div>
                        )}

                        {errorFirma && <p className="text-red-500 text-[10px] font-bold m-0">{errorFirma}</p>}
                    </div>

                    <div className="gelia-modal-footer p-5 md:p-6 flex gap-3">
                        <button
                            type="button"
                            onClick={() => {
                                firmaGerenteRef.current?.clear();
                                firmaColaboradorRef.current?.clear();
                            }}
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
