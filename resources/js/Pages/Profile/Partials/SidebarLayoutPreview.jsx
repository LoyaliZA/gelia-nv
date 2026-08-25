import React from 'react';

/** Miniatura fiel del sidebar profesional (barra completa + submenú). */
function PreviewProfessional() {
    return (
        <div className="gelia-sidebar-preview gelia-sidebar-preview--pro" aria-hidden>
            <div className="gelia-sidebar-preview__pro-shell">
                <div className="gelia-sidebar-preview__pro-brand">
                    <span className="gelia-sidebar-preview__dot gelia-sidebar-preview__dot--accent" />
                    <span className="gelia-sidebar-preview__line gelia-sidebar-preview__line--sm" />
                </div>
                <div className="gelia-sidebar-preview__pro-nav">
                    <div className="gelia-sidebar-preview__pro-root gelia-sidebar-preview__pro-root--open">
                        <span className="gelia-sidebar-preview__ico" />
                        <span className="gelia-sidebar-preview__line" />
                        <span className="gelia-sidebar-preview__chev gelia-sidebar-preview__chev--open" />
                    </div>
                    <div className="gelia-sidebar-preview__pro-sub">
                        <span className="gelia-sidebar-preview__sub-line gelia-sidebar-preview__sub-line--active" />
                        <span className="gelia-sidebar-preview__sub-line" />
                    </div>
                    <div className="gelia-sidebar-preview__pro-root">
                        <span className="gelia-sidebar-preview__ico" />
                        <span className="gelia-sidebar-preview__line gelia-sidebar-preview__line--short" />
                        <span className="gelia-sidebar-preview__chev" />
                    </div>
                    <div className="gelia-sidebar-preview__pro-root">
                        <span className="gelia-sidebar-preview__ico" />
                        <span className="gelia-sidebar-preview__line gelia-sidebar-preview__line--short" />
                    </div>
                </div>
                <div className="gelia-sidebar-preview__pro-user">
                    <span className="gelia-sidebar-preview__avatar" />
                    <span className="gelia-sidebar-preview__line gelia-sidebar-preview__line--xs" />
                </div>
            </div>
            <div className="gelia-sidebar-preview__content">
                <span className="gelia-sidebar-preview__content-bar" />
                <span className="gelia-sidebar-preview__content-block" />
            </div>
        </div>
    );
}

/** Miniatura barra fija legacy: rail + panel flotante. */
function PreviewFixed() {
    return (
        <div className="gelia-sidebar-preview gelia-sidebar-preview--fixed" aria-hidden>
            <div className="gelia-sidebar-preview__fixed-rail">
                <span className="gelia-sidebar-preview__dot gelia-sidebar-preview__dot--accent" />
                <span className="gelia-sidebar-preview__rail-ico" />
                <span className="gelia-sidebar-preview__rail-ico" />
                <span className="gelia-sidebar-preview__avatar gelia-sidebar-preview__avatar--sm" />
            </div>
            <div className="gelia-sidebar-preview__fixed-panel">
                <span className="gelia-sidebar-preview__line gelia-sidebar-preview__line--xs" />
                <span className="gelia-sidebar-preview__sub-line" />
                <span className="gelia-sidebar-preview__sub-line gelia-sidebar-preview__sub-line--active" />
                <span className="gelia-sidebar-preview__sub-line" />
            </div>
            <div className="gelia-sidebar-preview__content gelia-sidebar-preview__content--offset">
                <span className="gelia-sidebar-preview__content-bar" />
                <span className="gelia-sidebar-preview__content-block" />
            </div>
        </div>
    );
}

/** Miniatura widget flotante. */
function PreviewFloating({ side = 'left' }) {
    const sideClass = side === 'right' ? 'gelia-sidebar-preview--float-right' : 'gelia-sidebar-preview--float-left';
    return (
        <div className={`gelia-sidebar-preview gelia-sidebar-preview--float ${sideClass}`} aria-hidden>
            <div className="gelia-sidebar-preview__content">
                <span className="gelia-sidebar-preview__content-bar" />
                <span className="gelia-sidebar-preview__content-block" />
            </div>
            <div className="gelia-sidebar-preview__float-panel">
                <span className="gelia-sidebar-preview__sub-line" />
                <span className="gelia-sidebar-preview__sub-line gelia-sidebar-preview__sub-line--active" />
                <span className="gelia-sidebar-preview__sub-line" />
            </div>
            <div className="gelia-sidebar-preview__float-pill">
                <span className="gelia-sidebar-preview__dot gelia-sidebar-preview__dot--accent" />
                <span className="gelia-sidebar-preview__pill-ico" />
                <span className="gelia-sidebar-preview__avatar gelia-sidebar-preview__avatar--xs" />
            </div>
        </div>
    );
}

const PREVIEW_MAP = {
    pro: PreviewProfessional,
    fixed: PreviewFixed,
    'float-left': () => <PreviewFloating side="left" />,
    'float-right': () => <PreviewFloating side="right" />,
};

export default function SidebarLayoutPreview({ variant = 'pro' }) {
    const Component = PREVIEW_MAP[variant] || PreviewProfessional;
    return <Component />;
}
