// Servicio dedicado a interactuar con APIs nativas del navegador (Audio y Notificaciones OS).
// Mantiene los componentes de React limpios y enfocados en la UI.

import { DEFAULT_ALERTAS_PREFS, resolveTonoPath } from '@/utils/alertasPrefs';

const PREFERRED_VOICES = [
    'Microsoft Renata Online (Natural) - Spanish (Mexico)',
    'Microsoft Dalia Online (Natural) - Spanish (Mexico)',
    'Microsoft Sabina Online (Natural) - Spanish (Mexico)',
    'Microsoft Renata - Spanish (Mexico)',
    'Microsoft Sabina - Spanish (Mexico)',
    'Paulina',
    'Monica',
    'Spanish (Mexico) Female',
    'Google español de Estados Unidos',
    'Google español',
];

class NotificationBrowserService {
    constructor() {
        this.audio = null;
        this.currentTonePath = '/assets/sounds/notification.mp3';
        this.permissionsGranted = false;
        this.preferences = { ...DEFAULT_ALERTAS_PREFS };
        this.tonosAlertas = [];

        this._selectedVoice = null;
        this._voicesReady = false;
        this._swPushActivo = false;
        this._audioUnlocked = false;

        if (typeof window !== 'undefined') {
            this._loadAudio(this.currentTonePath);
            if ('speechSynthesis' in window) {
                this._initVoice();
            }
            this._bindAudioUnlock();
        }
    }

