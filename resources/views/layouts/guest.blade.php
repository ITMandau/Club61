<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'VANTAGE') }} - Sports & Social Club</title>

        <!-- Google Fonts: Luxury Serif, Clean Athletic Sans, Script Accent -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

        <!-- Scripts & Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans antialiased text-slate-100 bg-[#04160F] selection:bg-[#CCFF00] selection:text-[#04160F] relative overflow-x-hidden flex flex-col justify-center">
        <!-- Ambient Sports Club Glow & Court Geometry Background -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-40 left-1/4 w-[750px] h-[500px] bg-gradient-to-b from-emerald-600/15 via-emerald-800/10 to-transparent blur-3xl rounded-full"></div>
            <div class="absolute -bottom-40 right-1/4 w-[600px] h-[450px] bg-[#CCFF00]/5 blur-3xl rounded-full"></div>
            
            <svg class="absolute inset-0 w-full h-full opacity-[0.03] stroke-white" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="court-grid" width="120" height="120" patternUnits="userSpaceOnUse">
                        <path d="M 120 0 L 0 0 0 120" fill="none" stroke="currentColor" stroke-width="1.2" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#court-grid)" />
            </svg>
        </div>

        <!-- Main Content Area: Responsive container for desktop & mobile -->
        <main class="relative z-10 w-full py-6 sm:py-10 px-4 sm:px-6 lg:px-8 flex items-center justify-center min-h-screen">
            {{ $slot }}
        </main>
    </body>
</html>