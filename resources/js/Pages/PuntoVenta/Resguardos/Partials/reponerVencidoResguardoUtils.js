const STORAGE_REPONER = 'pdv:reponer_vencido';

export function admiteReponerVencido(resguardo) {
    return resguardo?.estado === 'en_custodia'
        && Boolean(resguardo?.clasificaciones?.vencido)
        && !resguardo?.vencido_repuesto_at;
}

export function puedeReponerVencido(permisos, resguardo) {
    return Boolean(permisos?.reponer_vencido) && admiteReponerVencido(resguardo);
}

export function claveIdempotenciaReponerVencido(resguardoId) {
    const storageKey = `${STORAGE_REPONER}:${resguardoId}`;
    let clave = sessionStorage.getItem(storageKey);

    if (!clave) {
        clave = `pdv:rep:${resguardoId}:${crypto.randomUUID()}`;
        sessionStorage.setItem(storageKey, clave);
    }

    return clave;
}

export function limpiarClaveIdempotenciaReponerVencido(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_REPONER}:${resguardoId}`);
}

export function validarFormularioReponerVencido({ motivo }) {
    const errores = {};
    const texto = String(motivo || '').trim();

    if (!texto) {
        errores.motivo = 'El motivo de reposición es obligatorio.';
    } else if (texto.length > 1000) {
        errores.motivo = 'El motivo no puede superar 1000 caracteres.';
    }

    return errores;
}

export function esConflictoVersionReponer(err) {
    return err?.response?.status === 422
        && Boolean(err?.response?.data?.errors?.version);
}

export function mensajeErrorReponerVencido(err) {
    const status = err?.response?.status;
    const data = err?.response?.data;

    if (status === 403) {
        return 'No tiene permiso para reponer resguardos vencidos en esta sucursal.';
    }

    if (status === 409) {
        return data?.message || 'Este resguardo vencido ya fue repuesto.';
    }

    if (status === 422) {
        const errores = data?.errors;
        if (errores && typeof errores === 'object') {
            const primer = Object.values(errores).flat()[0];
            if (primer) return String(primer);
        }
    }

    return data?.message || 'No se pudo reponer el resguardo vencido. Intente de nuevo.';
}

export function resumenImpactoReponerVencido(resguardo) {
    return {
        folio: resguardo?.snapshot_folio || `#${resguardo?.id}`,
        recepcionFisicaAt: resguardo?.recepcion_fisica_at,
        mantienePlazo: true,
    };
}
