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
 * Al sincronizar por ruta: mantener ancestros abiertos pero solo una raíz activa.
 */
export function mergeRouteOpenGroups(prev, routeOpenIds, rootGroupIds) {
    const next = { ...prev };
    const routeRoots = rootGroupIds.filter((id) => routeOpenIds.has(id));

    routeOpenIds.forEach((id) => {
        next[id] = true;
    });

    if (routeRoots.length > 1) {
        const keepRoot = routeRoots[routeRoots.length - 1];
        rootGroupIds.forEach((rootId) => {
            if (rootId !== keepRoot) next[rootId] = false;
        });
    }

    return next;
}
