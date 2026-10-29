<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }} - @yield('title', 'Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Scripts -->
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-100 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Logo y Título -->
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center">
                            <i class="fas fa-box text-indigo-600 text-2xl mr-3"></i>
                            <span class="text-xl font-bold text-gray-800">{{ config('app.name') }}</span>
                        </a>
                    </div>

                    <!-- Usuario y Rol -->
                    <div class="flex items-center space-x-4">
                        <!-- Rol Badge -->
                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                            @if(auth()->user()->role->nombre == 'Administración') bg-red-100 text-red-800
                            @elseif(auth()->user()->role->nombre == 'Contador') bg-green-100 text-green-800
                            @elseif(auth()->user()->role->nombre == 'Logística') bg-blue-100 text-blue-800
                            @else bg-purple-100 text-purple-800
                            @endif">
                            <i class="fas fa-user-tag mr-1"></i>
                            {{ auth()->user()->role->nombre }}
                        </span>

                        <!-- Dropdown de Usuario -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                                <i class="fas fa-user-circle text-2xl"></i>
                                <span class="font-medium">{{ auth()->user()->name }}</span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="open" 
                                @click.away="open = false"
                                x-transition
                                class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                {{-- <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-edit mr-2"></i>Perfil
                                </a> --}}
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-6">
            <!-- Alertas -->
            @if(session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Contenido Principal -->
            @yield('content')
        </main>
    </div>


</body>
</html>