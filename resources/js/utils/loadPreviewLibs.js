const XLSX_CDN = 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js';
const MAMMOTH_CDN = 'https://cdn.jsdelivr.net/npm/mammoth@1.11.0/mammoth.browser.min.js';

let xlsxPromise = null;
let mammothPromise = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const prev = document.querySelector(`script[data-gelia-preview="${src}"]`);
        if (prev) {
            if (prev.dataset.loaded === '1') {
                resolve();
                return;
            }
            prev.addEventListener('load', () => resolve());
            prev.addEventListener('error', () => reject(new Error(`No se pudo cargar ${src}`)));
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.geliaPreview = src;
        script.onload = () => {
            script.dataset.loaded = '1';
            resolve();
        };
        script.onerror = () => reject(new Error(`No se pudo cargar ${src}`));
        document.head.appendChild(script);
    });
}

export function loadXlsx() {
    if (typeof window !== 'undefined' && window.XLSX) {
        return Promise.resolve(window.XLSX);
    }

    if (!xlsxPromise) {
        xlsxPromise = loadScript(XLSX_CDN).then(() => {
            if (!window.XLSX) {
                throw new Error('SheetJS no disponible');
            }
            return window.XLSX;
        });
    }

    return xlsxPromise;
}

export function loadMammoth() {
    if (typeof window !== 'undefined' && window.mammoth) {
        return Promise.resolve(window.mammoth);
    }

    if (!mammothPromise) {
        mammothPromise = loadScript(MAMMOTH_CDN).then(() => {
            if (!window.mammoth) {
                throw new Error('Mammoth no disponible');
            }
            return window.mammoth;
        });
    }

    return mammothPromise;
}
