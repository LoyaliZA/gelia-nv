export const GELIA_TASKS_KEY = 'gelia_background_tasks';
export const GELIA_TASKS_POS_KEY = 'gelia_background_tasks_pos';
export const GELIA_TASK_STARTED = 'gelia-background-task-started';
export const GELIA_TASK_DISMISSED = 'gelia-background-task-dismissed';
export const GELIA_TASK_LOCAL = 'gelia-background-task-local';

const TN_POS_KEY = 'gelia_tiendanube_image_import_pos';

function readTasks() {
    if (typeof window === 'undefined') return [];
    try {
        const raw = JSON.parse(localStorage.getItem(GELIA_TASKS_KEY) || '[]');
        if (!Array.isArray(raw)) return [];
        return raw
            .filter((task) => task && task.module && task.id != null && task.id !== '')
            .map((task) => ({ module: String(task.module), id: task.id }));
    } catch {
        return [];
    }
}

function writeTasks(tasks) {
    localStorage.setItem(GELIA_TASKS_KEY, JSON.stringify(tasks.map((task) => ({
        module: task.module,
        id: task.id,
    }))));
}

export function listBackgroundTasks() {
    return readTasks();
}

export function startBackgroundTask({ module, id }) {
    if (typeof window === 'undefined' || !module || id == null || id === '') return;
    const tasks = readTasks();
    const exists = tasks.some((task) => task.module === module && String(task.id) === String(id));
    if (!exists) {
        tasks.push({ module: String(module), id });
        writeTasks(tasks);
    }
    window.dispatchEvent(new CustomEvent(GELIA_TASK_STARTED, { detail: { module: String(module), id } }));
}

export function dismissBackgroundTask({ module, id }) {
    if (typeof window === 'undefined' || !module) return;
    writeTasks(readTasks().filter((task) => !(task.module === module && String(task.id) === String(id))));
    window.dispatchEvent(new CustomEvent(GELIA_TASK_DISMISSED, { detail: { module: String(module), id } }));
}

export function forgetBackgroundTask({ module, id }) {
    if (typeof window === 'undefined' || !module) return;
    writeTasks(readTasks().filter((task) => !(task.module === module && String(task.id) === String(id))));
}

export function publishLocalTask(detail) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(GELIA_TASK_LOCAL, { detail }));
}

function readPos(raw) {
    if (!raw) return null;
    try {
        const pos = JSON.parse(raw);
        if (typeof pos?.x !== 'number' || typeof pos?.y !== 'number') return null;
        return {
            x: pos.x,
            y: pos.y,
            dock: pos.dock === 'left' || pos.dock === 'right' ? pos.dock : null,
        };
    } catch {
        return null;
    }
}

export function getStoredBackgroundWidgetPos() {
    if (typeof window === 'undefined') return null;
    const propia = readPos(localStorage.getItem(GELIA_TASKS_POS_KEY));
    if (propia) return propia;
    const heredada = readPos(localStorage.getItem(TN_POS_KEY));
    if (heredada) {
        setStoredBackgroundWidgetPos(heredada);
        return heredada;
    }
    return null;
}

export function setStoredBackgroundWidgetPos(pos) {
    if (typeof window === 'undefined' || !pos) return;
    localStorage.setItem(GELIA_TASKS_POS_KEY, JSON.stringify({
        x: pos.x,
        y: pos.y,
        dock: pos.dock === 'left' || pos.dock === 'right' ? pos.dock : null,
    }));
}
