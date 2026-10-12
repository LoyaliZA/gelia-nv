import React from 'react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';

/** Facturas owns its presentation; shared dialogs retain their current appearance. */
export default function FacturaDialog(props) {
    return <SolicitudDialog {...props} className={`gelia-facturas-dialog-overlay ${props.className || ''}`} />;
}
