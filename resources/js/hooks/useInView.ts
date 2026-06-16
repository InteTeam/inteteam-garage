import { useEffect, useRef, useState } from 'react';

interface Options {
    threshold?: number;
    rootMargin?: string;
    once?: boolean;
}

export function useInView<T extends HTMLElement = HTMLDivElement>(
    options: Options = {}
): [React.RefObject<T | null>, boolean] {
    const { threshold = 0.15, rootMargin = '0px 0px -80px 0px', once = true } = options;
    const ref = useRef<T>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const node = ref.current;
        if (node === null) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setInView(true);
                    if (once) observer.unobserve(entry.target);
                } else if (!once) {
                    setInView(false);
                }
            },
            { threshold, rootMargin }
        );

        observer.observe(node);
        return () => observer.disconnect();
    }, [threshold, rootMargin, once]);

    return [ref, inView];
}
