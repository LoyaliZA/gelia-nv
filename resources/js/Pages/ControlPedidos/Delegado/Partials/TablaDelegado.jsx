import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Check, Upload, Eye, Trash2 } from 'lucide-react';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import { geliaCardClass, THEME_INPUT } from '../../../../utils/geliaTheme';
import {
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    BTN_PRIMARY,
    BTN_SECONDARY,
    guiaPdfDe,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

function CampoAsignarGuia({ pedido, compact = false }) {
    const [guia, setGuia] = useState('');
    const [procesando, setProcesando] = useState(false);

    const guardar = (e) => {
        e?.preventDefault?.();
        const valor = guia.trim();
        if (!valor || procesando) return;

        setProcesando(true);
        router.post(
            route('control_pedidos.delegado.asignar_guia', pedido.id),
            { numero_rastreo: valor },
            {
                preserveScroll: true,
                only: PROPS_LISTADO,
                onFinish: () => setProcesando(false),
            }
        );
    };

    const inputClass = `${THEME_INPUT} ${compact ? 'py-2.5 text-xs' : 'py-3 text-sm'} font-bold font-mono w-full min-w-[10rem]`;

    return (
        <form onSubmit={guardar} className={`flex ${compact ? 'flex-col' : 'flex-row'} gap-2 items-stretch`}>
            <input
                type="text"
                value={guia}
                onChange={(e) => setGuia(e.target.value)}
                placeholder="Número de guía"
                className={inputClass}
                disabled={procesando}
                aria-label={`Guía para pedido ${pedido.folio_remision || pedido.id}`}
            />
            <button
                type="submit"
                disabled={procesando || !guia.trim()}
                className={`${BTN_PRIMARY} flex items-center justify-center gap-1.5 text-[10px] outline-none disabled:opacity-50 shrink-0 ${compact ? 'w-full' : 'px-4'}`}
            >
                <Check className="w-3.5 h-3.5" />
                {procesando ? 'Guardando…' : 'Asignar'}
            </button>
        </form>
    );
}

function CampoActualizarGuia({ pedido, compact = false }) {
    const [guia, setGuia] = useState(pedido.numero_rastreo || '');
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        setGuia(pedido.numero_rastreo || '');
    }, [pedido.id, pedido.numero_rastreo]);

    const guardar = (e) => {
        e?.preventDefault?.();
        const valor = guia.trim();
        if (!valor || procesando || valor === pedido.numero_rastreo) return;

        setProcesando(true);
        router.post(
            route('control_pedidos.delegado.actualizar_guia', pedido.id),
            { numero_rastreo: valor },
            {
                preserveScroll: true,
                only: PROPS_LISTADO,
                onFinish: () => setProcesando(false),
            }
        );
    };

    const inputClass = `${THEME_INPUT} ${compact ? 'py-2.5 text-xs' : 'py-3 text-sm'} font-bold font-mono w-full min-w-[10rem]`;

    return (
        <form onSubmit={guardar} className={`flex ${compact ? 'flex-col' : 'flex-row'} gap-2 items-stretch`}>
            <input
                type="text"
                value={guia}
                onChange={(e) => setGuia(e.target.value)}
                placeholder="Número de guía"
                className={inputClass}
                disabled={procesando}
                aria-label={`Corregir guía para pedido ${pedido.folio_remision || pedido.id}`}
            />
            <button
                type="submit"
                disabled={procesando || !guia.trim() || guia.trim() === pedido.numero_rastreo}
                className={`${BTN_PRIMARY} flex items-center justify-center gap-1.5 text-[10px] outline-none disabled:opacity-50 shrink-0 ${compact ? 'w-full' : 'px-4'}`}
            >
                <Check className="w-3.5 h-3.5" />
                {procesando ? 'Guardando…' : 'Corregir'}
            </button>
        </form>
    );
}

