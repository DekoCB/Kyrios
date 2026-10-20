<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; margin: 0; }

            .login-page {
                min-height: 100vh;
                display: grid;
                grid-template-columns: 1fr 1fr;
                overflow: hidden;
            }

            /* ── Left Panel: Branding ──────────────────────────────── */
            .login-brand {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem;
                background: linear-gradient(135deg, #034C8C, #4F758C, #FF3950, #034C8C);
                background-size: 400% 400%;
                animation: gradientFlow 10s ease infinite;
                color: #fff;
                overflow: hidden;
            }
            @keyframes gradientFlow {
                0%   { background-position: 0% 50%; }
                25%  { background-position: 100% 0%; }
                50%  { background-position: 100% 100%; }
                75%  { background-position: 0% 100%; }
                100% { background-position: 0% 50%; }
            }
            /* Animated mesh overlay with glowing orbs */
            .login-brand::before {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(circle 320px at 15% 85%, rgba(255, 57, 80, 0.25) 0%, transparent 70%),
                    radial-gradient(circle 280px at 85% 15%, rgba(3, 76, 140, 0.35) 0%, transparent 70%),
                    radial-gradient(circle 220px at 50% 50%, rgba(79, 117, 140, 0.2) 0%, transparent 65%),
                    radial-gradient(circle 180px at 75% 70%, rgba(255, 57, 80, 0.15) 0%, transparent 60%);
                animation: meshPulse 8s ease-in-out infinite alternate;
                z-index: 1;
            }
            @keyframes meshPulse {
                0%   { opacity: .5; transform: scale(1) rotate(0deg); }
                50%  { opacity: .8; transform: scale(1.05) rotate(1deg); }
                100% { opacity: 1;  transform: scale(1.1) rotate(-1deg); }
            }
            /* Spinning ring decoration */
            .login-brand::after {
                content: '';
                position: absolute;
                width: 600px; height: 600px;
                border-radius: 50%;
                border: 1px solid rgba(255,255,255,0.06);
                top: -200px; right: -200px;
                animation: spinSlow 50s linear infinite;
                z-index: 1;
            }
            @keyframes spinSlow { to { transform: rotate(360deg); } }

            .brand-content {
                position: relative;
                z-index: 2;
                text-align: center;
                max-width: 420px;
            }
            .brand-logo-wrap {
                width: 110px; height: 110px;
                margin: 0 auto 2rem;
                border-radius: 24px;
                background: rgba(255,255,255,0.1);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255,255,255,0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8px 32px rgba(0,0,0,0.25);
                transition: transform .3s ease;
            }
            .brand-logo-wrap:hover { transform: translateY(-4px) scale(1.03); }
            .brand-logo-wrap img { width: 80px; height: 80px; object-fit: contain; }
            .brand-logo-wrap i { font-size: 3rem; }
            .brand-title {
                font-size: 1.75rem;
                font-weight: 800;
                letter-spacing: -.02em;
                line-height: 1.2;
                margin-bottom: .5rem;
                text-shadow: 0 2px 12px rgba(0,0,0,.3);
            }
            .brand-subtitle {
                font-size: .9rem;
                font-weight: 400;
                color: rgba(255,255,255,.6);
                margin-bottom: 3rem;
            }
            /* Feature pills */
            .feature-list {
                display: flex;
                flex-direction: column;
                gap: .75rem;
                text-align: left;
            }
            .feature-item {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding: .65rem 1rem;
                border-radius: 12px;
                background: rgba(255,255,255,0.06);
                backdrop-filter: blur(6px);
                border: 1px solid rgba(255,255,255,0.08);
                font-size: .82rem;
                color: rgba(255,255,255,.85);
                transition: background .25s, transform .25s;
            }
            .feature-item:hover { background: rgba(255,255,255,0.1); transform: translateX(4px); }
            .feature-icon {
                width: 32px; height: 32px;
                border-radius: 8px;
                display: flex; align-items: center; justify-content: center;
                font-size: .8rem;
                flex-shrink: 0;
            }

            /* Floating geometric shapes */
            .geo-shape {
                position: absolute;
                border-radius: 50%;
                z-index: 1;
            }
            .geo-1 {
                width: 350px; height: 350px;
                bottom: -100px; left: -100px;
                background: radial-gradient(circle, rgba(255,57,80,0.12) 0%, transparent 70%);
                border: 1px solid rgba(255,255,255,0.06);
                animation: float1 8s ease-in-out infinite;
            }
            .geo-2 {
                width: 220px; height: 220px;
                top: 8%; right: 3%;
                background: radial-gradient(circle, rgba(3,76,140,0.15) 0%, transparent 70%);
                border: 1px solid rgba(255,255,255,0.05);
                animation: float2 11s ease-in-out infinite;
            }
            .geo-3 {
                width: 140px; height: 140px;
                bottom: 18%; right: 12%;
                background: radial-gradient(circle, rgba(255,57,80,0.1) 0%, transparent 70%);
                animation: float3 7s ease-in-out infinite;
            }
            .geo-4 {
                width: 80px; height: 80px;
                top: 30%; left: 10%;
                background: radial-gradient(circle, rgba(79,117,140,0.18) 0%, transparent 70%);
                animation: float4 9s ease-in-out infinite;
            }
            .geo-5 {
                width: 60px; height: 60px;
                top: 60%; right: 25%;
                background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
                animation: float5 6s ease-in-out infinite;
            }
            @keyframes float1 { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-25px) scale(1.05); } }
            @keyframes float2 { 0%,100% { transform: translate(0,0); } 50% { transform: translate(-15px, 20px); } }
            @keyframes float3 { 0%,100% { transform: scale(1); } 50% { transform: scale(1.2); } }
            @keyframes float4 { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(10px,-15px) scale(1.1); } }
            @keyframes float5 { 0%,100% { transform: translateY(0); } 50% { transform: translateY(12px); } }

            /* ── Right Panel: Form ─────────────────────────────────── */
            .login-form-panel {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 3rem 2rem;
                background: #f8fafc;
                position: relative;
            }
            /* Subtle grid pattern */
            .login-form-panel::before {
                content: '';
                position: absolute;
                inset: 0;
                background-image:
                    linear-gradient(rgba(0,0,0,0.02) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(0,0,0,0.02) 1px, transparent 1px);
                background-size: 40px 40px;
                pointer-events: none;
            }
            .form-wrapper {
                position: relative;
                z-index: 2;
                width: 100%;
                max-width: 440px;
            }
            .form-card {
                background: #fff;
                border-radius: 20px;
                padding: 2.5rem;
                box-shadow:
                    0 1px 3px rgba(0,0,0,0.04),
                    0 8px 24px rgba(0,0,0,0.06),
                    0 24px 48px rgba(0,0,0,0.04);
                border: 1px solid rgba(0,0,0,0.04);
            }
            .form-header {
                text-align: center;
                margin-bottom: 2rem;
            }
            .form-header h2 {
                font-size: 1.5rem;
                font-weight: 700;
                color: #0f172a;
                margin: 0 0 .35rem;
            }
            .form-header p {
                font-size: .85rem;
                color: #64748b;
                margin: 0;
            }
            .form-footer {
                text-align: center;
                margin-top: 1.5rem;
                font-size: .78rem;
                color: #94a3b8;
            }

            /* ── Responsive ────────────────────────────────────────── */
            @media (max-width: 900px) {
                .login-page { grid-template-columns: 1fr; }
                .login-brand { display: none; }
                .login-form-panel {
                    background: linear-gradient(160deg, #034C8C, #4F758C, #FF3950, #034C8C);
                    background-size: 400% 400%;
                    animation: gradientFlow 10s ease infinite;
                    min-height: 100vh;
                }
                .login-form-panel::before { display: none; }
                .form-card {
                    background: rgba(255,255,255,0.97);
                    backdrop-filter: blur(20px);
                }
                .form-footer { color: rgba(255,255,255,.6); }
                .mobile-brand {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    margin-bottom: 2rem;
                }
                .mobile-brand .brand-logo-wrap { width: 80px; height: 80px; margin-bottom: 1rem; }
                .mobile-brand .brand-logo-wrap img { width: 60px; height: 60px; }
                .mobile-brand h1 { font-size: 1.25rem; font-weight: 700; color: #fff; margin: 0 0 .25rem; }
                .mobile-brand p { font-size: .8rem; color: rgba(255,255,255,.6); margin: 0; }
            }
            @media (min-width: 901px) {
                .mobile-brand { display: none; }
            }
        </style>
    </head>
    <body class="antialiased">
        @php $empresa = \App\Models\Empresa::instancia(); @endphp

        <div class="login-page">
            {{-- ── LEFT: Branding Panel ─────────────────────── --}}
            <div class="login-brand">
                <div class="geo-shape geo-1"></div>
                <div class="geo-shape geo-2"></div>
                <div class="geo-shape geo-3"></div>
                <div class="geo-shape geo-4"></div>
                <div class="geo-shape geo-5"></div>

                <div class="brand-content">
                    <a href="/" class="brand-logo-wrap">
                        @if($empresa?->logo_url)
                            <img src="{{ $empresa->logo_url }}" alt="Logo">
                        @else
                            <i class="fas fa-building text-white/80"></i>
                        @endif
                    </a>
                    <h1 class="brand-title">{{ $empresa?->nombre_display ?? config('app.name') }}</h1>
                    <p class="brand-subtitle">Sistema de Gestión de Importaciones</p>

                    <div class="feature-list">
                        <div class="feature-item">
                            <span class="feature-icon" style="background:rgba(59,130,246,.2); color:#60a5fa;">
                                <i class="fas fa-chart-pie"></i>
                            </span>
                            Control total de ventas, compras e inventario
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon" style="background:rgba(16,185,129,.2); color:#34d399;">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </span>
                            Facturación electrónica SUNAT integrada
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon" style="background:rgba(168,85,247,.2); color:#c084fc;">
                                <i class="fas fa-boxes-stacked"></i>
                            </span>
                            Gestión multi-almacén con trazabilidad IMEI
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon" style="background:rgba(251,191,36,.2); color:#fbbf24;">
                                <i class="fas fa-shield-halved"></i>
                            </span>
                            Seguridad avanzada con roles y permisos
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── RIGHT: Form Panel ────────────────────────── --}}
            <div class="login-form-panel">
                <div class="form-wrapper">
                    {{-- Mobile brand (visible < 900px) --}}
                    <div class="mobile-brand">
                        <div class="brand-logo-wrap">
                            @if($empresa?->logo_url)
                                <img src="{{ $empresa->logo_url }}" alt="Logo">
                            @else
                                <i class="fas fa-building text-white/80 text-2xl"></i>
                            @endif
                        </div>
                        <h1>{{ $empresa?->nombre_display ?? config('app.name') }}</h1>
                        <p>Sistema de Gestión de Importaciones</p>
                    </div>

                    <div class="form-card">
                        {{ $slot }}
                    </div>

                    <div class="form-footer">
                        &copy; {{ date('Y') }} {{ $empresa?->nombre_display ?? config('app.name') }}. Todos los derechos reservados.
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>