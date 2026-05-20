import React, { useState, FormEvent } from "react";
import LoginForm from "./LoginForm";
import { Sidebar } from "./Sidebar";

interface LoginViewType2Props {
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
    handleSubmit: (e: FormEvent<HTMLFormElement>) => void;
    isLoading: boolean;
    error?: string | null;
}

const LoginV2: React.FC<LoginViewType2Props> = ({
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
    isLoading,
    error,
}) => {
    const [shake, setShake] = useState<boolean>(false);

    // Trigger shake animation when there's an error
    React.useEffect(() => {
        if (error) {
            setShake(true);
            const timer = setTimeout(() => setShake(false), 350);
            return () => clearTimeout(timer);
        }
    }, [error]);
    return (
        <div
            data-testid="login-v2-page"
            className="relative min-h-screen w-full flex bg-white text-slate-900 font-sans antialiased selection:bg-cyan-200/60 selection:text-slate-900 overflow-hidden"
        >
            {/* LEFT — Sidebar */}
            <div className="animate-fade-in-right hidden md:flex md:w-2/5 lg:w-1/2">
                <Sidebar />
            </div>

            {/* RIGHT — Login form */}
            <section
                data-testid="v2-form-section"
                className={["relative flex w-full md:w-3/5 lg:w-1/2 items-center justify-center px-6 py-12 sm:px-10 animate-fade-up bg-white", shake ? "animate-shake" : ""].join(" ")}
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 overflow-hidden"
                >
                    <div className="absolute -top-32 -left-24 h-80 w-80 rounded-full bg-cyan-100/60 blur-3xl animate-float-slow" />
                    <div className="absolute -bottom-32 -right-24 h-80 w-80 rounded-full bg-sky-100/60 blur-3xl animate-float-slower" />
                </div>

                <div className="relative z-10 w-full flex justify-center">
                    <LoginForm
                        nip={nip}
                        setNip={setNip}
                        password={password}
                        setPassword={setPassword}
                        showPassword={showPassword}
                        setShowPassword={setShowPassword}
                        focusedInput={focusedInput}
                        setFocusedInput={setFocusedInput}
                        handleSubmit={handleSubmit}
                        isLoading={isLoading}
                        error={error}
                        showError={showError}
                        onCloseError={onCloseError}
                    />
                </div>

                <div className="absolute bottom-6 left-0 right-0 flex justify-center px-6 text-xs text-slate-400">
                    <span>© {new Date().getFullYear()} RS Citra Husada · v2</span>
                </div>
            </section>
        </div>
    );
};

export default LoginV2;