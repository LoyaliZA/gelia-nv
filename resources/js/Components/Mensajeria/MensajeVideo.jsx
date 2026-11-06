import React, { useEffect, useRef, useState } from 'react';

export default function MensajeVideo({ adjunto }) {
    const marcoRef = useRef(null);
    const [visible, setVisible] = useState(false);
    const poster = adjunto.thumbnail_url;

    useEffect(() => {
        const marco = marcoRef.current;
        if (!marco || visible) return undefined;
        const observer = new IntersectionObserver(([entrada]) => {
            if (!entrada?.isIntersecting) return;
            setVisible(true);
            observer.disconnect();
        }, { rootMargin: '240px' });
        observer.observe(marco);
        return () => observer.disconnect();
    }, [visible]);

    return (
        <div ref={marcoRef} className="mt-1 max-w-sm">
            <video
                controls
                preload={visible ? 'metadata' : 'none'}
                poster={poster || undefined}
                className="w-full rounded-xl max-h-64"
                src={visible ? adjunto.url : undefined}
            />
        </div>
    );
}
