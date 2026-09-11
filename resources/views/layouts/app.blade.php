<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'VANTAGE') }} - Sports & Social Club</title>

        <!-- Google Fonts: Luxury Serif & Athletic Sans -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans antialiased text-[#1F170D] bg-[#FBF9F5] selection:bg-[#D4AF37] selection:text-[#1E160A] relative overflow-x-hidden flex flex-col"
          style="background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
        
        <!-- Ambient Warm Gold Luxury Lighting -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-32 left-1/4 w-[850px] h-[550px] bg-gradient-to-b from-amber-300/20 via-yellow-500/10 to-transparent blur-3xl rounded-full"></div>
            <div class="absolute -bottom-32 right-1/4 w-[700px] h-[500px] bg-[#D4AF37]/15 blur-3xl rounded-full"></div>
        </div>

        <div class="min-h-screen flex flex-col relative z-10">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white/80 backdrop-blur-xl border-b border-[#D4AF37]/40 shadow-sm">
                    <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 pb-20 md:pb-8">
                {{ $slot }}
            </main>

            <!-- Bottom Navigation Bar for Mobile -->
            @include('layouts.bottom-nav')
        </div>

        @stack('scripts')
    </body>
</html>