/** Una cola por apertura del modal. Un 409 la detiene hasta reconciliación explícita. */
export class ColaGuardadoPedido {
    constructor() { this.cola = Promise.resolve(); this.conflicto = false; this.cerrada = false; }
    ejecutar(accion) {
        const trabajo = this.cola.catch(() => {}).then(async () => {
            if (this.cerrada || this.conflicto) throw new Error('Revise la captura antes de continuar.');
            try { return await accion(); }
            catch (error) { if (error?.response?.status === 409) this.conflicto = true; throw error; }
        });
        this.cola = trabajo;
        return trabajo;
    }
}
