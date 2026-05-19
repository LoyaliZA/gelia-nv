// Servicio dedicado a interactuar con APIs nativas del navegador (Audio y Notificaciones OS).
// Mantiene los componentes de React limpios y enfocados en la UI.

// Voces neurales preferidas por navegador/SO, ordenadas de mejor a peor calidad.
// El servicio elige automáticamente la primera disponible en el sistema del usuario.
const PREFERRED_VOICES = [
    // Windows – voces neurales de Microsoft (requieren descarga en Configuración → Voz)
    'Microsoft Renata Online (Natural) - Spanish (Mexico)',  // Windows 11, la mejor
    'Microsoft Dalia Online (Natural) - Spanish (Mexico)',   // Windows 11, alternativa
    'Microsoft Sabina Online (Natural) - Spanish (Mexico)',  // Windows 10/11
    'Microsoft Renata - Spanish (Mexico)',                   // Windows 10 offline
    'Microsoft Sabina - Spanish (Mexico)',                   // Windows 10 offline
    // macOS / iOS
    'Paulina',   // macOS – es-MX, muy natural
    'Monica',    // macOS – es-ES, fallback aceptable
    // Android Chrome
    'Spanish (Mexico) Female',
    // Google Chrome en cualquier OS (voz en la nube, requiere internet)
    'Google español de Estados Unidos',
    'Google español',
];

