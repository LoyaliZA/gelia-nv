import { useEffect, useRef } from 'react';

let locks = 0;
let previousOverflow = '';
const focusableSelector = 'button:not([disabled]), a[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex="0"]';

function topOverlay() {
    return [...document.querySelectorAll('.gelia-modal-overlay')]
        .filter((el) => el.getClientRects().length)
        .sort((a, b) => (Number(getComputedStyle(a).zIndex) || 0) - (Number(getComputedStyle(b).zIndex) || 0))
        .at(-1);
}

/** Retains focus and scroll through stacked order dialogs, including photo previews. */
export default function usePedidoDialog({ abierto, onClose, bloqueado = false }) {
    const ref = useRef(null);
    const options = useRef({ onClose, bloqueado });
    options.current = { onClose, bloqueado };

    useEffect(() => {
        const el = ref.current;
        if (!abierto || !el) return undefined;
        const previousFocus = document.activeElement;
        if (locks++ === 0) {
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }
        // Focus the container, avoiding opening a phone keyboard on entry.
        el.focus({ preventScroll: true });
        const isTop = () => el.closest('.gelia-modal-overlay') === topOverlay();
        const onKey = (event) => {
            if (!isTop()) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (!options.current.bloqueado) options.current.onClose?.();
            }
            if (event.key === 'Tab') {
                const controls = [...el.querySelectorAll(focusableSelector)]
                    .filter((node) => node.getClientRects().length && !node.closest('[inert]'));
                const first = controls[0];
                const last = controls.at(-1);
                if (!first) {
                    event.preventDefault();
                    el.focus();
                } else if (!el.contains(document.activeElement) || document.activeElement === el) {
                    event.preventDefault();
                    (event.shiftKey ? last : first).focus();
                } else if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };
        const onFocus = (event) => {
            if (isTop() && !el.contains(event.target)) el.focus({ preventScroll: true });
        };
        document.addEventListener('keydown', onKey, true);
        document.addEventListener('focusin', onFocus);
        return () => {
            document.removeEventListener('keydown', onKey, true);
            document.removeEventListener('focusin', onFocus);
            if (--locks === 0) document.body.style.overflow = previousOverflow;
            if (previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
        };
    }, [abierto]);

    return { ref, role: 'dialog', 'aria-modal': true, tabIndex: -1 };
}
