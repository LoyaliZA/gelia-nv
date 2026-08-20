import React from 'react';
import { geliaCardClass } from '@/utils/geliaTheme';

/** Nodo del diagrama maestro */
function Node({ children, tone = 'main', className = '' }) {
    const tones = {
        main: {
            border: 'color-mix(in srgb, var(--color-primario) 45%, transparent)',
            bg: 'color-mix(in srgb, var(--color-primario) 10%, transparent)',
        },
        ok: { border: 'color-mix(in srgb, #16a34a 40%, transparent)', bg: 'color-mix(in srgb, #16a34a 10%, transparent)' },
        warn: { border: 'color-mix(in srgb, #d97706 40%, transparent)', bg: 'color-mix(in srgb, #d97706 10%, transparent)' },
        err: { border: 'color-mix(in srgb, #dc2626 40%, transparent)', bg: 'color-mix(in srgb, #dc2626 10%, transparent)' },
        mute: { border: 'var(--tw-border, currentColor)', bg: 'transparent' },
    };
    const t = tones[tone] || tones.main;
    return (
        <div
            className={`rounded-xl border px-3 py-2 text-[10px] font-bold leading-snug theme-text-main ${className}`}
            style={{ borderColor: t.border, backgroundColor: t.bg }}
        >
            {children}
        </div>
    );
}

function Arrow({ label }) {
    return (
        <div className="flex flex-col items-center justify-center py-1 px-1 shrink-0 text-[9px] font-black uppercase tracking-wider theme-text-muted">
            <span aria-hidden className="text-sm leading-none" style={{ color: 'var(--color-primario)' }}>↓</span>
            {label && <span className="text-center max-w-[9rem] mt-0.5 opacity-80">{label}</span>}
        </div>
    );
}

function Lane({ title, children }) {
    return (
        <div className={geliaCardClass('p-3 md:p-4 space-y-2 min-w-0')}>
            <p
                className="text-[9px] font-black uppercase tracking-[0.2em] m-0 mb-1"
                style={{ color: 'var(--color-primario)' }}
            >
                {title}
            </p>
            {children}
        </div>
    );
}

/**
 * Diagrama maestro operativo (mapeo completo del ciclo BMA).
 * Camino feliz + ramas de error / rechazo / incidencia / resguardo.
 */
