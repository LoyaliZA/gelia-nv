import React from 'react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';

export default function AvisoResultadoTienda({ flash }) {
    const error = flash?.error;
    const mensaje = error || flash?.success;
    if (!mensaje) return null;

    return (
        <div role={error ? 'alert' : 'status'}>
            <AvisoOperativoPedido tono={error ? 'danger' : 'success'} icon={error ? AlertTriangle : CheckCircle2}>
                {mensaje}
            </AvisoOperativoPedido>
        </div>
    );
}
