import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import MensajeriaShell from '@/Components/Mensajeria/MensajeriaShell';

export default function Index() {
    return (
        <AppLayout fullScreen>
            <div className="gelia-mensajeria-page">
                <MensajeriaShell variant="page" />
            </div>
        </AppLayout>
    );
}
