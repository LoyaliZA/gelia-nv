import React, { useEffect, useId, useRef } from 'react';
import { AlertTriangle, FileSpreadsheet, RotateCcw } from 'lucide-react';
import { EstadoBadge } from './GeliaAiEstado';
import {
    ESTADO_PROPUESTA,
    formatearHora,
    formatearNumero,
    textoConfirmar,
} from './geliaAiEstados';

/**
 * Tarjeta para revisar y confirmar una propuesta del servidor.
 * El botón evita dobles clics en la interfaz; el consumo único lo garantiza el servidor.
 */
export default function PropuestaAccionCard({
    propuesta,
    estado = 'pendiente',
    mensaje = '',
    bloqueado = false,
    onConfirmar,
    onCancelar,
    onPrepararDeNuevo,
}) {
    const tituloId = useId();
    const info = ESTADO_PROPUESTA[estado] || ESTADO_PROPUESTA.pendiente;
    const detalle = propuesta?.detalle || {};
    const archivos = Array.isArray(detalle.archivos) ? detalle.archivos : [];
    const columnas = Array.isArray(detalle.columnas) ? detalle.columnas : [];
    const advertencias = Array.isArray(propuesta?.advertencias) ? propuesta.advertencias : [];
    const activa = ['pendiente', 'ejecutando', 'cancelando'].includes(estado);
    const ocupada = estado !== 'pendiente' || bloqueado;
    const hora = formatearHora(propuesta?.expira_en);
    const enviado = useRef(false);

    useEffect(() => {
        enviado.current = false;
    }, [estado]);

    const una = (fn, permitido = true) => () => {
        if (ocupada || !permitido || enviado.current) return;
        enviado.current = true;
        fn?.();
    };
    const confirmar = una(onConfirmar, Boolean(propuesta?.puede));
    const cancelar = una(onCancelar);

    return (
        <section className="gelia-ai-card" data-estado={estado} aria-labelledby={tituloId} aria-busy={estado === 'ejecutando' || estado === 'cancelando'}>
            <div className="gelia-ai-card-head">
                <EstadoBadge etiqueta={info.etiqueta} tono={info.tono} icono={info.icono} />
            </div>
            <h3 id={tituloId} className="gelia-ai-card-title">
                {detalle.operacion || propuesta?.resumen_corto || 'Acción propuesta'}
            </h3>

            {(detalle.almacen || archivos.length > 0 || columnas.length > 0) && (
                <dl className="gelia-ai-dl">
                    {detalle.almacen && (
                        <div className="gelia-ai-dl-row">
                            <dt>Almacén destino</dt>
                            <dd>{detalle.almacen}</dd>
                        </div>
                    )}
                    {archivos.length > 0 && (
                        <div className="gelia-ai-dl-row">
                            <dt>{archivos.length === 1 ? 'Archivo' : 'Archivos'}</dt>
                            <dd>
                                <ul className="gelia-ai-card-files">
                                    {archivos.map((a) => (
                                        <li key={`${a.rol || ''}-${a.nombre}`}>
                                            <FileSpreadsheet className="w-3.5 h-3.5 shrink-0" aria-hidden />
                                            <span className="min-w-0 break-words">
                                                {a.rol ? `${a.rol}: ` : ''}
                                                <span className="font-semibold">{a.nombre}</span>
                                                {a.filas ? ` · ≈${formatearNumero(a.filas)} filas` : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </dd>
                        </div>
                    )}
                    {columnas.length > 0 && (
                        <div className="gelia-ai-dl-row">
                            <dt>Columnas</dt>
                            <dd>
                                {columnas.map((c) => `${c.campo}: «${c.columna}»`).join(' · ')}
                            </dd>
                        </div>
                    )}
                </dl>
            )}

            {!detalle.operacion && !detalle.almacen && propuesta?.resumen_corto && (
                <p className="gelia-ai-card-text">{propuesta.resumen_corto}</p>
            )}

            {advertencias.length > 0 && (
                <ul className="gelia-ai-card-warn" aria-label="Advertencias">
                    {advertencias.map((adv) => (
                        <li key={adv}>
                            <AlertTriangle className="w-3.5 h-3.5 shrink-0" aria-hidden />
                            <span>{adv}</span>
                        </li>
                    ))}
                </ul>
            )}

            {activa ? (
                <p className="gelia-ai-card-note">
                    <strong>Aún no se ha ejecutado.</strong>{' '}
                    {detalle.efecto || 'Revisa los datos antes de confirmar.'}
                    {hora && estado === 'pendiente' ? ` Puedes confirmarla hasta las ${hora}${hora.endsWith('.') ? '' : '.'}` : ''}
                </p>
            ) : (
                mensaje && <p className="gelia-ai-card-text">{mensaje}</p>
            )}

            {activa && !propuesta?.puede && (
                <p className="gelia-ai-card-note" data-tono="aviso">
                    Tu cuenta no tiene permiso para esta acción. Pide acceso a una persona administradora.
                </p>
            )}

            {activa && (
                <div className="gelia-ai-card-actions">
                    <button
                        type="button"
                        className="gelia-ai-btn gelia-ai-btn--primary"
                        disabled={ocupada || !propuesta?.puede}
                        onClick={confirmar}
                    >
                        {estado === 'ejecutando' ? 'Ejecutando…' : textoConfirmar(propuesta)}
                    </button>
                    <button
                        type="button"
                        className="gelia-ai-btn"
                        disabled={ocupada}
                        onClick={cancelar}
                    >
                        {estado === 'cancelando' ? 'Cancelando…' : 'Cancelar propuesta'}
                    </button>
                </div>
            )}

            {info.recuperable && onPrepararDeNuevo && (
                <div className="gelia-ai-card-actions">
                    <button type="button" className="gelia-ai-btn" disabled={bloqueado} onClick={onPrepararDeNuevo}>
                        <RotateCcw className="w-3.5 h-3.5" aria-hidden />
                        Preparar de nuevo
                    </button>
                </div>
            )}
        </section>
    );
}
