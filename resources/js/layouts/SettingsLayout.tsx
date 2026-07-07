import React, { useState } from 'react';
import { Hospital, ChevronLeft, Moon, Sun } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import type { User as UserType } from '../types';
import { KEYFRAME_STYLES } from '../components/Dashboard/styles';
import TopBar from '../components/TopBar';
import { useTheme } from '../hooks/useTheme';

interface SettingsLayoutProps {
    children: React.ReactNode;
    title: string;
}

export default function SettingsLayout({ children, title }: SettingsLayoutProps) {
    const { props } = usePage();
    const user = (props.user || props.auth?.user) as UserType;
    const { theme, toggleTheme } = useTheme();

    return (
        <div className={`relative isolate min-h-dvh w-full overflow-x-hidden font-sans antialiased flex flex-col transition-colors duration-500 ${theme === 'dark'
            ? 'bg-[#0b1226] text-slate-100 selection:bg-cyan-400/30 selection:text-white'
            : 'bg-slate-50 text-slate-800 selection:bg-blue-400/30 selection:text-blue-900'
            }`}>
            <style>{KEYFRAME_STYLES}</style>

            {/* Background elements - similar to dashboard */}
            <div
                className="absolute inset-0 -z-10 min-h-full w-full pointer-events-none transition-opacity duration-500"
                style={{ opacity: theme === 'dark' ? 1 : 0.5 }}
            >
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
            <TopBar theme={theme} toggleTheme={toggleTheme} />

            {/* Main Content */}
            <main className="flex-1 w-full max-w-7xl mx-auto relative z-10 p-4 sm:p-6 lg:p-8">
                <div className="animate-slideUp" style={{ animationFillMode: 'both' }}>
                    {React.cloneElement(children as React.ReactElement, { theme, user })}
                </div>
            </main>
        </div>
    );
}
