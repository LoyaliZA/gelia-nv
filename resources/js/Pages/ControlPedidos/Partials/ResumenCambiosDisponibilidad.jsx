import React from 'react';
import { CheckCircle2, ArrowRightLeft, PackageMinus, PackagePlus, ClipboardList, Eye, AlertTriangle, PackageX } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { cambiosDisponibilidad } from './cambiosDisponibilidad';

export default function ResumenCambiosDisponibilidad({ solicitud = {}, documentos = [], onVer, interactivo = false, respuestas = {}, onResponder, onIncidencia, disabled = false }) {
    const cambios = cambiosDisponibilidad(solicitud, documentos);
    if (!cambios.length && !solicitud.reapertura_disponibilidad) return null;
    const pendientes = cambios.filter(c => c.pendiente);
    const anteriores = cambios.filter(c => !c.pendiente);
    const ficha = cambio => {
        const retiro = cambio.tipo === 'retiro';
        const confirmacion = cambio.tipo === 'confirmacion';
        const estado = respuestas[cambio.clave] || (!cambio.esDocumento && !retiro ? 'correcto' : 'pendiente');
        const revisar = interactivo && cambio.pendiente && !confirmacion;
        return <article key={cambio.clave} className="consulta-cambio" data-tipo={retiro ? 'retiro' : confirmacion ? 'confirmacion' : 'anexo'} data-cambio={cambio.clave}>
            {cambio.retirada && <div className="consulta-cambio-retirada"><PackageMinus size={17} aria-hidden /><div><strong>Pieza retirada</strong><p>{cambio.retirada.descripcion}</p><span>{cambio.retirada.sku}</span></div></div>}
            <div className="consulta-cambio-cuerpo">
                <header className="consulta-cambio-titulo">
                    {retiro ? <PackageMinus size={19} aria-hidden /> : confirmacion ? <CheckCircle2 size={19} aria-hidden /> : cambio.retirada ? <ArrowRightLeft size={19} aria-hidden /> : <PackagePlus size={19} aria-hidden />}
                    <strong>{retiro ? 'Retiro solicitado' : confirmacion ? 'Estado confirmado' : cambio.retirada ? 'Nueva pieza solicitada' : 'Anexo solicitado'}</strong>
                    <span className="consulta-cambio-formato">{cambio.esDocumento ? 'Captura / PDF' : cambio.sku ? 'SKU' : 'Piezas'}</span>
                </header>
                {(!cambio.esDocumento || !cambio.documentos.length) && <div className="consulta-cambio-identidad"><strong>{cambio.descripcion || 'Piezas en captura'}</strong>{cambio.sku && <span>{cambio.sku}{cambio.cantidad ? ` · ${cambio.cantidad} pza(s)` : ''}</span>}</div>}
                {cambio.documentos.length > 0 && <div className="consulta-cambio-documentos">{cambio.documentos.map((d, i) => <div key={d.id || i} className="consulta-cambio-documento">
                    <MiniaturaDocumento documento={d} onVer={() => onVer?.(cambio.documentos, i)} className="consulta-cambio-miniatura" />
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={!onVer} onClick={() => onVer?.(cambio.documentos, i)}><Eye size={16} aria-hidden />Abrir captura</button>
                </div>)}</div>}
                {revisar ? <>
                    <div className="consulta-cambio-acciones">
                        <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={estado === 'correcto'} disabled={disabled} onClick={() => onResponder?.(cambio, 'correcto')}><CheckCircle2 size={16} aria-hidden />{retiro ? 'Retiro revisado' : 'Todo OK'}</button>
                        {!retiro && <button type="button" className={GELIA_BTN_OUTLINE} disabled={disabled} onClick={() => onIncidencia?.(cambio, 'detalle')}><AlertTriangle size={16} aria-hidden />{cambio.esDocumento ? 'Registrar detalle' : 'Con detalle'}</button>}
                        {!retiro && !cambio.esDocumento && <button type="button" className={GELIA_BTN_OUTLINE} disabled={disabled} onClick={() => onIncidencia?.(cambio, 'faltante')}><PackageX size={16} aria-hidden />Sin existencias</button>}
                    </div>
                    <p className="consulta-cambio-estado" role="status">{estado === 'incidencia' ? 'Con piezas reportadas' : estado === 'correcto' ? retiro ? 'Retiro revisado' : 'Todo OK' : 'Pendiente de revisar'}</p>
                </> : <p className="consulta-cambio-estado">{cambio.respuesta_cambio === 'correcto' ? 'Todo OK' : cambio.respuesta_cambio === 'incidencia' ? 'Con piezas reportadas' : cambio.respondida_at || cambio.estado === 'aplicada' ? 'Respuesta registrada' : 'Pendiente de revisión'}{cambio.resultado && !cambio.respuesta_cambio ? ` · ${cambio.resultado}` : ''}</p>}
            </div>
        </article>;
    };
    return <section className="registrar-tienda-panel consulta-cambios" aria-label={interactivo ? 'Cambios por revisar' : 'Cambios guardados del pedido'}>
        <div className="registrar-tienda-seccion-titulo"><ClipboardList size={18} aria-hidden /><h3>{interactivo ? 'Cambios por revisar' : 'Cambios guardados del pedido'}</h3>{interactivo && <span className="text-xs theme-text-muted">{pendientes.length}</span>}</div>
        {solicitud.reapertura_disponibilidad && <p className="text-xs theme-text-muted m-0">Consulta reabierta por {solicitud.reapertura_disponibilidad.por}.</p>}
        {interactivo ? <>{pendientes.map(ficha)}{anteriores.length > 0 && <details className="consulta-cambios-historial"><summary>Mostrar todos los cambios ({cambios.length})</summary><div className="grid gap-3 mt-3">{anteriores.map(ficha)}</div></details>}</> : cambios.map(ficha)}
    </section>;
}
