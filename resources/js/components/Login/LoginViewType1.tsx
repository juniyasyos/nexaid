import React, { useState, FormEvent } from "react";
import { Eye, EyeOff, Lock, User, ShieldCheck, ArrowRight } from "lucide-react";

/**
 * RS CITRA HUSADA — Admin Login
 * Single-page component. Split-screen enterprise layout.
 *  - Left: branding with abstract layered waves (hidden on mobile)
 *  - Right: login form (NIP + password)
 */

// Animation styles
const animationStyles = `
    @keyframes wave-drift {
        0%, 100% { transform: translateX(0) translateY(0); }
        25% { transform: translateX(-8px) translateY(-4px); }
        50% { transform: translateX(0) translateY(-8px); }
        75% { transform: translateX(8px) translateY(-4px); }
    }
    @keyframes float-gentle {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-12px) rotate(2deg); }
    }
    @keyframes float-gentle-alt {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(-1.5deg); }
    }
    @keyframes pulse-glow {
        0%, 100% { opacity: 0.35; filter: drop-shadow(0 0 0px rgba(96, 165, 250, 0.5)); }
        50% { opacity: 0.55; filter: drop-shadow(0 0 8px rgba(96, 165, 250, 0.8)); }
    }
    @keyframes icon-float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-16px); }
    }
    .animate-wave-drift { animation: wave-drift 6s ease-in-out infinite; }
    .animate-float-gentle { animation: float-gentle 4s ease-in-out infinite; }
    .animate-float-gentle-alt { animation: float-gentle-alt 5s ease-in-out infinite; }
    .animate-pulse-glow { animation: pulse-glow 3s ease-in-out infinite; }
    .animate-icon-float { animation: icon-float 3.5s ease-in-out infinite; }
`;
interface Props {
    nip: string;
    setNip: (v: string) => void;
    password: string;
    setPassword: (v: string) => void;
    focusedInput?: string | null;
    setFocusedInput?: (v: string | null) => void;
    showPassword: boolean;
    setShowPassword: (v: boolean) => void;
    showError?: boolean;
    onCloseError?: () => void;
    handleSubmit: (e: FormEvent) => void;
    companyName?: string;
    isLoading: boolean;
    error?: string | null;
}

