import React from 'react';
import { Eye, Edit2, CheckCircle2, Package, Truck } from 'lucide-react';
import { geliaCardClass } from '@/utils/geliaTheme';
import UiMock from './UiMock';

const DEMO = {
    folio: 'PED-10482',
    remision: 'REM-77821',
    cliente: 'Boutique Ejemplo SA de CV',
    numero: 'C-4521',
    vendedora: 'Ana López',
    fecha: '15/07/2026',
    banco: 'BBVA',
    almacen: 'CEDIS Norte',
    origen: 'WhatsApp',
    paqueteria: 'Estafeta',
    tipoGuia: 'Terrestre',
    cajas: '2',
    peso: '3.40 kg',
    mercancia: '$4,850.00',
    envio: '$189.00',
    total: '$5,039.00',
    domicilio: 'Av. Reforma 120, Col. Centro, CP 06000, CDMX',
    telefono: '55 1234 5678',
    destinatario: 'Recepción · Boutique Ejemplo',
    guia: '285019284756',
};

function FakeField({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <p className="text-[9px] font-black uppercase tracking-wider theme-text-muted m-0 mb-1">{label}</p>
            <div className="theme-input w-full px-3 py-2.5 text-xs font-bold theme-text-main rounded-xl border theme-border pointer-events-none select-none">
                {value}
            </div>
        </div>
    );
}

function FakeBadge({ label, color = 'var(--color-primario)' }) {
    return (
        <span
            className="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border"
            style={{
                color,
                borderColor: `color-mix(in srgb, ${color} 35%, transparent)`,
                backgroundColor: `color-mix(in srgb, ${color} 18%, transparent)`,
            }}
        >
            {label}
        </span>
    );
}

