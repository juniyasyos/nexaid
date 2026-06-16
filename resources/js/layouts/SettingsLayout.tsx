import React, { useState } from 'react';
import { Hospital, ChevronLeft, Moon, Sun } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import type { User as UserType } from '../types';
import { KEYFRAME_STYLES } from '../components/Dashboard/styles';

interface SettingsLayoutProps {
    children: React.ReactNode;
    title: string;
}

export default function SettingsLayout({ children, title }: SettingsLayoutProps) {
    const { props } = usePage();
    const user = props.auth?.user as UserType;
    const [theme, setTheme] = useState<'dark' | 'light'>('dark');

    const toggleTheme = () => setTheme(prev => prev === 'dark' ? 'light' : 'dark');

    return (
        <div className={`w-screen overflow-x-hidden font-sans antialiased relative flex flex-col transition-colors duration-500 ${theme === 'dark' ? 'bg-[#0b1226] text-slate-100 selection:bg-cyan-400/30 selection:text-white' : 'bg-slate-50 text-slate-800 selection:bg-blue-400/30 selection:text-blue-900'}`}>
            <style>{KEYFRAME_STYLES}</style>
            
            {/* Background elements - similar to dashboard */}
            <div className="fixed inset-0 pointer-events-none transition-opacity duration-500" style={{ opacity: theme === 'dark' ? 1 : 0.5 }}>
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
                        <radialGradient id="glow" cx="20%" cy="30%" r="60%">
                            <stop offset="0%" stopColor={theme === 'dark' ? "#60a5fa" : "#38bdf8"} stopOpacity={theme === 'dark' ? "0.35" : "0.2"} />
                            <stop offset="100%" stopColor={theme === 'dark' ? "#0b1226" : "#ffffff"} stopOpacity="0" />
                        </radialGradient>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#bg)" />
                    <rect width="100%" height="100%" fill="url(#glow)" />
                    <path d="M0,720 C150,650 250,820 400,760 C560,700 660,860 800,800 L800,1000 L0,1000 Z" fill="url(#wave1)" />
                </svg>
                <div
                    className="absolute inset-0 opacity-[0.07] mix-blend-overlay"
                    style={{
                        backgroundImage: `linear-gradient(to right, ${theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)'} 1px, transparent 1px), linear-gradient(to bottom, ${theme === 'dark' ? 'rgba(255,255,255,0.6)' : 'rgba(0,0,0,0.6)'} 1px, transparent 1px)`,
                        backgroundSize: "48px 48px",
                    }}
                />
            </div>

            {/* Header */}
            <header className={`h-[64px] lg:h-[72px] shrink-0 px-4 sm:px-6 lg:px-8 flex items-center z-40 border-b backdrop-blur-md transition-colors duration-300 ${theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white/70 border-slate-200 shadow-sm'}`}>
                <div className="w-full flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/dashboard" className={`p-2 rounded-xl border transition-colors ${theme === 'dark' ? 'bg-white/5 hover:bg-white/10 border-white/10 text-slate-300 hover:text-white' : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-600 hover:text-slate-900 shadow-sm'}`}>
                            <ChevronLeft className="w-5 h-5" />
                        </Link>
                        <div className="flex items-center gap-3">
                            <div className={`flex h-9 w-9 lg:h-10 lg:w-10 items-center justify-center rounded-xl ring-1 shadow-lg relative overflow-hidden ${theme === 'dark' ? 'bg-white/10 ring-white/20' : 'bg-blue-50 ring-blue-100'}`}>
                                <div className={`absolute inset-0 z-0 ${theme === 'dark' ? 'bg-gradient-to-br from-cyan-400/20 to-blue-500/20' : 'bg-gradient-to-br from-blue-400/10 to-cyan-500/10'}`}></div>
                                <Hospital className={`h-4 w-4 lg:h-5 lg:w-5 relative z-10 ${theme === 'dark' ? 'text-cyan-300' : 'text-blue-600'}`} />
                            </div>
                            <div>
                                <h1 className={`text-sm lg:text-base font-bold tracking-wide ${theme === 'dark' ? 'text-white' : 'text-slate-800'}`}>Single Sign-On</h1>
                            </div>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-3">
                        <button onClick={toggleTheme} className={`flex items-center justify-center w-9 h-9 rounded-full transition-colors border ${theme === 'dark' ? 'bg-white/5 border-white/10 hover:bg-white/10 text-cyan-300' : 'bg-white border-slate-200 hover:bg-slate-50 text-blue-600 shadow-sm'}`}>
                            {theme === 'dark' ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                        </button>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex-1 w-full max-w-7xl mx-auto relative z-10 p-4 sm:p-6 lg:p-8">
                <div className="animate-slideUp" style={{ animationFillMode: 'both' }}>
                    {React.cloneElement(children as React.ReactElement, { theme, user })}
                </div>
            </main>
        </div>
    );
}
