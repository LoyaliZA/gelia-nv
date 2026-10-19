import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { BTN_SECONDARY } from './pedidosBmaStyles';

export default function ReabrirApartadoRecoge({ tarea, incidencia = false, anexo = false }) {
    const [motivo, setMotivo] = useState('');
    const [archivo, setArchivo] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const reenviar = () => {
        setError('');
        const form = new FormData();
        form.append('motivo', motivo);
        form.append('version', tarea.version);
        if (archivo) form.append('archivo', archivo);
        setProcesando(true);
        router.post(route('control_pedidos.preparacion.reabrir_apartado', tarea.id), form, {
            forceFormData: true, preserveScroll: true,
            onFinish: () => setProcesando(false),
            onError: (errores) => setError(Object.values(errores).flat().join(' ')),
        });
    };
    return <details className="text-sm mt-3">
        <summary className="cursor-pointer font-bold">{incidencia ? 'Resolver y reenviar a PDV' : (anexo ? 'Solicitar anexo de piezas' : 'Solicitar piezas adicionales')}</summary>
        <div className="space-y-2 mt-2">
            <p className="m-0">{anexo ? 'Las piezas ya registradas se conservan. Tienda revisará el anexo y preparará las piezas adicionales.' : 'Las piezas ya registradas se conservan. El auxiliar revisará la solicitud y volverá a confirmar.'}</p>
            <label className="block">{incidencia ? 'Respuesta para el auxiliar' : (anexo ? 'Qué piezas se agregan' : 'Qué cambia en el pedido')}<textarea className={`${THEME_TEXTAREA} w-full`} value={motivo} disabled={procesando} onChange={(e) => setMotivo(e.target.value)} /></label>
            <label className="block">{incidencia ? 'Documento actualizado (opcional)' : (anexo ? 'Anexo de piezas' : 'Complemento del pedido')}<input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" className={THEME_INPUT} disabled={procesando} onChange={(e) => setArchivo(e.target.files?.[0] || null)} /></label>
            {error && <p role="alert" className="text-sm font-semibold theme-text-peligro m-0">{error}</p>}
            <button type="button" className={BTN_SECONDARY} disabled={procesando || !motivo.trim() || (!incidencia && !archivo)} onClick={reenviar}>{anexo ? 'Enviar anexo a Tienda' : 'Reenviar a PDV'}</button>
        </div>
    </details>;
}
