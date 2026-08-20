const BIP_SCANNER_SRC = '/assets/sound_efects/bip_scanner.mp3';

let bipAudioEl = null;
let bipAudioUnlocked = false;

function obtenerBipAudio() {
    if (!bipAudioEl) {
        bipAudioEl = new Audio(BIP_SCANNER_SRC);
        bipAudioEl.preload = 'auto';
        bipAudioEl.volume = 1;
    }
    return bipAudioEl;
}

export function desbloquearBipAudio() {
    if (bipAudioUnlocked || typeof Audio === 'undefined') return;
    const audio = obtenerBipAudio();
    const prevMuted = audio.muted;
    audio.muted = true;
    const playPromise = audio.play();
    if (playPromise?.then) {
        playPromise
            .then(() => {
                audio.pause();
                audio.currentTime = 0;
                audio.muted = prevMuted;
                bipAudioUnlocked = true;
            })
            .catch(() => {
                audio.muted = prevMuted;
            });
    }
}

export function reproducirBipConfirmacion() {
    try {
        if (typeof Audio === 'undefined') return;
        const audio = obtenerBipAudio();
        audio.currentTime = 0;
        const playPromise = audio.play();
        if (playPromise?.catch) {
            playPromise.catch(() => {});
        }
    } catch {
        // sin audio en este dispositivo
    }
}

/** Tono grave distinto al bip de pistola (error de SKU). */
export function reproducirBipError() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'square';
        osc.frequency.value = 220;
        gain.gain.value = 0.06;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        window.setTimeout(() => {
            osc.stop();
            ctx.close().catch(() => {});
        }, 160);
    } catch {
        // sin audio
    }
}
