import React, { useState } from 'react';
import { Download, FileText, Image as ImageIcon } from 'lucide-react';
import SolicitudDialog from './SolicitudDialog';
import CabeceraFlujoSolicitud from './CabeceraFlujoSolicitud';

export default function EvidenciaSolicitud({ path, url, label = 'Ver evidencia', title = 'Evidencia de la respuesta', esPdf, dialogClassName = '' }) {
    const [abierta, setAbierta] = useState(false);
    const [fallo, setFallo] = useState(false);
    const src = url || (path ? `/storage/${path}` : null);
    if (!src) return null;
    const pdf = esPdf ?? /\.pdf(?:$|[?#])/i.test(src);
    const Icon = pdf ? FileText : ImageIcon;
    return (
        <>
            <button type="button" className="gelia-evidencia-link" onClick={() => { setFallo(false); setAbierta(true); }}>
                <Icon className="w-4 h-4 shrink-0" aria-hidden="true" />{label}
            </button>
            {abierta && (
                <SolicitudDialog className={dialogClassName} title={title} onClose={() => setAbierta(false)}>
                    <div className="gelia-modal-shell gelia-evidencia-visor w-full max-w-5xl">
                        <CabeceraFlujoSolicitud icono={Icon} tono="neutro" titulo={title} closeLabel="Cerrar evidencia" />
                        <div className="gelia-modal-body p-4 flex items-center justify-center">
                            {pdf ? <iframe title={title} src={src} className="w-full h-[65dvh] rounded-lg border theme-border" />
                                : fallo ? <p role="status" className="text-sm theme-text-muted">No se pudo mostrar la imagen. Puedes abrir el archivo con el enlace de abajo.</p>
                                    : <img src={src} alt={title} width="1200" height="900" onError={() => setFallo(true)} className="gelia-evidencia-imagen" />}
                        </div>
                        <footer className="gelia-modal-footer p-4 flex justify-end">
                            <a href={src} target="_blank" rel="noopener noreferrer" className="theme-btn-secondary"><Download className="w-4 h-4" aria-hidden="true" /> Abrir archivo</a>
                        </footer>
                    </div>
                </SolicitudDialog>
            )}
        </>
    );
}
