// Servicio dedicado a interactuar con APIs nativas del navegador (Audio y Notificaciones OS).
// Mantiene los componentes de React limpios y enfocados en la UI.

class NotificationBrowserService {
    constructor() {
        this.audio = typeof window !== 'undefined' ? new Audio('/assets/sounds/notification.mp3') : null;
        this.permissionsGranted = false;
    }

    /**
     * Solicita permisos al sistema operativo para mostrar notificaciones de escritorio.
     */
    async requestDesktopPermissions() {
        if (!("Notification" in window)) {
            console.warn("El navegador no soporta notificaciones de escritorio.");
            return;
        }

        if (Notification.permission === "granted") {
            this.permissionsGranted = true;
        } else if (Notification.permission !== "denied") {
            const permission = await Notification.requestPermission();
            this.permissionsGranted = permission === "granted";
        }
    }

    /**
     * Dispara la reproducción de audio manejando las excepciones de Autoplay.
     */
    playAudio() {
        if (!this.audio) return;
        
        try {
            this.audio.currentTime = 0; // Permite que el sonido se solape si llegan 2 alertas rápido
            const playPromise = this.audio.play();
            
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    console.warn("Autoplay bloqueado. El usuario debe hacer clic en la página primero.", error);
                });
            }
        } catch (error) {
            console.error("Error al reproducir audio:", error);
        }
    }

    /**
     * Muestra una notificación nativa en el sistema operativo.
     */
    showDesktopNotification(title, options) {
        if (this.permissionsGranted && Notification.permission === "granted") {
            new Notification(title, options);
        }
    }

    /**
     * Orquesta el audio y la alerta visual del sistema.
     */
    triggerFullAlert(title, message) {
        this.playAudio();
        this.showDesktopNotification(title, {
            body: message,
            // icon: '/assets/logo.png', // Descomenta y ajusta si tienes un logo para la alerta
        });
    }
}

export default new NotificationBrowserService();