import React, { useEffect, useState } from 'react';
import { User, Users } from 'lucide-react';
import { resolveFotoPerfilUrl } from '@/utils/fotoPerfil';

export default function AvatarUsuario({
    foto,
    nombre = '',
    esGrupo = false,
    enLinea = false,
    className = 'w-11 h-11',
    iconClassName = 'w-5 h-5',
}) {
    const [falloCarga, setFalloCarga] = useState(false);
    const url = resolveFotoPerfilUrl(foto);

    useEffect(() => {
        setFalloCarga(false);
    }, [url]);

    const Icon = esGrupo ? Users : User;

    const marca = enLinea ? <span className="gelia-presencia-punto" aria-hidden /> : null;

    if (url && !falloCarga) {
        return (
            <span className="gelia-avatar-marco">
                <img
                    src={url}
                    alt={nombre ? `Foto de ${nombre}` : 'Avatar'}
                    className={`${className} rounded-full object-cover shrink-0 border theme-border`}
                    onError={() => setFalloCarga(true)}
                />
                {marca}
            </span>
        );
    }

    return (
        <span className="gelia-avatar-marco">
            <div className={`${className} rounded-full theme-element flex items-center justify-center shrink-0 border theme-border`}>
                <Icon className={`${iconClassName} theme-text-muted`} />
            </div>
            {marca}
        </span>
    );
}
