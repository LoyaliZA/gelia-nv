import React, { useState } from 'react';
import axios from 'axios';
import { FileText, IdCard, Phone, Printer, UserRound } from 'lucide-react';
import { BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';

const LEYENDA = { PAGADO: 'Pagado', POR_COBRAR: 'Por cobrar' };
const ERROR_GENERICA = 'No se pudo generar la carátula. Intente de nuevo; si continúa, avise a Sistemas.';
const BTN = `${BTN_SECONDARY} inline-flex items-center justify-center gap-2 min-h-[44px] w-full sm:w-auto disabled:opacity-50`;
const BTN_PRINCIPAL = `${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] w-full sm:w-auto disabled:opacity-50`;

export function imprimirPdf(url) {
    const iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.src = url;
    iframe.onload = () => {
        try {
            iframe.contentWindow?.focus();
            iframe.contentWindow?.print();
        } catch {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    };
    document.body.appendChild(iframe);
}

async function mensajeError(error) {
    const data = error?.response?.data;
    if (data instanceof Blob) {
        const texto = await data.text();
        try {
            return JSON.parse(texto).message || ERROR_GENERICA;
        } catch {
            return texto || ERROR_GENERICA;
        }
    }
    return data?.message || ERROR_GENERICA;
}

function Campo({ icon: Icono, label, value }) {
    return (
        <div>
            <p className="text-xs font-semibold theme-text-muted m-0 inline-flex items-center gap-1.5">
                <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                {label}
            </p>
            <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value || '—'}</p>
        </div>
    );
}

export default function SeccionCaratulaMunicipalCedis({ pedido, onVer }) {
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(null);
    const caratula = pedido?.caratula_municipal || null;
    const expediente = pedido?.solicitud_preparacion?.expediente || {};
    const finanzas = pedido?.solicitud_preparacion?.finanzas_municipales || {};
    const nombre = caratula?.nombre || expediente.nombre || '';
    const telefono = caratula?.telefono || expediente.telefono || '';
    const leyendaClave = caratula?.leyenda || finanzas.leyenda_terminal;
    const tieneConsignatario = caratula?.tiene_consignatario ?? Boolean(nombre);

    const abrir = async (variante) => {
        setError(null);
        if (!tieneConsignatario) {
            setError('El pedido no tiene un consignatario vigente.');
            return;
        }
        if (variante === 'con_identificacion' && caratula && caratula.tiene_identificacion === false) {
            setError('Este consignatario no tiene archivo de identificación.');
            return;
        }
        setCargando(variante);
        try {
            const url = `${route('control_pedidos.cedis.caratula_municipal', pedido.id)}?variante=${variante}`;
            const respuesta = await axios.get(url, {
                responseType: 'blob',
                headers: { Accept: 'application/pdf' },
            });
            const tipo = String(respuesta.headers['content-type'] || '');
            if (!tipo.includes('pdf')) {
                const texto = await respuesta.data.text();
                let mensaje = ERROR_GENERICA;
                try {
                    mensaje = JSON.parse(texto).message || mensaje;
                } catch {
                    mensaje = texto || mensaje;
                }
                setError(mensaje);
                return;
            }
            const blobUrl = URL.createObjectURL(respuesta.data);
            onVer?.({
                url: blobUrl,
                nombre_original: variante === 'con_identificacion'
                    ? 'Carátula con identificación.pdf'
                    : 'Carátula sin identificación.pdf',
                mime_type: 'application/pdf',
                tipo: 'caratula_municipal',
            });
        } catch (err) {
            setError(await mensajeError(err));
        } finally {
            setCargando(null);
        }
    };

    return (
        <div className="mt-4 space-y-4" aria-label="Carátula municipal">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Campo icon={UserRound} label="Consignatario" value={nombre} />
                <Campo icon={Phone} label="Teléfono" value={telefono} />
                <Campo icon={FileText} label="Condición de terminal" value={LEYENDA[leyendaClave] || leyendaClave} />
            </div>
            {!tieneConsignatario && (
                <p className="text-sm theme-text-muted m-0">
                    Este pedido no tiene un consignatario vigente. No se puede emitir la carátula.
                </p>
            )}
            <div className="flex flex-col sm:flex-row sm:flex-wrap gap-2">
                <button
                    type="button"
                    className={BTN_PRINCIPAL}
                    disabled={Boolean(cargando)}
                    onClick={() => abrir('con_identificacion')}
                >
                    <IdCard className="w-4 h-4 shrink-0" aria-hidden="true" />
                    {cargando === 'con_identificacion' ? 'Preparando carátula…' : 'Ver carátula con identificación'}
                </button>
                <button
                    type="button"
                    className={BTN}
                    disabled={Boolean(cargando)}
                    onClick={() => abrir('sin_identificacion')}
                >
                    <FileText className="w-4 h-4 shrink-0" aria-hidden="true" />
                    {cargando === 'sin_identificacion' ? 'Preparando carátula…' : 'Ver carátula sin identificación'}
                </button>
            </div>
            {error && (
                <p role="alert" className="text-sm font-semibold m-0" style={{ color: 'var(--color-peligro)' }}>
                    {error}
                </p>
            )}
        </div>
    );
}

export function AccionImprimirCaratula({ documento }) {
    if (!documento?.url) return null;
    return (
        <button
            type="button"
            className={`${BTN_SECONDARY} min-h-[44px] inline-flex items-center justify-center gap-2`}
            onClick={() => imprimirPdf(documento.url)}
        >
            <Printer className="w-4 h-4 shrink-0" aria-hidden="true" />
            Imprimir
        </button>
    );
}
