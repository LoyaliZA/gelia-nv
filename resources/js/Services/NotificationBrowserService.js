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
        this._alertQueue = [];
        this._processing = false;
        this._stopRequested = false;

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
        this._enqueueAlertSession({
            force,
            sonido: true,
            voz: false,
            escritorio: false,
        });
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

    _speakTextDirect(text, force = false) {
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

    _speakSequentialDirect(texts = [], force = false) {
        const mensajes = (Array.isArray(texts) ? texts : []).filter((t) => typeof t === 'string' && t.trim());
        if (mensajes.length === 0) return;
        if (!force && this.preferences?.canales?.voz === false) return;
        if (!('speechSynthesis' in window)) return;

        window.speechSynthesis.cancel();

        let index = 0;
        const speakNext = () => {
            if (index >= mensajes.length) return;

            const utterance = this._createUtterance(mensajes[index]);
            utterance.onend = () => {
                index += 1;
                speakNext();
            };
            utterance.onerror = () => {
                index += 1;
                speakNext();
            };

            if (!this._voicesReady && index === 0) {
                setTimeout(() => window.speechSynthesis.speak(utterance), 100);
            } else {
                window.speechSynthesis.speak(utterance);
            }
        };

        speakNext();
    }

    speakText(text, force = false, options = {}) {
        if (options.immediate) {
            this._speakTextDirect(text, force);
            return;
        }

        if (!text || typeof text !== 'string') return;

        this._enqueueAlertSession({
            force,
            sonido: false,
            voz: true,
            escritorio: false,
            voiceMessage: text,
            onComplete: options.onComplete ?? null,
        });
    }

    speakSequentialTexts(texts = [], force = false, options = {}) {
        const mensajes = (Array.isArray(texts) ? texts : []).filter((t) => typeof t === 'string' && t.trim());
        if (mensajes.length === 0) return;

        if (options.immediate) {
            this._speakSequentialDirect(mensajes, force);
            return;
        }

        this._enqueueAlertSession({
            force,
            sonido: false,
            voz: true,
            escritorio: false,
            voiceMessages: mensajes,
            onComplete: options.onComplete ?? null,
        });
    }

    _enqueueAlertSession(session) {
        this._alertQueue.push(session);
        void this._processQueue();
    }

    async _processQueue() {
        if (this._processing) return;
        this._processing = true;

        while (this._alertQueue.length > 0 && !this._stopRequested) {
            const session = this._alertQueue.shift();
            await this._playAlertSession(session);
        }

        this._stopRequested = false;
        this._processing = false;

        if (this._alertQueue.length > 0) {
            void this._processQueue();
        }
    }

    async _playAlertSession(session) {
        if (this._stopRequested) return;

        const {
            force = false,
            sonido = false,
            voz = false,
            escritorio = false,
            title = '',
            message = '',
            voiceMessage = null,
            voiceMessages = null,
            onClick = null,
            onComplete = null,
        } = session;

        if (sonido) {
            await this._playAudioAndWait(force);
        }

        if (this._stopRequested) return;

        if (voz) {
            if (Array.isArray(voiceMessages) && voiceMessages.length > 0) {
                await this._speakSequentialAndWait(voiceMessages, force);
            } else if (voiceMessage) {
                await this._speakTextAndWait(voiceMessage, force);
            }
        }

        if (this._stopRequested) return;

        if (escritorio) {
            this.showDesktopNotification(title, { body: message }, true, onClick);
        }

        onComplete?.();
    }

    _playAudioAndWait(force = false) {
        return new Promise((resolve) => {
            if (!force && this.preferences?.canales?.sonido === false) {
                resolve();
                return;
            }
            if (!this.audio) {
                resolve();
                return;
            }

            const cleanup = () => {
                this.audio.removeEventListener('ended', onEnded);
                clearTimeout(fallback);
                resolve();
            };

            const onEnded = () => cleanup();
            const fallback = setTimeout(cleanup, 8000);

            this.audio.addEventListener('ended', onEnded);

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
                            cleanup();
                        });
                }
            } catch (error) {
                console.error('[NotificationBrowserService] Error al reproducir audio:', error);
                cleanup();
            }
        });
    }

    _speakTextAndWait(text, force = false) {
        return new Promise((resolve) => {
            if (!text || typeof text !== 'string') {
                resolve();
                return;
            }
            if (!force && this.preferences?.canales?.voz === false) {
                resolve();
                return;
            }
            if (!('speechSynthesis' in window)) {
                resolve();
                return;
            }

            const utterance = this._createUtterance(text);
            utterance.onend = () => resolve();
            utterance.onerror = () => resolve();

            if (!this._voicesReady) {
                setTimeout(() => window.speechSynthesis.speak(utterance), 100);
            } else {
                window.speechSynthesis.speak(utterance);
            }
        });
    }

    _speakSequentialAndWait(texts = [], force = false) {
        const mensajes = (Array.isArray(texts) ? texts : []).filter((t) => typeof t === 'string' && t.trim());

        return new Promise((resolve) => {
            if (mensajes.length === 0) {
                resolve();
                return;
            }
            if (!force && this.preferences?.canales?.voz === false) {
                resolve();
                return;
            }
            if (!('speechSynthesis' in window)) {
                resolve();
                return;
            }

            let index = 0;
            const speakNext = () => {
                if (this._stopRequested || index >= mensajes.length) {
                    resolve();
                    return;
                }

                const utterance = this._createUtterance(mensajes[index]);
                utterance.onend = () => {
                    index += 1;
                    speakNext();
                };
                utterance.onerror = () => {
                    index += 1;
                    speakNext();
                };

                if (!this._voicesReady && index === 0) {
                    setTimeout(() => window.speechSynthesis.speak(utterance), 100);
                } else {
                    window.speechSynthesis.speak(utterance);
                }
            };

            speakNext();
        });
    }

    stopCurrentAlert() {
        this._stopRequested = true;
        this._alertQueue = [];

        if (this.audio) {
            this.audio.pause();
            this.audio.currentTime = 0;
        }

        if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
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
            voiceMessages = null,
            onComplete = null,
            immediate = false,
        } = options;

        const session = {
            force: true,
            sonido,
            voz,
            escritorio,
            title,
            message,
            voiceMessage: voz ? voiceMessage : null,
            voiceMessages: voz ? voiceMessages : null,
            onClick,
            onComplete,
        };

        if (immediate) {
            void this._playAlertSession(session);
            return;
        }

        this._enqueueAlertSession(session);
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
