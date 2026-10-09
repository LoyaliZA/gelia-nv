export const DEPLOY_INTERVALO_MS = 8 * 60 * 1000;
export const DEPLOY_REINTENTO_AUDIO_MS = 15 * 1000;
export const DEPLOY_REINTENTO_AUDIO_TOPE_MS = 2 * 60 * 1000;

const oyentesConsulta = new Set();

export function alConsultarDeploy(oyente) {
    oyentesConsulta.add(oyente);
    return () => oyentesConsulta.delete(oyente);
}

export function notificarConsultaDeploy() {
    oyentesConsulta.forEach((oyente) => oyente());
}

export function versionSuperficieCargada(superficie) {
    if (typeof document === 'undefined') return '';
    return document.querySelector(`meta[name="gelia-deploy-${superficie}"]`)?.getAttribute('content') || '';
}

export function decidirRecargaDeploy({
    versionCargada,
    versionRemota,
    intentoPrevio = '',
    enReposo = true,
}) {
    if (!versionCargada || !versionRemota || versionCargada === versionRemota) {
        return 'nada';
    }
    if (intentoPrevio === versionRemota) {
        return 'nada';
    }
    if (!enReposo) {
        return 'esperar';
    }
    return 'recargar';
}

export function vistaEnReposo(mutacionesEnCurso = 0) {
    if (mutacionesEnCurso > 0) return false;
    if (typeof document === 'undefined') return true;
    return !document.querySelector('[role="dialog"], [role="alertdialog"]');
}
