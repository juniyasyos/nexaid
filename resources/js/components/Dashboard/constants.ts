import {
    Hospital,
    Pill,
    TestTube,
    FileText,
    Users,
    ShieldCheck,
    CircleAlert,
    Utensils,
    Activity,
    Stethoscope,
    HeartPulse,
    Syringe,
    Bed,
    Microscope,
    ClipboardList,
    GraduationCap,
    BookOpen,
    Library,
    Fingerprint,
    UserCheck,
} from 'lucide-react';

export const AVAILABLE_ICONS: Record<string, React.ElementType> = {
    Hospital,
    Pill,
    TestTube,
    FileText,
    Users,
    ShieldCheck,
    CircleAlert,
    Utensils,
    Activity,
    Stethoscope,
    HeartPulse,
    Syringe,
    Bed,
    Microscope,
    ClipboardList,
    GraduationCap,
    BookOpen,
    Library,
    Fingerprint,
    UserCheck,
};

export const ICON_GRADIENTS: Record<string, string> = {
    Hospital: 'from-purple-500 to-purple-600',
    Pill: 'from-emerald-500 to-emerald-600',
    TestTube: 'from-indigo-500 to-indigo-600',
    Microscope: 'from-indigo-400 to-indigo-500',
    FileText: 'from-cyan-500 to-cyan-600',
    ClipboardList: 'from-cyan-400 to-cyan-500',
    Users: 'from-pink-500 to-pink-600',
    ShieldCheck: 'from-blue-500 to-blue-600',
    CircleAlert: 'from-orange-500 to-orange-600',
    Utensils: 'from-teal-500 to-teal-600',
    Activity: 'from-blue-400 to-blue-500',
    Stethoscope: 'from-blue-600 to-blue-700',
    HeartPulse: 'from-rose-500 to-rose-600',
    Syringe: 'from-sky-500 to-sky-600',
    Bed: 'from-slate-500 to-slate-600',
    GraduationCap: 'from-amber-500 to-amber-600',
    BookOpen: 'from-amber-400 to-amber-500',
    Library: 'from-amber-600 to-amber-700',
    Fingerprint: 'from-zinc-500 to-zinc-600',
    UserCheck: 'from-emerald-400 to-emerald-500',
};

export const DEFAULT_APP_CONFIG = {
    icon: Hospital,
    gradient: 'from-gray-500 to-gray-600'
};

export const DASHBOARD_TEXTS = {
    welcomePrefix: 'SELAMAT DATANG,',
    mainHeadline: 'Satu pintu untuk semua',
    mainHeadlineHighlight: 'aplikasi layanan.',
    description: 'Masuk sekali, lalu akses seluruh aplikasi operasional rumah sakit dengan aman: mutu, insiden, dokumen, hingga analitik manajemen.',
    title: 'Single Sign-On',
    subtitle: 'Portal akses terpadu Rumah Sakit Citra Husada Jember',
    noAppsMessage: 'Tidak ada aplikasi yang tersedia',
    noAppsHint: 'Hubungi administrator untuk akses aplikasi',
    footerTip: '💡 Tip: Klik pada aplikasi untuk membuka, atau akses Admin Panel untuk pengaturan tambahan',
    footerSecurity: 'Semua data terlindungi dengan enkripsi tingkat enterprise',
};

export const MODAL_TEXTS = {
    title: 'Info Akun',
    status: 'Status',
    active: 'Active',
    profiles: 'Profil Akses',
    noProfiles: 'Tidak memiliki akses profil. Hubungi administrator untuk diberikan akses.',
    system: 'System',
    adminPanel: 'Admin Panel',
    logout: 'Keluar',
};

export const ANIMATION_VARIANTS = {
    fadeIn: '0.8s ease-out forwards',
    fadeInDelay: (delay: number) => `0.8s ease-out ${delay}s forwards`,
    slideUp: (delay: number) => `0.6s ease-out ${delay}s forwards`,
    slideDown: '0.3s ease-out forwards',
    slideLeft: '0.3s ease-out forwards',
};
