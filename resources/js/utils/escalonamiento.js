export const obtenerPorcentajeLista = (lista) => {
    if (!lista) return 0;

    if (lista.porcentaje_descuento != null && lista.porcentaje_descuento !== '') {
        return parseFloat(lista.porcentaje_descuento) || 0;
    }

    if (lista.porcentaje_escalonamiento_pct != null && lista.porcentaje_escalonamiento_pct !== '') {
        return parseFloat(lista.porcentaje_escalonamiento_pct) || 0;
    }

    const pct = lista.porcentaje_escalonamiento;
    if (!pct) return 0;
    if (typeof pct === 'object' && pct !== null) {
        if (pct.activo === false || pct.activo === 0) return 0;
        return parseFloat(pct.porcentaje_descuento || 0);
    }
    return parseFloat(pct || 0);
};

export const buscarListaPorId = (catalogoListas, id) => {
    if (!id) return null;
    return catalogoListas.find(l => String(l.id) === String(id)) || null;
};

export const calcularMontoFinalTentativo = (montoCotizado, porcentaje) =>
    Math.round(montoCotizado * (1 - porcentaje / 100) * 100) / 100;

export const calcularMontoBrutoNecesario = (faltanteNeto, porcentaje) => {
    if (faltanteNeto <= 0) return 0;
    const mult = 1 - porcentaje / 100;
    return mult <= 0 ? faltanteNeto : Math.round((faltanteNeto / mult) * 100) / 100;
};

