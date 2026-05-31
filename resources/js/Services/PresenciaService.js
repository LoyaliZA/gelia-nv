import axios from 'axios';

function tieneRuta(nombre) {
    try {
        return typeof route === 'function' && route().has?.(nombre);
    } catch {
        return false;
    }
}

function urlCatalogo() {
    if (tieneRuta('mensajeria.presencia.catalogo')) {
        return route('mensajeria.presencia.catalogo');
    }
    return '/mensajeria/presencia/catalogo';
}

function urlShow() {
    if (tieneRuta('mensajeria.presencia.show')) {
        return route('mensajeria.presencia.show');
    }
    return '/mensajeria/presencia';
}

function urlUpdate() {
    if (tieneRuta('mensajeria.presencia.update')) {
        return route('mensajeria.presencia.update');
    }
    return '/mensajeria/presencia';
}

function urlHeartbeat() {
    if (tieneRuta('mensajeria.presencia.heartbeat')) {
        return route('mensajeria.presencia.heartbeat');
    }
    return '/mensajeria/presencia/heartbeat';
}

export default {
    async catalogo() {
        const { data } = await axios.get(urlCatalogo());
        return data;
    },

    async obtener() {
        const { data } = await axios.get(urlShow());
        return data;
    },

    async actualizar(payload) {
        const { data } = await axios.put(urlUpdate(), payload);
        if (!data?.presencia?.estado) {
            throw new Error('El servidor no devolvió el estado actualizado.');
        }
        return data.presencia;
    },

    async heartbeat() {
        const { data } = await axios.post(urlHeartbeat());
        return data.presencia;
    },
};
