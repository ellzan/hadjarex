<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Hadjar Express</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
        <header class="w-full bg-[#14532D] text-white border-b-4 border-[#D4AF37]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <img
                        src="{{ route('brand.logo') }}"
                        alt="Hadjar Express"
                        class="h-10 w-auto object-contain"
                    />
                    <span class="font-bold text-lg tracking-wide text-[#D4AF37]">Hadjar Express</span>
                </a>

                <div>
                    @auth
                        <a
                            href="{{ url('/panel') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-[#D4AF37] text-[#14532D] font-semibold text-sm hover:opacity-90 transition"
                        >
                            @if (auth()->user()->isSuperAdmin())
                                Panel Super Admin (HSC {{ auth()->user()->kode_mitra }})
                            @else
                                Panel Mitra (HSC {{ auth()->user()->kode_mitra }})
                            @endif
                        </a>
                    @else
                        <a
                            href="{{ url('/panel/login') }}"
                            class="inline-flex items-center px-4 py-2 rounded-md bg-[#D4AF37] text-[#14532D] font-semibold text-sm hover:opacity-90 transition"
                        >
                            Login
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-xl bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
                <h1 class="text-xl font-bold text-[#14532D] mb-2">Lacak Resi Pengiriman</h1>
                <p class="text-sm text-slate-600 mb-6">
                    Masukkan nomor resi Hadjar Express (maksimal 10 nomor, satu per baris).
                </p>

                <form method="GET" action="{{ url('/') }}" class="space-y-4">
                    <div>
                        <label for="nomor_resi" class="block text-sm font-medium text-slate-700 mb-1">
                            Nomor Resi
                        </label>
                        <textarea
                            id="nomor_resi"
                            name="nomor_resi"
                            rows="4"
                            placeholder="HSC 001 26 13 11 001"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#D4AF37]"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-2.5 px-4 rounded-lg bg-[#14532D] text-[#D4AF37] font-semibold text-sm hover:bg-[#104224] transition"
                    >
                        Lacak
                    </button>
                </form>
            </div>
        </main>
    </body>
</html>