const LoginViewType1: React.FC<Props> = ({
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
}) => {
    const [remember, setRemember] = useState<boolean>(true); // UI-only

    return (
        <div
            data-testid="login-page"
            className="min-h-screen w-full flex bg-[#0b1226] text-slate-100 font-sans antialiased selection:bg-sky-400/30 selection:text-white"
        >
            <style>{animationStyles}</style>
            {/* ===================== LEFT — BRANDING ===================== */}
            <aside
                data-testid="login-branding"
                className="relative hidden lg:flex w-1/2 overflow-hidden"
            >
                {/* Layered abstract wave background */}
                <svg
                    aria-hidden="true"
                    className="absolute inset-0 h-full w-full"
                    viewBox="0 0 800 1000"
                    preserveAspectRatio="xMidYMid slice"
                >
                    <defs>
                        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stopColor="#1e3a8a" />
                            <stop offset="60%" stopColor="#0f1d44" />
                            <stop offset="100%" stopColor="#070d22" />
                        </linearGradient>
                        <linearGradient id="wave1" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#3b82f6" stopOpacity="0.35" />
                            <stop offset="100%" stopColor="#1e40af" stopOpacity="0.15" />
                        </linearGradient>
                        <linearGradient id="wave2" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stopColor="#0ea5e9" stopOpacity="0.25" />
                            <stop offset="100%" stopColor="#1e3a8a" stopOpacity="0.55" />
                        </linearGradient>
                        <linearGradient id="wave3" x1="0" y1="1" x2="0" y2="0">
                            <stop offset="0%" stopColor="#60a5fa" stopOpacity="0.45" />
                            <stop offset="100%" stopColor="#1e3a8a" stopOpacity="0.0" />
                        </linearGradient>
                        <radialGradient id="glow" cx="20%" cy="30%" r="60%">
                            <stop offset="0%" stopColor="#60a5fa" stopOpacity="0.35" />
                            <stop offset="100%" stopColor="#0b1226" stopOpacity="0" />
                        </radialGradient>
                    </defs>

                    <rect width="800" height="1000" fill="url(#bg)" />
                    <rect width="800" height="1000" fill="url(#glow)" />

                    {/* Soft wave shapes */}
                    <path
                        d="M0,720 C150,650 250,820 400,760 C560,700 660,860 800,800 L800,1000 L0,1000 Z"
                        fill="url(#wave1)"
                        className="animate-wave-drift"
                    />
                    <path
                        d="M0,820 C180,760 320,900 500,840 C640,790 720,920 800,880 L800,1000 L0,1000 Z"
                        fill="url(#wave2)"
                        className="animate-wave-drift"
                        style={{ animationDelay: "0.3s" }}
                    />
                    <path
                        d="M0,900 C200,860 400,960 600,910 C700,885 760,940 800,920 L800,1000 L0,1000 Z"
                        fill="url(#wave3)"
                        className="animate-wave-drift"
                        style={{ animationDelay: "0.6s" }}
                    />

                    {/* Decorative top blob */}
                    <circle cx="640" cy="120" r="180" fill="#3b82f6" fillOpacity="0.08" className="animate-pulse-glow" />
                    <circle cx="720" cy="60" r="60" fill="#60a5fa" fillOpacity="0.12" className="animate-pulse-glow" style={{ animationDelay: "0.5s" }} />
                </svg>

                {/* Grid / noise overlay for depth */}
                <div
                    aria-hidden="true"
                    className="absolute inset-0 opacity-[0.07] mix-blend-overlay"
                    style={{
                        backgroundImage:
                            "linear-gradient(to right, rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.6) 1px, transparent 1px)",
                        backgroundSize: "48px 48px",
                    }}
                />

                {/* Content */}
                <div className="relative z-10 flex h-full w-full flex-col justify-between p-12 xl:p-16">
                    <div className="flex items-center gap-3">
                        <div
                            data-testid="brand-logo"
                            className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 backdrop-blur-md ring-1 ring-white/20"
                        >
                            <ShieldCheck className="h-6 w-6 text-sky-300" />
                        </div>
                        <span className="text-sm font-medium tracking-[0.2em] text-sky-200/80">
                            RSCH · IHMS
                        </span>
                    </div>

                    <div className="max-w-xl">
                        <span
                            data-testid="brand-eyebrow"
                            className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-medium uppercase tracking-[0.18em] text-sky-200/90 backdrop-blur-md animate-float-gentle-alt"
                        >
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_12px_2px_rgba(52,211,153,0.7)] animate-pulse" />
                            Hello There!! 👋
                        </span>

                        <h1
                            data-testid="brand-title"
                            className="mt-6 text-5xl xl:text-6xl font-bold leading-[1.05] tracking-tight text-white"
                        >
                            RS CITRA{" "}
                            <span className="bg-gradient-to-r from-sky-300 via-blue-200 to-indigo-300 bg-clip-text text-transparent">
                                HUSADA
                            </span>
                        </h1>

                        <p className="mt-5 max-w-md text-lg leading-relaxed text-slate-300/85">
                            Integrated hospital system for managing clinical, administrative, and operational workflows securely.
                        </p>

                        <div className="mt-10 grid grid-cols-3 gap-4 max-w-md">
                            {[
                                { k: "99.98%", v: "Uptime" },
                                { k: "24/7 Hours", v: "Accessible" },
                                { k: "ISO 27001", v: "Compliant" },
                            ].map((s) => (
                                <div
                                    key={s.v}
                                    className="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 backdrop-blur-md"
                                >
                                    <div className="text-base font-semibold text-white">{s.k}</div>
                                    <div className="mt-1 text-xs text-slate-400">{s.v}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center justify-between text-xs text-slate-400">
                        <span>© {new Date().getFullYear()} RS Citra Husada</span>
                        <span className="flex items-center gap-2 animate-float-gentle">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                            All systems operational
                        </span>
                    </div>
                </div>
            </aside>

            {/* ===================== RIGHT — FORM ===================== */}
            <main
                data-testid="login-form-section"
                className="relative flex w-full lg:w-1/2 items-center justify-center px-6 py-12 sm:px-10"
            >
                {/* Soft ambient orb (mobile fallback feel) */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 overflow-hidden lg:hidden"
                >
                    <div className="absolute -top-32 -right-24 h-80 w-80 rounded-full bg-blue-600/25 blur-3xl" />
                    <div className="absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl" />
                </div>

                <div className="relative z-10 w-full max-w-md">
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
                    {/* Mobile branding (small) */}
                    <div className="mb-10 flex items-center gap-3 lg:hidden">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                            <ShieldCheck className="h-5 w-5 text-sky-300" />
                        </div>
                        <div>
                            <div className="text-sm font-semibold text-white">RS Citra Husada</div>
                            <div className="text-xs text-slate-400">Integrated Hospital Managemen Syetem</div>
                        </div>
                    </div>

                    <header className="mb-8">
                        <h2
                            data-testid="form-title"
                            className="text-4xl font-bold tracking-tight text-white"
                        >
                            Admin Login
                        </h2>
                        <p
                            data-testid="form-subtitle"
                            className="mt-3 text-sm text-slate-400"
                        >
                            Secure access to system dashboard. Sign in with your hospital
                            credentials to continue.
                        </p>
                    </header>

                    <form
                        data-testid="login-form"
                        onSubmit={handleSubmit}
                        noValidate
                        className="space-y-5"
                    >
                        {/* NIP */}
                        <Field id="nip" label="NIP" hint="Nomor Induk Pegawai">
                            <div className="relative">
                                <User className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                                <input
                                    id="nip"
                                    name="nip"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="username"
                                    placeholder="e.g. 1987654321"
                                    value={nip}
                                    data-testid="nip-input"
                                    onChange={(e) => setNip(e.target.value)}
                                    onFocus={() => setFocusedInput && setFocusedInput('nip')}
                                    onBlur={() => setFocusedInput && setFocusedInput(null)}
                                    className={inputClasses(false)}
                                />
                            </div>
                        </Field>

                        {/* Password */}
                        <Field id="password" label="Password">
                            <div className="relative">
                                <Lock className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? "text" : "password"}
                                    autoComplete="current-password"
                                    placeholder="Enter your password"
                                    value={password}
                                    data-testid="password-input"
                                    onChange={(e) => setPassword(e.target.value)}
                                    onFocus={() => setFocusedInput && setFocusedInput('password')}
                                    onBlur={() => setFocusedInput && setFocusedInput(null)}
                                    className={inputClasses(false) + " pr-12"}
                                />
                                <button
                                    type="button"
                                    data-testid="toggle-password-visibility"
                                    onClick={() => setShowPassword(!showPassword)}
                                    aria-label={showPassword ? "Hide password" : "Show password"}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 transition hover:text-white hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-sky-500/60"
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-5 w-5" />
                                    ) : (
                                        <Eye className="h-5 w-5" />
                                    )}
                                </button>
                            </div>
                        </Field>

                        {/* Remember / forgot */}
                        <div className="flex items-center justify-between pt-1">
                            <label
                                htmlFor="remember"
                                className="group flex cursor-pointer items-center gap-2.5 text-sm text-slate-300 select-none"
                            >
                                <span className="relative inline-flex">
                                    <input
                                        id="remember"
                                        type="checkbox"
                                        checked={remember}
                                        data-testid="remember-me-checkbox"
                                        onChange={(e) => setRemember(e.target.checked)}
                                        className="peer h-4 w-4 cursor-pointer appearance-none rounded border border-white/20 bg-white/5 transition checked:border-sky-500 checked:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:ring-offset-0"
                                    />
                                    <svg
                                        className="pointer-events-none absolute left-0 top-0 h-4 w-4 scale-90 text-white opacity-0 transition peer-checked:opacity-100"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M3 8.5l3 3 7-7" />
                                    </svg>
                                </span>
                                <span className="transition group-hover:text-white">
                                    Remember me
                                </span>
                            </label>

                            <a
                                href="#"
                                data-testid="forgot-password-link"
                                className="text-sm font-medium text-sky-400 transition hover:text-sky-300 focus:outline-none focus:underline"
                            >
                                Forgot password?
                            </a>
                        </div>

                        {/* Submit */}
                        <button
                            type="submit"
                            data-testid="signin-button"
                            disabled={isLoading}
                            className="group relative mt-2 inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 px-5 py-3.5 text-sm font-semibold text-white shadow-[0_10px_30px_-10px_rgba(59,130,246,0.7)] ring-1 ring-white/10 transition-all duration-200 hover:shadow-[0_18px_40px_-12px_rgba(59,130,246,0.9)] hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 focus:ring-offset-[#0b1226] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {isLoading ? (
                                <>
                                    <Spinner />
                                    <span>Signing in…</span>
                                </>
                            ) : (
                                <>
                                    <span>Sign in</span>
                                    <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5" />
                                </>
                            )}
                        </button>

                        <p className="pt-3 text-center text-xs text-slate-500">
                            Protected by hospital-grade encryption. Unauthorized access is
                            strictly prohibited.
                        </p>
                    </form>
                </div>
            </main>
        </div>
    );
};

