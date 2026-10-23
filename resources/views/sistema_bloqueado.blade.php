<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Suspendido — Estelar Software</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0f1e;
            overflow: hidden;
        }

        /* Fondo animado */
        .bg-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(99,102,241,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.07) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0%   { transform: translateY(0); }
            100% { transform: translateY(60px); }
        }

        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.25;
            animation: float 8s ease-in-out infinite;
        }
        .glow-orb.one {
            width: 500px; height: 500px;
            background: #44413A;
            top: -150px; left: -100px;
        }
        .glow-orb.two {
            width: 400px; height: 400px;
            background: #818cf8;
            bottom: -120px; right: -80px;
            animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-30px) scale(1.05); }
        }

        /* Tarjeta principal */
        .card {
            position: relative;
            background: rgba(15, 20, 40, 0.85);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 24px;
            padding: 56px 52px;
            max-width: 560px;
            width: 90%;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow:
                0 0 0 1px rgba(99,102,241,0.1),
                0 40px 80px rgba(0,0,0,0.6),
                0 0 60px rgba(99,102,241,0.08);
            animation: cardIn 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(40px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Línea superior decorativa */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #44413A, #818cf8, transparent);
            border-radius: 999px;
        }

        /* Logo / Icono de bloqueo */
        .lock-ring {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: rgba(239,68,68,0.12);
            border: 1.5px solid rgba(239,68,68,0.35);
            margin-bottom: 28px;
            position: relative;
            animation: pulse-ring 3s ease-in-out infinite;
        }

        @keyframes pulse-ring {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.2); }
            50%       { box-shadow: 0 0 0 14px rgba(239,68,68,0); }
        }

        .lock-ring i {
            font-size: 36px;
            color: #ef4444;
        }

        /* Badge "Estelar Software" */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 999px;
            padding: 6px 18px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 500;
            color: #a5b4fc;
            letter-spacing: 0.03em;
        }

        .brand-badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #44413A;
            animation: blink 1.8s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }

        /* Textos */
        h1 {
            font-size: 26px;
            font-weight: 700;
            color: #f1f5f9;
            line-height: 1.25;
            margin-bottom: 14px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.7;
            margin-bottom: 36px;
        }

        .subtitle strong {
            color: #cbd5e1;
            font-weight: 600;
        }

        /* Separador */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(99,102,241,0.25), transparent);
            margin-bottom: 32px;
        }

        /* Contacto */
        .contact-box {
            background: rgba(99,102,241,0.07);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 28px;
            text-align: left;
        }

        .contact-box p.label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #44413A;
            margin-bottom: 14px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .contact-item:last-child { margin-bottom: 0; }

        .contact-item i {
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(99,102,241,0.15);
            border-radius: 8px;
            color: #818cf8;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* Botón */
        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 24px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-logout:hover {
            background: rgba(239,68,68,0.18);
            border-color: rgba(239,68,68,0.5);
        }

        /* Footer */
        .footer-note {
            margin-top: 28px;
            font-size: 12px;
            color: #475569;
        }

        .footer-note span {
            color: #44413A;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="bg-grid"></div>
    <div class="glow-orb one"></div>
    <div class="glow-orb two"></div>

    <div class="card">

        <!-- Badge marca -->
        <div class="brand-badge">
            <span class="dot"></span>
            Estelar Software Empresarial
        </div>

        <!-- Icono de bloqueo -->
        <div class="lock-ring">
            <i class="fas fa-lock"></i>
        </div>

        <!-- Título -->
        <h1>Acceso Suspendido</h1>

        <!-- Mensaje -->
        <p class="subtitle">
            Estimado cliente, le informamos que el acceso a su sistema
            ha sido <strong>suspendido temporalmente</strong> debido a
            una deuda pendiente de pago.<br><br>
            Para restablecer el servicio de manera inmediata, por favor
            comuníquese con nuestro equipo de soporte.
        </p>

        <div class="divider"></div>

        <!-- Información de contacto -->
        <div class="contact-box">
            <p class="label">Contacte a soporte</p>
            <div class="contact-item">
                <i class="fas fa-phone-alt"></i>
                <span>+51 924 210 341</span>
            </div>
            <div class="contact-item">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp: +51 924 210 341</span>
            </div>
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <span>soporte@estelar.software</span>
            </div>
        </div>

        <!-- Cerrar sesión -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                Cerrar Sesión
            </button>
        </form>

        <!-- Footer -->
        <p class="footer-note">
            &copy; {{ date('Y') }} <span>Estelar Software Empresarial</span> — Todos los derechos reservados
        </p>

    </div>

</body>
</html>
