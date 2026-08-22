<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title>Login - Kasir Just</title>
    <meta name="description" content="Halaman Masuk Sistem Kasir Point of Sale">

    <!-- Google Fonts CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Google Material Symbols CDN -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex items-center justify-center min-h-dvh p-4 bg-stone-100">

    <div class="w-full max-w-md bg-white border border-stone-200 rounded-2xl shadow-xl p-6 sm:p-8 space-y-6">
        
        {{-- App Header / Brand --}}
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-2xl bg-amber-100 text-amber-700 border border-amber-300 flex items-center justify-center mx-auto shadow-md shadow-amber-700/10">
                <span class="material-symbols-outlined text-4xl">storefront</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-stone-900">Kasir Just</h1>
            <p class="text-xs text-stone-500">Masuk untuk mengelola sistem kasir</p>
        </div>

        {{-- Error Alert --}}
        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-3 rounded-xl text-sm flex items-start gap-2">
                <span class="material-symbols-outlined text-xl shrink-0 mt-0.5">warning</span>
                <div class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Login Form --}}
        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf

            <div class="space-y-1">
                <label for="email" class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Email Address</label>
                <input type="email" 
                       name="email" 
                       id="email" 
                       value="{{ old('email') }}" 
                       required 
                       autofocus
                       placeholder="nama@kasir.com" 
                       class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" />
            </div>

            <div class="space-y-1">
                <label for="password" class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Password</label>
                <input type="password" 
                       name="password" 
                       id="password" 
                       required 
                       placeholder="••••••••" 
                       class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-stone-600">
                    <input type="checkbox" name="remember" class="rounded border-stone-300 text-amber-700 focus:ring-amber-700/20">
                    <span>Ingat saya</span>
                </label>
            </div>

            <button type="submit" id="btn-login-submit" class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-3 rounded-xl font-semibold text-base cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-md shadow-amber-700/20 w-full mt-2">
                <span>Masuk Sekarang</span>
                <span class="material-symbols-outlined text-xl">login</span>
            </button>
        </form>

        {{-- Quick Fill Demo Account Helper --}}
        <div class="pt-4 border-t border-stone-200 space-y-3 text-center">
            <p class="text-xs font-medium text-stone-500 uppercase tracking-wider">Demo Account (Klik untuk isi otomatis)</p>
            <div>
                <button type="button" 
                        onclick="fillDemo('admin@kasir.com', 'password')"
                        class="inline-flex items-center justify-center gap-1 px-3.5 py-2.5 rounded-lg text-xs font-semibold border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-col h-auto text-center w-full">
                    <span class="text-xs font-bold text-amber-700">Admin Kasir</span>
                    <span class="text-[11px] text-stone-500">admin@kasir.com (Password: password)</span>
                </button>
            </div>
        </div>

    </div>

    <script>
        function fillDemo(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