/* ----------------------------- sub components ----------------------------- */

const inputClasses = (hasError: boolean): string =>
    [
        "w-full rounded-xl bg-white/[0.04] pl-11 pr-4 py-3.5 text-sm text-white placeholder:text-slate-500",
        "border transition duration-200 outline-none",
        "focus:bg-white/[0.06] focus:ring-2 focus:ring-offset-0",
        hasError
            ? "border-rose-500/60 focus:border-rose-400 focus:ring-rose-500/30"
            : "border-white/10 focus:border-sky-500/60 focus:ring-sky-500/30 hover:border-white/20",
    ].join(" ");

interface FieldProps {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: React.ReactNode;
}

const Field: React.FC<FieldProps> = ({ id, label, hint, error, children }) => (
    <div className="space-y-2">
        <div className="flex items-baseline justify-between">
            <label
                htmlFor={id}
                className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-300"
            >
                {label}
            </label>
            {hint && !error && <span className="text-[11px] text-slate-500">{hint}</span>}
        </div>
        {children}
        {error && (
            <p data-testid={`${id}-error`} className="text-xs font-medium text-rose-400">
                {error}
            </p>
        )}
    </div>
);

const Spinner: React.FC = () => (
    <svg
        className="h-4 w-4 animate-spin text-white"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
    >
        <circle
            className="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            strokeWidth="4"
        />
        <path
            className="opacity-90"
            fill="currentColor"
            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
        />
    </svg>
);

export default LoginViewType1;