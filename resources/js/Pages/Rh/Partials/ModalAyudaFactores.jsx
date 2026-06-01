import React, { useState } from 'react';
import { X, HelpCircle, AlertTriangle, Clock, Coins, TrendingDown, ChevronDown, ChevronRight, Calculator, Info } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, geliaCardClass } from '../../../utils/geliaTheme';

// ─── Datos de cada sección de ayuda ──────────────────────────────────────────
const SECCIONES = [
    {
        id: 'base_salarial',
        icono: Calculator,
        color: 'text-sky-500',
        bgColor: 'bg-sky-500/10',
        titulo: 'Derivaciones de Salario Base',
        subtitulo: 'La raíz de todos los cálculos',
        descripcion: 'Antes de calcular cualquier deducción o pago, el sistema descompone el salario quincenal en tarifas más pequeñas. Esto ocurre automáticamente al guardar o actualizar un colaborador.',
        formulas: [
            {
                nombre: 'Salario Diario',
                formula: 'Salario Base ÷ Días del Periodo',
                ejemplo: '$9,000 ÷ 15 días = $600 / día',
                nota: 'El periodo lo configura la encargada en Configuración → Días Periodo Pago.',
            },
            {
                nombre: 'Salario por Hora',
                formula: 'Salario Diario ÷ Horas Laboradas Oficiales',
                ejemplo: '$600 ÷ 8 hrs = $75.00 / hora',
                nota: 'Las horas oficiales las define el perfil del colaborador.',
            },
            {
                nombre: 'Salario por Minuto',
                formula: 'Salario Diario ÷ (Horas Oficiales × 60)',
                ejemplo: '$600 ÷ 480 min = $1.25 / minuto',
                nota: 'Este valor se usa exclusivamente para calcular Salidas Personales.',
            },
        ],
        alerta: null,
    },
    {
        id: 'factor_multiplicador',
        icono: AlertTriangle,
        color: 'text-red-500',
        bgColor: 'bg-red-500/10',
        titulo: 'Factor Multiplicador',
        subtitulo: 'Deducciones e Incidencias',
        descripcion: 'Al registrar una deducción o incidencia, el supervisor puede ajustar su peso con un factor numérico. Sirve para aplicar penalizaciones proporcionales sin crear nuevas reglas.',
        formulas: [
            {
                nombre: 'Cobro Fijo / Producto / Bono',
                formula: 'Monto Base × Factor',
                ejemplo: 'Regla $500 × 0.5 = $250 (media falta)',
                nota: 'El Monto Base es el valor definido en la regla (monto fijo, costo del producto, o valor del bono del colaborador).',
            },
            {
                nombre: 'Faltas y Retardos (Nómina)',
                formula: 'Salario Diario × Factor\n+ Bono Puntualidad × Factor Penaliz. Puntualidad × Factor\n+ Bono Productividad × Factor Penaliz. Productividad × Factor',
                ejemplo: 'Falta completa (Factor=1.0): $600 + $200 + $150 = $950\nMedia falta (Factor=0.5): $300 + $100 + $75 = $475',
                nota: 'Los factores de penalización de bonos son configurables por regla en el catálogo. El factor del supervisor va encima de ellos.',
            },
        ],
        tabla: {
            cabeceras: ['Factor', 'Equivale a', 'Ejemplo ($500 base)'],
            filas: [
                ['0.25', 'Cuarto de falta', '$125'],
                ['0.5', 'Media falta', '$250'],
                ['1.0', 'Falta completa', '$500'],
                ['2.0', 'Doble penalización', '$1,000'],
            ],
        },
        alerta: '⚠️ El sistema no establece un tope máximo al factor. Use valores altos con precaución.',
    },
    {
        id: 'horas_extra',
        icono: Clock,
        color: 'text-amber-500',
        bgColor: 'bg-amber-500/10',
        titulo: 'Multiplicador de Horas Extra',
        subtitulo: 'Percepciones Adicionales',
        descripcion: 'El pago de horas extra no es calculado por el supervisor manualmente: el sistema lo determina automáticamente según la hora de salida real vs. la hora oficial del colaborador, aplicando un multiplicador global.',
        formulas: [
            {
                nombre: 'Tiempo Extra (con gracia)',
                formula: 'Minutos trabajados desde (Salida Oficial + Minutos de Gracia)',
                ejemplo: 'Salida oficial 18:00 + 30 min gracia → Extras desde 18:30',
                nota: 'Los minutos de gracia se configuran globalmente. El tiempo antes del umbral de gracia NO se cuenta.',
            },
            {
                nombre: 'Horas a Pagar (redondeo por bloque)',
                formula: 'floor((Minutos Extra − Mínimo) ÷ Mínimo) + 1',
                ejemplo: 'Con mínimo=60 min: 75 min → 1 hr | 125 min → 2 hrs',
                nota: 'Solo se pagan horas completas, no fracciones. Si no se alcanza el mínimo, el pago es $0.',
            },
            {
                nombre: 'Pago Total',
                formula: 'Horas a Pagar × Multiplicador × Tarifa por Hora',
                ejemplo: '3 hrs × 2.0 × $39 = $234',
                nota: 'Si "Tarifa Fija" está activa en Configuración, se usa ese monto fijo. Si no, se usa el salario/hora del colaborador.',
            },
        ],
        alerta: null,
    },
    {
        id: 'salidas_personales',
        icono: TrendingDown,
        color: 'text-indigo-500',
        bgColor: 'bg-indigo-500/10',
        titulo: 'Descuento por Salida Personal',
        subtitulo: 'Ausencias Temporales en Turno',
        descripcion: 'Las salidas personales no usan un factor configurable. El descuento es directo: se cobra exactamente el salario correspondiente a los minutos que el colaborador estuvo fuera.',
        formulas: [
            {
                nombre: 'Monto a Descontar',
                formula: 'Minutos Ausente × Salario por Minuto',
                ejemplo: '45 min × $1.25 = $56 (redondeado a entero)',
                nota: 'El resultado final se redondea al peso entero más cercano. El salario por minuto se toma del perfil calculado del colaborador en el momento del registro.',
            },
        ],
        alerta: null,
    },
];

