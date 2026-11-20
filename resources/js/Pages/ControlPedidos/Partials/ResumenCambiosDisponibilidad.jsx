import React from 'react';
import { CheckCircle2, ArrowRightLeft, PackageMinus, PackagePlus, ClipboardList, AlertTriangle, PackageX } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { cambiosDisponibilidad, particionarCambiosConsulta } from './cambiosDisponibilidad';

function Badge({ tipo, cantidad, icon: Icono, etiqueta }) {
    if (!cantidad) return null;
    return <span className="consulta-cambio-badge" data-tipo={tipo} aria-label={etiqueta}><Icono size={14} aria-hidden /><span aria-hidden>{cantidad}</span></span>;
}

export default function ResumenCambiosDisponibilidad({ solicitud = {}, documentos = [], onVer, interactivo = false, vista = 'ventas', respuestas = {}, onResponder, onIncidencia, disabled = false, puedeVerDocumentos = true }) {
    const cambios = cambiosDisponibilidad(solicitud, documentos);
    if (!cambios.length && !solicitud.reapertura_disponibilidad) return null;
    const anexos = cambios.filter(c => c.tipo !== 'retiro');
    const retiros = cambios.filter(c => c.tipo === 'retiro');
    const { actuales, anteriores } = particionarCambiosConsulta(cambios, solicitud, vista);
    const porRevisar = interactivo && actuales.some(c => c.pendiente && c.tipo !== 'retiro');
    const ficha = cambio => {
        const retiro = cambio.tipo === 'retiro';
        const confirmacion = cambio.tipo === 'confirmacion';
        const estado = respuestas[cambio.clave] || cambio.resultado || (retiro ? 'informe' : 'pendiente');
        const revisar = interactivo && cambio.pendiente && !confirmacion && !cambio.persistida && !retiro;
        const docs = (cambio.documentos || []).slice(0, 1);
        return <article key={cambio.clave} className="consulta-cambio" data-tipo={retiro ? 'retiro' : confirmacion ? 'confirmacion' : 'anexo'} data-cambio={cambio.clave} data-indice={cambio.indice} data-clase={cambio.clase || ''}>
            {puedeVerDocumentos && docs.map((d, i) => <MiniaturaDocumento key={d.id || i} documento={d} onVer={() => onVer?.(cambio.documentos, i)} className="consulta-cambio-miniatura" />)}
            <div className="consulta-cambio-cuerpo">
                <header className="consulta-cambio-titulo">
                    {retiro ? <PackageMinus size={16} aria-hidden /> : confirmacion ? <CheckCircle2 size={16} aria-hidden /> : cambio.retirada ? <ArrowRightLeft size={16} aria-hidden /> : <PackagePlus size={16} aria-hidden />}
                    <strong>{retiro ? 'Retiro solicitado' : confirmacion ? 'Estado confirmado' : cambio.retirada ? 'Nueva pieza solicitada' : 'Anexo solicitado'}</strong>
                </header>
                <p className="consulta-cambio-identidad">{cambio.descripcion || (retiro ? 'Piezas retiradas' : 'Piezas en captura')}{cambio.sku ? ` · ${cambio.sku}` : ''}{cambio.cantidad ? ` · ${cambio.cantidad} pza(s)` : ''}</p>
                {revisar ? <div className="consulta-cambio-acciones">
                    <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={estado === 'correcto'} disabled={disabled} onClick={() => onResponder?.(cambio, 'correcto')}><CheckCircle2 size={16} aria-hidden />Todo OK</button>
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={disabled} onClick={() => onIncidencia?.(cambio, 'detalle')}><AlertTriangle size={16} aria-hidden />{cambio.esDocumento ? 'Registrar detalle' : 'Con detalle'}</button>
                    {!cambio.esDocumento && <button type="button" className={GELIA_BTN_OUTLINE} disabled={disabled} onClick={() => onIncidencia?.(cambio, 'faltante')}><PackageX size={16} aria-hidden />Sin existencias</button>}
                </div> : <p className="consulta-cambio-estado">{retiro ? 'Informe de retiro' : vista === 'ventas' && cambio.avance_pendiente ? 'Pendiente de revisión' : estado === 'incidencia' || cambio.respuesta_cambio === 'incidencia' ? 'Con piezas reportadas' : estado === 'correcto' || cambio.respuesta_cambio === 'correcto' ? 'Todo OK' : cambio.respondida_at ? 'Respuesta registrada' : 'Pendiente de revisión'}</p>}
            </div>
        </article>;
    };
    return <section className="registrar-tienda-panel consulta-cambios" aria-label={porRevisar ? 'Cambios por revisar' : 'Cambios del pedido'}>
        <div className="registrar-tienda-seccion-titulo">
            <ClipboardList size={18} aria-hidden />
            <h3>{porRevisar ? 'Cambios por revisar' : 'Cambios del pedido'}</h3>
            <span className="consulta-cambios-badges" aria-label={`${anexos.length} anexos, ${retiros.length} retiros`}>
                <Badge tipo="anexo" cantidad={anexos.length} icon={PackagePlus} etiqueta={`${anexos.length} anexos`} />
                <Badge tipo="retiro" cantidad={retiros.length} icon={PackageMinus} etiqueta={`${retiros.length} retiros`} />
            </span>
        </div>
        {solicitud.reapertura_disponibilidad && <p className="text-xs theme-text-muted m-0">Consulta reabierta por {solicitud.reapertura_disponibilidad.por}.</p>}
        {actuales.length > 0 && <div className="consulta-cambios-actuales">{actuales.map(ficha)}</div>}
        {anteriores.length > 0 && (
            <details className="consulta-cambios-historial">
                <summary>Movimientos ({anteriores.length})</summary>
                <div className="consulta-cambios-minis">{anteriores.map(ficha)}</div>
            </details>
        )}
    </section>;
}
