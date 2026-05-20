import React from "react";
import { ShieldCheck, Activity } from "lucide-react";

const Sidebar: React.FC = () => {
    return (
        <aside
            data-testid="v2-sidebar"
            className="relative flex h-full w-full overflow-hidden bg-gradient-to-br from-sky-50 via-cyan-50 to-indigo-50"
        >
            {/* Floating blobs */}
            <div aria-hidden="true" className="absolute inset-0">
                <div className="absolute -top-20 -left-16 h-80 w-80 rounded-full bg-cyan-300/40 blur-3xl animate-float-slow" />
                <div className="absolute top-1/3 -right-24 h-96 w-96 rounded-full bg-blue-300/40 blur-3xl animate-float-slower" />
                <div className="absolute -bottom-28 left-1/4 h-80 w-80 rounded-full bg-indigo-300/35 blur-3xl animate-float-slow" />
            </div>

            {/* Subtle grid */}
            <div
                aria-hidden="true"
                className="absolute inset-0 opacity-[0.5] mix-blend-multiply"
                style={{
                    backgroundImage:
                        "linear-gradient(to right, rgba(15,23,42,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(15,23,42,0.04) 1px, transparent 1px)",
                    backgroundSize: "48px 48px",
                }}
            />

            <div className="relative z-10 flex h-full w-full flex-col justify-between p-10 xl:p-14">
                {/* Top — logo */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                            <ShieldCheck className="h-6 w-6 text-cyan-600" />
                        </div>
                        <span className="text-sm font-semibold tracking-[0.2em] text-slate-600">
                            RSCH · IHMS
                        </span>
                    </div>
                    <span className="flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                        <Activity className="h-3 w-3" /> Live
                    </span>
                </div>

                {/* Middle — greeting + image */}
                <div className="flex flex-col gap-8">
                    <div>
                        <h2
                            data-testid="v2-greeting"
                            className="text-4xl font-bold leading-tight text-slate-900 xl:text-5xl"
                        >
                            Hello there{" "}
                            <span className="inline-block animate-wave origin-[70%_70%]">👋</span>
                        </h2>
                        <div className="mt-4 space-y-1">
                            <p className="text-2xl font-semibold text-slate-900">RS Citra Husada</p>
                            <p className="text-sm font-medium text-cyan-700">
                                Integrated Hospital Management System
                            </p>
                        </div>
                        <p className="mt-5 max-w-md text-base leading-relaxed text-slate-600">
                            Empowering healthcare teams with secure and reliable digital
                            systems — from patient records to operations, all in one place.
                        </p>
                    </div>

                    {/* Image card */}
                    <div className="relative">
                        <div className="relative overflow-hidden rounded-3xl ring-1 ring-slate-200 shadow-[0_25px_70px_-25px_rgba(8,47,73,0.25)]">
                            <img
                                data-testid="v2-team-image"
                                src="https://images.unsplash.com/photo-1551601651-2a8555f1a136?auto=format&fit=crop&w=1200&q=70"
                                alt="Healthcare professionals collaborating"
                                className="h-72 w-full object-cover"
                                loading="lazy"
                            />
                            <div className="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/20 to-transparent" />

                            <div className="absolute left-5 top-5 flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-700 backdrop-blur-md ring-1 ring-white/60 shadow-sm animate-float-card" style={{ animationDelay: "1.5s" }}>
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_10px_2px_rgba(16,185,129,0.6)]" />
                                12 teams online
                            </div>

                            <div className="absolute right-5 bottom-5 rounded-2xl bg-white px-4 py-3 text-slate-900 shadow-xl ring-1 ring-slate-200 animate-float-card">
                                <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                    Morning Shift
                                </p>
                                <p className="mt-0.5 text-sm font-semibold">Dr. Anwar · ICU Unit</p>
                                <div className="mt-2 flex -space-x-2">
                                    {[
                                        "https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=facearea&facepad=3&w=80&h=80&q=70",
                                        "https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=facearea&facepad=3&w=80&h=80&q=70",
                                        "https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=facearea&facepad=3&w=80&h=80&q=70",
                                    ].map((src, i) => (
                                        <img
                                            key={i}
                                            src={src}
                                            alt=""
                                            className="h-7 w-7 rounded-full border-2 border-white object-cover"
                                            loading="lazy"
                                        />
                                    ))}
                                    <span className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-cyan-500 text-[10px] font-semibold text-white">
                                        +6
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    );
};

export default Sidebar;
