import React from 'react';
import { Head } from '@inertiajs/react';
import SettingsLayout from '../../layouts/SettingsLayout';

export default function Password({ theme = 'dark' }: { theme?: 'dark' | 'light' }) {
    return (
        <div className="space-y-6">
            <Head title="Kata Sandi" />
            
            <div className={`p-6 sm:p-8 rounded-[24px] border shadow-lg ${
                theme === 'dark' 
                    ? 'bg-[#ffffff]/5 border-white/10' 
                    : 'bg-white border-slate-200 shadow-sm'
            }`}>
                <h3 className={`text-lg font-bold mb-4 ${theme === 'dark' ? 'text-white' : 'text-slate-800'}`}>Ubah Kata Sandi</h3>
                <p className={`text-sm ${theme === 'dark' ? 'text-slate-400' : 'text-slate-500'}`}>
                    Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.
                </p>
                {/* Form to change password goes here */}
            </div>
        </div>
    );
}

Password.layout = (page: React.ReactNode) => (
    <SettingsLayout title="Kata Sandi">{page}</SettingsLayout>
);
