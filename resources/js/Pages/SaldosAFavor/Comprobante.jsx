import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Printer, Receipt, RefreshCw, PenLine } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    BTN_BACK,
    BTN_PRIMARY,
    BTN_SECONDARY,
    FLASH_OK,
    THEME_LABEL,
    fmtMoneda,
} from './Partials/safStyles';

export default function Comprobante({ auth, comprobante, encabezado }) {
    const { flash } = usePage().props;
    const perfil = comprobante.perfil_impresion || '80mm';
    const [evidencia, setEvidencia] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const logo = encabezado?.logos?.[0];
    const marca = logo?.alt
        || (encabezado?.mostrar_bellaroma ? 'Bellaroma' : (encabezado?.mostrar_aromas ? 'Aromas' : 'GELIA'));

    const firmar = (e) => {
        e.preventDefault();
        setEnviando(true);
        router.post(route('saldos_favor.caja.firmar', comprobante.id), {
            evidencia_firmada: evidencia,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setEnviando(false),
        });
    };

    const urlImprimir = (opts = {}) => route('saldos_favor.caja.imprimir', {
        comprobante: comprobante.id,
        perfil,
        ...opts,
    });

    const urlDescargar = () => route('saldos_favor.caja.descargar', {
        comprobante: comprobante.id,
        perfil,
    });

    return (
        <AppLayout auth={auth}>
            <Head title={comprobante.folio} />
            <GeliaPageShell className="space-y-6">
                <div>
                    <Link
                        href={route('saldos_favor.caja.index', { cliente_id: comprobante.cliente_id })}
                        className={BTN_BACK}
                    >
                        <ArrowLeft className="w-3.5 h-3.5" /> Caja
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow={comprobante.estado}
                    title={comprobante.folio}
                    description="Comprobante de aplicación de saldo a favor"
                    icon={Receipt}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}

                <div className={geliaCardClass('p-5 max-w-md mx-auto space-y-2.5 text-sm theme-text-main')}>
                    {comprobante.es_reimpresion && (
                        <div className="text-center text-[10px] font-medium tracking-[0.2em] uppercase theme-text-muted">REIMPRESIÓN</div>
                    )}
                    <div className="flex flex-col items-center gap-1">
                        {logo?.base64 ? (
                            <img
                                src={`data:image/png;base64,${logo.base64}`}
                                alt={marca}
                                className="max-h-28 w-auto max-w-[75%] object-contain"
                            />
                        ) : (
                            <div className="text-center font-medium uppercase tracking-tight text-base">{marca}</div>
                        )}
                        <div className="text-center text-[10px] font-medium uppercase tracking-widest theme-text-muted">
                            Aplicación de saldo a favor
                        </div>
                    </div>
                    <hr className="border-theme-border theme-border !my-2" />
                    <div>
                        <span className="theme-text-muted font-medium">Cliente:</span> {comprobante.cliente?.nombre}
                        {' '}
                        <span className="theme-text-muted">#{comprobante.cliente?.numero_cliente}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="theme-text-muted font-medium">Saldo anterior</span>
                        <span className="font-medium tabular-nums">{fmtMoneda(comprobante.saldo_anterior)}</span>
                    </div>
                    <div className="pt-1 text-[10px] font-medium uppercase tracking-widest theme-text-muted">
                        Saldos utilizados ({(comprobante.creditos_detalle || []).length})
                    </div>
                    {(comprobante.creditos_detalle || []).map((item, idx) => (
                        <div key={idx} className="flex justify-between gap-2 py-0.5">
                            <span className="min-w-0 break-words font-medium">
                                {item.folio}{item.canal_origen ? ` · ${item.canal_origen}` : ''}
                            </span>
                            <span className="shrink-0 tabular-nums font-medium">{fmtMoneda(item.monto)}</span>
                        </div>
                    ))}
                    <hr className="border-theme-border theme-border !my-2" />
                    <div className="flex justify-between font-medium text-base">
                        <span>Total usado</span>
                        <span className="tabular-nums" style={{ color: 'var(--color-primario)' }}>{fmtMoneda(comprobante.monto_aplicado)}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="theme-text-muted font-medium">Saldo restante</span>
                        <span className="font-medium tabular-nums">{fmtMoneda(comprobante.saldo_restante)}</span>
                    </div>
                    <div className="text-[10px] font-medium uppercase tracking-wide theme-text-muted pt-1">
                        Aplicó: {comprobante.generado_por?.name}
                    </div>
                    {comprobante.ruta_evidencia_firmada && (
                        <a
                            href={`/storage/${comprobante.ruta_evidencia_firmada}`}
                            target="_blank"
                            rel="noreferrer"
                            className="block text-xs font-medium underline"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            Ver evidencia firmada
                        </a>
                    )}
                </div>

                <div className="flex flex-wrap justify-center gap-2">
                    <a
                        href={urlImprimir()}
                        target="_blank"
                        rel="noreferrer"
                        className={`${BTN_PRIMARY} inline-flex items-center gap-2`}
                    >
                        <Printer className="w-4 h-4" /> Imprimir
                    </a>
                    <a
                        href={urlDescargar()}
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                    >
                        <Download className="w-4 h-4" /> Descargar
                    </a>
                    <a
                        href={urlImprimir({ reimpresion: 1 })}
                        target="_blank"
                        rel="noreferrer"
                        className={BTN_SECONDARY}
                    >
                        <RefreshCw className="w-4 h-4" /> Reimprimir
                    </a>
                </div>

                {comprobante.estado === 'pendiente_firma' && (
                    <form onSubmit={firmar} className={`${geliaCardClass('p-4 max-w-md mx-auto space-y-3')}`}>
                        <label className={THEME_LABEL}>Evidencia firmada (opcional)</label>
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            className="block w-full text-sm"
                            onChange={(e) => setEvidencia(e.target.files?.[0] || null)}
                        />
                        <button type="submit" disabled={enviando} className={`${BTN_PRIMARY} w-full`}>
                            <PenLine className="w-4 h-4" /> {enviando ? 'Guardando…' : 'Marcar firmado'}
                        </button>
                    </form>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