/** Card de listado como en Registrar / Auditar / CEDIS (solo estética). */
export function MockCardPedido({
    fase = 'Borrador',
    faseColor = '#64748b',
    envioLabel = null,
    rechazado = false,
    conGuia = false,
    acciones = ['ver', 'editar'],
}) {
    return (
        <div className={`${geliaCardClass('p-4 space-y-3')} ${rechazado ? 'ring-1 ring-red-500/30' : ''} pointer-events-none select-none`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm font-black italic theme-text-main m-0 tracking-tight">{DEMO.folio}</p>
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">{DEMO.fecha}</p>
                    <p className="text-[9px] theme-text-muted font-bold mt-1 m-0">{DEMO.vendedora}</p>
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0">
                    <FakeBadge label={fase} color={faseColor} />
                    {envioLabel && <FakeBadge label={envioLabel} color="#d97706" />}
                </div>
            </div>
            <div>
                <p className="text-xs font-black theme-text-main uppercase m-0">{DEMO.cliente}</p>
                <p className="text-[9px] theme-text-muted m-0">{DEMO.numero}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <span>{DEMO.almacen}</span>
                <span>·</span>
                <span>{DEMO.banco}</span>
            </div>
            <p className="text-lg font-black m-0" style={{ color: 'var(--color-primario)' }}>{DEMO.total}</p>
            {conGuia && (
                <p className="text-xs font-black font-mono theme-text-main m-0">Guía: {DEMO.guia}</p>
            )}
            {rechazado && (
                <p className="text-[10px] text-red-500 font-bold m-0">
                    Motivo ejemplo: Teléfono del destinatario no corresponde al domicilio.
                </p>
            )}
            <div className="flex gap-1.5">
                {acciones.includes('ver') && (
                    <span className="p-2 theme-element border theme-border rounded-xl"><Eye className="w-4 h-4 theme-text-main" /></span>
                )}
                {acciones.includes('editar') && (
                    <span className="p-2 theme-element border theme-border rounded-xl"><Edit2 className="w-4 h-4 theme-text-main" /></span>
                )}
                {acciones.includes('aprobar') && (
                    <span className="p-2 theme-element border theme-border rounded-xl"><CheckCircle2 className="w-4 h-4 text-emerald-600" /></span>
                )}
                {acciones.includes('empacar') && (
                    <span className="p-2 theme-element border theme-border rounded-xl"><Package className="w-4 h-4 theme-text-main" /></span>
                )}
                {acciones.includes('enviar') && (
                    <span className="p-2 theme-element border theme-border rounded-xl"><Truck className="w-4 h-4 theme-text-main" /></span>
                )}
            </div>
        </div>
    );
}

/** Formulario de captura (vendedora) — sin funcionalidad, datos ficticios. */
export function MockFormPedido() {
    return (
        <UiMock
            titulo="Formulario Nuevo pedido (ejemplo)"
            anotaciones={[
                'Datos ilustrativos: no corresponden a clientes reales.',
                'Misma estructura visual que el modal de captura en Registrar pedidos.',
            ]}
        >
            <div className="space-y-5 pointer-events-none select-none">
                <div>
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-3">Datos generales</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FakeField label="Folio remisión" value={DEMO.remision} />
                        <FakeField label="Fecha" value={DEMO.fecha} />
                        <FakeField label="Cliente" value={`${DEMO.numero} — ${DEMO.cliente}`} wide />
                        <FakeField label="Origen" value={DEMO.origen} />
                        <FakeField label="Banco" value={DEMO.banco} />
                        <FakeField label="Almacén de salida" value={DEMO.almacen} />
                    </div>
                </div>
                <div>
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-3">Envío / logística</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FakeField label="Paquetería" value={DEMO.paqueteria} />
                        <FakeField label="Tipo de guía" value={DEMO.tipoGuia} />
                        <FakeField label="Peso (pesaje CEDIS)" value={DEMO.peso} />
                        <FakeField label="Nº cajas" value={DEMO.cajas} />
                        <FakeField label="Destinatario" value={DEMO.destinatario} />
                        <FakeField label="Teléfono" value={DEMO.telefono} />
                        <FakeField label="Domicilio de entrega" value={DEMO.domicilio} wide />
                    </div>
                </div>
                <div>
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-3">Montos</p>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <FakeField label="Mercancía" value={DEMO.mercancia} />
                        <FakeField label="Costo envío" value={DEMO.envio} />
                        <FakeField label="Total a cobrar" value={DEMO.total} />
                    </div>
                </div>
                <div className="flex flex-wrap gap-2 pt-1">
                    <span
                        className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-white"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Enviar a auxiliar
                    </span>
                    <span className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider theme-element border theme-border theme-text-muted">
                        Guardar borrador
                    </span>
                </div>
            </div>
        </UiMock>
    );
}

/** Vista auxiliar: revisión con pago + remisión. */
export function MockFormAuditoria() {
    return (
        <UiMock
            titulo="Auditar pedido (ejemplo)"
            anotaciones={[
                'Antes de aprobar: pago validado + remisión PDF.',
                'Rechazar pide motivo; reportar error marca campos incorrectos.',
            ]}
        >
            <div className="space-y-4 pointer-events-none select-none">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p className="text-sm font-black italic theme-text-main m-0">{DEMO.folio}</p>
                        <p className="text-[10px] theme-text-muted m-0 mt-1">{DEMO.cliente}</p>
                    </div>
                    <FakeBadge label="Pendiente Auxiliar" color="#2563eb" />
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <FakeField label="Mercancía" value={DEMO.mercancia} />
                    <FakeField label="Envío" value={DEMO.envio} />
                    <FakeField label="Total" value={DEMO.total} />
                    <FakeField label="Banco" value={DEMO.banco} />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="rounded-xl border theme-border p-3 theme-element">
                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Comprobante</p>
                        <p className="text-xs font-bold theme-text-main m-0 mt-2">comprobante_ejemplo.pdf</p>
                        <p className="text-[10px] text-emerald-600 font-bold m-0 mt-2">Pago validado ✓</p>
                    </div>
                    <div className="rounded-xl border theme-border p-3 theme-element">
                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Remisión</p>
                        <p className="text-xs font-bold theme-text-main m-0 mt-2">remision_{DEMO.remision}.pdf</p>
                        <p className="text-[10px] text-emerald-600 font-bold m-0 mt-2">Adjunta ✓</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <span className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-white bg-emerald-600">
                        Aprobar → CEDIS
                    </span>
                    <span className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-white bg-red-600">
                        Rechazar
                    </span>
                    <span className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider theme-element border theme-border theme-text-muted">
                        Reportar error datos
                    </span>
                </div>
            </div>
        </UiMock>
    );
}

/** CEDIS: empacar / enviar. */
export function MockFormCedis() {
    return (
        <UiMock
            titulo="CEDIS — detalle operativo (ejemplo)"
            anotaciones={[
                'Empacar decide PENDIENTE_DE_GUIA vs PENDIENTE_DE_ENVIO.',
                'Marcar enviado exige empaque y guía si la paquetería rastrea.',
            ]}
        >
            <div className="space-y-4 pointer-events-none select-none">
                <div className="flex flex-wrap justify-between gap-2">
                    <div>
                        <p className="text-sm font-black italic theme-text-main m-0">{DEMO.folio}</p>
                        <p className="text-[10px] theme-text-muted m-0 mt-1">{DEMO.paqueteria} · {DEMO.tipoGuia}</p>
                    </div>
                    <FakeBadge label="En CEDIS" color="#ca8a04" />
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <FakeField label="Peso" value={DEMO.peso} />
                    <FakeField label="Cajas" value={DEMO.cajas} />
                    <FakeField label="Almacén" value={DEMO.almacen} />
                </div>
                <FakeField label="Domicilio" value={DEMO.domicilio} wide />
                <div className="flex flex-wrap gap-2">
                    <span
                        className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-white"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Marcar empacado
                    </span>
                    <span className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider theme-element border theme-border theme-text-muted">
                        Reportar error
                    </span>
                </div>
            </div>
        </UiMock>
    );
}

/** Guías: asignar rastreo. */
export function MockFormGuia() {
    return (
        <UiMock
            titulo="Asignar guía (ejemplo)"
            anotaciones={[
                'Tras asignar (pedido ya empacado) pasa a PENDIENTE_DE_ENVIO.',
                'No asignar guía si el pedido está en resguardo.',
            ]}
        >
            <div className="space-y-4 pointer-events-none select-none">
                <div className="flex flex-wrap justify-between gap-2">
                    <p className="text-sm font-black italic theme-text-main m-0">{DEMO.folio}</p>
                    <FakeBadge label="Pendiente de guía" color="#7c3aed" />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FakeField label="Paquetería" value={DEMO.paqueteria} />
                    <FakeField label="Número de guía / rastreo" value={DEMO.guia} />
                </div>
                <div className="rounded-xl border border-dashed theme-border p-4 text-center">
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0">PDF de guía (ejemplo)</p>
                    <p className="text-xs font-bold theme-text-main m-0 mt-2">guia_{DEMO.guia}.pdf</p>
                </div>
                <span
                    className="inline-flex px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider text-white"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    Guardar guía
                </span>
            </div>
        </UiMock>
    );
}

/**
 * Galería de ejemplos prácticos filtrada por secciones visibles del usuario.
 */
export default function EjemplosPracticos({ idsSecciones = [] }) {
    const ids = new Set(idsSecciones);
    const show = (id) => ids.size === 0 || ids.has(id);

    return (
        <div className="space-y-8">
            <div>
                <h2 className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                    Ejemplos prácticos
                </h2>
                <p className="text-sm theme-text-muted m-0 mt-2 leading-relaxed">
                    Recreaciones estéticas de pantallas y cards del día a día. Los datos son ficticios e informativos;
                    no hay formularios reales ni acciones.
                </p>
            </div>

            {show('vendedora') && (
                <div className="space-y-4">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">Vendedora</h3>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <MockCardPedido fase="Borrador" faseColor="#64748b" />
                        <MockCardPedido fase="Rechazado" faseColor="#dc2626" rechazado acciones={['ver', 'editar']} />
                        <MockCardPedido fase="Enviado" faseColor="#16a34a" conGuia acciones={['ver']} />
                    </div>
                    <MockFormPedido />
                </div>
            )}

            {show('auxiliar') && (
                <div className="space-y-4">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">Auxiliar</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                        <MockCardPedido fase="Pendiente Auxiliar" faseColor="#2563eb" acciones={['ver', 'aprobar']} />
                        <MockCardPedido fase="En CEDIS" faseColor="#ca8a04" envioLabel="Completo" acciones={['ver']} />
                    </div>
                    <MockFormAuditoria />
                </div>
            )}

            {show('cedis') && (
                <div className="space-y-4">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">CEDIS</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                        <MockCardPedido fase="En CEDIS" faseColor="#ca8a04" acciones={['ver', 'empacar']} />
                        <MockCardPedido fase="Pendiente de envío" faseColor="#0d9488" conGuia acciones={['ver', 'enviar']} />
                    </div>
                    <MockFormCedis />
                </div>
            )}

            {show('guias') && (
                <div className="space-y-4">
                    <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">Guías</h3>
                    <div className="max-w-sm">
                        <MockCardPedido fase="Pendiente de guía" faseColor="#7c3aed" acciones={['ver']} />
                    </div>
                    <MockFormGuia />
                </div>
            )}
        </div>
    );
}
