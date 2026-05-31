import React, { useRef, useState } from 'react';
import { CornerDownRight } from 'lucide-react';
import EstadoLectura from './EstadoLectura';
import MensajeImagen from './MensajeImagen';
import MensajeVideo from './MensajeVideo';
import MensajeAudio from './MensajeAudio';
import MensajeArchivo from './MensajeArchivo';
import AvatarUsuario from './AvatarUsuario';
import MensajeMenuAcciones from './MensajeMenuAcciones';
import { fragmentoRespuesta } from '@/utils/mensajeriaRespuesta';
import {
    colorRemitenteGrupo,
    nombreRemitenteGrupo,
    subtituloRemitenteGrupo,
} from '@/utils/mensajeriaGrupo';

const SWIPE_UMBRAL = 56;

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

function CitacionMensaje({ replyTo, esPropio, colorRemitente, onIrAMensaje }) {
    const nombre = replyTo.user?.name || 'Usuario';
    const fragmento = fragmentoRespuesta(replyTo);

    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onIrAMensaje?.(replyTo.id);
            }}
            className={`gelia-mensaje-cita text-left mb-1.5 rounded-lg px-2 py-1.5 transition-opacity hover:opacity-90 ${
                esPropio
                    ? 'bg-black/15 border-l-2 border-white/70'
                    : 'theme-element border-l-2 border-[var(--color-primario)]'
            }`}
            title="Ir al mensaje original"
        >
            <span
                className="gelia-mensaje-cita__nombre"
                style={!esPropio ? { color: colorRemitente } : undefined}
            >
                {nombre}
            </span>
            <span className={`gelia-mensaje-cita__fragmento ${esPropio ? 'opacity-90' : 'theme-text-muted'}`}>
                {fragmento}
            </span>
        </button>
    );
}

