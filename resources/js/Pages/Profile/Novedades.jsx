import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Novedades() {
    return (
        <AppLayout>
            <Head title="Novedades | GELIANV" />
            <div className="gelia-page-shell">
                <header className={geliaCardClass('p-8 md:p-10')}>
                    <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                        Novedades
                    </h1>
                </header>
            </div>
        </AppLayout>
    );
}
