import axios from 'axios';
import { router } from '@inertiajs/react';

export function esCodigoConsultaActivo(texto) {
    const valor = String(texto || '').trim();
    if (!valor) return false;
    if (/\/activos\/consulta\/[a-f0-9-]{36}/i.test(valor)) return true;
    return /^[a-f0-9-]{36}$/i.test(valor);
}

export async function resolverCodigoEscaneado(codigo, { redirigir = true } = {}) {
    const { data } = await axios.get(route('activos.resolver_codigo'), {
        params: { codigo },
    });

    if (redirigir && data?.id) {
        router.visit(route('activos.show', data.id));
    }

    return data;
}
