import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { User, Save, Settings, LogOut, Shield, AppWindow, KeyRound, CheckCircle2, XCircle } from 'lucide-react';
import SettingsLayout from '../../layouts/SettingsLayout';
import type { User as UserType, AccessProfile, UserApplication } from '../../types';
import { ssoService } from '../../services/ssoService';

interface ProfileProps {
    user?: UserType;
    theme?: 'dark' | 'light';
    mustVerifyEmail?: boolean;
    status?: string;
}

export default function Profile(props: ProfileProps) {
    const theme = props.theme || 'dark';
    const authUser = props.user!; 
    const isDark = theme === 'dark';

    // Profile Form
    const profileForm = useForm({
        name: authUser?.name || '',
        email: authUser?.email || '',
    });

    // Password Form
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitProfile = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.patch('/settings/profile', {
            preserveScroll: true,
        });
    };

    const submitPassword = (e: React.FormEvent) => {
        e.preventDefault();
        passwordForm.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const logout = () => {
        ssoService.logout();
    };

    return (
        <div className="flex flex-col md:flex-row gap-5 max-w-7xl mx-auto items-start">
            <Head title="Profil Akun" />
            
            {/* ─── LEFT SIDEBAR ────────────────────────────────────── */}
            <div className={`w-full md:w-72 shrink-0 flex flex-col gap-4 p-5 rounded-2xl border shadow-sm ${
                isDark ? 'bg-white/[0.02] border-white/10' : 'bg-white border-slate-200'
            }`}>
                
                {/* Avatar & Identitas Singkat */}
                <div className="flex flex-col items-center text-center">
                    <div className={`w-20 h-20 rounded-xl flex items-center justify-center border shadow-inner mb-3 ${
                        isDark 
                            ? 'bg-gradient-to-br from-cyan-500/10 to-blue-500/10 border-cyan-500/20' 
                            : 'bg-gradient-to-br from-blue-50 to-cyan-50 border-blue-200'
                    }`}>
                        <User className={`w-10 h-10 ${isDark ? 'text-cyan-400' : 'text-blue-500'}`} />
                    </div>
                    
                    <h2 className={`text-base font-bold tracking-tight leading-tight ${isDark ? 'text-white' : 'text-slate-900'}`}>
                        {authUser?.name}
                    </h2>
                    <p className={`text-xs font-medium mt-1 font-mono ${isDark ? 'text-slate-400' : 'text-slate-500'}`}>
                        {authUser?.nip || '-'}
                    </p>
                </div>

                <div className={`border-t my-1 ${isDark ? 'border-white/10' : 'border-slate-100'}`} />

                {/* Status */}
                <div className="flex flex-col gap-1">
                    <span className={`text-[10px] font-bold uppercase tracking-wider ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>Status</span>
                    <div className="flex items-center gap-1.5">
                        {authUser?.active !== false ? (
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase flex items-center gap-1.5 border ${
                                isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-600 border-emerald-200'
                            }`}>
                                <span className={`w-1.5 h-1.5 rounded-full ${isDark ? 'bg-emerald-400' : 'bg-emerald-500'}`} />
                                Active
                            </span>
                        ) : (
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase flex items-center gap-1.5 border ${
                                isDark ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-rose-50 text-rose-600 border-rose-200'
                            }`}>
                                <span className={`w-1.5 h-1.5 rounded-full ${isDark ? 'bg-rose-400' : 'bg-rose-500'}`} />
                                Inactive
                            </span>
                        )}
                    </div>
                </div>

                {/* Profil Akses Utama */}
                <div className="flex flex-col gap-1">
                    <span className={`text-[10px] font-bold uppercase tracking-wider ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>Profil Akses Utama</span>
                    <span className={`text-xs font-semibold leading-snug ${isDark ? 'text-cyan-200' : 'text-blue-700'}`}>
                        {authUser?.access_profiles?.[0]?.name || 'Tidak ada profil'}
                    </span>
                </div>

                <div className={`border-t my-1 ${isDark ? 'border-white/10' : 'border-slate-100'}`} />

                {/* Aplikasi List (Sidebar) */}
                <div className="flex flex-col gap-2">
                    <span className={`text-[10px] font-bold uppercase tracking-wider ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>Aplikasi</span>
                    {authUser?.applications && authUser.applications.length > 0 ? (
                        <ul className="space-y-1.5">
                            {authUser.applications.map((app: UserApplication, i: number) => (
                                <li key={i} className="flex items-center gap-2">
                                    <div className={`w-1 h-1 rounded-full ${isDark ? 'bg-cyan-500' : 'bg-blue-500'}`} />
                                    <span className={`text-xs font-medium ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>{app.name}</span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className={`text-xs ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>Belum ada aplikasi.</p>
                    )}
                </div>
            </div>

            {/* ─── RIGHT MAIN CONTENT ────────────────────────────────────────────── */}
            <div className="flex-1 flex flex-col gap-5 w-full min-w-0">
                
                {/* Header Profile Main */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className={`text-xl font-bold ${isDark ? 'text-white' : 'text-slate-900'}`}>{authUser?.name}</h1>
                            <span className={`text-[10px] px-2 py-0.5 rounded border font-bold uppercase ${
                                authUser?.active !== false
                                    ? (isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-600 border-emerald-200')
                                    : (isDark ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-rose-50 text-rose-600 border-rose-200')
                            }`}>
                                {authUser?.active !== false ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <p className={`text-sm mt-0.5 ${isDark ? 'text-cyan-200/70' : 'text-blue-600/80'}`}>{authUser?.role || 'Pengguna Sistem'}</p>
                    </div>

                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <button
                            onClick={() => ssoService.redirectToAdminPanel()}
                            className={`flex-1 sm:flex-none flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all border ${
                                isDark
                                    ? 'bg-white/5 border-white/10 text-white hover:bg-white/10'
                                    : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 shadow-sm'
                            }`}
                        >
                            <Settings className="w-3.5 h-3.5" />
                            Admin Panel
                        </button>
                        
                        <button
                            onClick={logout}
                            className={`flex-1 sm:flex-none flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all border ${
                                isDark
                                    ? 'bg-rose-500/10 border-rose-500/20 text-rose-400 hover:bg-rose-500/20'
                                    : 'bg-rose-50 border-rose-200 text-rose-600 hover:bg-rose-100'
                            }`}
                        >
                            <LogOut className="w-3.5 h-3.5" />
                            Keluar
                        </button>
                    </div>
                </div>

                <div className={`border-t w-full ${isDark ? 'border-white/10' : 'border-slate-200'}`} />

                {/* Grid 2 Column for Forms */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    
                    {/* Account Info Form */}
                    <div className={`p-5 rounded-2xl border flex flex-col ${isDark ? 'bg-white/[0.02] border-white/10' : 'bg-white border-slate-200'}`}>
                        <h3 className={`text-sm font-bold flex items-center gap-2 mb-4 uppercase tracking-wider ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>
                            <User className={`w-4 h-4 ${isDark ? 'text-cyan-400' : 'text-blue-500'}`} />
                            Informasi Akun
                        </h3>

                        <form onSubmit={submitProfile} className="space-y-4 flex-1 flex flex-col">
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>NIP</label>
                                <div className="col-span-2">
                                    <input
                                        type="text"
                                        value={authUser?.nip || ''}
                                        disabled
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs font-mono opacity-60 cursor-not-allowed ${
                                            isDark ? 'bg-black/20 border-white/10 text-slate-300' : 'bg-slate-100 border-slate-200 text-slate-600'
                                        }`}
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Nama</label>
                                <div className="col-span-2">
                                    <input
                                        type="text"
                                        value={profileForm.data.name}
                                        onChange={(e) => profileForm.setData('name', e.target.value)}
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs focus:ring-1 focus:outline-none transition-all ${
                                            isDark 
                                                ? 'bg-black/20 border-white/10 text-white focus:border-cyan-500 focus:ring-cyan-500' 
                                                : 'bg-white border-slate-300 text-slate-900 focus:border-blue-500 focus:ring-blue-500'
                                        }`}
                                    />
                                    {profileForm.errors.name && <p className="text-[10px] text-rose-500 mt-1">{profileForm.errors.name}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Email</label>
                                <div className="col-span-2">
                                    <input
                                        type="email"
                                        value={profileForm.data.email}
                                        onChange={(e) => profileForm.setData('email', e.target.value)}
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs focus:ring-1 focus:outline-none transition-all ${
                                            isDark 
                                                ? 'bg-black/20 border-white/10 text-white focus:border-cyan-500 focus:ring-cyan-500' 
                                                : 'bg-white border-slate-300 text-slate-900 focus:border-blue-500 focus:ring-blue-500'
                                        }`}
                                    />
                                    {profileForm.errors.email && <p className="text-[10px] text-rose-500 mt-1">{profileForm.errors.email}</p>}
                                </div>
                            </div>
                            <div className="mt-auto pt-4 flex items-center justify-end gap-3">
                                {profileForm.recentlySuccessful && (
                                    <span className="text-[10px] font-bold text-emerald-500 uppercase tracking-wide">Tersimpan</span>
                                )}
                                <button
                                    type="submit"
                                    disabled={profileForm.processing || !profileForm.isDirty}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed border ${
                                        isDark
                                            ? 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20 hover:bg-cyan-500/20'
                                            : 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
                                    }`}
                                >
                                    Simpan Info
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Ubah Sandi Form */}
                    <div className={`p-5 rounded-2xl border flex flex-col ${isDark ? 'bg-white/[0.02] border-white/10' : 'bg-white border-slate-200'}`}>
                        <h3 className={`text-sm font-bold flex items-center gap-2 mb-4 uppercase tracking-wider ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>
                            <KeyRound className={`w-4 h-4 ${isDark ? 'text-amber-400' : 'text-amber-500'}`} />
                            Ubah Sandi
                        </h3>

                        <form onSubmit={submitPassword} className="space-y-4 flex-1 flex flex-col">
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Sandi Saat Ini</label>
                                <div className="col-span-2">
                                    <input
                                        type="password"
                                        value={passwordForm.data.current_password}
                                        onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs focus:ring-1 focus:outline-none transition-all ${
                                            isDark 
                                                ? 'bg-black/20 border-white/10 text-white focus:border-amber-500 focus:ring-amber-500' 
                                                : 'bg-white border-slate-300 text-slate-900 focus:border-amber-500 focus:ring-amber-500'
                                        }`}
                                    />
                                    {passwordForm.errors.current_password && <p className="text-[10px] text-rose-500 mt-1">{passwordForm.errors.current_password}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Sandi Baru</label>
                                <div className="col-span-2">
                                    <input
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs focus:ring-1 focus:outline-none transition-all ${
                                            isDark 
                                                ? 'bg-black/20 border-white/10 text-white focus:border-amber-500 focus:ring-amber-500' 
                                                : 'bg-white border-slate-300 text-slate-900 focus:border-amber-500 focus:ring-amber-500'
                                        }`}
                                    />
                                    {passwordForm.errors.password && <p className="text-[10px] text-rose-500 mt-1">{passwordForm.errors.password}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 items-center">
                                <label className={`text-xs font-semibold ${isDark ? 'text-slate-400' : 'text-slate-600'}`}>Konfirmasi</label>
                                <div className="col-span-2">
                                    <input
                                        type="password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        className={`w-full rounded-lg border px-3 py-1.5 text-xs focus:ring-1 focus:outline-none transition-all ${
                                            isDark 
                                                ? 'bg-black/20 border-white/10 text-white focus:border-amber-500 focus:ring-amber-500' 
                                                : 'bg-white border-slate-300 text-slate-900 focus:border-amber-500 focus:ring-amber-500'
                                        }`}
                                    />
                                </div>
                            </div>
                            <div className="mt-auto pt-4 flex items-center justify-end gap-3">
                                {passwordForm.recentlySuccessful && (
                                    <span className="text-[10px] font-bold text-emerald-500 uppercase tracking-wide">Sandi Diubah</span>
                                )}
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing || !passwordForm.isDirty}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed border ${
                                        isDark
                                            ? 'bg-amber-500/10 text-amber-300 border-amber-500/20 hover:bg-amber-500/20'
                                            : 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100'
                                    }`}
                                >
                                    Ubah Sandi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Detailed Sections: Access Profiles */}
                <div className="mt-2">
                    <h3 className={`text-sm font-bold flex items-center gap-2 mb-3 uppercase tracking-wider ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>
                        <Shield className={`w-4 h-4 ${isDark ? 'text-purple-400' : 'text-purple-600'}`} />
                        Profil Akses
                    </h3>
                    
                    {authUser?.access_profiles && authUser.access_profiles.length > 0 ? (
                        <div className={`border rounded-2xl overflow-hidden ${isDark ? 'border-white/10 bg-white/[0.01]' : 'border-slate-200 bg-white'}`}>
                            {authUser.access_profiles.map((profile: AccessProfile, idx) => (
                                <div key={profile.id} className={`p-4 flex flex-col sm:flex-row sm:items-center gap-3 ${idx !== 0 && (isDark ? 'border-t border-white/5' : 'border-t border-slate-100')}`}>
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-1">
                                            <span className={`text-sm font-bold ${isDark ? 'text-white' : 'text-slate-900'}`}>{profile.name}</span>
                                            {profile.is_system && (
                                                <span className={`px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border ${
                                                    isDark ? 'bg-purple-500/10 text-purple-300 border-purple-500/20' : 'bg-purple-50 text-purple-700 border-purple-200'
                                                }`}>
                                                    System
                                                </span>
                                            )}
                                        </div>
                                        <p className={`text-xs ${isDark ? 'text-slate-400' : 'text-slate-500'}`}>{profile.description || 'Tidak ada deskripsi'}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className={`p-4 rounded-2xl border text-center text-xs ${isDark ? 'border-white/10 bg-white/[0.02] text-slate-500' : 'border-slate-200 bg-slate-50 text-slate-400'}`}>
                            Tidak ada profil akses
                        </div>
                    )}
                </div>

                {/* Detailed Sections: Aplikasi & Role di dalamnya */}
                <div className="mt-2">
                    <h3 className={`text-sm font-bold flex items-center gap-2 mb-3 uppercase tracking-wider ${isDark ? 'text-slate-300' : 'text-slate-700'}`}>
                        <AppWindow className={`w-4 h-4 ${isDark ? 'text-teal-400' : 'text-teal-600'}`} />
                        Aplikasi & Role Spesifik
                    </h3>

                    {authUser?.applications && authUser.applications.length > 0 ? (
                        <div className={`border rounded-2xl overflow-hidden ${isDark ? 'border-white/10 bg-white/[0.01]' : 'border-slate-200 bg-white'}`}>
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className={`border-b text-[10px] uppercase tracking-wider ${isDark ? 'bg-black/20 border-white/10 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-500'}`}>
                                        <th className="py-2.5 px-4 font-bold">Aplikasi</th>
                                        <th className="py-2.5 px-4 font-bold">Deskripsi</th>
                                        <th className="py-2.5 px-4 font-bold">Role Yang Dimiliki</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {authUser.applications.map((app: UserApplication, idx) => (
                                        <tr key={idx} className={`text-xs ${idx !== authUser.applications!.length - 1 && (isDark ? 'border-b border-white/5' : 'border-b border-slate-100')}`}>
                                            <td className={`py-3 px-4 font-bold ${isDark ? 'text-slate-200' : 'text-slate-800'}`}>
                                                {app.name}
                                                {!app.enabled && (
                                                    <span className={`ml-2 px-1.5 py-0.5 rounded text-[9px] uppercase border ${isDark ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-rose-50 text-rose-600 border-rose-200'}`}>Offline</span>
                                                )}
                                            </td>
                                            <td className={`py-3 px-4 ${isDark ? 'text-slate-400' : 'text-slate-500'}`}>{app.description || '-'}</td>
                                            <td className="py-3 px-4">
                                                {app.roles && app.roles.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {app.roles.map((r, i) => (
                                                            <span key={i} className={`px-2 py-0.5 rounded border text-[10px] font-semibold ${
                                                                isDark ? 'bg-teal-500/10 text-teal-300 border-teal-500/20' : 'bg-teal-50 text-teal-700 border-teal-200'
                                                            }`}>
                                                                {r.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className={`italic ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>Tidak ada role spesifik</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className={`p-4 rounded-2xl border text-center text-xs ${isDark ? 'border-white/10 bg-white/[0.02] text-slate-500' : 'border-slate-200 bg-slate-50 text-slate-400'}`}>
                            Tidak ada akses aplikasi spesifik
                        </div>
                    )}
                </div>

            </div>
        </div>
    );
}

Profile.layout = (page: React.ReactNode) => (
    <SettingsLayout title="Account Profile">{page}</SettingsLayout>
);
