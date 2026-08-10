<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            :root {
                --kip-blue: #00a6d6;
                --kip-blue-deep: #006f9f;
                --kip-ink: #17212b;
                --kip-panel: rgba(255, 255, 255, .9);
            }

            body {
                margin: 0;
                min-height: 100vh;
                overflow-x: hidden;
                background: #07151f;
                color: white;
                font-family: var(--font-sans, ui-sans-serif, system-ui, sans-serif);
            }

            .landing-shell {
                position: relative;
                min-height: 100svh;
                isolation: isolate;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .landing-bg,
            .landing-bg-next {
                position: absolute;
                inset: 0;
                z-index: -4;
                background-position: center;
                background-size: cover;
                transform: scale(1.025);
                transition: opacity 1200ms ease, transform 9000ms ease;
            }

            .landing-bg-next {
                opacity: 0;
            }

            .landing-bg.is-zooming,
            .landing-bg-next.is-zooming {
                transform: scale(1.08);
            }

            .landing-bg-next.is-visible {
                opacity: 1;
            }

            .landing-shell::before {
                content: "";
                position: absolute;
                inset: 0;
                z-index: -3;
                background:
                    linear-gradient(100deg, rgba(2, 15, 25, .88) 0%, rgba(5, 33, 48, .58) 44%, rgba(5, 18, 27, .48) 100%),
                    linear-gradient(0deg, rgba(5, 12, 18, .78) 0%, rgba(5, 12, 18, .18) 48%, rgba(5, 12, 18, .54) 100%);
            }

            .landing-shell::after {
                content: "";
                position: absolute;
                inset: 0;
                z-index: -2;
                pointer-events: none;
                background-image:
                    linear-gradient(rgba(255,255,255,.055) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
                background-size: 72px 72px;
                mask-image: linear-gradient(180deg, rgba(0,0,0,.55), transparent 72%);
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                padding: 28px clamp(20px, 5vw, 72px);
            }

            .brand {
                display: inline-flex;
                align-items: center;
                gap: 14px;
                color: white;
                text-decoration: none;
            }

            .brand-mark {
                width: 46px;
                height: 46px;
                display: grid;
                place-items: center;
                border-radius: 8px;
                background: rgba(255,255,255,.94);
                box-shadow: 0 18px 40px rgba(0,0,0,.18);
            }

            .brand-copy {
                display: flex;
                flex-direction: column;
                line-height: 1.05;
                text-transform: uppercase;
                letter-spacing: 0;
            }

            .brand-copy strong {
                font-size: 1.02rem;
                font-weight: 800;
            }

            .brand-copy span {
                margin-top: 4px;
                font-size: .72rem;
                font-weight: 700;
                color: rgba(255,255,255,.74);
            }

            .partner-brand {
                display: inline-flex;
                align-items: center;
                gap: 14px;
                color: white;
                text-decoration: none;
            }

            .danantara-mark {
                width: 46px;
                height: 46px;
                flex: 0 0 auto;
                padding: 8px;
                border-radius: 8px;
                background: rgba(255,255,255,.94);
                box-shadow: 0 18px 40px rgba(0,0,0,.18);
            }

            .danantara-copy {
                display: grid;
                gap: 0;
                line-height: 1.05;
                font-weight: 800;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            .danantara-copy strong {
                font-size: 1.02rem;
            }

            .danantara-copy span {
                margin-top: 4px;
                color: rgba(255,255,255,.74);
                font-size: .72rem;
                font-weight: 700;
                line-height: 1.05;
            }

            .hero {
                flex: 1;
                display: grid;
                align-items: center;
                justify-items: center;
                padding: 24px clamp(20px, 5vw, 72px) 72px;
                transition: padding 420ms ease, transform 420ms ease;
            }

            .hero-inner {
                width: min(100%, 980px);
                display: grid;
                justify-items: center;
                gap: 24px;
                text-align: center;
                transition: gap 420ms ease, transform 420ms ease;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 999px;
                border: 1px solid rgba(255,255,255,.22);
                background: rgba(255,255,255,.1);
                color: rgba(255,255,255,.82);
                font-size: .78rem;
                font-weight: 700;
                text-transform: uppercase;
                backdrop-filter: blur(16px);
            }

            h1 {
                max-width: 820px;
                margin: 0;
                font-size: clamp(4.2rem, 13vw, 9.5rem);
                line-height: .86;
                font-weight: 820;
                letter-spacing: 0;
                text-wrap: balance;
                text-shadow: 0 18px 42px rgba(0,0,0,.32);
                transition: font-size 420ms ease, transform 420ms ease;
            }

            .hero-subtitle {
                max-width: 660px;
                margin: 0;
                color: rgba(255,255,255,.84);
                font-size: clamp(1.08rem, 2vw, 1.55rem);
                line-height: 1.35;
                font-weight: 760;
                text-transform: uppercase;
                text-wrap: balance;
                transition: font-size 420ms ease, opacity 420ms ease, transform 420ms ease;
            }

            .landing-shell.is-login-open .hero {
                padding-top: 0;
                padding-bottom: 42px;
            }

            .landing-shell.is-login-open .hero-inner {
                gap: 12px;
                transform: translateY(-18px);
            }

            .landing-shell.is-login-open h1 {
                font-size: clamp(2.8rem, 6.4vw, 4.8rem);
            }

            .landing-shell.is-login-open .hero-subtitle {
                font-size: clamp(.92rem, 1.35vw, 1.08rem);
            }

            .login-panel {
                width: min(100%, 560px);
                margin-top: 12px;
                border: 1px solid rgba(255,255,255,.24);
                border-radius: 8px;
                background: rgba(255,255,255,.11);
                backdrop-filter: blur(22px);
                box-shadow: 0 28px 80px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.18);
                overflow: hidden;
                transition: width 420ms ease, background 420ms ease, border-color 420ms ease, box-shadow 420ms ease, border-radius 420ms ease;
            }

            .login-panel.is-open {
                border-color: rgba(255,255,255,.72);
                border-radius: 14px;
                background: rgba(255,255,255,.94);
                box-shadow: 0 34px 90px rgba(0,0,0,.24);
            }

            .login-toggle {
                width: 100%;
                min-height: 58px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                border: 0;
                cursor: pointer;
                color: white;
                background: linear-gradient(135deg, var(--kip-blue), var(--kip-blue-deep));
                font-weight: 800;
                letter-spacing: 0;
                box-shadow: inset 0 1px 0 rgba(255,255,255,.28);
                transition: transform 220ms ease, filter 220ms ease;
            }

            .login-toggle:hover {
                filter: brightness(1.06);
            }

            .login-toggle:active {
                transform: scale(.99);
            }

            .login-toggle svg {
                width: 19px;
                height: 19px;
            }

            .login-toggle .icon-collapse {
                display: none;
            }

            .login-toggle-text {
                display: inline-block;
                max-width: 120px;
                opacity: 1;
                overflow: hidden;
                white-space: nowrap;
                transition: max-width 260ms ease, opacity 180ms ease, margin 260ms ease;
            }

            .login-panel.is-open .login-toggle .icon-login {
                display: none;
            }

            .login-panel.is-open .login-toggle .icon-collapse {
                display: block;
            }

            .login-panel.is-open .login-toggle .login-toggle-text {
                max-width: 0;
                margin-left: -12px;
                opacity: 0;
            }

            .login-form-wrap {
                display: grid;
                grid-template-rows: 0fr;
                transition: grid-template-rows 420ms ease;
            }

            .login-form-inner {
                min-height: 0;
                overflow: hidden;
            }

            .login-panel.is-open .login-form-wrap {
                grid-template-rows: 1fr;
            }

            .login-form {
                display: grid;
                gap: 14px;
                padding: 30px 42px 28px;
                text-align: left;
                background: rgba(255,255,255,.96);
                color: var(--kip-ink);
            }

            .login-form-brand {
                display: flex;
                justify-content: center;
                margin-bottom: 4px;
            }

            .login-form-brand .brand {
                color: #263645;
            }

            .login-form-brand .brand-copy {
                display: none;
            }

            .login-form-brand .brand-mark {
                width: 42px;
                height: 42px;
                background: transparent;
                box-shadow: none;
            }

            .login-form-brand .brand-mark svg {
                width: 52px;
                height: 52px;
            }

            .login-form-brand .brand-copy span {
                color: #5c6874;
            }

            .login-form-title {
                margin: 0 0 6px;
                color: #07172a;
                font-size: clamp(1.35rem, 2.4vw, 1.65rem);
                font-weight: 760;
                line-height: 1.15;
                text-align: center;
            }

            .field {
                position: relative;
                display: grid;
                gap: 7px;
            }

            .field label {
                font-size: 1rem;
                font-weight: 500;
                color: #4b5c72;
            }

            .field input {
                width: 100%;
                min-height: 52px;
                border: 1px solid #bfccd9;
                border-radius: 10px;
                padding: 0 16px;
                background: white;
                color: #17212b;
                font-size: 1rem;
                outline: none;
                transition: border-color 180ms ease, box-shadow 180ms ease;
            }

            .field input:focus {
                border-color: #4a7cff;
                box-shadow: 0 0 0 4px rgba(74, 124, 255, .14);
            }

            .password-field input {
                padding-right: 60px;
            }

            .password-input-wrap {
                position: relative;
            }

            .password-input-wrap input {
                display: block;
            }

            .password-toggle {
                position: absolute;
                right: 16px;
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 30px;
                display: grid;
                place-items: center;
                border: 0;
                background: transparent;
                color: #5a6878;
                cursor: pointer;
            }

            .password-toggle svg {
                width: 26px;
                height: 26px;
            }

            .password-toggle .icon-eye {
                display: none;
            }

            .password-toggle.is-visible {
                color: #0076a8;
            }

            .password-toggle.is-visible .icon-eye-off {
                display: none;
            }

            .password-toggle.is-visible .icon-eye {
                display: block;
            }

            .field-error,
            .session-status {
                margin: 0;
                font-size: .8rem;
                line-height: 1.5;
            }

            .field-error {
                color: #b42318;
            }

            .session-status {
                padding: 10px 12px;
                border-radius: 6px;
                background: #ecfdf3;
                color: #027a48;
            }

            .login-row {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 16px;
                color: #526273;
                font-size: .83rem;
            }

            .remember {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                user-select: none;
            }

            .remember input {
                width: 16px;
                height: 16px;
                accent-color: var(--kip-blue-deep);
            }

            .submit-button {
                min-height: 52px;
                border: 0;
                border-radius: 10px;
                color: white;
                background: #4a7cff;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 800;
                transition: transform 180ms ease, background 180ms ease;
            }

            .submit-button:hover {
                background: #386df0;
            }

            .submit-button:active {
                transform: translateY(1px);
            }

            .auth-note {
                margin: 0;
                color: #5c6c7b;
                font-size: .78rem;
                line-height: 1.55;
                text-align: center;
            }

            .esisman-lockup {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                height: 34px;
                margin-top: 2px;
                overflow: visible;
            }

            .esisman-lockup img {
                display: block;
                width: auto;
                height: 98px;
                object-fit: contain;
                transform: translateY(4px);
            }

            .slide-dots {
                position: absolute;
                right: clamp(20px, 4vw, 56px);
                bottom: clamp(20px, 4vw, 42px);
                display: flex;
                gap: 8px;
            }

            .slide-dot {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: rgba(255,255,255,.38);
                transition: width 300ms ease, background 300ms ease;
            }

            .slide-dot.is-active {
                width: 28px;
                background: white;
            }

            @media (max-width: 720px) {
                .topbar {
                    padding: 20px;
                }

                .brand-copy strong {
                    font-size: .92rem;
                }

                .partner-brand {
                    display: none;
                }

                .hero {
                    align-items: center;
                    padding: 12px 20px 54px;
                }

                .hero-inner {
                    gap: 16px;
                }

                h1 {
                    font-size: clamp(3.6rem, 18vw, 5.8rem);
                }

                .landing-shell.is-login-open .hero {
                    padding-top: 0;
                    padding-bottom: 24px;
                }

                .landing-shell.is-login-open .hero-inner {
                    gap: 10px;
                    transform: translateY(-8px);
                }

                .landing-shell.is-login-open h1 {
                    font-size: clamp(2.45rem, 12vw, 3.4rem);
                }

                .landing-shell.is-login-open .hero-subtitle {
                    font-size: .86rem;
                }

                .login-row {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 10px;
                }

                .login-form {
                    padding: 26px 20px 24px;
                }

                .field input {
                    min-height: 50px;
                    font-size: 1rem;
                }

                .field label {
                    font-size: 1rem;
                }

                .submit-button {
                    min-height: 50px;
                }

                .esisman-lockup img {
                    height: 44px;
                    transform: translateY(2px);
                }

                .slide-dots {
                    left: 20px;
                    right: auto;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    scroll-behavior: auto !important;
                    transition-duration: 1ms !important;
                    animation-duration: 1ms !important;
                    animation-iteration-count: 1 !important;
                }
            }
        </style>
    </head>
    <body>
        @php
            $shouldOpenLogin = $errors->any() || session('status');
        @endphp

        <main class="landing-shell {{ $shouldOpenLogin ? 'is-login-open' : '' }}" data-landing data-login-open="{{ $shouldOpenLogin ? 'true' : 'false' }}">
            <div class="landing-bg" data-bg-current></div>
            <div class="landing-bg-next" data-bg-next></div>

            <header class="topbar">
                <a class="brand" href="{{ route('home') }}" aria-label="Krakatau International Port">
                    <span class="brand-mark" aria-hidden="true">
                        <svg width="30" height="30" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4h15.8L4 21.2V4Z" fill="#00A6D6"/>
                            <path d="M23.5 4H40L21 22.7 40 40H23.1L8.6 25.8 23.5 4Z" fill="#0086BF"/>
                            <path d="M4 25.4 18.7 40H4V25.4Z" fill="#00A6D6"/>
                        </svg>
                    </span>
                    <span class="brand-copy">
                        <strong>Krakatau</strong>
                        <span>International Port</span>
                    </span>
                </a>

                <a class="partner-brand" href="{{ route('home') }}" aria-label="Danantara Indonesia">
                    <svg class="danantara-mark" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M5 5h17.5C35.5 5 43 13.1 43 24c0 10.8-7.4 19-20.5 19H5V5Z" fill="#050505"/>
                        <path d="M11.6 22.4c7.7-5.2 19.8-7.1 28.6 1.7-1.1-5.6-4.8-10-10.6-12.1-7.7-2.8-16.8-.3-22.4 5.8l4.4 4.6Z" fill="#E30613"/>
                        <path d="M14.1 25c5.8-2.5 13.8-2.7 21.1 3.2-3.8 1.3-7.9 3.7-11.5 7.3L14.1 25Z" fill="white"/>
                    </svg>
                    <span class="danantara-copy">
                        <strong>Danantara</strong>
                        <strong>Indonesia</strong>
                        <span>Sovereign Fund</span>
                    </span>
                </a>
            </header>

            <section class="hero" aria-labelledby="landing-title">
                <div class="hero-inner">
                    <div class="eyebrow">Sistem Manajemen Dokumen</div>

                    <h1 id="landing-title">E-SISMAN</h1>

                    <p class="hero-subtitle">PT Krakatau Bandar Samudera</p>

                    <section class="login-panel {{ $shouldOpenLogin ? 'is-open' : '' }}" data-login-panel aria-label="Form login E-SISMAN">
                        @guest
                            <button class="login-toggle" type="button" data-login-toggle aria-expanded="{{ $shouldOpenLogin ? 'true' : 'false' }}">
                                <svg class="icon-login" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M15 3h3.2A1.8 1.8 0 0 1 20 4.8v14.4a1.8 1.8 0 0 1-1.8 1.8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="m10 17 5-5-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M15 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <svg class="icon-collapse" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="login-toggle-text">{{ $shouldOpenLogin ? 'Masuk ke E-SISMAN' : 'Login' }}</span>
                            </button>

                            <div class="login-form-wrap">
                                <div class="login-form-inner">
                                    <form method="POST" action="{{ route('login.store') }}" class="login-form">
                                        @csrf

                                        <div class="login-form-brand">
                                            <span class="brand" aria-label="Krakatau International Port">
                                                <span class="brand-mark" aria-hidden="true">
                                                    <svg width="30" height="30" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4 4h15.8L4 21.2V4Z" fill="#00A6D6"/>
                                                        <path d="M23.5 4H40L21 22.7 40 40H23.1L8.6 25.8 23.5 4Z" fill="#0086BF"/>
                                                        <path d="M4 25.4 18.7 40H4V25.4Z" fill="#00A6D6"/>
                                                    </svg>
                                                </span>
                                                <span class="brand-copy">
                                                    <strong>Krakatau</strong>
                                                    <span>International Port</span>
                                                </span>
                                            </span>
                                        </div>

                                        <h4 class="login-form-title">Masuk Aplikasi e-SISMAN</h4>

                                        @if (session('status'))
                                            <p class="session-status">{{ session('status') }}</p>
                                        @endif

                                        <div class="field">
                                            <label for="nik">NIK</label>
                                            <input
                                                id="nik"
                                                name="nik"
                                                value="{{ old('nik') }}"
                                                type="text"
                                                required
                                                autofocus
                                                autocomplete="username"
                                                inputmode="numeric"
                                                placeholder="Masukkan NIK"
                                            >
                                            @error('nik')
                                                <p class="field-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="field password-field">
                                            <label for="password">Password</label>
                                            <div class="password-input-wrap">
                                                <input
                                                    id="password"
                                                    name="password"
                                                    type="password"
                                                    required
                                                    autocomplete="current-password"
                                                    placeholder="Masukkan password"
                                                >
                                                <button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password">
                                                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M10.7 5.1A9.8 9.8 0 0 1 12 5c5.5 0 9 5 9 7a8.8 8.8 0 0 1-2 3.6M6.5 6.7C4.3 8.1 3 10.5 3 12c0 2 3.5 7 9 7 1.6 0 3-.4 4.2-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M9.9 9.9A3 3 0 0 0 14.1 14.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    </svg>
                                                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            @error('password')
                                                <p class="field-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="login-row">
                                            <label class="remember">
                                                <input name="remember" type="checkbox" @checked(old('remember'))>
                                                <span>Ingat saya</span>
                                            </label>

                                        </div>

                                        <button class="submit-button" type="submit">Masuk</button>

                                        <p class="auth-note">
                                            Akses pengguna dikelola melalui akun SSO perusahaan.
                                        </p>

                                        <div class="esisman-lockup" aria-label="E-SISMAN">
                                            <img src="{{ asset('image/esisman_logo.png') }}" alt="E-SISMAN">
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a class="login-toggle" href="{{ route('dashboard') }}">
                                <span>Buka Dashboard</span>
                            </a>
                        @endguest
                    </section>

                </div>
            </section>

            <div class="slide-dots" data-slide-dots aria-hidden="true"></div>
        </main>

        @fluxScripts

        <script>
            (() => {
                const root = document.querySelector('[data-landing]');
                if (!root) return;

                const slides = [
                    'https://cm.ptksi.id/storage/project/Fsr38Fwrgw3J53MLwQazlJcFbyiQlicrJloZbRBM.jpg',
                    'https://asset.kompas.com/crops/83UYbTElJ8DVGJ4LusvMBQZGdHQ%3D/0x0%3A1600x1067/750x500/data/photo/2023/12/30/658f94245ada7.jpeg',
                    'https://cm.ptksi.id/storage/kik/services/BQbdEOTeZfzbC0rmvmk2ssvNLsgUKyDiJYt7DhIO.jpg',
                ];

                const current = root.querySelector('[data-bg-current]');
                const next = root.querySelector('[data-bg-next]');
                const dotsWrap = root.querySelector('[data-slide-dots]');
                const loginPanel = root.querySelector('[data-login-panel]');
                const loginToggle = root.querySelector('[data-login-toggle]');
                let active = 0;

                current.style.backgroundImage = `url("${slides[0]}")`;
                current.classList.add('is-zooming');

                slides.forEach((_, index) => {
                    const dot = document.createElement('span');
                    dot.className = `slide-dot${index === 0 ? ' is-active' : ''}`;
                    dotsWrap.appendChild(dot);
                });

                const dots = [...dotsWrap.children];
                const setActiveDot = (index) => {
                    dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
                };

                const showSlide = (index) => {
                    next.style.backgroundImage = `url("${slides[index]}")`;
                    next.classList.add('is-visible', 'is-zooming');
                    setActiveDot(index);

                    window.setTimeout(() => {
                        current.style.backgroundImage = next.style.backgroundImage;
                        next.classList.remove('is-visible');
                        active = index;
                    }, 1250);
                };

                window.setInterval(() => {
                    showSlide((active + 1) % slides.length);
                }, 6800);

                if (loginToggle && loginPanel) {
                    const openFromServer = root.dataset.loginOpen === 'true';

                    if (openFromServer) {
                        root.classList.add('is-login-open');
                        loginPanel.classList.add('is-open');
                        loginToggle.setAttribute('aria-expanded', 'true');
                    }

                    loginToggle.addEventListener('click', () => {
                        const isOpen = loginPanel.classList.toggle('is-open');
                        root.classList.toggle('is-login-open', isOpen);
                        loginToggle.setAttribute('aria-expanded', String(isOpen));

                        if (isOpen) {
                            window.setTimeout(() => {
                                document.querySelector('#nik')?.focus();
                            }, 260);
                        }
                    });
                }

                const passwordToggle = document.querySelector('[data-password-toggle]');
                const passwordInput = document.querySelector('#password');

                if (passwordToggle && passwordInput) {
                    passwordToggle.addEventListener('click', () => {
                        const showing = passwordInput.type === 'text';
                        passwordInput.type = showing ? 'password' : 'text';
                        passwordToggle.classList.toggle('is-visible', !showing);
                        passwordToggle.setAttribute('aria-label', showing ? 'Tampilkan password' : 'Sembunyikan password');
                    });
                }
            })();
        </script>
    </body>
</html>
