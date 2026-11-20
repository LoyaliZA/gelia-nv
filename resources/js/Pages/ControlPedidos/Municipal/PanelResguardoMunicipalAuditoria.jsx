import React from 'react';
import { PauseCircle } from 'lucide-react';
import EstadosResguardoMunicipal from './EstadosResguardoMunicipal';
import ConjuntoRemisionesMunicipal from './ConjuntoRemisionesMunicipal';
import { MOTIVOS_RESGUARDO, textoCoberturaMercancia, textoTransporte } from './estadosResguardoMunicipal';

/** Lo que la persona auxiliar necesita ver antes de liberar un resguardo de envío. */
export default function PanelResguardoMunicipalAuditoria({ resguardo }) {
    if (!resguardo?.activo) return null;
    const fecha = resguardo.fecha_solicitada;
    return (
        <section className="registrar-tienda-panel grid gap-3" aria-label="Resguardo de envío">
            <div className="registrar-tienda-seccion-titulo"><PauseCircle size={18} aria-hidden="true" /><h3>Resguardo de envío</h3></div>
            <EstadosResguardoMunicipal estados={resguardo.estados} />
            <dl className="grid sm:grid-cols-2 gap-3 text-sm m-0">
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Motivo</dt><dd className="m-0 theme-text-main">{MOTIVOS_RESGUARDO[resguardo.motivo] || '—'}</dd></div>
                {resguardo.motivo === 'fecha_posterior' && (
                    <div><dt className="text-xs font-semibold theme-text-muted m-0">Fecha solicitada</dt><dd className="m-0 theme-text-main">{fecha || '—'}</dd></div>
                )}
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Pago de la mercancía</dt><dd className="m-0 theme-text-main">{textoCoberturaMercancia(resguardo.cobertura_mercancia)}</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Transporte</dt><dd className="m-0 theme-text-main">{textoTransporte(resguardo.cobertura_transporte)}</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Cajas</dt><dd className="m-0 theme-text-main">{resguardo.cajas === 'N/A' ? 'N/A (aún no se determinan)' : resguardo.cajas}</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Continuación</dt><dd className="m-0 theme-text-main">{resguardo.continuacion_solicitada_at ? 'Solicitada por Ventas' : 'Aún no solicitada'}</dd></div>
            </dl>
            {resguardo.motivo === 'fecha_posterior' && (
                <p className="text-xs theme-text-muted m-0">Llegar a la fecha no libera el envío: hace falta la instrucción de Ventas y su autorización.</p>
            )}
            <ConjuntoRemisionesMunicipal vinculos={resguardo.vinculos} vacio="Este envío no incorpora remisiones anteriores." />
            {resguardo.bloqueo_liberacion
                ? <p className="text-sm theme-text-aviso m-0" role="status"><strong>No se puede liberar todavía.</strong> {resguardo.bloqueo_liberacion}</p>
                : <p className="text-sm theme-text-exito m-0" role="status">El resguardo cumple lo necesario para liberarse.</p>}
        </section>
    );
}
