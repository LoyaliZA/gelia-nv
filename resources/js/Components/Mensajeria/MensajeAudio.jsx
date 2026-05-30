import React from 'react';

export default function MensajeAudio({ adjunto }) {
    return (
        <div className="mt-1 min-w-[200px]">
            <audio controls preload="metadata" className="w-full max-w-xs" src={adjunto.url} />
            {adjunto.duracion_seg && (
                <span className="text-[10px] opacity-60 ml-1">
                    {Math.floor(adjunto.duracion_seg / 60)}:{String(adjunto.duracion_seg % 60).padStart(2, '0')}
                </span>
            )}
        </div>
    );
}
