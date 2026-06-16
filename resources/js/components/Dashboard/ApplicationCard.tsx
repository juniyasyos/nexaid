import React from 'react';
import type { ApplicationWithIcon } from './types';
import type { Application } from '../../types';

interface ApplicationCardProps {
    app: ApplicationWithIcon;
    index: number;
    onAppClick: (app: Application) => void;
    theme?: 'dark' | 'light';
}

export default function ApplicationCard({ app, index, onAppClick, theme = 'dark' }: ApplicationCardProps) {
    const Icon = app.icon;

    return (
        <div style={{ opacity: 0, animation: `slideUp 0.6s ease-out ${0.1 * index}s forwards` }} className="h-full">
            <button
                onClick={() => onAppClick(app)}
                disabled={!app.isOnline}
                className={`group relative w-full text-left h-full min-h-[220px] disabled:opacity-60 disabled:cursor-not-allowed backdrop-blur-xl rounded-[20px] p-5 sm:p-6 shadow-[0_8px_30px_rgb(0,0,0,0.12)] transition-all duration-300 hover:-translate-y-1.5 active:scale-[0.98] overflow-hidden flex flex-col border ${
                    theme === 'dark' 
                        ? 'bg-[#ffffff]/5 border-white/10 hover:border-cyan-500/50 hover:shadow-[0_20px_40px_rgba(6,182,212,0.15)]' 
                        : 'bg-white border-slate-200 hover:border-blue-400/50 hover:shadow-[0_20px_40px_rgba(59,130,246,0.15)]'
                }`}
            >
                {/* Gradient overlay on hover */}
                <div className={`absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 ${theme === 'dark' ? 'bg-gradient-to-br from-cyan-500/5 to-blue-500/5' : 'bg-gradient-to-br from-blue-500/5 to-cyan-500/5'}`} />

                {/* Offline overlay */}
                {!app.isOnline && (
                    <div className={`absolute inset-0 z-30 flex items-center justify-center backdrop-blur-[2px] ${theme === 'dark' ? 'bg-rose-900/30' : 'bg-rose-100/50'}`}>
                        <span className={`font-bold text-xs px-3 py-1.5 rounded-lg border shadow-lg tracking-wide ${theme === 'dark' ? 'text-rose-200 bg-rose-600/40 border-rose-500/50' : 'text-rose-700 bg-rose-100 border-rose-300'}`}>
                            MAINTENANCE / OFFLINE
                        </span>
                    </div>
                )}

                {/* Header with Icon and Status */}
                <div className="flex justify-between items-start mb-4 relative z-10">
                    {/* Icon */}
                    <div className={`relative inline-flex p-3 rounded-xl text-white shadow-xl group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 bg-gradient-to-br ${app.gradient}`}>
                        <Icon className="w-6 h-6 sm:w-8 sm:h-8" />
                        <div className={`absolute inset-0 rounded-xl blur-lg opacity-60 -z-10 bg-gradient-to-br ${app.gradient}`}></div>
                    </div>
                    {/* Status Dot */}
                    <div className="flex flex-col items-end gap-2">
                        {app.status && (
                            <span className={`text-[10px] font-bold tracking-wide px-2.5 py-1 rounded-lg flex items-center gap-1.5 border shadow-inner ${
                                theme === 'dark' 
                                    ? 'bg-[#0b1226]/50 border-white/10 text-slate-200' 
                                    : 'bg-slate-50 border-slate-200 text-slate-700'
                            }`}>
                                <span className={`w-1.5 h-1.5 rounded-full shadow-[0_0_8px_currentColor] ${app.status === 'Siap Diakses' ? (theme === 'dark' ? 'bg-cyan-400 text-cyan-400' : 'bg-emerald-500 text-emerald-500') : 'bg-amber-400 text-amber-400 animate-pulse'}`} />
                                {app.status === 'Siap Diakses' ? 'Ready' : app.status}
                            </span>
                        )}
                        {app.userRole && (
                            <span className={`text-[10px] px-2 py-0.5 rounded-lg font-semibold shadow-sm border ${
                                theme === 'dark'
                                    ? 'text-cyan-100 bg-gradient-to-r from-cyan-600/40 to-blue-600/40 border-cyan-500/30'
                                    : 'text-blue-700 bg-blue-50 border-blue-200'
                            }`}>
                                👤 {app.userRole}
                            </span>
                        )}
                    </div>
                </div>

                {/* Content */}
                <div className="relative z-10 flex-1 flex flex-col">
                    <h3 className={`text-[85%] font-bold mb-2 leading-tight transition-colors line-clamp-2 ${theme === 'dark' ? 'text-white group-hover:text-cyan-300' : 'text-slate-800 group-hover:text-blue-600'}`}>
                        {app.name}
                    </h3>
                    <p className={`text-[65%] line-clamp-3 mb-4 leading-relaxed font-medium ${theme === 'dark' ? 'text-slate-300/80' : 'text-slate-600'}`}>
                        {app.description}
                    </p>

                    {/* Action Footer */}
                    <div className={`mt-auto pt-4 border-t flex items-center justify-between ${theme === 'dark' ? 'border-white/10' : 'border-slate-100'}`}>
                        <span className={`text-sm font-bold tracking-wide transition-colors ${theme === 'dark' ? 'text-cyan-400 group-hover:text-cyan-300' : 'text-blue-600 group-hover:text-blue-700'}`}>
                            Buka Aplikasi
                        </span>
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 border ${
                            theme === 'dark' 
                                ? 'bg-white/5 border-white/10 group-hover:bg-gradient-to-r group-hover:from-cyan-500 group-hover:to-blue-600 group-hover:border-transparent group-hover:text-white group-hover:shadow-[0_0_15px_rgba(6,182,212,0.5)] text-slate-400' 
                                : 'bg-slate-50 border-slate-200 group-hover:bg-gradient-to-r group-hover:from-blue-500 group-hover:to-cyan-500 group-hover:border-transparent group-hover:text-white group-hover:shadow-[0_0_15px_rgba(59,130,246,0.5)] text-slate-400'
                        }`}>
                            <svg className={`w-4 h-4 transform group-hover:translate-x-0.5 transition-transform group-hover:text-white`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </div>
                    </div>
                </div>
            </button>
        </div>
    );
}
