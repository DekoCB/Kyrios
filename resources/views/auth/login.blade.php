<x-guest>
    <style>
        .login-form-header { text-align: center; margin-bottom: 2rem; }
        .login-form-header .welcome-icon {
            width: 56px; height: 56px;
            margin: 0 auto 1rem;
            border-radius: 16px;
            background: linear-gradient(135deg, #2b2924, #44413A);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 16px rgba(0,0,0,.25);
        }
        .login-form-header .welcome-icon i { font-size: 1.4rem; color: #fff; }
        .login-form-header h2 {
            font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0 0 .3rem;
        }
        .login-form-header p { font-size: .85rem; color: #64748b; margin: 0; }

        /* Input groups */
        .input-group { margin-bottom: 1.25rem; }
        .input-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }
        .input-group .input-wrap {
            position: relative;
        }
        .input-group .input-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: .85rem;
            transition: color .2s;
            pointer-events: none;
        }
        .input-group .input-wrap input {
            width: 100%;
            padding: .75rem .875rem .75rem 2.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: .9rem;
            color: #0f172a;
            background: #f8fafc;
            transition: all .25s ease;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .input-group .input-wrap input:focus {
            border-color: #44413A;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(201,165,0,.12);
        }
        .input-group .input-wrap input:focus ~ .input-icon {
            color: #44413A;
        }
        .input-group .input-wrap input::placeholder { color: #cbd5e1; }
        .input-group .input-wrap .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            padding: 4px;
            font-size: .95rem;
            transition: color .2s;
        }
        .input-group .input-wrap .toggle-pw:hover { color: #475569; }

        /* Error messages */
        .field-error {
            display: block;
            margin-top: .35rem;
            font-size: .75rem;
            color: #ef4444;
        }

        /* Extras row */
        .extras-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .remember-check {
            display: flex;
            align-items: center;
            gap: .4rem;
            cursor: pointer;
        }
        .remember-check input[type="checkbox"] {
            width: 16px; height: 16px;
            border-radius: 5px;
            border: 1.5px solid #cbd5e1;
            accent-color: #44413A;
            cursor: pointer;
        }
        .remember-check span { font-size: .8rem; color: #64748b; }
        .forgot-link {
            font-size: .8rem;
            color: #44413A;
            text-decoration: none;
            font-weight: 500;
            transition: color .2s;
        }
        .forgot-link:hover { color: #44413A; text-decoration: underline; }

        /* Submit button */
        .login-btn {
            width: 100%;
            padding: .85rem;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #2b2924 0%, #44413A 100%);
            cursor: pointer;
            transition: all .3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
            letter-spacing: .02em;
            position: relative;
            overflow: hidden;
        }
        .login-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 60%);
            opacity: 0;
            transition: opacity .3s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,.3);
        }
        .login-btn:hover::after { opacity: 1; }
        .login-btn:active { transform: translateY(0); }
        .login-btn i { margin-right: .5rem; }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.5rem 0;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .login-divider span { font-size: .75rem; color: #94a3b8; font-weight: 500; }

        /* Register link */
        .register-row {
            text-align: center;
        }
        .register-row span { font-size: .82rem; color: #64748b; }
        .register-row a {
            font-size: .82rem;
            color: #44413A;
            font-weight: 600;
            text-decoration: none;
            transition: color .2s;
        }
        .register-row a:hover { color: #44413A; text-decoration: underline; }

        /* Alert */
        .login-alert {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: .82rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .login-alert i { flex-shrink: 0; }
    </style>

    <!-- Session Status / Success Messages -->
    @if (session('status'))
        <div class="login-alert">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="login-form-header">
        <div class="welcome-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h2>Bienvenido de vuelta</h2>
        <p>Ingresa tus credenciales para continuar</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="input-group">
            <label for="email">Correo Electrónico</label>
            <div class="input-wrap">
                <i class="fas fa-envelope input-icon"></i>
                <input id="email" type="email" name="email"
                       value="{{ old('email') }}"
                       placeholder="nombre@empresa.com"
                       required autofocus autocomplete="username">
            </div>
            @error('email')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <!-- Password -->
        <div class="input-group" x-data="{ mostrar: false }">
            <label for="password">Contraseña</label>
            <div class="input-wrap">
                <i class="fas fa-key input-icon"></i>
                <input id="password"
                       :type="mostrar ? 'text' : 'password'"
                       name="password"
                       placeholder="••••••••"
                       required autocomplete="current-password">
                <button type="button" @click="mostrar = !mostrar" class="toggle-pw" tabindex="-1">
                    <i :class="mostrar ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
            </div>
            @error('password')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <!-- Remember / Forgot -->
        <div class="extras-row">
            <label class="remember-check">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Recordarme</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="forgot-link">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <!-- Submit -->
        <button type="submit" class="login-btn">
            <i class="fas fa-arrow-right-to-bracket"></i>
            Iniciar Sesión
        </button>

        <!-- Divider + Register -->
        <div class="login-divider"><span>o</span></div>
        <div class="register-row">
            <span>¿No tienes cuenta? </span>
            <a href="{{ route('register') }}">Crear cuenta</a>
        </div>
    </form>
</x-guest>