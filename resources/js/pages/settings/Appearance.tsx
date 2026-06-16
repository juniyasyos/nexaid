import React from 'react';
import { Head } from '@inertiajs/react';
import SettingsLayout from '../../layouts/SettingsLayout';

export default function Appearance({ theme = 'dark' }: { theme?: 'dark' | 'light' }) {
    return (
        <div className="space-y-6">
            <Head title="Penampilan" />
            
            <div className={`p-6 sm:p-8 rounded-[24px] border shadow-lg ${
                theme === 'dark' 
                    ? 'bg-[#ffffff]/5 border-white/10' 
                    : 'bg-white border-slate-200 shadow-sm'
            }`}>
                <h3 className={`text-lg font-bold mb-4 ${theme === 'dark' ? 'text-white' : 'text-slate-800'}`}>Penampilan Tema</h3>
                <p className={`text-sm ${theme === 'dark' ? 'text-slate-400' : 'text-slate-500'}`}>
                    Ubah preferensi tampilan sistem sesuai keinginan Anda. (Gunakan tombol matahari/bulan di sudut atas).
                </p>
                {/* Theme settings content goes here */}
            </div>
        </div>
    );
}

Appearance.layout = (page: React.ReactNode) => (
    <SettingsLayout title="Penampilan">{page}</SettingsLayout>
);
