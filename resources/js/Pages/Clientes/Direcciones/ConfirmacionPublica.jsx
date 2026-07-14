import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
import { LABELS_ESTADO_SOLICITUD } from '../../ControlPedidos/Partials/DireccionPedidoResumen';

const LABELS_PUBLICOS = {
    ...LABELS_ESTADO_SOLICITUD,
    pending: 'En revisión',
};

function labelEstadoPublico(estado) {
    return LABELS_PUBLICOS[estado] || estado || '—';
}

export default function ConfirmacionPublica({ folio, estado }) {
    return (
        <div
            className="min-h-screen px-4 py-16"
            style={{
                background: 'radial-gradient(ellipse at top, color-mix(in srgb, var(--color-primario) 12%, transparent), var(--color-fondo, #f4f4f5) 55%)',
            }}
        >
            <Head title={`Solicitud ${folio}`} />
            <div className={`mx-auto max-w-xl ${geliaCardClass()} p-8 md:p-10 text-center`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                    Gelia NV
                </p>
                <h1 className="mt-3 text-3xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    Solicitud registrada
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    Conserve este folio para seguimiento con su vendedora o auxiliar.
                </p>
                <p className="mt-6 rounded-xl border theme-border theme-element px-4 py-3 font-mono text-lg theme-text-main m-0">
                    {folio}
                </p>
                <p className="mt-3 text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                    Estado: {labelEstadoPublico(estado)}
                </p>
                <Link href={route('direcciones.publicas.form')} className={`${THEME_BTN_SECONDARY} mt-8 inline-flex`}>
                    Registrar otra solicitud
                </Link>
            </div>
        </div>
    );
}
