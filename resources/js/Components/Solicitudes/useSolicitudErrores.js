import { useEffect } from 'react';

export default function useSolicitudErrores(errors) {
    useEffect(() => {
        const key = Object.keys(errors || {})[0];
        if (!key) return;
        const dialog = [...document.querySelectorAll('.gelia-workflow-dialog')].at(-1);
        const control = [...(dialog?.querySelectorAll('input, select, textarea') || [])].find(field => field.name === key);
        control?.focus();
    }, [errors]);
}
