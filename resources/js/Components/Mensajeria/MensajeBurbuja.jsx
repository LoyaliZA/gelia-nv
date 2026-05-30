import React from 'react';
import EstadoLectura from './EstadoLectura';
import MensajeImagen from './MensajeImagen';
import MensajeVideo from './MensajeVideo';
import MensajeAudio from './MensajeAudio';
import MensajeArchivo from './MensajeArchivo';

const formatearHora = (fecha) => {
    if (!fecha) return '';
    try {
        return new Date(fecha).toLocaleTimeString('es-MX', {
            hour: 'numeric', minute: '2-digit', hour12: true,
        });
    } catch {
        return '';
    }
};

export default function MensajeBurbuja({ mensaje, esGrupo = false }) {
    const esPropio = mensaje.es_propio;
    const adjunto = mensaje.adjuntos?.[0];

    return (
        <div className={`flex ${esPropio ? 'justify-end' : 'justify-start'} mb-2 px-3`}>
            <div
                className={`max-w-[75%] sm:max-w-[65%] rounded-2xl px-3 py-2 shadow-sm ${
                    esPropio
                        ? 'rounded-br-sm bg-[var(--color-primario)] text-white'
                        : 'rounded-bl-sm theme-element theme-text-main border theme-border'
                }`}
            >
                {!esPropio && esGrupo && mensaje.user?.name && (
                    <p className="text-[10px] font-black uppercase opacity-70 mb-0.5">
                        {mensaje.user.name}
                    </p>
                )}

                {mensaje.reply_to && (
                    <div className={`text-[11px] opacity-70 border-l-2 pl-2 mb-1 ${esPropio ? 'border-white/50' : 'border-[var(--color-primario)]'}`}>
                        <span className="font-bold">{mensaje.reply_to.user?.name}</span>
                        <p className="truncate">{mensaje.reply_to.contenido || `[${mensaje.reply_to.tipo}]`}</p>
                    </div>
                )}

                {mensaje.tipo === 'texto' && mensaje.contenido && (
                    <p className="text-sm whitespace-pre-wrap break-words">{mensaje.contenido}</p>
                )}

                {mensaje.tipo === 'imagen' && adjunto && <MensajeImagen adjunto={adjunto} />}
                {mensaje.tipo === 'video' && adjunto && <MensajeVideo adjunto={adjunto} />}
                {mensaje.tipo === 'audio' && adjunto && <MensajeAudio adjunto={adjunto} />}
                {mensaje.tipo === 'archivo' && adjunto && <MensajeArchivo adjunto={adjunto} />}

                {mensaje.contenido && mensaje.tipo !== 'texto' && (
                    <p className="text-sm mt-1 whitespace-pre-wrap break-words">{mensaje.contenido}</p>
                )}

                <div className={`flex items-center justify-end gap-0.5 mt-0.5 ${esPropio ? 'text-white/70' : 'opacity-50'}`}>
                    <span className="text-[10px]">{formatearHora(mensaje.created_at)}</span>
                    <EstadoLectura estado={mensaje.estado_lectura} esPropio={esPropio} />
                </div>
            </div>
        </div>
    );
}
