<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $catalogo->nombre }} · Kyrios</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/catalogo-libro.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #2c2b26; color: #f5f3ea; font-family: 'Archivo', system-ui, 'Segoe UI', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        header { display: flex; align-items: center; gap: 14px; padding: 14px max(16px, env(safe-area-inset-left, 0px)); flex-wrap: wrap; }
        header img { height: 34px; }
        header b { font-size: 1.05rem; }
        header span { color: #bdbaae; font-size: .85rem; }
        main { flex: 1; width: 100%; max-width: 980px; margin: 0 auto; padding: 8px max(16px, env(safe-area-inset-left, 0px)) 32px; }
        .cl-escena { border: 0; }
        .cl-controles button { font-family: inherit; cursor: pointer; border: 1px solid #5b5a52; background: transparent; color: #f5f3ea; padding: 9px 16px; border-radius: 9px; font-weight: 600; font-size: .9rem; }
        .cl-controles button:last-child { background: #f2c80f; border-color: #f2c80f; color: #2a2300; }
        .cl-controles button:disabled { opacity: .4; cursor: not-allowed; }
        .cl-estado { color: #bdbaae; }
        .ayuda { color: #bdbaae; font-size: .8rem; text-align: center; margin-top: 8px; }
        footer { text-align: center; color: #8f8d81; font-size: .78rem; padding: 12px 16px 24px; }
    </style>
</head>
<body>
    <header>
        <img src="{{ asset('img/logo-kyrios-fondo-oscuro.png') }}" alt="Kyrios">
        <div><b>{{ $catalogo->nombre }}</b><br><span>{{ $catalogo->etiquetaTemporada() }}@if($catalogo->precio_enlace !== 'none') · precios sin IGV @endif</span></div>
    </header>
    <main>
        @include('catalogos.partials.libro', ['catalogo' => $catalogo, 'libro' => $libro, 'modo' => $modo, 'qr' => $qr])
    </main>
    <footer>KYRIOS LUCES S.A.C. · administracion@kyrios.com.pe · www.kyrios.com.pe</footer>
    <script src="{{ asset('js/catalogo-libro.js') }}"></script>
</body>
</html>