    _bindAudioUnlock() {
        const unlock = () => {
            if (this._audioUnlocked) return;
            if (!this.audio) return;

            this._audioUnlocked = true;
            const playPromise = this.audio.play();
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        this.audio.pause();
                        this.audio.currentTime = 0;
                    })
                    .catch(() => {
                        this._audioUnlocked = false;
                    });
            }
        };

        ['click', 'touchstart', 'keydown'].forEach((eventName) => {
            window.addEventListener(eventName, unlock, { once: true, passive: true, capture: true });
        });
    }

    _loadAudio(path) {
        this.currentTonePath = path;
        this.audio = new Audio(path);
    }

    setTonosCatalog(tonos = []) {
        this.tonosAlertas = tonos;
    }

    setPreferences(prefs) {
        this.preferences = prefs;
        const path = resolveTonoPath(this.tonosAlertas, prefs?.tono_id);
        if (path !== this.currentTonePath) {
            this._loadAudio(path);
        }
    }

    setTone(toneId, path) {
        if (path && path !== this.currentTonePath) {
            this._loadAudio(path);
        }
        if (this.preferences) {
            this.preferences = { ...this.preferences, tono_id: toneId };
        }
    }

    previewTone(path) {
        if (!path) return;
        const preview = new Audio(path);
        preview.currentTime = 0;
        const playPromise = preview.play();
        if (playPromise !== undefined) {
            playPromise.catch((error) => {
                console.warn('[NotificationBrowserService] No se pudo reproducir vista previa del tono.', error);
            });
        }
    }

    _initVoice() {
        const trySelect = () => {
            const voices = window.speechSynthesis.getVoices();
            if (voices.length === 0) return;

            this._selectedVoice = this._pickBestVoice(voices);
            this._voicesReady = true;

            if (this._selectedVoice) {
                console.info(
                    `[NotificationBrowserService] Voz seleccionada: "${this._selectedVoice.name}" (${this._selectedVoice.lang})`
                );
            }
        };

        trySelect();

        if (!this._voicesReady) {
            window.speechSynthesis.addEventListener('voiceschanged', trySelect, { once: true });
        }
    }

    _pickBestVoice(voices) {
        for (const name of PREFERRED_VOICES) {
            const match = voices.find((v) => v.name === name);
            if (match) return match;
        }

        const mxVoice = voices.find((v) => v.lang === 'es-MX');
        if (mxVoice) return mxVoice;

        const esVoice = voices.find((v) => v.lang.startsWith('es'));
        if (esVoice) return esVoice;

        return null;
    }

    async requestDesktopPermissions() {
        if (!('Notification' in window)) {
            console.warn('[NotificationBrowserService] El navegador no soporta notificaciones de escritorio.');
            return;
        }

        if (Notification.permission === 'granted') {
            this.permissionsGranted = true;
        } else if (Notification.permission !== 'denied') {
            const permission = await Notification.requestPermission();
            this.permissionsGranted = permission === 'granted';
        }
    }

    playAudio(force = false) {
        if (!force && this.preferences?.canales?.sonido === false) return;
        if (!this.audio) return;

        try {
            this.audio.currentTime = 0;
            const playPromise = this.audio.play();

            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        this._audioUnlocked = true;
                    })
                    .catch((error) => {
                        console.warn(
                            '[NotificationBrowserService] Autoplay bloqueado. El usuario debe interactuar con la página primero.',
                            error
                        );
                    });
            }
        } catch (error) {
            console.error('[NotificationBrowserService] Error al reproducir audio:', error);
        }
    }

    /**
     * Siempre crea un SpeechSynthesisUtterance nuevo.
     * Los navegadores ignoran silenciosamente utterances que ya terminaron
     * de reproducirse, por lo que NO se deben cachear ni reutilizar.
     */
    _createUtterance(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'es-MX';
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        if (this._selectedVoice) {
            utterance.voice = this._selectedVoice;
        }

        utterance.onerror = (event) => {
            console.warn('[NotificationBrowserService] Error en speechSynthesis:', event.error);
        };

        return utterance;
    }

    speakText(text, force = false) {
        if (!text || typeof text !== 'string') return;
        if (!force && this.preferences?.canales?.voz === false) return;
        if (!('speechSynthesis' in window)) return;

        window.speechSynthesis.cancel();

        const utterance = this._createUtterance(text);

        if (!this._voicesReady) {
            setTimeout(() => window.speechSynthesis.speak(utterance), 100);
        } else {
            window.speechSynthesis.speak(utterance);
        }
    }

    /**
     * Verifica si hay un Service Worker activo con suscripción push.
     * Si existe, el SW ya mostrará la notificación vía Web Push,
     * por lo que no debemos duplicar con la Notification API del navegador.
     */
    _hasActiveServiceWorkerPush() {
        return this._swPushActivo === true;
    }

    /**
     * Llamado desde useWebPush una vez que se confirma la suscripción push.
     * Indica que las notificaciones de escritorio se entregarán vía SW.
     */
    setServiceWorkerPushActive(activo) {
        this._swPushActivo = activo;
    }

    showDesktopNotification(title, options, force = false, onClick = null) {
        if (!force && this.preferences?.canales?.escritorio === false) return;

        // Si hay SW push activo, no duplicar: el Service Worker ya muestra la notificación.
        if (this._hasActiveServiceWorkerPush()) return;

        if (this.permissionsGranted && Notification.permission === 'granted') {
            const notification = new Notification(title, options);
            if (onClick) {
                notification.onclick = () => {
                    window.focus();
                    onClick();
                    notification.close();
                };
            }
        }
    }

    triggerFullAlert(title, message, voiceMessage = null, options = {}) {
        const {
            sonido = this.preferences?.canales?.sonido !== false,
            voz = this.preferences?.canales?.voz !== false,
            escritorio = this.preferences?.canales?.escritorio !== false,
            onClick = null,
        } = options;

        if (sonido) this.playAudio(true);
        if (voz && voiceMessage) {
            setTimeout(() => this.speakText(voiceMessage, true), 1000);
        }
        if (escritorio) {
            this.showDesktopNotification(title, { body: message }, true, onClick);
        }
    }

    clearCache() {
        // No-op: se mantiene por retrocompatibilidad.
        // Los utterances ya no se cachean (los navegadores ignoran utterances reutilizados).
    }

    getSelectedVoiceName() {
        return this._selectedVoice?.name ?? 'Voz por defecto del sistema';
    }
}

export default new NotificationBrowserService();
