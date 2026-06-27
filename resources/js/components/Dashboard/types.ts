import type { User as UserType, Application, UserApplication } from '../../types';

export interface ApplicationRole {
    id: number;
    slug: string;
    name: string;
    description?: string;
}

export interface ApplicationData {
    id: number;
    app_key: string;
    name: string;
    description?: string;
    enabled: boolean;
    logo_url?: string;
    app_url?: string;
    redirect_uris?: string[];
    role: ApplicationRole;
}

export interface AccessProfile {
    id: number;
    slug: string;
    name: string;
    description?: string;
    is_system: boolean;
    is_active: boolean;
    applications_count: number;
    applications: ApplicationData[];
}

export interface DashboardProps {
    user: UserType;
    applications?: Array<{
        app_key: string;
        name: string;
        description: string;
        app_url?: string;
        enabled: boolean;
        logo_url?: string | null;
        icon?: string | null;
        gradient?: string | null;
    }>;
    accessProfiles?: AccessProfile[];
}

export interface ApplicationWithIcon extends Application {
    icon: React.ElementType | string;
    iconString?: string | null;
    gradient: string;
    isOnline: boolean;
    userRole?: string;
}

export interface ModalContentProps {
    user: UserType;
    nip: string;
    logout: () => void;
    onClose: () => void;
    isMobile?: boolean;
    accessProfiles?: AccessProfile[];
}
