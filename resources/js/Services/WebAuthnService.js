import axios from 'axios';
import {
    startAuthentication,
    startRegistration,
    browserSupportsWebAuthn,
} from '@simplewebauthn/browser';

function hexToBase64Url(hex) {
    const normalized = String(hex).replace(/-/g, '');
    const bytes = new Uint8Array(normalized.length / 2);
    for (let i = 0; i < normalized.length; i += 2) {
        bytes[i / 2] = Number.parseInt(normalized.slice(i, i + 2), 16);
    }
    let binary = '';
    bytes.forEach((b) => {
        binary += String.fromCharCode(b);
    });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function prepararOpcionesRegistro(optionsJSON) {
    const user = optionsJSON.user ? { ...optionsJSON.user } : optionsJSON.user;
    if (user?.id && /^[0-9a-f-]{32,36}$/i.test(user.id)) {
        user.id = hexToBase64Url(user.id);
    }
    return { ...optionsJSON, user };
}

function mensajeErrorPasskey(error, respaldo) {
    const name = error?.name || '';
    if (name === 'NotAllowedError') {
        return 'La verificación se canceló o el dispositivo no respondió.';
    }
    if (name === 'InvalidStateError') {
        return 'Esta passkey ya está registrada en este dispositivo.';
    }
    if (name === 'NotSupportedError') {
        return 'Este dispositivo no es compatible con el acceso por huella.';
    }
    if (error?.response?.data?.message) {
        return error.response.data.message;
    }
    return error?.message || respaldo;
}

const headers = { Accept: 'application/json' };

const WebAuthnService = {
    soportado() {
        return typeof window !== 'undefined' && browserSupportsWebAuthn();
    },

    nicknameEquipo() {
        if (typeof window === 'undefined') {
            return 'Este equipo';
        }

        return window.navigator.userAgentData?.platform
            || window.navigator.platform
            || 'Este equipo';
    },

    async loginConPasskey(login, remember = true) {
        const { data: optionsJSON } = await axios.post('/api/v1/passkeys/login/options', {
            login,
            client: 'web',
        }, { headers });

        if (!optionsJSON?.allowCredentials?.length) {
            const error = new Error('No hay una passkey registrada para esta cuenta en este dispositivo.');
            error.code = 'sin_credenciales';
            throw error;
        }

        const credential = await startAuthentication({ optionsJSON });
        const { data } = await axios.post('/api/v1/passkeys/login/verify', {
            login,
            client: 'web',
            remember,
            credential,
        }, { headers });

        return data;
    },

    async registrarPasskey(nickname, platform = 'web') {
        const { data: optionsJSON } = await axios.post('/api/v1/passkeys/register/options', {
            client: 'web',
            nickname,
            platform,
        }, { headers });

        const credential = await startRegistration({
            optionsJSON: prepararOpcionesRegistro(optionsJSON),
        });

        const { data } = await axios.post('/api/v1/passkeys/register', {
            client: 'web',
            nickname,
            platform,
            credential,
        }, { headers });

        return data;
    },

    async listar() {
        const { data } = await axios.get('/api/v1/passkeys', { headers });
        return data.data || [];
    },

    async revocar(id) {
        const { data } = await axios.delete(`/api/v1/passkeys/${encodeURIComponent(id)}`, { headers });
        return data;
    },

    async renombrar(id, nickname) {
        const { data } = await axios.patch(`/api/v1/passkeys/${encodeURIComponent(id)}`, { nickname }, { headers });
        return data;
    },

    mensajeErrorPasskey,
};

export default WebAuthnService;
