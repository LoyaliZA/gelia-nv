/**
 * IDs de grupos raíz (depth 0) en el árbol de navegación.
 */
export function collectRootGroupIds(tree) {
    return (tree || [])
        .filter((node) => node?.type === 'group')
        .map((node) => node.id);
}

/**
 * Toggle de acordeón: en raíz solo una sección abierta a la vez.
 * @returns {{ next: Record<string, boolean>, scrollGroupId: string|null }}
 */
export function computeExclusiveGroupToggle(prev, id, rootGroupIds, depth = 0) {
    const opening = !prev[id];

    if (depth !== 0) {
        return {
            next: { ...prev, [id]: opening },
            scrollGroupId: opening ? id : null,
        };
    }

    if (!opening) {
        return { next: { ...prev, [id]: false }, scrollGroupId: null };
    }

    const next = { ...prev };
    rootGroupIds.forEach((rootId) => {
        if (rootId !== id) next[rootId] = false;
    });
    next[id] = true;
    return { next, scrollGroupId: id };
}

/**
 * Al sincronizar por ruta: abrir ancestros de la página activa sin cerrar secciones
 * que el usuario abrió manualmente (el acordeón exclusivo aplica solo en toggle).
 */
export function mergeRouteOpenGroups(prev, routeOpenIds) {
    const next = { ...prev };

    routeOpenIds.forEach((id) => {
        next[id] = true;
    });

    return next;
}
