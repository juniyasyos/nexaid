import React from 'react';
import { Hospital, Sun, Moon, User } from 'lucide-react';
import { Link } from '@inertiajs/react';
import type { User as UserType } from '../types';

interface TopBarProps {
    theme: 'dark' | 'light';
    toggleTheme: () => void;
    user?: UserType;
    showUserMobileButton?: boolean;
    onUserMobileClick?: () => void;
}

export default function TopBar({ 
    theme, 
    toggleTheme, 
    user, 
    showUserMobileButton = false,
    onUserMobileClick 
}: TopBarProps) {
    return (
        <header className={`h-[64px] lg:h-[72px] shrink-0 px-4 sm:px-6 lg:px-8 flex items-center z-40 border-b backdrop-blur-md transition-colors duration-300 ${theme === 'dark' ? 'bg-white/5 border-white/10' : 'bg-white/70 border-slate-200 shadow-sm'}`}>
            <div className="w-full flex items-center justify-between">
                <Link href="/" className="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <div className={`flex h-9 w-9 lg:h-10 lg:w-10 items-center justify-center rounded-xl ring-1 shadow-lg relative overflow-hidden ${theme === 'dark' ? 'bg-white/10 ring-white/20' : 'bg-blue-50 ring-blue-100'}`}>
                        <div className={`absolute inset-0 z-0 ${theme === 'dark' ? 'bg-gradient-to-br from-cyan-400/20 to-blue-500/20' : 'bg-gradient-to-br from-blue-400/10 to-cyan-500/10'}`}></div>
                        <Hospital className={`h-4 w-4 lg:h-5 lg:w-5 relative z-10 ${theme === 'dark' ? 'text-cyan-300' : 'text-blue-600'}`} />
                    </div>
                    <div>
                        <h1 className={`text-sm lg:text-base font-bold tracking-wide ${theme === 'dark' ? 'text-white' : 'text-slate-800'}`}>NEXA ID</h1>
                        <p className={`text-xs hidden sm:block ${theme === 'dark' ? 'text-cyan-200/70' : 'text-slate-500'}`}>Enterprise Identity and Access Management (IAM) platform with Single Sign-On (SSO)</p>
                    </div>
                </Link>

                <div className="flex items-center gap-3">
                    {/* Theme Toggle (Desktop) */}
                    <button onClick={toggleTheme} className={`hidden lg:flex items-center justify-center w-8 h-8 rounded-full transition-colors border ${theme === 'dark' ? 'bg-white/5 border-white/10 hover:bg-white/10 text-cyan-300' : 'bg-white border-slate-200 hover:bg-slate-50 text-blue-600 shadow-sm'}`}>
                        {theme === 'dark' ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    </button>

                    {/* User button (Mobile only) */}
                    {showUserMobileButton && onUserMobileClick && (
                        <button
                            onClick={onUserMobileClick}
                            className={`lg:hidden flex items-center gap-2 px-3 py-1.5 rounded-full border transition-colors ${theme === 'dark' ? 'bg-white/5 border-white/10 hover:bg-white/10 text-white' : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm'}`}
                        >
                            <span className="text-xs font-medium">{user?.name || 'User'}</span>
                            <User className={`w-4 h-4 ${theme === 'dark' ? 'text-cyan-300' : 'text-blue-600'}`} />
                        </button>
                    )}
                </div>
            </div>
        </header>
    );
}
