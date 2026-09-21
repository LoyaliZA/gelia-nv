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

export default function AdvertisingPanel({ items = [] }) {
    const [index, setIndex] = useState(0);
    const [ciclo, setCiclo] = useState(0);
    const [progress, setProgress] = useState(0);
    const videoRef = useRef(null);
    const playlist = Array.isArray(items) ? items.filter((item) => item?.url) : [];
    const actual = playlist[index] ?? null;

    useEffect(() => {
        setIndex(0);
        setProgress(0);
    }, [playlist.map((item) => item.id).join(',')]);

    useEffect(() => {
        if (!actual || actual.tipo === 'video') return undefined;

        const duracionMs = Math.max(3, Number(actual.duracion_seg) || 8) * 1000;
        const iniciado = performance.now();
        let frame;

        const tick = (now) => {
            const elapsed = now - iniciado;
            setProgress(Math.min(1, elapsed / duracionMs));
            if (elapsed >= duracionMs) {
            setIndex((prev) => (prev + 1) % playlist.length);
            setCiclo((prev) => prev + 1);
            setProgress(0);
            return;
        }
            frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);
        return () => window.cancelAnimationFrame(frame);
    }, [actual, ciclo, playlist.length]);

    const manejarTiempoVideo = () => {
        const video = videoRef.current;
        if (!video) return;
        const limite = Number(actual?.duracion_seg) > 0 ? Number(actual.duracion_seg) : video.duration;
        if (!limite || Number.isNaN(limite)) return;
        const elapsed = video.currentTime;
        setProgress(Math.min(1, elapsed / limite));
        if (elapsed >= limite - 0.05) {
            setIndex((prev) => (prev + 1) % playlist.length);
            setCiclo((prev) => prev + 1);
            setProgress(0);
        }
    };

    const objectFit = actual?.ajuste === 'contain' ? 'contain' : 'cover';

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
                    onTimeUpdate={manejarTiempoVideo}
                    onEnded={() => {
                        setIndex((prev) => (prev + 1) % playlist.length);
                        setCiclo((prev) => prev + 1);
                        setProgress(0);
                    }}
                />
            ) : (
                <img
                    key={actual.id}
                    src={actual.url}
                    alt=""
                    className="h-full w-full"
                    style={{ objectFit }}
                />
            )}
            <MediaProgress items={playlist} index={index} progress={progress} />
        </section>
    );
}
