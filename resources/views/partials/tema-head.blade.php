{{-- Favicon de marca y tema claro/oscuro (se aplica antes de pintar para evitar parpadeo) --}}
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
<meta name="theme-color" content="#44413A">
<script>
    (function () {
        var tema = null;
        try { tema = localStorage.getItem('kyr-tema'); } catch (e) {}
        if (!tema) tema = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
        document.documentElement.classList.toggle('dark', tema === 'oscuro');
        window.kyrToggleTema = function () {
            var oscuro = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', oscuro);
            try { localStorage.setItem('kyr-tema', oscuro ? 'oscuro' : 'claro'); } catch (e) {}
        };
    })();
</script>
