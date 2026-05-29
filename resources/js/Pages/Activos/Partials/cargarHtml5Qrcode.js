const SCRIPT_URL = '/vendor/html5-qrcode.min.js';

let promesaCarga = null;

function obtenerModulo() {
    const lib = window.__Html5QrcodeLibrary__;
    const Html5Qrcode = lib?.Html5Qrcode;
    const Html5QrcodeSupportedFormats = lib?.Html5QrcodeSupportedFormats;

    if (!Html5Qrcode || !Html5QrcodeSupportedFormats) {
        throw new Error('No se pudo cargar el escáner de códigos.');
    }

    return { Html5Qrcode, Html5QrcodeSupportedFormats };
}

function estaCargado() {
    return Boolean(window.__Html5QrcodeLibrary__?.Html5Qrcode);
}

export function cargarHtml5Qrcode() {
    if (estaCargado()) {
        return Promise.resolve(obtenerModulo());
    }

    if (!promesaCarga) {
        promesaCarga = new Promise((resolve, reject) => {
            const finalizar = () => {
                try {
                    resolve(obtenerModulo());
                } catch (err) {
                    reject(err);
                }
            };

            const existente = document.querySelector(`script[src="${SCRIPT_URL}"]`);
            if (existente) {
                if (estaCargado()) {
                    finalizar();
                    return;
                }
                existente.addEventListener('load', finalizar, { once: true });
                existente.addEventListener('error', () => reject(new Error('Error al cargar el escáner.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            script.onload = finalizar;
            script.onerror = () => reject(new Error('Error al cargar el escáner.'));
            document.head.appendChild(script);
        });
    }

    return promesaCarga;
}
