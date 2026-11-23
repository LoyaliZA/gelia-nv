import { useEffect, useRef } from 'react';
import { animate } from 'motion';
import { PIEZAS_LOGO } from '@/utils/piezasLogoGelia';

// Misma línea de tiempo que la capa CSS: explosión 13 %, torbellino hasta 73 %, retorno hasta 88 %.
const INICIO = 0.13;
const REGRESO = 0.73;
const FIN = 0.88;
const FOCAL = 320;
const PROFUNDIDAD = 640;
// En vuelo los triángulos crecen más que el logo reunido; vuelven a 1 al llegar a casa.
const AUMENTO_TRIANGULO = 2.4;

const suave = (t) => (t < 0.5 ? 2 * t * t : 1 - ((-2 * t + 2) ** 2) / 2);
const salida = (t) => 1 - (1 - t) ** 3;

function envolvente(u) {
    if (u <= INICIO || u >= FIN) {
        return 0;
    }
    if (u < REGRESO) {
        return salida((u - INICIO) / (REGRESO - INICIO));
    }

    return 1 - suave((u - REGRESO) / (FIN - REGRESO));
}

function colorAcento() {
    const crudo = getComputedStyle(document.documentElement).getPropertyValue('--color-primario').trim();
    const hex = /^#([0-9a-f]{6})$/i.exec(crudo);
    if (!hex) {
        return [236, 72, 153];
    }
    const n = parseInt(hex[1], 16);

    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function crearParticulas(cantidad) {
    return Array.from({ length: cantidad }, () => {
        const angulo = Math.random() * Math.PI * 2;
        const dz = -0.25 + Math.random() * 1.1; // la mayoría viaja hacia la cámara
        const plano = Math.sqrt(Math.max(0, 1 - dz * dz));

        return {
            dx: Math.cos(angulo) * plano,
            dy: Math.sin(angulo) * plano,
            dz,
            velocidad: 350 + Math.random() ** 2 * 1900,
            giro: (Math.random() < 0.5 ? -1 : 1) * (0.5 + Math.random()),
            brillo: 0.35 + Math.random() * 0.65,
            grosor: 0.5 + Math.random() * 1.1,
        };
    });
}

/** Explosión de partículas con perspectiva 3D a pantalla completa; avanza con `animate` de motion. */
export default function CampoParticulasActualizacion({ duracionMs }) {
    const lienzo = useRef(null);

    useEffect(() => {
        const canvas = lienzo.current;
        const ctx = canvas?.getContext('2d');
        if (!ctx) {
            return undefined;
        }

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const ancho = window.innerWidth;
        const alto = window.innerHeight;
        canvas.width = Math.round(ancho * dpr);
        canvas.height = Math.round(alto * dpr);
        ctx.scale(dpr, dpr);

        const [r, g, b] = colorAcento();
        const particulas = crearParticulas(ancho < 640 ? 700 : 1700);
        // El estallido nace en el logo: se toma su centro y su tamaño reales.
        const logo = document.querySelector('.gelia-actualizacion-logo')?.getBoundingClientRect();
        const cx = logo ? logo.left + logo.width / 2 : ancho / 2;
        const cy = logo ? logo.top + logo.height / 2 : alto * 0.42;
        const k = (logo?.width || 80) / 100;
        const triangulos = PIEZAS_LOGO.map((pieza, i) => {
            const angulo = ((i * 137.5) % 360) * (Math.PI / 180);
            const dz = 0.35 + ((i * 29) % 50) / 100;
            const plano = Math.sqrt(1 - dz * dz);

            return {
                ...pieza,
                dx: Math.cos(angulo) * plano,
                dy: Math.sin(angulo) * plano,
                dz,
                velocidad: 450 + ((i * 53) % 9) * 110,
                giro: (i % 2 === 0 ? 1 : -1) * (0.6 + (i % 3) * 0.2),
                vuelta: (i % 2 === 0 ? 1 : -1) * (Math.PI + (i % 4) * Math.PI * 0.6),
                volteo: Math.PI * (1 + (i % 3)),
            };
        });

        const proyectar = (p, u) => {
            const radio = p.velocidad * envolvente(u);
            const ang = Math.max(0, u - INICIO) * 6 * p.giro;
            const x0 = p.dx * radio;
            const y0 = p.dy * radio;
            const x = x0 * Math.cos(ang) - y0 * Math.sin(ang);
            const y = x0 * Math.sin(ang) + y0 * Math.cos(ang);
            const profundidad = PROFUNDIDAD - p.dz * radio;
            if (profundidad < FOCAL * 0.25) {
                return null;
            }
            const escala = FOCAL / profundidad;

            return { x: cx + x * escala, y: cy + y * escala, escala };
        };

        const dibujar = (u) => {
            ctx.clearRect(0, 0, ancho, alto);
            if (u <= INICIO || u >= FIN) {
                return;
            }
            ctx.globalCompositeOperation = 'lighter';
            ctx.lineCap = 'round';

            // Destello central en el instante de la explosión.
            const fuerza = Math.max(0, 1 - Math.abs(u - INICIO - 0.04) / 0.12);
            if (fuerza > 0) {
                const rad = 60 + 260 * fuerza;
                const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, rad);
                grad.addColorStop(0, `rgba(255,255,255,${0.9 * fuerza})`);
                grad.addColorStop(0.25, `rgba(${r},${g},${b},${0.55 * fuerza})`);
                grad.addColorStop(1, `rgba(${r},${g},${b},0)`);
                ctx.fillStyle = grad;
                ctx.fillRect(cx - rad, cy - rad, rad * 2, rad * 2);
            }

            for (const p of particulas) {
                const ahora = proyectar(p, u);
                const antes = proyectar(p, Math.max(INICIO, u - 0.012));
                if (!ahora || !antes) {
                    continue;
                }
                const cerca = Math.min(1, ahora.escala * 1.4);
                const alfa = Math.min(1, p.brillo * cerca);
                const blanco = Math.max(0, 1 - ahora.escala * 1.6) * 0.6 + (ahora.escala > 1.4 ? 0.3 : 0);
                ctx.strokeStyle = `rgba(${Math.round(r + (255 - r) * blanco)},${Math.round(g + (255 - g) * blanco)},${Math.round(b + (255 - b) * blanco)},${alfa})`;
                ctx.lineWidth = p.grosor * Math.min(3, ahora.escala * 1.5);
                ctx.beginPath();
                ctx.moveTo(antes.x, antes.y);
                ctx.lineTo(ahora.x, ahora.y);
                ctx.stroke();
            }
            ctx.globalCompositeOperation = 'source-over';

            const E = envolvente(u);
            const ang = Math.max(0, u - INICIO) * 6;
            ctx.fillStyle = `rgb(${r},${g},${b})`;
            for (const t of triangulos) {
                const radio = t.velocidad * E;
                const a = ang * t.giro;
                const rx = t.dx * radio * Math.cos(a) - t.dy * radio * Math.sin(a);
                const ry = t.dx * radio * Math.sin(a) + t.dy * radio * Math.cos(a);
                const profundidad = PROFUNDIDAD - t.dz * radio;
                if (profundidad < FOCAL * 0.25) {
                    continue;
                }
                const tam = PROFUNDIDAD / profundidad;
                const px = cx + (t.centro[0] - 50) * k * tam + rx * (FOCAL / profundidad);
                const py = cy + (t.centro[1] - 50) * k * tam + ry * (FOCAL / profundidad);
                const aumento = 1 + (AUMENTO_TRIANGULO - 1) * E;
                const giroPropio = t.vuelta * E;
                const cos = Math.cos(giroPropio);
                const sen = Math.sin(giroPropio);
                const volteo = Math.cos(t.volteo * E);
                ctx.globalAlpha = t.opacidad * Math.max(0, Math.min(1, (9 - tam) / 3));
                ctx.beginPath();
                t.vertices.forEach(([vx, vy], idx) => {
                    const lx = (vx - t.centro[0]) * k * tam * aumento * volteo;
                    const ly = (vy - t.centro[1]) * k * tam * aumento;
                    const sx = px + lx * cos - ly * sen;
                    const sy = py + lx * sen + ly * cos;
                    if (idx === 0) {
                        ctx.moveTo(sx, sy);
                    } else {
                        ctx.lineTo(sx, sy);
                    }
                });
                ctx.closePath();
                ctx.fill();
            }
            ctx.globalAlpha = 1;
        };

        const control = animate(0, 1, {
            duration: duracionMs / 1000,
            ease: 'linear',
            onUpdate: dibujar,
        });

        return () => control.stop();
    }, [duracionMs]);

    return <canvas ref={lienzo} className="gelia-actualizacion-particulas" aria-hidden="true" />;
}