export default function MensajeBurbuja({
    mensaje,
    esGrupo = false,
    mostrarRemitente = true,
    participantesGrupo = [],
    onResponder,
    onIrAMensaje,
}) {
    const esPropio = mensaje.es_propio;
    const adjunto = mensaje.adjuntos?.[0];
    const esGrupoAjeno = esGrupo && !esPropio;
    const mostrarCabecera = esGrupoAjeno && mostrarRemitente;
    const colorRemitente = colorRemitenteGrupo(mensaje.user?.id);
    const nombreRemitente = nombreRemitenteGrupo(mensaje.user);
    const subtituloRemitente = subtituloRemitenteGrupo(mensaje.user, participantesGrupo);

    const [menuAnchor, setMenuAnchor] = useState(null);
    const [deslizamiento, setDeslizamiento] = useState(0);
    const touchInicio = useRef(null);

    const abrirMenu = (clientX, clientY) => {
        setMenuAnchor({ x: clientX, y: clientY });
    };

    const handleContextMenu = (e) => {
        e.preventDefault();
        abrirMenu(e.clientX, e.clientY);
    };

    const handleTouchStart = (e) => {
        touchInicio.current = {
            x: e.touches[0].clientX,
            y: e.touches[0].clientY,
        };
        setDeslizamiento(0);
    };

    const handleTouchMove = (e) => {
        if (!touchInicio.current) return;
        const dx = e.touches[0].clientX - touchInicio.current.x;
        const dy = Math.abs(e.touches[0].clientY - touchInicio.current.y);
        if (dy > 40) {
            touchInicio.current = null;
            setDeslizamiento(0);
            return;
        }
        if (dx > 0) {
            setDeslizamiento(Math.min(dx, 72));
        }
    };

    const handleTouchEnd = () => {
        if (deslizamiento >= SWIPE_UMBRAL) {
            onResponder?.(mensaje);
        }
        touchInicio.current = null;
        setDeslizamiento(0);
    };

    const burbuja = (
        <div
            className={`gelia-mensaje-burbuja-wrap relative group ${esPropio ? 'gelia-mensaje-burbuja-wrap--propio' : ''}`}
            style={{ transform: deslizamiento ? `translateX(${deslizamiento}px)` : undefined }}
            onContextMenu={handleContextMenu}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
        >
            {deslizamiento > 20 && (
                <span
                    className={`absolute top-1/2 -translate-y-1/2 opacity-60 pointer-events-none ${
                        esPropio ? 'right-full mr-1' : 'left-full ml-1'
                    }`}
                    aria-hidden
                >
                    <CornerDownRight className="w-4 h-4 text-[var(--color-primario)]" />
                </span>
            )}

            <button
                type="button"
                onClick={() => onResponder?.(mensaje)}
                className={`gelia-mensaje-responder-btn absolute top-1/2 -translate-y-1/2 p-1 rounded-full theme-element border theme-border opacity-0 group-hover:opacity-100 transition-opacity z-10 ${
                    esPropio ? 'right-full mr-1' : 'left-full ml-1'
                } hidden sm:flex`}
                title="Responder"
                aria-label="Responder a este mensaje"
            >
                <CornerDownRight className="w-3.5 h-3.5" />
            </button>

            <div
                className={`gelia-mensaje-burbuja rounded-2xl px-3 py-2 shadow-sm ${
                    esPropio ? 'gelia-mensaje-burbuja--propio' : ''
                } ${mensaje.reply_to ? 'gelia-mensaje-burbuja--con-cita' : ''} ${
                    esPropio
                        ? 'rounded-br-sm bg-[var(--color-primario)] text-white'
                        : 'rounded-bl-sm theme-element theme-text-main border theme-border'
                } ${mostrarCabecera ? 'rounded-tl-sm' : ''}`}
            >
                {mensaje.reply_to && (
                    <CitacionMensaje
                        replyTo={mensaje.reply_to}
                        esPropio={esPropio}
                        colorRemitente={colorRemitente}
                        onIrAMensaje={onIrAMensaje}
                    />
                )}

                {mensaje.tipo === 'texto' && mensaje.contenido && (
                    <p className="gelia-mensaje-burbuja__texto text-sm">{mensaje.contenido}</p>
                )}

                {mensaje.tipo === 'imagen' && adjunto && <MensajeImagen adjunto={adjunto} />}
                {mensaje.tipo === 'video' && adjunto && <MensajeVideo adjunto={adjunto} />}
                {mensaje.tipo === 'audio' && adjunto && <MensajeAudio adjunto={adjunto} />}
                {mensaje.tipo === 'archivo' && adjunto && <MensajeArchivo adjunto={adjunto} />}

                {mensaje.contenido && mensaje.tipo !== 'texto' && (
                    <p className="gelia-mensaje-burbuja__texto text-sm mt-1">{mensaje.contenido}</p>
                )}

                <div className={`gelia-mensaje-meta ${esPropio ? 'gelia-mensaje-meta--propio' : ''}`}>
                    <span className={`gelia-mensaje-meta__hora text-[10px] ${esPropio ? '' : 'opacity-50'}`}>
                        {formatearHora(mensaje.created_at)}
                    </span>
                    <EstadoLectura estado={mensaje.estado_lectura} esPropio={esPropio} />
                </div>
            </div>

            <MensajeMenuAcciones
                anchor={menuAnchor}
                onCerrar={() => setMenuAnchor(null)}
                onResponder={() => onResponder?.(mensaje)}
            />
        </div>
    );

    if (esGrupoAjeno) {
        return (
            <div
                id={`mensaje-${mensaje.id}`}
                className={`gelia-mensaje-fila gelia-mensaje-grupo-fila flex justify-start gap-2 mb-0.5 px-3 ${mostrarCabecera ? 'mt-3' : 'mt-0.5'}`}
            >
                <div className="w-8 shrink-0 flex justify-center">
                    {mostrarCabecera ? (
                        <AvatarUsuario
                            foto={mensaje.user?.foto_perfil}
                            nombre={nombreRemitente}
                            className="w-8 h-8"
                            iconClassName="w-4 h-4"
                        />
                    ) : (
                        <span className="w-8 shrink-0" aria-hidden />
                    )}
                </div>
                <div className="gelia-mensaje-grupo-cuerpo">
                    {mostrarCabecera && (
                        <div className="mb-1 ms-0.5 max-w-full min-w-0">
                            <p
                                className="text-xs font-bold m-0 leading-tight truncate"
                                style={{ color: colorRemitente }}
                            >
                                {nombreRemitente}
                            </p>
                            {subtituloRemitente && (
                                <p
                                    className="text-[10px] font-bold m-0 mt-0.5 truncate opacity-80"
                                    style={{ color: colorRemitente }}
                                >
                                    {subtituloRemitente}
                                </p>
                            )}
                        </div>
                    )}
                    {burbuja}
                </div>
            </div>
        );
    }

    return (
        <div
            id={`mensaje-${mensaje.id}`}
            className={`gelia-mensaje-fila ${esPropio ? 'gelia-mensaje-fila--propio' : ''} mb-2 px-3`}
        >
            {burbuja}
        </div>
    );
}
