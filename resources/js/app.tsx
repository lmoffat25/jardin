import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

// Le seul point d'entrée applicatif (chapitre 3 §3, encart V1) : bureau et
// terrain sont des pages de ce même bundle.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
    resolve: async (nom) => {
        const charger = pages[`./pages/${nom}.tsx`];
        if (charger === undefined) {
            throw new Error(`Page Inertia introuvable : ${nom}`);
        }
        return (await charger()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
