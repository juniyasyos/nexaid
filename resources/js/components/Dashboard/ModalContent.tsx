import React from 'react';
import { LogOut, Settings, User, X, UserCog } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { ssoService } from '../../services/ssoService';
import { MODAL_TEXTS } from './constants';
import type { ModalContentProps } from './types';

interface ExtendedModalContentProps extends ModalContentProps {
    theme?: 'dark' | 'light';
}

export default function ModalContent({
    user,
    nip,
    logout,
    onClose,
    isMobile = false,
    accessProfiles = [],
    theme = 'dark'
}: ExtendedModalContentProps) {
    const isDark = theme === 'dark';

    return (
        <div className={`flex h-full flex-col ${isDark ? 'text-slate-200' : 'text-slate-700'}`}>
            {/* Header */}
            <div className={`md:hidden flex items-center justify-between px-4 py-3 border-b ${
                isDark ? 'border-white/10' : 'border-slate-100'
            }`}>
                {isMobile && (
                    <button
                        onClick={onClose}
                        className={`rounded-lg p-1 transition ${
                            isDark
                                ? 'text-slate-400 hover:bg-white/10 hover:text-white'
                                : 'text-slate-400 hover:bg-slate-100 hover:text-slate-800'
                        }`}
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>

            {/* Content */}
            <div className={`flex-1 px-4 py-4 ${isMobile ? 'overflow-y-auto' : ''}`}>
                {/* Profile */}
                <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ${
                        isDark
                            ? 'bg-cyan-500/10 border-cyan-400/20 text-cyan-300'
                            : 'bg-blue-50 border-blue-100 text-blue-600'
                    }`}>
                        <User className="h-4.5 w-4.5" />
                    </div>

                    <div className="min-w-0 flex-1">
                        <p className={`truncate text-sm font-bold leading-tight ${
                            isDark ? 'text-white' : 'text-slate-900'
                        }`}>
                            {user?.name || 'User'}
                        </p>
                        <p className={`mt-0.5 truncate text-xs font-semibold ${
                            isDark ? 'text-cyan-200/70' : 'text-slate-500'
                        }`}>
                            {nip}
                        </p>
                    </div>
                </div>

                <div className={`my-4 border-t ${
                    isDark ? 'border-white/10' : 'border-slate-100'
                }`} />

                {/* Status */}
                <div className="flex items-center justify-between">
                    <span className={`text-[11px] font-bold uppercase tracking-wider ${
                        isDark ? 'text-slate-400' : 'text-slate-500'
                    }`}>
                        {MODAL_TEXTS.status}
                    </span>

                    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                        isDark
                            ? 'bg-emerald-500/10 text-emerald-300 border-emerald-400/20'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    }`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${
                            isDark ? 'bg-emerald-300' : 'bg-emerald-500'
                        }`} />
                        {MODAL_TEXTS.active}
                    </span>
                </div>

                {/* Access Profiles */}
                <div className="mt-5">
                    <h3 className={`mb-2 text-[11px] font-bold uppercase tracking-[0.16em] ${
                        isDark ? 'text-cyan-300/90' : 'text-blue-600'
                    }`}>
                        {MODAL_TEXTS.profiles}
                    </h3>

                    {accessProfiles && accessProfiles.length > 0 ? (
                        <div className="space-y-2">
                            {accessProfiles.map((profile) => (
                                <div
                                    key={profile.id}
                                    className={`rounded-xl border p-3 ${
                                        isDark
                                            ? 'bg-white/[0.04] border-white/10'
                                            : 'bg-white border-slate-200'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className={`line-clamp-2 text-xs font-bold leading-snug ${
                                                isDark ? 'text-white' : 'text-slate-900'
                                            }`}>
                                                {profile.name}
                                            </p>
                                        </div>

                                        {profile.is_system && (
                                            <span className={`shrink-0 rounded-md border px-1.5 py-0.5 text-[9px] font-bold uppercase ${
                                                isDark
                                                    ? 'bg-amber-500/10 text-amber-300 border-amber-400/20'
                                                    : 'bg-amber-50 text-amber-700 border-amber-200'
                                            }`}>
                                                System
                                            </span>
                                        )}
                                    </div>

                                    {profile.description && (
                                        <p className={`mt-1.5 line-clamp-2 text-[11px] leading-relaxed ${
                                            isDark ? 'text-slate-400' : 'text-slate-500'
                                        }`}>
                                            {profile.description}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className={`rounded-xl border px-3 py-4 text-center text-xs ${
                            isDark
                                ? 'bg-white/[0.04] border-white/10 text-slate-500'
                                : 'bg-slate-50 border-slate-100 text-slate-400'
                        }`}>
                            {MODAL_TEXTS.noProfiles}
                        </div>
                    )}
                </div>
            </div>

            {/* Actions */}
            <div className={`border-t px-4 py-4 ${
                isDark
                    ? 'border-white/10 bg-slate-950/20'
                    : 'border-slate-100 bg-slate-50/70'
            }`}>
                <Link
                    href="/settings/profile"
                    className={`flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold text-white transition-all active:scale-[0.98] ${
                        isDark
                            ? 'bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 shadow-[0_8px_18px_rgba(6,182,212,0.2)]'
                            : 'bg-gradient-to-r from-blue-600 to-cyan-500 hover:from-blue-500 hover:to-cyan-400 shadow-[0_8px_18px_rgba(59,130,246,0.2)]'
                    }`}
                >
                    <UserCog className="h-3.5 w-3.5" />
                    Pengaturan Akun
                </Link>

                {nip === '0000.00000' && (
                    <button
                        onClick={() => ssoService.redirectToAdminPanel()}
                        className={`mt-2 flex w-full items-center justify-center gap-2 rounded-xl py-2.5 text-xs font-bold transition-all active:scale-[0.98] border ${
                            isDark
                                ? 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10 hover:text-white'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:text-slate-900 shadow-sm'
                        }`}
                    >
                        <Settings className="h-3.5 w-3.5" />
                        {MODAL_TEXTS.adminPanel}
                    </button>
                )}

                <button
                    onClick={logout}
                    className={`mt-2 flex w-full items-center justify-center gap-2 rounded-xl py-2 text-xs font-bold transition active:scale-[0.98] ${
                        isDark
                            ? 'text-rose-400 hover:bg-rose-500/10 hover:text-rose-300'
                            : 'text-rose-600 hover:bg-rose-50 hover:text-rose-700'
                    }`}
                >
                    <LogOut className="h-3.5 w-3.5" />
                    {MODAL_TEXTS.logout}
                </button>
            </div>
        </div>
    );
}