export default function DiagramaMaestro() {
    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                    Diagrama maestro del flujo
                </h2>
                <p className="text-sm theme-text-muted m-0 mt-2 leading-relaxed">
                    Vista de punta a punta: qué hace cada cargo, qué provoca cada acción y adónde va el pedido
                    cuando hay rechazo o error reportado.
                </p>
            </div>

            {/* Camino feliz por escritorios */}
            <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-3">
                <Lane title="1 · Vendedora · Registrar">
                    <Node>Crear / autoguardar → <strong>BORRADOR</strong></Node>
                    <Arrow label="PDF o foto (Envío o Tienda)" />
                    <Node tone="mute">Solicitar consulta CEDIS → <strong>PESAJE_PENDIENTE</strong></Node>
                    <Arrow label="CEDIS responde (pesaje o mercancía)" />
                    <Node tone="ok"><strong>PESAJE_RESPONDIDO</strong>. Cerrar consulta → monto → pago</Node>
                    <Arrow label="Actualizar consulta si hay anexo/retiro" />
                    <Node tone="ok">Enviar → <strong>PENDIENTE_AUDITORÍA</strong></Node>
                    <p className="text-[9px] theme-text-muted m-0 pt-1 leading-relaxed">
                        Sin cierre de consulta no hay monto editable, pago ni enviar. Tienda: sin cajas/kg. Envío: cotización tras pesaje.
                    </p>
                </Lane>

                <Lane title="2 · Auxiliar · Auditar">
                    <Node>Hitos: pago en revisión → pago validado → pendiente de remisión</Node>
                    <Arrow label="Pago + remisión OK" />
                    <Node tone="ok">Aprobar → <strong>PENDIENTE_EMPAQUE</strong></Node>
                    <Arrow label="O" />
                    <Node tone="err">Rechazar + motivo → <strong>RECHAZADO</strong></Node>
                    <p className="text-[9px] theme-text-muted m-0 pt-1 leading-relaxed">
                        Rechazo / error a vendedora: ella corrige y reenvía.
                    </p>
                </Lane>

                <Lane title="3 · CEDIS · Empaque">
                    <Node>Pedido en <strong>PENDIENTE_EMPAQUE</strong></Node>
                    <Arrow label="¿Resguardo abierto?" />
                    <Node tone="warn">Sí → bloquea empaque/guía hasta liberar</Node>
                    <Arrow label="No / liberado" />
                    <Node tone="ok">Empacar (≠ enviado)</Node>
                    <Arrow label="¿Ofrece rastreo y sin guía?" />
                    <Node>Sí → <strong>PENDIENTE_DE_GUIA</strong></Node>
                    <Node tone="ok">No / ya hay guía → <strong>PENDIENTE_RECOLECCIÓN</strong></Node>
                </Lane>

                <Lane title="4 · Guías + envío">
                    <Node>Asignar Nº guía / PDF</Node>
                    <Arrow label="Post-empaque" />
                    <Node tone="ok"><strong>PENDIENTE_RECOLECCIÓN</strong></Node>
                    <Arrow label="Paquetería recogió" />
                    <Node tone="ok"><strong>ENVIADO</strong></Node>
                    <p className="text-[9px] theme-text-muted m-0 pt-1 leading-relaxed">
                        Guía pre-empaque: se puede capturar en pendiente de empaque sin cambiar fase. Overlay «Con retraso» no sustituye la fase.
                    </p>
                </Lane>
            </div>

            {/* Flujo lineal compacto */}
            <div className={geliaCardClass('p-4 md:p-5 overflow-x-auto')}>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-3">
                    Camino feliz (fases)
                </p>
                <div className="flex flex-wrap items-center gap-2 min-w-max">
                    {[
                        'BORRADOR',
                        'PESAJE_PENDIENTE',
                        'PESAJE_RESPONDIDO',
                        'PENDIENTE_AUDITORÍA',
                        'PENDIENTE_EMPAQUE',
                        'PENDIENTE_DE_GUIA*',
                        'PENDIENTE_RECOLECCIÓN',
                        'ENVIADO',
                    ].map((fase, i, arr) => (
                        <React.Fragment key={fase}>
                            <span
                                className="inline-flex px-2.5 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-wider border"
                                style={{
                                    color: 'var(--color-primario)',
                                    borderColor: 'color-mix(in srgb, var(--color-primario) 35%, transparent)',
                                    backgroundColor: 'color-mix(in srgb, var(--color-primario) 12%, transparent)',
                                }}
                            >
                                {fase}
                            </span>
                            {i < arr.length - 1 && (
                                <span className="theme-text-muted font-black text-xs" aria-hidden>→</span>
                            )}
                        </React.Fragment>
                    ))}
                </div>
                <p className="text-[10px] theme-text-muted m-0 mt-3 leading-relaxed">
                    * <strong>PENDIENTE_DE_GUIA</strong> solo si la paquetería ofrece rastreo y aún no hay número de guía.
                </p>
            </div>

            {/* Ramas */}
            <div>
                <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0 mb-3">
                    Ramas: qué provoca cada caso
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div className={geliaCardClass('p-4 space-y-2')}>
                        <Node tone="err">Rechazo auxiliar</Node>
                        <p className="text-xs theme-text-muted m-0 leading-relaxed">
                            <strong className="theme-text-main">Desde</strong> PENDIENTE_AUXILIAR →{' '}
                            <strong className="theme-text-main">RECHAZADO_VENDEDORA</strong>.
                            Limpia remisión y validación de pago. Alerta a vendedora. Ella corrige y reenvía.
                        </p>
                    </div>
                    <div className={geliaCardClass('p-4 space-y-2')}>
                        <Node tone="err">Error de datos (cola por dueño)</Node>
                        <p className="text-xs theme-text-muted m-0 leading-relaxed">
                            Prioridad <strong className="theme-text-main">vendedora → auxiliar → guías</strong>.
                            Vendedora → RECHAZADO; auxiliar → PENDIENTE_AUXILIAR; guías → PENDIENTE_DE_GUIA
                            (o EN_CEDIS si aún no empacó). Puede invalidar guía / remisión.
                        </p>
                    </div>
                    <div className={geliaCardClass('p-4 space-y-2')}>
                        <Node tone="warn">Error CEDIS / empaque</Node>
                        <p className="text-xs theme-text-muted m-0 leading-relaxed">
                            EN_CEDIS → <strong className="theme-text-main">INCIDENCIA_CEDIS</strong>.
                            Notifica auxiliar y vendedora. El pedido sigue pudiendo empacarse después.
                        </p>
                    </div>
                    <div className={geliaCardClass('p-4 space-y-2')}>
                        <Node tone="warn">Resguardo / anexo de envío</Node>
                        <p className="text-xs theme-text-muted m-0 leading-relaxed">
                            <code className="text-[10px]">es_resguardo</code> bloquea empaque y guía hasta liberar.
                            Municipio diferido sin costo → <code className="text-[10px]">pendiente_regularizacion</code> → anexar pago → revisión auxiliar.
                        </p>
                    </div>
                    <div className={geliaCardClass('p-4 space-y-2 md:col-span-2')}>
                        <Node tone="mute">Señalizar error (validación)</Node>
                        <p className="text-xs theme-text-muted m-0 leading-relaxed">
                            Campos incompletos, sin pesaje, sin pago/remisión al aprobar, resguardo al empacar, o envío sin guía cuando corresponde:
                            <strong className="theme-text-main"> no cambian fase</strong> — muestran <code className="text-[10px]">flash.error</code> / modal de alerta.
                            Reportar error / rechazo sí cambian fase y escriben historial.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
