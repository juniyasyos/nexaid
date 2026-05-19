import React from 'react';
import { Hospital, Lock, User, Heart, Activity, Stethoscope, EyeOff, Eye } from 'lucide-react';

interface Props {
  nip: string;
  setNip: (v: string) => void;
  password: string;
  setPassword: (v: string) => void;
  focusedInput: string | null;
  setFocusedInput: (v: string | null) => void;
  showPassword: boolean;
  setShowPassword: (v: boolean) => void;
  showError: boolean;
  onCloseError: () => void;
  handleSubmit: (e: React.FormEvent) => void;
  companyName?: string;
  isLoading: boolean;
  error?: string | null;
}

import '../../../css/components/login.css';

export default function LoginDefaultView({
  nip,
  setNip,
  password,
  setPassword,
  focusedInput,
  setFocusedInput,
  showPassword,
  setShowPassword,
  showError,
  onCloseError,
  handleSubmit,
  companyName,
  isLoading,
  error,
}: Props) {
  return (
    <div className="min-h-screen relative flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-cyan-50 p-4 md:p-6 relative overflow-hidden">
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50" />

        <div className="absolute top-20 left-[10%] text-blue-300/40 animate-float-slow">
          <Heart className="w-16 h-16" />
        </div>
        <div className="absolute top-40 right-[15%] text-cyan-300/40 animate-float-medium">
          <Activity className="w-20 h-20" />
        </div>
        <div className="absolute bottom-32 left-[20%] text-teal-300/40 animate-float-fast">
          <Stethoscope className="w-14 h-14" />
        </div>
        <div className="absolute bottom-20 right-[10%] text-blue-300/40 animate-float-slow" style={{ animationDelay: '1s' }}>
          <Hospital className="w-12 h-12" />
        </div>
        <div className="absolute top-[60%] left-[5%] text-cyan-200/30 animate-float-medium" style={{ animationDelay: '2s' }}>
          <Heart className="w-10 h-10" />
        </div>
        <div className="absolute top-[30%] right-[5%] text-teal-200/30 animate-float-fast" style={{ animationDelay: '1.5s' }}>
          <Activity className="w-12 h-12" />
        </div>

        <div className="absolute -top-40 -left-40 w-80 h-80 bg-gradient-to-br from-blue-300/30 to-cyan-300/30 rounded-full blur-3xl animate-blob" />
        <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-gradient-to-br from-cyan-300/30 to-teal-300/30 rounded-full blur-3xl animate-blob" style={{ animationDelay: '2s' }} />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-br from-cyan-200/20 to-blue-200/20 rounded-full blur-3xl animate-blob" style={{ animationDelay: '4s' }} />

        <div className="absolute top-[15%] left-[25%] w-3 h-3 bg-blue-400/50 rounded-full animate-float-particle" />
        <div className="absolute top-[45%] left-[15%] w-2 h-2 bg-cyan-400/50 rounded-full animate-float-particle" style={{ animationDelay: '1s' }} />
        <div className="absolute top-[70%] right-[20%] w-4 h-4 bg-teal-400/50 rounded-full animate-float-particle" style={{ animationDelay: '2s' }} />
        <div className="absolute top-[25%] right-[30%] w-2 h-2 bg-blue-400/50 rounded-full animate-float-particle" style={{ animationDelay: '3s' }} />
        <div className="absolute bottom-[40%] left-[35%] w-3 h-3 bg-cyan-400/50 rounded-full animate-float-particle" style={{ animationDelay: '1.5s' }} />

        <div className="absolute top-0 left-[20%] w-px h-full bg-gradient-to-b from-transparent via-blue-200/30 to-transparent animate-slide-down" />
        <div className="absolute top-0 left-[60%] w-px h-full bg-gradient-to-b from-transparent via-cyan-200/30 to-transparent animate-slide-down" style={{ animationDelay: '2s' }} />
        <div className="absolute top-0 right-[25%] w-px h-full bg-gradient-to-b from-transparent via-teal-200/30 to-transparent animate-slide-down" style={{ animationDelay: '4s' }} />
      </div>

      {showError && (
        <div className="fixed top-4 right-4 z-50 w-[320px] animate-fadeIn">
          <div className="relative overflow-hidden rounded-xl border border-red-200 bg-white shadow-xl">
            <div className="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-red-400 to-red-500 animate-error-bar" />
            <div className="flex items-start gap-3 p-4">
              <div className="flex-shrink-0 mt-0.5 text-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z" />
                </svg>
              </div>
              <div className="flex-1 text-sm">
                <p className="font-semibold text-red-600">Terjadi kesalahan</p>
                <p className="text-slate-500">{error}</p>
              </div>
              <button onClick={onCloseError} className="text-slate-400 hover:text-red-500 transition">✕</button>
            </div>
          </div>
        </div>
      )}

      <div className="w-full scale-[0.9] max-w-md relative z-10">
        <div className="text-center scale-[0.9] mb-8 -mt-8 animate-fadeIn">
          <div className="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500 rounded-full mb-6 shadow-2xl animate-float">
            <Hospital className="w-12 h-12 text-white" />
          </div>
          <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-700 via-cyan-600 to-teal-600 bg-clip-text text-transparent mb-3 uppercase">
            {companyName ?? 'RS Citra Husada'}
          </h1>
          <p className="text-slate-500 text-lg">Secure Integrated Hospital Management Platform</p>
          {import.meta.env.VITE_APP_ENV === 'dev' && (
            <div className="mt-2 px-3 py-1 bg-orange-100 border border-orange-300 rounded-full text-orange-800 text-sm font-medium text-center">Development Mode - Auto-filled credentials</div>
          )}
          <div className="mt-4 h-1 w-20 bg-gradient-to-r from-blue-400 to-cyan-400 mx-auto rounded-full animate-pulse" />
        </div>

        <div className="space-y-8">
          <div className="relative group">
            <label htmlFor="nip" className="block text-slate-600 font-medium mb-3 ml-1">NIP</label>
            <div className="relative">
              <div className={`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${focusedInput === 'nip' ? 'text-blue-500' : 'text-slate-400'}`}>
                <User className="w-5 h-5" />
              </div>
              <input
                type="text"
                id="nip"
                value={nip}
                onChange={(e) => setNip(e.target.value)}
                onFocus={() => setFocusedInput('nip')}
                onBlur={() => setFocusedInput(null)}
                placeholder="Masukkan NIP"
                className={`w-full pl-10 pr-4 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ${import.meta.env.VITE_APP_ENV === 'dev' && nip ? 'bg-orange-50/50' : ''}`}
                required
              />
              <div className={`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${focusedInput === 'nip' ? 'w-full' : 'w-0'}`} />
            </div>
          </div>

          <div className="relative group">
            <label htmlFor="password" className="block text-slate-600 font-medium mb-3 ml-1">Password</label>
            <div className="relative">
              <div className={`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${focusedInput === 'password' ? 'text-blue-500' : 'text-slate-400'}`}>
                <Lock className="w-5 h-5" />
              </div>
              <input
                type={showPassword ? 'text' : 'password'}
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                onFocus={() => setFocusedInput('password')}
                onBlur={() => setFocusedInput(null)}
                placeholder="Masukkan password"
                className={`w-full pl-10 pr-12 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ${import.meta.env.VITE_APP_ENV === 'dev' && password ? 'bg-orange-50/50' : ''}`}
                required
              />
              <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-0 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition-colors duration-200" tabIndex={-1}>
                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
              </button>
              <div className={`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${focusedInput === 'password' ? 'w-full' : 'w-0'}`} />
            </div>
          </div>

          <button onClick={handleSubmit} disabled={isLoading} className="w-full relative group overflow-hidden bg-gradient-to-r from-blue-500 to-cyan-500 text-white py-4 rounded-full shadow-lg hover:shadow-2xl transition-all duration-300 mt-8 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
            <span className="relative z-10 font-semibold text-lg">{isLoading ? 'Logging in...' : 'Login'}</span>
            <div className="absolute inset-0 bg-gradient-to-r from-blue-400 to-cyan-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
          </button>
        </div>

        <div className="mt-10 text-center">
          <div className="mt-4 flex items-center justify-center gap-2">
            <div className="w-2 h-2 bg-blue-400 rounded-full animate-ping" />
            <div className="w-2 h-2 bg-cyan-400 rounded-full animate-ping" style={{ animationDelay: '0.5s' }} />
            <div className="w-2 h-2 bg-blue-400 rounded-full animate-ping" style={{ animationDelay: '1s' }} />
          </div>
        </div>
      </div>
    </div>
  );
}
