import React from 'react';

export default function MensajeVideo({ adjunto }) {
    const poster = adjunto.thumbnail_url;

    return (
        <div className="mt-1 max-w-sm">
            <video
                controls
                preload="metadata"
                poster={poster || undefined}
                className="w-full rounded-xl max-h-64"
                src={adjunto.url}
            />
        </div>
    );
}
