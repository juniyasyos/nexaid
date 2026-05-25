<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8" />
    <title>IAM Portal – Welcome</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />

    <!-- daisyUI + Tailwind -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- AOS (Animate On Scroll) -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css" />

    <!-- Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        iam: {
                            bg: '#020617',
                            surface: '#0b1120',
                            accent: '#2563eb',
                        },
                    },
                    boxShadow: {
                        'iam-card': '0 18px 40px rgba(15,23,42,0.42)',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: "Plus Jakarta Sans", system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.18), transparent 55%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.18), transparent 55%),
                #020617;
        }

        .noise-layer::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0.08;
            background-image: url("https://grainy-gradients.vercel.app/noise.svg");
            mix-blend-mode: soft-light;
        }

        .backdrop-card {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .app-card {
            cursor: pointer;
        }

        .app-card.disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .app-card.selected {
            @apply ring-2 ring-sky-300/90 shadow-sky-500/40;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-6 text-slate-900">

    <div class="w-full max-w-7xl">

        <div
            class="relative overflow-hidden rounded-[32px] bg-white/10 border border-white/10 backdrop-card noise-layer shadow-iam-card"
            data-aos="fade-up">
            <!-- Gradient lights -->
            <div class="pointer-events-none absolute -left-24 -top-24 h-64 w-64 rounded-full bg-gradient-to-br from-sky-400/30 via-sky-300/0 to-transparent blur-3xl"></div>
            <div class="pointer-events-none absolute -right-24 -bottom-24 h-64 w-64 rounded-full bg-gradient-to-tr from-cyan-400/25 via-cyan-300/0 to-transparent blur-3xl"></div>

            <!-- Top bar -->
            <header class="relative flex items-center justify-between gap-4 px-8 pt-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-cyan-400 shadow-lg shadow-sky-500/50">
                        <span class="text-xl font-extrabold text-white">IAM</span>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.32em] uppercase text-sky-100/90">
                            Unified Access
                        </p>
                        <p class="text-xs text-slate-100/90">
                            Portal akses terpusat Rumah Sakit Citra Husada Jember
                        </p>
                    </div>
                </div>

                <!-- User pill -->
                <div class="flex items-center gap-3">
                    @guest
                    <!-- Belum login -->
                    <div class="flex items-center gap-4 text-xs text-slate-100/80">
                        <span class="hidden sm:inline">
                            Belum punya akun? Hubungi admin IAM.
                        </span>
                        <button
                            onclick="document.getElementById('password-field').focus()"
                            class="btn btn-sm rounded-full border border-sky-300/40 bg-sky-500/10 text-sky-50 hover:bg-sky-500 hover:text-white">
                            Masuk
                        </button>
                    </div>
                    @endguest

                    @auth
                    <!-- Sudah login -->
                    <div class="flex items-center gap-3 text-xs text-slate-100">
                        <div class="avatar placeholder">
                            <div class="w-9 rounded-full bg-sky-500/30 text-sky-50">
                                <span>{{ $userInitials }}</span>
                            </div>
                        </div>
                        <div class="hidden sm:block">
                            <p class="font-medium">{{ $user['name'] }}</p>
                            <p class="text-[11px] text-slate-200/90">{{ $user['email'] }}</p>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-xs btn-ghost text-slate-200 hover:text-rose-200">
                                Keluar
                            </button>
                        </form>
                    </div>
                    @endauth
                </div>
            </header>

            <!-- Main -->
            <main class="relative grid gap-10 px-8 pb-10 pt-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] items-stretch">

                <!-- Left: hero + apps -->
                <section class="flex flex-col gap-6 py-4">

                    <!-- Greeting -->
                    <div class="space-y-2 text-slate-50" data-aos="fade-right">
                        @guest
                        <!-- sebelum login -->
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-sky-200/90">
                                Selamat datang
                            </p>
                            <h1 class="mt-1 text-3xl sm:text-[2.35rem] font-semibold leading-tight tracking-tight">
                                Satu pintu untuk semua
                                <span class="bg-gradient-to-r from-sky-300 to-cyan-200 bg-clip-text text-transparent">
                                    aplikasi layanan.
                                </span>
                            </h1>
                            <p class="mt-3 max-w-xl text-xs sm:text-sm text-slate-100/80">
                                Masuk sekali, lalu akses seluruh aplikasi operasional rumah sakit dengan aman:
                                mutu, insiden, dokumen, hingga analitik manajemen.
                            </p>
                        </div>
                        @endguest

                        @auth
                        <!-- setelah login -->
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.35em] text-emerald-200/95">
                                Halo, <span>{{ strtoupper($user['name']) }}</span>
                            </p>
                            <h1 class="mt-1 text-3xl sm:text-[2.35rem] font-semibold leading-tight tracking-tight">
                                Pilih aplikasi
                                <span class="bg-gradient-to-r from-emerald-300 to-sky-200 bg-clip-text text-transparent">
                                    untuk mulai bekerja.
                                </span>
                            </h1>
                            <p class="mt-3 max-w-xl text-xs sm:text-sm text-slate-100/80">
                                Hak akses sudah disesuaikan dengan role dan unit kerja Anda. Klik kartu aplikasi
                                untuk membuka modul yang dibutuhkan.
                            </p>
                        </div>
                        @endauth
                    </div>

                    <!-- App directory -->
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-sm border-outline bg-sky-500/20 text-sky-100">
                                    App Directory
                                </span>
                                <span class="text-slate-100/80">
                                    <span>{{ count($applications) }}</span> aplikasi terhubung
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-1 xl:grid-cols-2">
                            @foreach($applications as $index => $app)
                            <article
                                class="app-card group relative rounded-2xl border border-white/10 bg-white/7 backdrop-card px-4 py-4 shadow-sm hover:border-sky-300/70 hover:bg-white/12 transition-all duration-300 {{ !$isAuthenticated && $app['requiresAuth'] ? 'disabled' : '' }}"
                                data-aos="fade-up"
                                data-aos-delay="{{ 80 + ($index * 70) }}"
                                @auth
                                onclick="window.location.href='{{ $app['url'] }}'"
                                @endauth
                                @guest
                                onclick="document.getElementById('password-field').focus()"
                                @endguest>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 class="text-[13px] font-semibold text-slate-50">
                                            <span>{{ $app['name'] }}</span>
                                        </h2>
                                        <p class="mt-1 line-clamp-2 text-[11px] text-slate-100/70">{{ $app['description'] }}</p>
                                    </div>

                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-[2px] text-[10px] font-medium
                                            @if($app['status'] === 'ready') bg-emerald-400/20 text-emerald-100
                                            @elseif($app['status'] === 'beta') bg-amber-300/25 text-amber-50
                                            @else bg-slate-500/25 text-slate-100
                                            @endif">
                                            @if($app['status'] === 'ready') Ready
                                            @elseif($app['status'] === 'beta') Beta
                                            @elseif($app['status'] === 'planned') Segera
                                            @else Status tidak diketahui
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach($app['tags'] as $tag)
                                    <span class="rounded-full bg-slate-900/35 px-2 py-[1px] text-[10px] text-slate-100/80">
                                        #{{ $tag }}
                                    </span>
                                    @endforeach
                                </div>

                                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-200/80">
                                    <span>
                                        Akses:
                                        <span class="font-medium text-slate-50">{{ $app['scope'] }}</span>
                                    </span>

                                    @auth
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-500/90 px-3 py-[3px] text-[10px] font-medium text-white shadow-sm group-hover:bg-sky-400">
                                        Buka aplikasi
                                        <span class="material-symbols-outlined text-[14px]">
                                            open_in_new
                                        </span>
                                    </span>
                                    @endauth
                                </div>
                            </article>
                            @endforeach
                        </div>

                        @guest
                        <p class="mt-1 text-[10px] text-slate-100/65">
                            Login terlebih dahulu untuk mengaktifkan akses dan tombol
                            <span class="font-semibold text-slate-50">"Buka aplikasi".</span>
                        </p>
                        @endguest
                    </section>
                </section>

                <!-- Right: login / profile -->
                <aside class="flex items-center justify-center">
                    <div
                        class="w-full max-w-sm rounded-3xl border border-white/20 bg-slate-900/65 backdrop-card px-6 py-6 shadow-xl shadow-slate-900/60"
                        data-aos="fade-left">
                        @guest
                        <!-- sebelum login -->
                        <div>
                            <h2 class="text-base font-semibold text-slate-50">
                                Masuk ke IAM Portal
                            </h2>
                            <p class="mt-1 text-[11px] text-slate-200/80">
                                Gunakan akun IAM yang sama untuk seluruh aplikasi di rumah sakit Anda.
                            </p>

                            @if($errors->any())
                            <div class="alert alert-error mt-3 text-xs">
                                <span>{{ $errors->first() }}</span>
                            </div>
                            @endif

                            <form action="{{ route('login') }}" method="POST" class="mt-5 space-y-3">
                                @csrf
                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-[11px] text-slate-200/90">NIP</span>
                                    </div>
                                    <input
                                        name="nip"
                                        type="text"
                                        required
                                        value="{{ old('nip', $devAutofill['nip'] ?? '') }}"
                                        placeholder="Nomor Induk Karyawan"
                                        class="input input-sm input-bordered w-full rounded-xl bg-slate-950/40 border-slate-600/60 text-sm text-slate-50 placeholder:text-slate-500" />
                                </label>

                                <label class="form-control w-full">
                                    <div class="label py-1">
                                        <span class="label-text text-[11px] text-slate-200/90">Password</span>
                                    </div>
                                    <div class="relative">
                                        <input
                                            type="password"
                                            name="password"
                                            required
                                            value="{{ $devAutofill['password'] ?? '' }}"
                                            placeholder="••••••••••"
                                            class="input input-sm input-bordered w-full rounded-xl bg-slate-950/40 border-slate-600/60 pr-16 text-sm text-slate-50 placeholder:text-slate-500"
                                            id="password-field" />
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-ghost absolute right-1.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-300"
                                            onclick="togglePassword()">
                                            <span id="toggle-text">Show</span>
                                        </button>
                                    </div>
                                </label>

                                <div class="flex items-center justify-between pt-1">
                                    <label class="label cursor-pointer gap-2">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            class="checkbox checkbox-xs checkbox-primary" />
                                        <span class="label-text text-[11px] text-slate-300/90">
                                            Tetap masuk
                                        </span>
                                    </label>
                                    <a href="{{ route('password.request') }}" class="text-[11px] text-sky-300 hover:text-sky-200">
                                        Lupa password?
                                    </a>
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm mt-3 w-full rounded-xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[13px] font-semibold shadow-lg shadow-sky-500/40 hover:from-sky-400 hover:to-indigo-400">
                                    Masuk
                                    <span class="material-symbols-outlined text-[17px] ml-1">
                                        login
                                    </span>
                                </button>

                                <p class="mt-3 text-[10px] leading-relaxed text-slate-300/80">
                                    Dengan masuk, Anda menyetujui kebijakan keamanan dan kerahasiaan data
                                    yang berlaku di rumah sakit ini.
                                </p>
                            </form>
                        </div>
                        @endguest

                        @auth
                        <!-- setelah login -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="avatar placeholder">
                                    <div class="w-11 rounded-full bg-gradient-to-br from-sky-500 to-emerald-400 text-sky-50 shadow-lg">
                                        <span>{{ $userInitials }}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-50">{{ $user['name'] }}</p>
                                    <p class="text-xs text-slate-200/90">{{ $user['email'] }}</p>
                                    <p class="mt-1 text-[11px] text-slate-300">
                                        Role:
                                        <span class="font-medium text-emerald-200">{{ $user['role'] }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="divider my-3 border-slate-700/80"></div>

                            <ul class="space-y-2 text-[11px] text-slate-200/90">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] text-sky-300 mt-[1px]">
                                        verified_user
                                    </span>
                                    <span>Sesi aman menggunakan token SSO terenkripsi.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] text-sky-300 mt-[1px]">
                                        dashboard
                                    </span>
                                    <span>Hak akses mengikuti role dan unit kerja Anda.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] text-sky-300 mt-[1px]">
                                        history
                                    </span>
                                    <span>Aktivitas diaudit untuk kepatuhan mutu & keamanan.</span>
                                </li>
                            </ul>

                            <div class="space-y-2">
                                @if(count($applications) > 0)
                                <a
                                    href="{{ $applications[0]['url'] }}"
                                    class="btn btn-outline btn-sm w-full rounded-xl border-slate-600/80 text-slate-100 hover:border-sky-400 hover:text-sky-100">
                                    Buka aplikasi utama
                                </a>
                                @endif

                                <a
                                    href="/panel"
                                    class="btn btn-sm w-full rounded-xl bg-gradient-to-r from-indigo-500/90 to-purple-500/90 text-slate-50 hover:from-indigo-500 hover:to-purple-500 shadow-sm">
                                    <span class="material-symbols-outlined text-[16px]">
                                        admin_panel_settings
                                    </span>
                                    Admin Panel
                                </a>
                            </div>
                        </div>
                        @endauth
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <!-- AOS -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

    <script>
        // Initialize AOS
        window.addEventListener('DOMContentLoaded', () => {
            if (window.AOS) {
                AOS.init({
                    duration: 600,
                    once: true,
                    easing: 'ease-out-cubic',
                });
            }
        });

        // Toggle password visibility
        function togglePassword() {
            const field = document.getElementById('password-field');
            const text = document.getElementById('toggle-text');
            if (field.type === 'password') {
                field.type = 'text';
                text.textContent = 'Hide';
            } else {
                field.type = 'password';
                text.textContent = 'Show';
            }
        }
    </script>
</body>

</html>