function CampoSubirGuiaPdf({ pedido, onVerPdf, compact = false }) {
    const inputRef = useRef(null);
    const [procesando, setProcesando] = useState(false);
    const guiaPdf = guiaPdfDe(pedido);

    const subir = (e) => {
        const archivo = e.target.files?.[0];
        if (!archivo) return;

        setProcesando(true);
        router.post(
            route('control_pedidos.delegado.guia_pdf.store', pedido.id),
            { guia_pdf: archivo },
            {
                forceFormData: true,
                preserveScroll: true,
                only: PROPS_LISTADO,
                onFinish: () => {
                    setProcesando(false);
                    e.target.value = '';
                },
            }
        );
    };

    const eliminar = () => {
        if (!guiaPdf || procesando) return;
        setProcesando(true);
        router.delete(route('control_pedidos.delegado.guia_pdf.destroy', pedido.id), {
            preserveScroll: true,
            only: PROPS_LISTADO,
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <div className={`space-y-2 ${compact ? '' : 'mt-2'}`}>
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    disabled={procesando}
                    className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}
                >
                    <Upload className="w-3.5 h-3.5" />
                    {guiaPdf ? 'Reemplazar PDF' : 'Subir guía PDF'}
                </button>
                {guiaPdf && (
                    <>
                        <button
                            type="button"
                            onClick={() => onVerPdf(guiaPdf)}
                            className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}
                        >
                            <Eye className="w-3.5 h-3.5" /> Ver PDF
                        </button>
                        <button
                            type="button"
                            onClick={eliminar}
                            disabled={procesando}
                            className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none text-red-500`}
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                        </button>
                    </>
                )}
            </div>
            {guiaPdf && (
                <p className="text-[9px] font-bold theme-text-muted m-0 truncate">{guiaPdf.nombre_original}</p>
            )}
            <input ref={inputRef} type="file" accept=".pdf,application/pdf" className="hidden" onChange={subir} />
        </div>
    );
}

function FilaDelegado({ pedido, onVerPdf, modo = 'asignar', compact = false }) {
    return (
        <div className="space-y-2">
            {modo === 'correccion' ? (
                <CampoActualizarGuia pedido={pedido} compact={compact} />
            ) : (
                <CampoAsignarGuia pedido={pedido} compact={compact} />
            )}
            <CampoSubirGuiaPdf pedido={pedido} onVerPdf={onVerPdf} compact={compact} />
            {pedido.guia_subida_at && (
                <p className="text-[9px] font-bold theme-text-muted m-0 font-mono">
                    Liberado: {formatearFechaHoraAuditoria(pedido.guia_subida_at)}
                </p>
            )}
        </div>
    );
}

function CardPedidoDelegado({ pedido, onVerPdf, modo }) {
    return (
        <div className={`${geliaCardClass()} p-4 space-y-3`}>
            <EncabezadoFolioPedido pedido={pedido} size="sm" />
            <div className="grid grid-cols-2 gap-2 text-[10px] font-bold theme-text-muted">
                <span>ID: {pedido.id}</span>
                <span>{formatearFechaNegocio(pedido.fecha)}</span>
                <span className="col-span-2 normal-case">{pedido.cliente?.nombre || '—'}</span>
                <span className="uppercase">{pedido.paqueteria?.nombre || '—'}</span>
                <span>{pedido.vendedor?.name || '—'}</span>
            </div>
            <FilaDelegado pedido={pedido} onVerPdf={onVerPdf} modo={modo} compact />
        </div>
    );
}

export default function TablaDelegado({ pedidos, modo = 'asignar' }) {
    const [docPreview, setDocPreview] = useState(null);
    const items = pedidos?.data || [];
    const vacio = modo === 'correccion'
        ? 'No hay pedidos pendientes de corrección de guía_'
        : 'No hay pedidos pendientes de guía_';

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                {vacio}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="md:hidden space-y-3">
                {items.map((pedido) => (
                    <CardPedidoDelegado key={pedido.id} pedido={pedido} onVerPdf={setDocPreview} modo={modo} />
                ))}
            </div>

            <div className={`${geliaCardClass()} overflow-x-auto hidden md:block`}>
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">ID</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Folio</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Cliente</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Paquetería</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Vendedora</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Fecha</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest min-w-[20rem]">Guía / PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((pedido) => (
                            <tr key={pedido.id} className="border-b theme-border last:border-0 align-top">
                                <td className="px-5 py-4 text-sm font-black theme-text-main font-mono">{pedido.id}</td>
                                <td className="px-5 py-4">
                                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                </td>
                                <td className="px-5 py-4 text-xs font-bold theme-text-main">{pedido.cliente?.nombre || '—'}</td>
                                <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.paqueteria?.nombre || '—'}</td>
                                <td className="px-5 py-4 text-xs font-bold theme-text-muted">{pedido.vendedor?.name || '—'}</td>
                                <td className="px-5 py-4 text-[10px] font-bold theme-text-muted">{formatearFechaNegocio(pedido.fecha)}</td>
                                <td className="px-5 py-4 min-w-[20rem]">
                                    <FilaDelegado pedido={pedido} onVerPdf={setDocPreview} modo={modo} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {pedidos?.links && <GeliaPaginacion paginator={pedidos} />}
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </div>
    );
}
