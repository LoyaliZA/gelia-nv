/** Régimen 605 (Sueldos y Salarios) exige uso S01 (Sin efectos fiscales). */
export const REGIMEN_SUELDOS_SALARIOS = '605';
export const USO_SIN_EFECTOS_FISCALES = 'S01';

export function esRegimenSueldosSalarios(codigo) {
    return String(codigo || '') === REGIMEN_SUELDOS_SALARIOS;
}

export function usoCfdiParaRegimen(regimenCodigo) {
    return esRegimenSueldosSalarios(regimenCodigo) ? USO_SIN_EFECTOS_FISCALES : null;
}
