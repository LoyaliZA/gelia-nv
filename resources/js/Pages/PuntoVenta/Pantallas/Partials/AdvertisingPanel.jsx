import React, { useEffect, useRef, useState } from 'react';
import GeliaLogo from '@/Components/GeliaLogo';

function MediaProgress({ items, index, progress }) {
    if (!items.length) return null;

    return (
        <div className="pointer-events-none absolute bottom-5 left-5 right-5 flex gap-2" data-pdv-sala-progreso>
            {items.map((item, i) => (
                <div
                    key={item.id}
                    className="h-1.5 flex-1 overflow-hidden rounded-full"
                    style={{ backgroundColor: 'color-mix(in srgb, white 35%, transparent)' }}
                >
                    <div
                        className="h-full rounded-full"
                        style={{
                            width: i < index ? '100%' : i === index ? `${Math.min(100, Math.max(0, progress * 100))}%` : '0%',
                            backgroundColor: i === index
                                ? 'var(--color-primario)'
                                : 'color-mix(in srgb, white 70%, transparent)',
                        }}
                    />
                </div>
            ))}
        </div>
    );
}

function FallbackInstitucional() {
    return (
        <div
            className="flex h-full w-full flex-col items-center justify-center gap-6"
            data-pdv-sala-publicidad-vacia
            style={{
                background: 'linear-gradient(160deg, color-mix(in srgb, var(--color-primario) 18%, white), white 55%)',
            }}
        >
            <GeliaLogo variant="sparkle" className="h-28 w-28" />
            <p className="px-8 text-center text-xl font-black uppercase tracking-[0.18em] theme-text-main">
                Una atención más humana, es posible.
            </p>
        </div>
    );
}

function siguienteIndice(playlist, desde, omitidos) {
    if (!playlist.length) return -1;
    let i = (desde + 1) % playlist.length;
    const inicio = i;
    while (omitidos.has(playlist[i]?.id)) {
        i = (i + 1) % playlist.length;
        if (i === inicio) return -1;
    }
    return i;
}

export default function AdvertisingPanel({ items = [] }) {
    const [index, setIndex] = useState(0);
    const [ciclo, setCiclo] = useState(0);
    const [progress, setProgress] = useState(0);
    const [omitidos, setOmitidos] = useState(() => new Set());
    const videoRef = useRef(null);
    const preloadImg = useRef(null);
    const playlist = Array.isArray(items) ? items.filter((item) => item?.url && !omitidos.has(item.id)) : [];
    const actual = playlist[index] ?? playlist[0] ?? null;

    useEffect(() => {
        setIndex(0);
        setProgress(0);
        setOmitidos(new Set());
    }, [items.map((item) => item.id).join(',')]);

    useEffect(() => {
        if (index >= playlist.length) setIndex(0);
    }, [playlist.length, index]);

    useEffect(() => {
        if (!actual || actual.tipo === 'video') return undefined;

        const duracionMs = Math.max(3, Number(actual.duracion_seg) || 10) * 1000;
        const iniciado = performance.now();
        let frame;

        const tick = (now) => {
            const elapsed = now - iniciado;
            setProgress(Math.min(1, elapsed / duracionMs));
            if (elapsed >= duracionMs) {
                avanzar();
                return;
            }
            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);
        return () => window.cancelAnimationFrame(frame);
    }, [actual?.id, ciclo, playlist.length]);

    useEffect(() => {
        const siguiente = playlist[(index + 1) % playlist.length];
        if (!siguiente || siguiente.tipo === 'video') return undefined;
        const img = new Image();
        img.src = siguiente.url;
        preloadImg.current = img;
        return () => {
            preloadImg.current = null;
        };
    }, [index, playlist]);

    const avanzar = () => {
        setIndex((prev) => {
            const base = playlist.length ? playlist : (Array.isArray(items) ? items : []);
            const ids = base.map((item) => item.id);
            const actualId = actual?.id;
            const desde = Math.max(0, ids.indexOf(actualId));
            const next = siguienteIndice(base, desde, omitidos);
            return next < 0 ? 0 : next;
        });
        setCiclo((prev) => prev + 1);
        setProgress(0);
    };

    const omitir = (item) => {
        if (!item?.id) return;
        console.warn('Publicidad de sala omitida', item.id);
        setOmitidos((prev) => new Set(prev).add(item.id));
        setCiclo((prev) => prev + 1);
        setProgress(0);
    };

    const objectFit = actual?.ajuste === 'contain' ? 'contain' : 'cover';
    const siguienteVideo = playlist[(index + 1) % playlist.length];

    return (
        <section
            className="relative h-full min-h-0 overflow-hidden rounded-[2.5rem] border theme-border theme-surface"
            style={{ boxShadow: 'var(--theme-shadow-card)' }}
            data-pdv-sala-publicidad
        >
            {!actual ? (
                <FallbackInstitucional />
            ) : actual.tipo === 'video' ? (
                <video
                    key={actual.id}
                    ref={videoRef}
                    className="h-full w-full"
                    style={{ objectFit }}
                    src={actual.url}
                    autoPlay
                    muted
                    playsInline
                    preload="auto"
                    onTimeUpdate={() => {
                        const video = videoRef.current;
                        if (!video?.duration) return;
                        setProgress(Math.min(1, video.currentTime / video.duration));
                    }}
                    onEnded={avanzar}
                    onError={() => omitir(actual)}
                />
            ) : (
                <img
                    key={actual.id}
                    src={actual.url}
                    alt=""
                    className="h-full w-full"
                    style={{ objectFit }}
                    onError={() => omitir(actual)}
                />
            )}
            {siguienteVideo?.tipo === 'video' ? (
                <video src={siguienteVideo.url} preload="metadata" className="hidden" muted />
            ) : null}
            <MediaProgress items={playlist} index={Math.min(index, Math.max(0, playlist.length - 1))} progress={progress} />
        </section>
    );
}
