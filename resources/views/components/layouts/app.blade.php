<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? 'Kasir Just' }}</title>
    <meta name="description" content="Sistem Kasir Point of Sale modern dan cepat">

    <!-- Google Fonts CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Google Material Symbols CDN -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <!-- Tailwind CSS v4 CDN Browser Script -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-stone-100 text-stone-800 font-sans min-h-dvh">

@auth
<header class="w-full bg-white border-b border-stone-200 px-4 py-2.5 flex items-center justify-between text-xs sticky top-0 z-30 shadow-xs">
    <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 font-bold flex items-center justify-center border border-amber-200">
            {{ auth()->user()->initials() }}
        </div>
        <div>
            <p class="font-semibold text-stone-900 leading-tight">{{ auth()->user()->name }}</p>
            <p class="text-[10px] text-stone-500">{{ auth()->user()->email }}</p>
        </div>
    </div>
    
    <form action="{{ route('logout') }}" method="POST" class="inline">
        @csrf
        <button type="submit" id="btn-logout" class="inline-flex items-center justify-center gap-1.5 min-h-[32px] px-2.5 py-1 rounded-lg text-xs font-semibold border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-600 hover:text-rose-600 hover:border-rose-300 transition-all select-none active:scale-95">
            <span class="material-symbols-outlined text-base">logout</span>
            <span>Keluar</span>
        </button>
    </form>
</header>
@endauth

<div class="min-h-dvh pb-20">
    {{ $slot }}
</div>

@auth
{{-- Bottom Navigation Bar --}}
<nav class="fixed bottom-0 left-0 right-0 z-40 flex items-stretch bg-white/95 border-t border-stone-200 shadow-md backdrop-blur-md pb-[env(safe-area-inset-bottom)]" role="navigation" aria-label="Navigasi utama">
    <a href="{{ route('kasir') }}"
       id="nav-kasir"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 px-1 min-h-[60px] text-[11px] font-medium transition-colors relative no-underline {{ request()->routeIs('kasir') ? 'text-amber-700 font-semibold before:content-[""] before:absolute before:top-0 before:left-1/4 before:right-1/4 before:h-0.5 before:bg-amber-700 before:rounded-b-xs' : 'text-stone-500 hover:text-amber-700' }}"
       wire:navigate>
        <span class="material-symbols-outlined text-xl">point_of_sale</span>
        <span>Kasir</span>
    </a>

    <a href="{{ route('produk') }}"
       id="nav-produk"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 px-1 min-h-[60px] text-[11px] font-medium transition-colors relative no-underline {{ request()->routeIs('produk') || request()->routeIs('kategori') ? 'text-amber-700 font-semibold before:content-[""] before:absolute before:top-0 before:left-1/4 before:right-1/4 before:h-0.5 before:bg-amber-700 before:rounded-b-xs' : 'text-stone-500 hover:text-amber-700' }}"
       wire:navigate>
        <span class="material-symbols-outlined text-xl">inventory_2</span>
        <span>Produk</span>
    </a>

    <a href="{{ route('laporan') }}"
       id="nav-laporan"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 px-1 min-h-[60px] text-[11px] font-medium transition-colors relative no-underline {{ request()->routeIs('laporan') ? 'text-amber-700 font-semibold before:content-[""] before:absolute before:top-0 before:left-1/4 before:right-1/4 before:h-0.5 before:bg-amber-700 before:rounded-b-xs' : 'text-stone-500 hover:text-amber-700' }}"
       wire:navigate>
        <span class="material-symbols-outlined text-xl">bar_chart</span>
        <span>Laporan</span>
    </a>
</nav>
@endauth

@livewireScripts
</body>
</html>