// ─── Subcomponente: Sección de Ayuda ─────────────────────────────────────────
function SeccionAyuda({ seccion }) {
    const [abierta, setAbierta] = useState(false);
    const Icono = seccion.icono;

    return (
        <div className="border theme-border rounded-2xl overflow-hidden">
            {/* Header de la sección — siempre visible */}
            <button
                type="button"
                onClick={() => setAbierta(!abierta)}
                className="w-full flex items-center gap-4 p-4 text-left hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
            >
                <div className={`p-2.5 rounded-xl ${seccion.bgColor} shrink-0`}>
                    <Icono className={`w-5 h-5 ${seccion.color}`} />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-black uppercase tracking-widest theme-text-main">{seccion.titulo}</p>
                    <p className="text-[10px] theme-text-muted">{seccion.subtitulo}</p>
                </div>
                {abierta
                    ? <ChevronDown className="w-4 h-4 theme-text-muted shrink-0" />
                    : <ChevronRight className="w-4 h-4 theme-text-muted shrink-0" />}
            </button>

            {/* Contenido desplegable */}
            {abierta && (
                <div className="px-4 pb-5 space-y-4 border-t theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                    {/* Descripción */}
                    <p className="text-xs theme-text-muted pt-4 leading-relaxed">{seccion.descripcion}</p>

                    {/* Fórmulas */}
                    {seccion.formulas.map((f, i) => (
                        <div key={i} className="rounded-xl border theme-border overflow-hidden">
                            <div className="px-4 py-2.5 bg-black/5 dark:bg-white/5 border-b theme-border">
                                <p className="text-[10px] font-black uppercase tracking-wider theme-text-main">{f.nombre}</p>
                            </div>
                            <div className="px-4 py-3 space-y-2">
                                <div className="font-mono text-[11px] text-emerald-500 bg-emerald-500/5 px-3 py-2 rounded-lg whitespace-pre-line">
                                    = {f.formula}
                                </div>
                                <div className="text-[10px] theme-text-muted bg-black/5 dark:bg-white/5 px-3 py-2 rounded-lg whitespace-pre-line">
                                    <span className="font-bold">Ej:</span> {f.ejemplo}
                                </div>
                                {f.nota && (
                                    <div className="flex items-start gap-2 text-[10px] theme-text-muted">
                                        <Info className="w-3 h-3 mt-0.5 shrink-0 text-sky-500" />
                                        {f.nota}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}

                    {/* Tabla de referencia rápida (solo para factor_multiplicador) */}
                    {seccion.tabla && (
                        <div className="rounded-xl border theme-border overflow-hidden">
                            <div className="px-4 py-2.5 bg-black/5 dark:bg-white/5 border-b theme-border">
                                <p className="text-[10px] font-black uppercase tracking-wider theme-text-main">Referencia Rápida</p>
                            </div>
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b theme-border">
                                        {seccion.tabla.cabeceras.map((h) => (
                                            <th key={h} className="px-4 py-2 text-left text-[9px] font-black uppercase tracking-wider theme-text-muted">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {seccion.tabla.filas.map((fila, i) => (
                                        <tr key={i} className="border-b theme-border last:border-0">
                                            {fila.map((celda, j) => (
                                                <td key={j} className={`px-4 py-2.5 text-xs ${j === 0 ? 'font-black theme-text-main font-mono' : 'theme-text-muted'}`}>
                                                    {celda}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Alerta de advertencia */}
                    {seccion.alerta && (
                        <div className="flex items-start gap-2 text-[10px] text-amber-600 dark:text-amber-400 bg-amber-500/10 px-3 py-2.5 rounded-xl border border-amber-500/20">
                            <AlertTriangle className="w-3.5 h-3.5 mt-0.5 shrink-0" />
                            {seccion.alerta}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ─── Componente principal exportado ──────────────────────────────────────────
export default function ModalAyudaFactores({ className = '' }) {
    const [abierto, setAbierto] = useState(false);

    return (
        <>
            {/* Botón de activación */}
            <button
                type="button"
                onClick={() => setAbierto(true)}
                title="Guía de cálculos y factores"
                className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main border theme-border theme-element transition-all hover:border-current ${className}`}
            >
                <HelpCircle className="w-3.5 h-3.5" />
                Guía de Cálculos
            </button>

            {/* Modal */}
            {abierto && (
                <div
                    className={THEME_MODAL_OVERLAY}
                    style={{ zIndex: 9999 }}
                    onClick={(e) => { if (e.target === e.currentTarget) setAbierto(false); }}
                >
                    <div
                        className={`${THEME_MODAL_SHELL} flex flex-col`}
                        style={{ maxWidth: '680px', width: '95vw', maxHeight: '88vh' }}
                    >
                        {/* Header del modal */}
                        <div className="flex items-start justify-between gap-4 p-6 border-b theme-border shrink-0">
                            <div className="flex items-center gap-3">
                                <div className="p-2.5 rounded-2xl bg-sky-500/10">
                                    <HelpCircle className="w-6 h-6 text-sky-500" />
                                </div>
                                <div>
                                    <h2 className="text-lg font-black uppercase tracking-tighter theme-text-main">
                                        Guía de Cálculos RH
                                    </h2>
                                    <p className="text-[10px] theme-text-muted uppercase tracking-widest mt-0.5">
                                        Cómo funciona cada factor del sistema
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setAbierto(false)}
                                className="p-2 rounded-xl theme-element border theme-border hover:theme-text-main theme-text-muted transition-colors shrink-0"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Intro */}
                        <div className="px-6 pt-5 pb-1 shrink-0">
                            <div className="flex items-start gap-3 bg-sky-500/5 border border-sky-500/20 rounded-2xl p-4">
                                <Info className="w-4 h-4 text-sky-500 mt-0.5 shrink-0" />
                                <p className="text-[11px] text-sky-600 dark:text-sky-400 leading-relaxed">
                                    Todos los valores monetarios se calculan automáticamente. Esta guía explica la lógica detrás de cada número para que puedas verificarlos o explicarlos a los colaboradores.
                                </p>
                            </div>
                        </div>

                        {/* Secciones con scroll */}
                        <div className="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                            {SECCIONES.map((seccion) => (
                                <SeccionAyuda key={seccion.id} seccion={seccion} />
                            ))}

                            {/* Flujo general */}
                            <div className={geliaCardClass('p-5')}>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-4">Flujo General del Cálculo</p>
                                <div className="flex flex-wrap items-center gap-2 text-[10px] font-bold">
                                    {[
                                        { texto: 'Salario Base', color: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' },
                                        { texto: '÷ Días Periodo', color: 'theme-text-muted' },
                                        { texto: 'Salario Diario', color: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' },
                                        { texto: '× Factor / Regla', color: 'theme-text-muted' },
                                        { texto: 'Deducción Final', color: 'bg-red-500/10 text-red-600 dark:text-red-400' },
                                    ].map((item, i) => (
                                        <span key={i} className={`px-3 py-1.5 rounded-xl ${item.color}`}>{item.texto}</span>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 text-[10px] font-bold mt-3">
                                    {[
                                        { texto: 'Horas Extra (reales)', color: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
                                        { texto: '× Multiplicador', color: 'theme-text-muted' },
                                        { texto: '× Tarifa / Hora', color: 'theme-text-muted' },
                                        { texto: 'Pago HE', color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
                                    ].map((item, i) => (
                                        <span key={i} className={`px-3 py-1.5 rounded-xl ${item.color}`}>{item.texto}</span>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 text-[10px] font-bold mt-3">
                                    {[
                                        { texto: 'Minutos Ausente', color: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' },
                                        { texto: '× Salario / Minuto', color: 'theme-text-muted' },
                                        { texto: 'Desc. Salida Personal', color: 'bg-red-500/10 text-red-600 dark:text-red-400' },
                                    ].map((item, i) => (
                                        <span key={i} className={`px-3 py-1.5 rounded-xl ${item.color}`}>{item.texto}</span>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="px-6 py-4 border-t theme-border shrink-0 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setAbierto(false)}
                                className="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white transition-all hover:opacity-90"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
