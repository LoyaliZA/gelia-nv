import React from 'react';
import { Head } from '@inertiajs/react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { LABELS_ESTADO_SOLICITUD } from '../../ControlPedidos/Partials/DireccionPedidoResumen';

const LABELS_PUBLICOS = {
    ...LABELS_ESTADO_SOLICITUD,
    pending: 'En revisión',
};

function labelEstadoPublico(estado) {
    return LABELS_PUBLICOS[estado] || estado || '—';
}

function mensajePorMotivo({ aplicado, ya_utilizado, enlace_invalido, motivo }) {
    if (aplicado || ya_utilizado || motivo === 'usado' || motivo === 'ok') {
        if (ya_utilizado || motivo === 'usado') {
            return {
                titulo: 'Datos ya registrados',
                texto: 'Este enlace ya recibió una respuesta y quedó cerrado. No es necesario volver a enviar. Puede cerrar esta ventana.',
            };
        }
        return {
            titulo: 'Dirección guardada',
            texto: 'Sus datos de envío quedaron registrados. El enlace se cerró automáticamente. Puede cerrar esta ventana.',
        };
    }

    const mapa = {
        sin_token: 'Para capturar una dirección necesita un enlace único enviado por su vendedora.',
        invalido: 'El enlace no es válido. Solicite uno nuevo a su vendedora.',
        expirado: 'Este enlace expiró o fue revocado. Solicite uno nuevo a su vendedora.',
        sin_accion: 'Este enlace no está autorizado. Solicite uno nuevo a su vendedora.',
    };

    return {
        titulo: 'Enlace no disponible',
        texto: mapa[motivo] || (enlace_invalido
            ? 'No se puede abrir el formulario sin un enlace válido.'
            : 'No se pudo completar la operación.'),
    };
}

export default function ConfirmacionPublica({
    folio = null,
    estado = null,
    aplicado = false,
    ya_utilizado = false,
    enlace_invalido = false,
    motivo = null,
}) {
    const { titulo, texto } = mensajePorMotivo({ aplicado, ya_utilizado, enlace_invalido, motivo });
    const esExito = aplicado || ya_utilizado || motivo === 'ok' || motivo === 'usado';

    return (
        <div
            className="min-h-screen px-4 py-16"
            style={{
                background: 'radial-gradient(ellipse at top, color-mix(in srgb, var(--color-primario) 12%, transparent), var(--color-fondo, #f4f4f5) 55%)',
            }}
        >
            <Head title={titulo} />
            <div className={`mx-auto max-w-xl ${geliaCardClass()} p-8 md:p-10 text-center`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>
                    Gelia NV
                </p>
                <h1 className="mt-3 text-3xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                    {titulo}
                </h1>
                <p className="mt-3 text-sm theme-text-muted m-0">
                    {texto}
                </p>
                {folio && !esExito && (
                    <>
                        <p className="mt-6 rounded-xl border theme-border theme-element px-4 py-3 font-mono text-lg theme-text-main m-0">
                            {folio}
                        </p>
                        {estado && (
                            <p className="mt-3 text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Estado: {labelEstadoPublico(estado)}
                            </p>
                        )}
                    </>
                )}
                {esExito && (
                    <p className="mt-8 text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Registro temporal cerrado · enlace de un solo uso
                    </p>
                )}
            </div>
        </div>
    );
}
