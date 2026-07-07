import React, { useState, useMemo } from 'react';
import { Hospital, User, Search, Sun, Moon } from 'lucide-react';
import type { DashboardProps } from './types';
import { useAuth } from '../../hooks/useAuth';
import { useTheme } from '../../hooks/useTheme';
import { useApplications } from './useApplications';
import ModalContent from './ModalContent';
import ApplicationCard from './ApplicationCard';
import type { Application } from '../../types';
import { KEYFRAME_STYLES } from './styles';
import TopBar from '../TopBar';
export default function Dashboard({ user, applications: appsFromProps = [], accessProfiles = [] }: DashboardProps) {
    const { logout } = useAuth();
    const [showMobileAccount, setShowMobileAccount] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [filter, setFilter] = useState<'all' | 'ready'>('all');
    const { theme, toggleTheme } = useTheme();

    const { applications, loading } = useApplications({
        appsFromProps,
        userApplications: user?.applications,
    });

    const filteredApps = useMemo(() => {
        return applications.filter((app) => {
            const matchesSearch = app.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                app.description?.toLowerCase().includes(searchQuery.toLowerCase());
            const matchesFilter = filter === 'all' || (filter === 'ready' && app.status !== 'maintenance' && app.status !== 'offline');
            return matchesSearch && matchesFilter;
        });
    }, [applications, searchQuery, filter]);

    const handleAppClick = (app: Application) => {
        if (app.url) {
            window.location.href = app.url;
        }
    };

    const nip = user?.nip || '---';

    return (
        <div className={`h-screen w-screen font-sans antialiased relative overflow-hidden transition-colors duration-500 ${theme === 'dark' ? 'bg-[#0b1226] text-slate-100 selection:bg-cyan-400/30 selection:text-white' : 'bg-slate-50 text-slate-800 selection:bg-blue-400/30 selection:text-blue-900'}`}>
            <style>{KEYFRAME_STYLES}</style>
            <style>{`
                @keyframes slideLeft {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .animate-slideLeft {
                    animation: slideLeft 0.3s ease-out forwards;
                }
                
                /* Custom Scrollbar */
                ::-webkit-scrollbar {
                    width: 6px;
                    height: 6px;
                }
                ::-webkit-scrollbar-track {
                    background: transparent;
                }
                ::-webkit-scrollbar-thumb {
                    background: ${theme === 'dark' ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.15)'};
                    border-radius: 10px;
                }
                ::-webkit-scrollbar-thumb:hover {
                    background: ${theme === 'dark' ? 'rgba(6, 182, 212, 0.5)' : 'rgba(59, 130, 246, 0.5)'};
                }
            `}</style>

            {/* Layered abstract wave background */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none transition-opacity duration-500" style={{ opacity: theme === 'dark' ? 1 : 0.5 }}>
                <svg className="absolute inset-0 h-full w-full" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice">
                    <defs>
                        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stopColor={theme === 'dark' ? "#1e3a8a" : "#e0f2fe"} />
                            <stop offset="60%" stopColor={theme === 'dark' ? "#0f1d44" : "#f0f9ff"} />
                            <stop offset="100%" stopColor={theme === 'dark' ? "#070d22" : "#ffffff"} />
                        </linearGradient>
                        <linearGradient id="wave1" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={theme === 'dark' ? "#3b82f6" : "#7dd3fc"} stopOpacity={theme === 'dark' ? "0.35" : "0.5"} />
                            <stop offset="100%" stopColor={theme === 'dark' ? "#1e40af" : "#bae6fd"} stopOpacity={theme === 'dark' ? "0.15" : "0.3"} />
                        </linearGradient>
                        <linearGradient id="wave2" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stopColor={theme === 'dark' ? "#0ea5e9" : "#38bdf8"} stopOpacity={theme === 'dark' ? "0.25" : "0.4"} />
                            <stop offset="100%" stopColor={theme === 'dark' ? "#1e3a8a" : "#7dd3fc"} stopOpacity={theme === 'dark' ? "0.55" : "0.2"} />
                        </linearGradient>
                        <radialGradient id="glow" cx="20%" cy="30%" r="60%">
                            <stop offset="0%" stopColor={theme === 'dark' ? "#60a5fa" : "#38bdf8"} stopOpacity={theme === 'dark' ? "0.35" : "0.2"} />
                            <stop offset="100%" stopColor={theme === 'dark' ? "#0b1226" : "#ffffff"} stopOpacity="0" />
                        </radialGradient>
                    </defs>

                    <rect width="100%" height="100%" fill="url(#bg)" />
                    <rect width="100%" height="100%" fill="url(#glow)" />

                    {/* Soft wave shapes */}
                    <path
                        d="M0,720 C150,650 250,820 400,760 C560,700 660,860 800,800 L800,1000 L0,1000 Z"
                        fill="url(#wave1)"
                    />
                    <path
                        d="M0,820 C180,760 320,900 500,840 C640,790 720,920 800,880 L800,1000 L0,1000 Z"
                        fill="url(#wave2)"
                    />
                </svg>

                {/* Grid / noise overlay for depth */}
                <div
                    className="absolute inset-0 opacity-[0.07] mix-blend-overlay"
                    style={{
                        backgroundImage: `linear-gradient(to right, ${theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)'} 1px, transparent 1px), linear-gradient(to bottom, ${theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)'} 1px, transparent 1px)`,
                        backgroundSize: "48px 48px",
                    }}
                />
            </div>

            {/* Mobile/Tablet Slideover Overlay */}
            {showMobileAccount && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onClick={() => setShowMobileAccount(false)} />
                    <div className={`absolute right-0 top-0 bottom-0 w-[90vw] sm:w-[360px] md:w-[420px] shadow-[0_0_40px_rgba(0,0,0,0.5)] animate-slideLeft flex flex-col ${theme === 'dark' ? 'bg-[#0f1d44] border-l border-white/10' : 'bg-white border-l border-slate-200'}`}>
                        <div className={`p-5 flex items-center justify-between border-b ${theme === 'dark' ? 'border-white/10' : 'border-slate-100'}`}>
                            <h3 className={`font-semibold ${theme === 'dark' ? 'text-white' : 'text-slate-800'}`}>Info Akun</h3>
                            <div className="flex items-center gap-3">
                                <button onClick={toggleTheme} className={`p-1.5 rounded-lg transition-colors ${theme === 'dark' ? 'text-cyan-300 hover:bg-white/10' : 'text-blue-600 hover:bg-slate-100'}`}>
                                    {theme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                                </button>
                                <button onClick={() => setShowMobileAccount(false)} className={`transition-colors ${theme === 'dark' ? 'text-slate-400 hover:text-white' : 'text-slate-400 hover:text-slate-800'}`}>
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto">
                            <ModalContent
                                user={user}
                                nip={nip}
                                logout={logout}
                                onClose={() => setShowMobileAccount(false)}
                                isMobile
                                accessProfiles={accessProfiles}
                                theme={theme}
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* Main Layout */}
            <div className="relative z-10 flex flex-col h-full overflow-hidden">
                {/* Header */}
                <TopBar 
                    theme={theme} 
                    toggleTheme={toggleTheme} 
                    user={user} 
                    showUserMobileButton={true}
                    onUserMobileClick={() => setShowMobileAccount(true)} 
                />

                {/* Body Content */}
                <div className="flex-1 flex w-full relative overflow-hidden">
                    {/* Left Column: Apps */}
                    <main className="flex-1 px-[clamp(1rem,4vw,4rem)] py-[clamp(1.5rem,4vh,2.5rem)] flex flex-col min-w-0 overflow-y-auto">
                        <div className="mb-5 animate-slideUp w-full max-w-[520px] mx-auto lg:mx-0 text-center lg:text-left shrink-0">
                            <div className="mb-2">
                                <p className={`text-sm font-medium ${theme === 'dark' ? 'text-slate-400' : 'text-slate-500'
                                    }`}>
                                    Selamat datang kembali
                                </p>

                                <h2 className={`text-[clamp(1.5rem,2.4vw,2rem)] font-bold tracking-tight leading-tight ${theme === 'dark' ? 'text-white' : 'text-slate-900'
                                    }`}>
                                    <span className={theme === 'dark' ? 'text-cyan-400' : 'text-blue-600'}>
                                        {user?.name || 'admin'}
                                    </span>
                                </h2>
                            </div>

                            <p className={`text-xs ${theme === 'dark' ? 'text-slate-300' : 'text-slate-600'}`}>
                                Pilih aplikasi yang ingin Anda akses hari ini.
                            </p>

                            <div className="mt-3 flex flex-wrap gap-2 text-xs justify-center lg:justify-start">
                                <span className={`px-2.5 py-1 rounded-lg border flex items-center gap-1.5 ${theme === 'dark' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}`}>
                                    <span className={`w-1.5 h-1.5 rounded-full animate-pulse ${theme === 'dark' ? 'bg-emerald-400' : 'bg-emerald-500'}`}></span>
                                    SSO Active
                                </span>

                                <span className={`px-2.5 py-1 rounded-lg border flex items-center gap-1.5 ${theme === 'dark' ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' : 'bg-blue-50 text-blue-700 border-blue-200'}`}>
                                    🔒 Secure
                                </span>

                                <span className={`px-2.5 py-1 rounded-lg border flex items-center gap-1.5 ${theme === 'dark' ? 'bg-white/10 text-white border-white/20' : 'bg-slate-100 text-slate-700 border-slate-200'}`}>
                                    {filteredApps.length} Apps
                                </span>
                            </div>
                        </div>

                        {/* Search & Filter */}
                        <div
                            className="mb-7 space-y-3 animate-slideUp w-full max-w-[520px] mx-auto lg:mx-0 shrink-0"
                            style={{ animationDelay: '0.1s' }}
                        >
                            <div className="relative group">
                                <Search className={`absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 transition-colors ${theme === 'dark' ? 'text-slate-400 group-focus-within:text-cyan-400' : 'text-slate-400 group-focus-within:text-blue-500'}`} />

                                <input
                                    type="text"
                                    placeholder="Cari aplikasi..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className={`w-full border rounded-xl py-2 pl-10 pr-4 focus:outline-none transition-all shadow-inner text-sm ${theme === 'dark'
                                        ? 'bg-white/5 border-white/10 text-white placeholder:text-slate-500 focus:border-cyan-500/50 focus:bg-white/10 focus:ring-2 focus:ring-cyan-500/20'
                                        : 'bg-white border-slate-200 text-slate-900 placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100'
                                        }`}
                                />
                            </div>

                            {/* <div className="flex gap-2 justify-center lg:justify-start">
                                <button
                                    onClick={() => setFilter('all')}
                                    className={`px-4 py-2 rounded-lg text-xs font-semibold transition ${filter === 'all'
                                        ? (theme === 'dark' ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-[0_0_15px_rgba(6,182,212,0.4)] border-transparent' : 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-md border-transparent')
                                        : (theme === 'dark' ? 'bg-white/5 text-slate-300 hover:bg-white/10 border-white/10 border hover:border-white/20' : 'bg-white text-slate-600 hover:bg-slate-50 border-slate-200 border hover:border-slate-300')
                                        }`}
                                >
                                    Semua
                                </button>

                                <button
                                    onClick={() => setFilter('ready')}
                                    className={`px-4 py-2 rounded-lg text-xs font-semibold transition ${filter === 'ready'
                                        ? (theme === 'dark' ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-[0_0_15px_rgba(6,182,212,0.4)] border-transparent' : 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-md border-transparent')
                                        : (theme === 'dark' ? 'bg-white/5 text-slate-300 hover:bg-white/10 border-white/10 border hover:border-white/20' : 'bg-white text-slate-600 hover:bg-slate-50 border-slate-200 border hover:border-slate-300')
                                        }`}
                                >
                                    Siap Diakses
                                </button>
                            </div> */}
                        </div>

                        {/* Apps Grid */}
                        <div className="w-full max-w-[1200px] mx-auto lg:mx-0 shrink-0">
                            {loading ? (
                                <div className="flex justify-center py-16">
                                    <div className={`animate-spin rounded-full h-8 w-8 border-b-2 ${theme === 'dark' ? 'border-cyan-500' : 'border-blue-600'}`}></div>
                                </div>
                            ) : filteredApps.length === 0 ? (
                                <div className={`text-center py-16 rounded-2xl border ${theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white border-slate-200'}`}>
                                    <Hospital className={`w-12 h-12 mx-auto mb-3 ${theme === 'dark' ? 'text-slate-500' : 'text-slate-400'}`} />
                                    <p className={`text-base ${theme === 'dark' ? 'text-slate-300' : 'text-slate-600'}`}>Tidak ada aplikasi yang ditemukan.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-[clamp(1rem,3vw,1.5rem)]">
                                    {filteredApps.map((app, index) => (
                                        <ApplicationCard
                                            key={app.id}
                                            app={app}
                                            index={index}
                                            onAppClick={handleAppClick}
                                            theme={theme}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>

                        <footer className={`py-6 text-xs mt-auto flex justify-between items-center text-center lg:text-left shrink-0 ${theme === 'dark' ? 'text-slate-500 border-white/10' : 'text-slate-500 border-slate-200'}`}>
                            <span>© {new Date().getFullYear()} NEXA-ID · Portal Terpadu</span>
                            <span className="hidden sm:inline-block">Versi 2.0.0</span>
                        </footer>
                    </main>

                    {/* Right Column: Account Info (Desktop) */}
                    <aside className={`hidden lg:flex w-[clamp(300px,25vw,360px)] h-full flex-col border-l backdrop-blur-sm shrink-0 z-30 transition-colors duration-300 ${theme === 'dark' ? 'border-white/10 bg-white/5 shadow-[-10px_0_30px_-15px_rgba(0,0,0,0.5)]' : 'border-slate-200 bg-white/80 shadow-[-10px_0_30px_-15px_rgba(0,0,0,0.05)]'}`}>
                        <div className={`flex-1 overflow-y-auto`}>
                            <ModalContent
                                user={user}
                                nip={nip}
                                logout={logout}
                                accessProfiles={accessProfiles}
                                isMobile={false}
                                theme={theme}
                            />
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    );
}
