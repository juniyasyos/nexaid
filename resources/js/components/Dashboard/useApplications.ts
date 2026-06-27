import { useState, useEffect } from 'react';
import type { UserApplication } from '../../types';
import type { ApplicationWithIcon } from './types';
import { AVAILABLE_ICONS, DEFAULT_APP_CONFIG, ICON_GRADIENTS } from './constants';

interface UseApplicationsProps {
    appsFromProps: Array<{
        app_key: string;
        name: string;
        description: string;
        app_url?: string;
        enabled: boolean;
        logo_url?: string | null;
    }>;
    userApplications?: UserApplication[];
}

export function useApplications({ appsFromProps, userApplications }: UseApplicationsProps) {
    const [applications, setApplications] = useState<ApplicationWithIcon[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const processApplications = async () => {
            try {
                const appsList = appsFromProps;

                const appsWithIcons = appsList.map((app) => {
                    const appStatus: 'Siap Diakses' | 'Dalam Pengembangan' = app.enabled
                        ? 'Siap Diakses'
                        : 'Dalam Pengembangan';
                    const isOnline = app.enabled;

                    const userAppData = userApplications?.find((ua) => ua.app_key === app.app_key);
                    const userRole = userAppData?.roles?.[0]?.name || undefined;
                    
                    // Resolve Icon Component from AVAILABLE_ICONS map, fallback to default
                    const resolvedIconComponent = (app.icon && AVAILABLE_ICONS[app.icon]) 
                        ? AVAILABLE_ICONS[app.icon] 
                        : DEFAULT_APP_CONFIG.icon;

                    const defaultGradient = app.icon && ICON_GRADIENTS[app.icon]
                        ? ICON_GRADIENTS[app.icon]
                        : DEFAULT_APP_CONFIG.gradient;

                    return {
                        id: app.app_key,
                        name: app.name,
                        description: app.description || '',
                        status: appStatus,
                        url: app.app_url || `/${app.app_key}`,
                        notifications: 0,
                        icon: resolvedIconComponent,
                        gradient: app.gradient || defaultGradient,
                        isOnline: isOnline,
                        userRole: userRole,
                        iconString: app.icon || null,
                    };
                });

                setApplications(appsWithIcons);
            } catch (error) {
                setApplications([]);
            } finally {
                setLoading(false);
            }
        };

        processApplications();
    }, [appsFromProps, userApplications]);

    return { applications, loading };
}