export const fmtMontoEscalonamiento = (valor) =>
    `$${Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export const parseMontoHistorico = (cliente) =>
    parseFloat(cliente?.monto_venta_actual?.toString().replace(/[^0-9.-]+/g, '') || 0);

export const filtrarListasValidas = (catalogoListas) =>
    [...catalogoListas]
        .filter(l => !l.nombre.toUpperCase().includes('COLABORADOR') && !l.nombre.toUpperCase().includes('PLATAFORMAS'))
        .sort((a, b) => parseFloat(b.monto_requerido) - parseFloat(a.monto_requerido));

const NIVELES_MAYORES = ['PLATA', 'ORO', 'DIAMANTE'];

export const filtrarListasNivelesMayores = (catalogoListas) =>
    [...catalogoListas]
        .filter(l => {
            const nombre = l.nombre.toUpperCase();
            if (nombre.includes('PLATAFORMAS') || nombre.includes('COLABORADOR')) return false;
            return NIVELES_MAYORES.some(nivel => nombre.includes(nivel));
        })
        .sort((a, b) => parseFloat(a.monto_requerido) - parseFloat(b.monto_requerido));

export const evaluarEscalonamiento = (cliente, cotizacion, catalogoListas, listaActualObj = null, listaSolicitadaId = null) => {
    const montoHistorico = parseMontoHistorico(cliente);
    const montoCotizado = parseFloat(cotizacion || 0);

    const listasValidas = filtrarListasValidas(catalogoListas);
    const totalProyectadoBruto = montoHistorico + montoCotizado;

    const listaCalificadaBruto = listasValidas.find(l => totalProyectadoBruto >= parseFloat(l.monto_requerido)) || null;

    const listaActual = listaActualObj
        ?? catalogoListas.find(l => l.id == cliente?.lista_actual_id || l.nombre === cliente?.lista_actual)
        ?? null;
    const requisitoListaActual = listaActual ? parseFloat(listaActual.monto_requerido || 0) : 0;
    const esAscenso = listaCalificadaBruto && parseFloat(listaCalificadaBruto.monto_requerido) > requisitoListaActual;

    const listaAnticipada = listaCalificadaBruto;

    const listaSolicitada = listaSolicitadaId
        ? buscarListaPorId(catalogoListas, listaSolicitadaId)
        : (esAscenso && listaCalificadaBruto ? listaCalificadaBruto : null);

    const porcentajeDescuento = obtenerPorcentajeLista(listaAnticipada);
    const montoFinalTentativo = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
    const totalProyectadoNeto = montoHistorico + montoFinalTentativo;

    const listaCalificadaNeto = listasValidas.find(l => totalProyectadoNeto >= parseFloat(l.monto_requerido)) || null;

    const listasAscendentes = [...listasValidas].sort((a, b) => parseFloat(a.monto_requerido) - parseFloat(b.monto_requerido));
    const listaSiguienteNeto = listasAscendentes.find(l => parseFloat(l.monto_requerido) > totalProyectadoNeto) || null;
    const faltanteNetoSiguiente = listaSiguienteNeto
        ? Math.max(0, parseFloat(listaSiguienteNeto.monto_requerido) - totalProyectadoNeto)
        : 0;
    const porcentajeSiguiente = listaSiguienteNeto
        ? obtenerPorcentajeLista(listaSiguienteNeto)
        : porcentajeDescuento;
    const montoBrutoParaSiguiente = calcularMontoBrutoNecesario(faltanteNetoSiguiente, porcentajeSiguiente);

    let mantieneListaAnticipada = true;
    let faltanteNetoMantener = 0;
    let montoBrutoParaMantener = 0;
    let brutoCalificaNetoNo = false;

    if (listaAnticipada) {
        const umbral = parseFloat(listaAnticipada.monto_requerido);
        mantieneListaAnticipada = totalProyectadoNeto >= umbral;
        faltanteNetoMantener = Math.max(0, umbral - totalProyectadoNeto);
        montoBrutoParaMantener = calcularMontoBrutoNecesario(faltanteNetoMantener, porcentajeDescuento);
        brutoCalificaNetoNo = totalProyectadoBruto >= umbral && !mantieneListaAnticipada;
    }

    const desgloseListas = listasAscendentes.map(l => ({
        id: l.id,
        nombre: l.nombre,
        monto_requerido: parseFloat(l.monto_requerido),
        cubre: totalProyectadoNeto >= parseFloat(l.monto_requerido),
    }));

    return {
        montoHistorico,
        montoCotizado,
        porcentajeDescuento,
        porcentajeSiguiente,
        montoFinalTentativo,
        totalProyectadoBruto,
        totalProyectadoNeto,
        listaCalificadaBruto,
        listaCalificadaNeto,
        listaAnticipada,
        listaSiguienteNeto,
        faltanteNetoSiguiente,
        montoBrutoParaSiguiente,
        mantieneListaAnticipada,
        mantieneListaSolicitada: mantieneListaAnticipada,
        faltanteNetoMantener,
        montoBrutoParaMantener,
        brutoCalificaNetoNo,
        esAscenso,
        desgloseListas,
        listaSolicitada,
    };
};

export const desgloseSimulacionPorLista = (cliente, montoCotizadoInput, catalogoListas) => {
    const montoHistorico = parseMontoHistorico(cliente);
    const montoCotizado = parseFloat(montoCotizadoInput || 0);
    const totalProyectadoBruto = montoHistorico + montoCotizado;

    return filtrarListasNivelesMayores(catalogoListas).map(lista => {
        const montoRequerido = parseFloat(lista.monto_requerido);
        const porcentajeDescuento = obtenerPorcentajeLista(lista);
        const montoCotizadoNeto = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
        const totalProyectadoNeto = montoHistorico + montoCotizadoNeto;
        const calificaBruto = totalProyectadoBruto >= montoRequerido;
        const calificaNeto = totalProyectadoNeto >= montoRequerido;
        const faltanteNeto = Math.max(0, montoRequerido - totalProyectadoNeto);
        const montoBrutoAdicional = calcularMontoBrutoNecesario(faltanteNeto, porcentajeDescuento);

        return {
            id: lista.id,
            nombre: lista.nombre,
            monto_requerido: montoRequerido,
            porcentaje_descuento: porcentajeDescuento,
            monto_cotizado_neto: montoCotizadoNeto,
            total_proyectado_bruto: totalProyectadoBruto,
            total_proyectado_neto: totalProyectadoNeto,
            califica_bruto: calificaBruto,
            califica_neto: calificaNeto,
            faltante_neto: faltanteNeto,
            monto_bruto_adicional: montoBrutoAdicional,
        };
    });
};
