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

export const umbralEfectivo = (lista) => {
    if (!lista) return 0;
    return calcularMontoBrutoNecesario(parseFloat(lista.monto_requerido) || 0, obtenerPorcentajeLista(lista));
};

export const fmtMontoEscalonamiento = (valor) =>
    `$${Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export const parseMontoHistorico = (cliente) =>
    parseFloat(cliente?.monto_venta_actual?.toString().replace(/[^0-9.-]+/g, '') || 0);

export const filtrarListasValidas = (catalogoListas) =>
    [...catalogoListas]
        .filter(l => !l.nombre.toUpperCase().includes('COLABORADOR') && !l.nombre.toUpperCase().includes('PLATAFORMAS'))
        .sort((a, b) => parseFloat(b.monto_requerido) - parseFloat(a.monto_requerido));

export const resolverListaPorMonto = (monto, catalogoListas) => {
    const listasValidas = filtrarListasValidas(catalogoListas);
    return listasValidas.find(l => monto >= umbralEfectivo(l)) || null;
};

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
    const listaCalificadaEfectiva = resolverListaPorMonto(totalProyectadoBruto, listasValidas);

    const listaActual = listaActualObj
        ?? catalogoListas.find(l => l.id == cliente?.lista_actual_id || l.nombre === cliente?.lista_actual)
        ?? null;
    const requisitoListaActual = listaActual ? parseFloat(listaActual.monto_requerido || 0) : 0;
    const esAscenso = listaCalificadaEfectiva
        && parseFloat(listaCalificadaEfectiva.monto_requerido) > requisitoListaActual;

    const listaAnticipada = listaCalificadaEfectiva;
    const porcentajeDescuento = obtenerPorcentajeLista(listaAnticipada);
    const umbralEfectivoAnticipada = listaAnticipada ? umbralEfectivo(listaAnticipada) : 0;

    const listaSolicitada = listaSolicitadaId
        ? buscarListaPorId(catalogoListas, listaSolicitadaId)
        : (esAscenso && listaCalificadaEfectiva ? listaCalificadaEfectiva : null);

    const montoFinalTentativo = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
    const totalProyectadoNeto = montoHistorico + montoFinalTentativo;

    const listaCalificadaNeto = resolverListaPorMonto(totalProyectadoNeto, listasValidas);

    const listasPorUmbral = [...listasValidas].sort((a, b) => umbralEfectivo(a) - umbralEfectivo(b));
    const listaSiguienteEfectiva = listasPorUmbral.find(l => umbralEfectivo(l) > totalProyectadoBruto) || null;
    const porcentajeSiguiente = listaSiguienteEfectiva
        ? obtenerPorcentajeLista(listaSiguienteEfectiva)
        : porcentajeDescuento;
    const umbralEfectivoSiguiente = listaSiguienteEfectiva ? umbralEfectivo(listaSiguienteEfectiva) : 0;
    const faltanteBrutoParaSiguiente = listaSiguienteEfectiva
        ? Math.max(0, umbralEfectivoSiguiente - totalProyectadoBruto)
        : 0;
    const faltanteNetoSiguiente = listaSiguienteEfectiva
        ? Math.max(0, parseFloat(listaSiguienteEfectiva.monto_requerido) - totalProyectadoNeto)
        : 0;
    const montoBrutoParaSiguiente = faltanteBrutoParaSiguiente;

    let casiAlcanzaSiguiente = false;
    let listaCasiAlcanzada = null;
    let faltanteBrutoCasi = 0;
    let umbralEfectivoCasi = 0;

    if (listaCalificadaBruto
        && (!listaCalificadaEfectiva
            || parseFloat(listaCalificadaBruto.monto_requerido) > parseFloat(listaCalificadaEfectiva.monto_requerido))
    ) {
        casiAlcanzaSiguiente = true;
        listaCasiAlcanzada = listaCalificadaBruto;
        umbralEfectivoCasi = umbralEfectivo(listaCalificadaBruto);
        faltanteBrutoCasi = Math.max(0, umbralEfectivoCasi - totalProyectadoBruto);
    }

    const mantieneListaAnticipada = listaAnticipada
        ? totalProyectadoBruto >= umbralEfectivoAnticipada
        : true;

    const listasAscendentes = [...listasValidas].sort((a, b) => parseFloat(a.monto_requerido) - parseFloat(b.monto_requerido));
    const desgloseListas = listasAscendentes.map(l => ({
        id: l.id,
        nombre: l.nombre,
        monto_requerido: parseFloat(l.monto_requerido),
        umbral_efectivo: umbralEfectivo(l),
        cubre: totalProyectadoBruto >= umbralEfectivo(l),
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
        listaCalificadaEfectiva,
        listaCalificadaNeto,
        listaAnticipada,
        listaSiguienteNeto: listaSiguienteEfectiva,
        listaSiguienteEfectiva,
        faltanteNetoSiguiente,
        faltanteBrutoParaSiguiente,
        montoBrutoParaSiguiente,
        umbralEfectivoAnticipada,
        umbralEfectivoSiguiente,
        mantieneListaAnticipada,
        mantieneListaSolicitada: mantieneListaAnticipada,
        faltanteNetoMantener: 0,
        montoBrutoParaMantener: faltanteBrutoCasi,
        casiAlcanzaSiguiente,
        listaCasiAlcanzada,
        faltanteBrutoCasi,
        umbralEfectivoCasi,
        brutoCalificaNetoNo: casiAlcanzaSiguiente,
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
        const umbral = umbralEfectivo(lista);
        const montoCotizadoNeto = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
        const totalProyectadoNeto = montoHistorico + montoCotizadoNeto;
        const calificaEfectivo = totalProyectadoBruto >= umbral;
        const calificaBruto = totalProyectadoBruto >= montoRequerido;
        const calificaNeto = calificaEfectivo;
        const faltanteBruto = Math.max(0, umbral - totalProyectadoBruto);
        const faltanteNeto = Math.max(0, montoRequerido - totalProyectadoNeto);

        return {
            id: lista.id,
            nombre: lista.nombre,
            monto_requerido: montoRequerido,
            umbral_efectivo: umbral,
            porcentaje_descuento: porcentajeDescuento,
            monto_cotizado_neto: montoCotizadoNeto,
            total_proyectado_bruto: totalProyectadoBruto,
            total_proyectado_neto: totalProyectadoNeto,
            califica_bruto: calificaBruto,
            califica_neto: calificaNeto,
            califica_efectivo: calificaEfectivo,
            faltante_neto: faltanteNeto,
            faltante_bruto: faltanteBruto,
            monto_bruto_adicional: faltanteBruto,
        };
    });
};
