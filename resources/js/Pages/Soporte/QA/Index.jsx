import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

export default function Index({ auth }) {
    return (
        <AppLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Preguntas Frecuentes (QyA)</h2>}
        >
            <Head title="QyA de Soporte" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 className="text-lg font-bold mb-4">Base de Conocimientos</h3>
                        <p className="text-gray-600">Aquí encontrarás respuestas a preguntas frecuentes y manuales de ayuda. (Módulo en construcción)</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
