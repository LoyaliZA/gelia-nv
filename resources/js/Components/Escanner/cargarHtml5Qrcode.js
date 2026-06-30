let promesaCarga = null;

export function cargarHtml5Qrcode() {
    if (!promesaCarga) {
        promesaCarga = import('html5-qrcode').then((mod) => ({
            Html5Qrcode: mod.Html5Qrcode,
            Html5QrcodeSupportedFormats: mod.Html5QrcodeSupportedFormats,
        }));
    }
    return promesaCarga;
}
