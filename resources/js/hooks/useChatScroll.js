import { useCallback, useEffect, useRef } from 'react';

function scrollElementToBottom(el, behavior = 'instant') {
    if (behavior === 'smooth') {
        el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
    } else {
        el.scrollTop = el.scrollHeight;
    }
}

export default function useChatScroll(mensajes, conversacionId) {
    const scrollRef = useRef(null);
    const isInitialLoad = useRef(true);
    const prevLengthRef = useRef(0);
    const prevFirstIdRef = useRef(null);

    const scrollToBottom = useCallback((behavior = 'instant') => {
        const el = scrollRef.current;
        if (!el) return;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                scrollElementToBottom(el, behavior);
            });
        });
    }, []);

    const forceScrollToBottom = useCallback(() => {
        scrollToBottom('instant');
    }, [scrollToBottom]);

    useEffect(() => {
        isInitialLoad.current = true;
        prevLengthRef.current = 0;
        prevFirstIdRef.current = null;
    }, [conversacionId]);

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        if (isInitialLoad.current && mensajes.length > 0) {
            scrollElementToBottom(el, 'instant');
            isInitialLoad.current = false;
            prevLengthRef.current = mensajes.length;
            prevFirstIdRef.current = mensajes[0]?.id ?? null;
        }
    }, [mensajes, conversacionId]);

    useEffect(() => {
        if (isInitialLoad.current) return;
        if (mensajes.length === 0) {
            prevLengthRef.current = 0;
            prevFirstIdRef.current = null;
            return;
        }

        const firstId = mensajes[0]?.id ?? null;
        const isPrepend =
            mensajes.length > prevLengthRef.current &&
            prevFirstIdRef.current !== null &&
            firstId !== prevFirstIdRef.current;

        if (isPrepend) {
            prevLengthRef.current = mensajes.length;
            prevFirstIdRef.current = firstId;
            return;
        }

        const appended = mensajes.length > prevLengthRef.current;
        if (appended) {
            scrollToBottom('instant');
        }

        prevLengthRef.current = mensajes.length;
        prevFirstIdRef.current = firstId;
    }, [mensajes, scrollToBottom]);

    return {
        scrollRef,
        scrollToBottom,
        forceScrollToBottom,
    };
}
