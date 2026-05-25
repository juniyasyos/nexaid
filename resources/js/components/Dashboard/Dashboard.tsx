import React, { useState } from 'react';
import {
    Hospital,
    User
} from 'lucide-react';
import type { DashboardProps } from './types';
import { useAuth } from '../../hooks/useAuth';
import { useApplications } from './useApplications';
import ModalContent from './ModalContent';
import ApplicationCard from './ApplicationCard';
import { DASHBOARD_TEXTS, ANIMATION_VARIANTS } from './constants';
import { STYLES, KEYFRAME_STYLES } from './styles';
import type { Application } from '../../types';

export default function Dashboard({ user, applications: appsFromProps = [], accessProfiles = [] }: DashboardProps) {
    const { logout } = useAuth();
    const [showInfoModal, setShowInfoModal] = useState(false);
    const { applications, loading } = useApplications({
        appsFromProps,
        userApplications: user?.applications,
    });

    const handleAppClick = (app: Application) => {
        if (app.url) {
            window.location.href = app.url;
        }
    };

    const nip = user?.nip || '---';

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-cyan-50 relative overflow-hidden pb-20">
            {/* Animated Background Elements */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none">
                <div className="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-blue-300/20 to-cyan-300/20 rounded-full blur-3xl animate-pulse" style={{ animationDuration: '15s' }} />
                <div className="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-br from-teal-300/20 to-emerald-300/20 rounded-full blur-3xl animate-pulse" style={{ animationDuration: '15s', animationDelay: '3s' }} />
            </div>

            {/* User Info Modal - Desktop Popup */}
            {showInfoModal && (
                <>
                    {/* Backdrop */}
                    <div className={STYLES.modal.backdrop} onClick={() => setShowInfoModal(false)} />

                    {/* Modal Popup - Desktop */}
                    <div className={STYLES.modal.desktopPopup} onClick={(e) => e.stopPropagation()}>
                        <div className={`${STYLES.modal.container} animate-slideDown`}>
                            <ModalContent
                                user={user}
                                nip={nip}
                                logout={logout}
                                onClose={() => setShowInfoModal(false)}
                                accessProfiles={accessProfiles}
                            />
                        </div>
                    </div>

                    {/* Modal Popup - Mobile Sidebar */}
                    <div className={STYLES.modal.mobilePopup} onClick={(e) => e.stopPropagation()}>
                        <div className={`${STYLES.modal.mobileContainer} animate-slideLeft`}>
                            <ModalContent
                                user={user}
                                nip={nip}
                                logout={logout}
                                onClose={() => setShowInfoModal(false)}
                                isMobile
                                accessProfiles={accessProfiles}
                            />
                        </div>
                    </div>
                </>
            )}

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 relative z-10">
                {/* Header - Logo and User Info */}
                <div className={STYLES.header.container} style={{ animation: ANIMATION_VARIANTS.fadeIn }}>
                    {/* Logo and Title */}
                    <div className={STYLES.header.logoContainer}>
                        <div className={STYLES.header.logoIcon}>
                            <Hospital className={STYLES.header.logoIconInner} />
                            <div className="absolute inset-0 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-xl blur-lg opacity-50 -z-10"></div>
                        </div>
                        <div className={STYLES.header.logoContent}>
                            <h1 className={STYLES.header.title}>
                                {DASHBOARD_TEXTS.title}
                            </h1>
                            <p className={STYLES.header.subtitle}>
                                {DASHBOARD_TEXTS.subtitle}
                            </p>
                        </div>
                    </div>

                    {/* User Account Button */}
                    <button
                        onClick={() => setShowInfoModal(true)}
                        className={STYLES.header.userButton}
                    >
                        <div className={STYLES.header.userButtonIcon}>
                            <User className={STYLES.header.userButtonContent} />
                        </div>
                    </button>
                </div>

                {/* Welcome Text */}
                <div className={STYLES.welcome.container} style={{ animation: ANIMATION_VARIANTS.fadeInDelay(0.2), opacity: 0 }}>
                    <p className={STYLES.welcome.prefix}>{DASHBOARD_TEXTS.welcomePrefix} {user?.name?.toUpperCase() || 'USER'}</p>
                    <h2 className={STYLES.welcome.heading}>
                        {DASHBOARD_TEXTS.mainHeadline} <span className="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent">{DASHBOARD_TEXTS.mainHeadlineHighlight}</span>
                    </h2>
                    <p className={STYLES.welcome.description}>
                        {DASHBOARD_TEXTS.description}
                    </p>
                </div>

                <div className={STYLES.grid.container}>
                    {loading ? (
                        <div className={STYLES.empty.container}>
                            <div className={STYLES.empty.spinner}></div>
                        </div>
                    ) : applications.length === 0 ? (
                        <div className={STYLES.empty.emptyContainer}>
                            <div className={STYLES.empty.emptyContent}>
                                <Hospital className={STYLES.empty.emptyIcon} />
                                <p className={STYLES.empty.emptyTitle}>{DASHBOARD_TEXTS.noAppsMessage}</p>
                                <p className={STYLES.empty.emptySubtitle}>{DASHBOARD_TEXTS.noAppsHint}</p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Applications Grid */}
                            <div className={STYLES.grid.layout}>
                                {applications.map((app, index) => (
                                    <ApplicationCard
                                        key={app.id}
                                        app={app}
                                        index={index}
                                        onAppClick={handleAppClick}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </div>

                {/* Footer Info */}
                <div className={STYLES.footer.container} style={{ animation: ANIMATION_VARIANTS.fadeInDelay(1.5), opacity: 0 }}>
                    <p className={STYLES.footer.tip}>
                        {DASHBOARD_TEXTS.footerTip}
                    </p>
                    <p className={STYLES.footer.security}>
                        {DASHBOARD_TEXTS.footerSecurity}
                    </p>
                </div>
            </main>

            <style>{KEYFRAME_STYLES}</style>
        </div>
    );
}
