import axios from 'axios';

export default {
    async listarConversaciones() {
        const { data } = await axios.get(route('mensajeria.conversaciones.list'));
        return data.conversaciones;
    },

    async crearConversacion(payload) {
        const { data } = await axios.post(route('mensajeria.conversaciones.store'), payload);
        return data.conversacion;
    },

    async buscarUsuarios(q) {
        const { data } = await axios.get(route('mensajeria.usuarios'), { params: { q } });
        return data.usuarios;
    },

    async listarMensajes(conversacionId, cursor = null) {
        const { data } = await axios.get(route('mensajeria.mensajes.index', conversacionId), {
            params: cursor ? { cursor } : {},
        });
        return data;
    },

    async enviarMensaje(conversacionId, contenido, replyToId = null) {
        const { data } = await axios.post(route('mensajeria.mensajes.store', conversacionId), {
            contenido,
            reply_to_id: replyToId,
        });
        return data.mensaje;
    },

    async enviarAdjunto(conversacionId, file, tipo, contenido = null, replyToId = null, onProgress = null) {
        const form = new FormData();
        form.append('archivo', file);
        form.append('tipo', tipo);
        if (contenido) form.append('contenido', contenido);
        if (replyToId) form.append('reply_to_id', String(replyToId));

        const { data } = await axios.post(
            route('mensajeria.adjuntos.store', conversacionId),
            form,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: onProgress,
            }
        );
        return data.mensaje;
    },

    async marcarLeida(conversacionId) {
        try {
            await axios.put(route('mensajeria.conversaciones.leer', conversacionId));
        } catch (error) {
            console.warn('[Mensajeria] No se pudo marcar como leída:', error?.response?.status);
        }
    },

    async listarMedios(conversacionId, filtros = {}) {
        const { data } = await axios.get(route('mensajeria.conversaciones.medios', conversacionId), {
            params: filtros,
        });
        return data;
    },

    urlBuscar() {
        if (typeof route === 'function' && route().has?.('mensajeria.buscar')) {
            return route('mensajeria.buscar');
        }
        return '/mensajeria/buscar';
    },

    urlContextoMensaje(conversacionId) {
        if (typeof route === 'function' && route().has?.('mensajeria.conversaciones.contexto')) {
            return route('mensajeria.conversaciones.contexto', conversacionId);
        }
        return `/mensajeria/conversaciones/${conversacionId}/contexto`;
    },

    async buscar(q, conversacionId = null) {
        const { data } = await axios.get(this.urlBuscar(), {
            params: {
                q,
                ...(conversacionId ? { conversacion_id: conversacionId } : {}),
            },
        });
        return data;
    },

    async cargarContextoMensaje(conversacionId, mensajeId) {
        const { data } = await axios.get(this.urlContextoMensaje(conversacionId), {
            params: { mensaje_id: mensajeId },
        });
        return data;
    },
};