class NotificationBrowserService {
    constructor() {
        this.audio = typeof window !== 'undefined'
            ? new Audio('/assets/sounds/notification.mp3')
            : null;
        this.permissionsGranted = false;

        // Caché de texto → SpeechSynthesisUtterance ya configurada con la mejor voz.
        // Evita recalcular la voz y reasignar propiedades en cada llamada.
        this._utteranceCache = new Map();

        // La lista de voces se carga de forma asíncrona en muchos navegadores.
        // Guardamos la referencia a la voz elegida cuando esté lista.
        this._selectedVoice = null;
        this._voicesReady = false;

        if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
            this._initVoice();
        }
    }

    // ─────────────────────────────────────────────
    //  INICIALIZACIÓN DE VOZ
    // ─────────────────────────────────────────────

    /**
     * Espera a que el navegador cargue las voces disponibles y elige la mejor.
     * Chrome/Edge disparan el evento 'voiceschanged'; Safari y Firefox las tienen
     * disponibles de inmediato.
     */
    _initVoice() {
        const trySelect = () => {
            const voices = window.speechSynthesis.getVoices();
            if (voices.length === 0) return; // Aún no cargaron

            this._selectedVoice = this._pickBestVoice(voices);
            this._voicesReady = true;

            if (this._selectedVoice) {
                console.info(
                    `[NotificationBrowserService] Voz seleccionada: "${this._selectedVoice.name}" (${this._selectedVoice.lang})`
                );
            } else {
                console.warn(
                    '[NotificationBrowserService] No se encontró voz neural preferida. ' +
                    'Se usará la voz por defecto del sistema. ' +
                    'En Windows: Configuración → Hora e idioma → Voz → Agregar voces → busca "Renata" o "Dalia".'
                );
            }
        };

        // Intento inmediato (Firefox, Safari)
        trySelect();

        // Escucha el evento para Chrome/Edge/Brave (las voces llegan después)
        if (!this._voicesReady) {
            window.speechSynthesis.addEventListener('voiceschanged', trySelect, { once: true });
        }
    }

    /**
     * Recorre PREFERRED_VOICES en orden y devuelve la primera que exista en el sistema.
     * Si no hay ninguna coincidencia exacta, intenta encontrar cualquier voz es-MX,
     * luego cualquier voz es-*, y como último recurso devuelve null (voz por defecto).
     * @param {SpeechSynthesisVoice[]} voices
     * @returns {SpeechSynthesisVoice|null}
     */
    _pickBestVoice(voices) {
        // 1. Coincidencia exacta con la lista de preferidas
        for (const name of PREFERRED_VOICES) {
            const match = voices.find(v => v.name === name);
            if (match) return match;
        }

        // 2. Cualquier voz es-MX disponible
        const mxVoice = voices.find(v => v.lang === 'es-MX');
        if (mxVoice) return mxVoice;

        // 3. Cualquier voz en español
        const esVoice = voices.find(v => v.lang.startsWith('es'));
        if (esVoice) return esVoice;

        // 4. Sin coincidencia → el navegador usará su voz por defecto
        return null;
    }

    // ─────────────────────────────────────────────
    //  PERMISOS DE NOTIFICACIÓN
    // ─────────────────────────────────────────────

    /**
     * Solicita permisos al sistema operativo para mostrar notificaciones de escritorio.
     */
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

    // ─────────────────────────────────────────────
    //  AUDIO
    // ─────────────────────────────────────────────

    /**
     * Dispara la reproducción de audio manejando las restricciones de Autoplay.
     */
    playAudio() {
        if (!this.audio) return;

        try {
            this.audio.currentTime = 0; // Permite solapar sonidos si llegan 2 alertas rápido
            const playPromise = this.audio.play();

            if (playPromise !== undefined) {
                playPromise.catch(error => {
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

    // ─────────────────────────────────────────────
    //  SÍNTESIS DE VOZ CON CACHÉ
    // ─────────────────────────────────────────────

    /**
     * Devuelve un SpeechSynthesisUtterance listo para usar.
     * Si el mismo texto ya fue procesado antes, reutiliza el objeto cacheado
     * para evitar reconstruirlo (la voz y las propiedades ya están asignadas).
     *
     * Nota: el caché nunca crece indefinidamente; se limita a MAX_CACHE_SIZE entradas.
     * @param {string} text
     * @returns {SpeechSynthesisUtterance}
     */
    _getUtterance(text) {
        const MAX_CACHE_SIZE = 50;

        if (this._utteranceCache.has(text)) {
            return this._utteranceCache.get(text);
        }

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang  = 'es-MX';
        utterance.rate  = 1.0;
        utterance.pitch = 1.0;

        // Asigna la mejor voz si ya está disponible
        if (this._selectedVoice) {
            utterance.voice = this._selectedVoice;
        }

        // Limpia la entrada más antigua si se alcanza el límite
        if (this._utteranceCache.size >= MAX_CACHE_SIZE) {
            const firstKey = this._utteranceCache.keys().next().value;
            this._utteranceCache.delete(firstKey);
        }

        this._utteranceCache.set(text, utterance);
        return utterance;
    }

    /**
     * Lee el texto en voz alta usando la mejor voz neural disponible en el sistema.
     *
     * Compatibilidad:
     *  ✅ Chrome, Edge, Brave  (Windows/macOS/Android)
     *  ✅ Safari               (macOS/iOS)
     *  ✅ Firefox              (usa voces del SO en Windows/macOS)
     *  ⚠️  Firefox Linux       (requiere espeak-ng instalado en el sistema)
     *
     * @param {string} text  Texto a leer.
     */
    speakText(text) {
        if (!('speechSynthesis' in window)) return;

        // Cancela la lectura en curso para no encolar retrasos
        window.speechSynthesis.cancel();

        const utterance = this._getUtterance(text);

        // Si las voces aún no cargaron (raro, solo en Chrome muy lento al inicio),
        // esperamos un ciclo de evento antes de hablar.
        if (!this._voicesReady) {
            setTimeout(() => window.speechSynthesis.speak(utterance), 100);
        } else {
            window.speechSynthesis.speak(utterance);
        }
    }

    // ─────────────────────────────────────────────
    //  NOTIFICACIÓN DE ESCRITORIO
    // ─────────────────────────────────────────────

    /**
     * Muestra una notificación nativa en el sistema operativo.
     */
    showDesktopNotification(title, options) {
        if (this.permissionsGranted && Notification.permission === 'granted') {
            new Notification(title, options);
        }
    }

    // ─────────────────────────────────────────────
    //  ALERTA COMPLETA
    // ─────────────────────────────────────────────

    /**
     * Orquesta el audio, la síntesis de voz y la notificación visual del sistema.
     *
     * @param {string}      title         Título de la notificación del SO.
     * @param {string}      message       Cuerpo de la notificación del SO.
     * @param {string|null} voiceMessage  Texto a leer en voz alta (null = sin voz).
     */
    triggerFullAlert(title, message, voiceMessage = null) {
        this.playAudio();

        if (voiceMessage) {
            // Retraso de 1 segundo para que suene la campana antes que la voz
            setTimeout(() => this.speakText(voiceMessage), 1000);
        }

        this.showDesktopNotification(title, {
            body: message,
            // icon: '/assets/logo.png',
        });
    }

    // ─────────────────────────────────────────────
    //  UTILIDADES
    // ─────────────────────────────────────────────

    /**
     * Limpia el caché de utterances manualmente (útil si cambias el idioma en runtime).
     */
    clearCache() {
        this._utteranceCache.clear();
    }

    /**
     * Devuelve el nombre de la voz actualmente seleccionada (útil para debugging).
     * @returns {string}
     */
    getSelectedVoiceName() {
        return this._selectedVoice?.name ?? 'Voz por defecto del sistema';
    }
}

export default new NotificationBrowserService();