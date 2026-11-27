<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SchoolLogs — Passage' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    @if(request()->routeIs('connexion') || ! auth()->check())
        <main class="min-h-screen flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                @if(session('statut'))
                    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 text-sm">
                        {{ session('statut') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 text-sm">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    @else
        @php
            $role = auth()->user()->role;
        @endphp
        <div class="min-h-screen flex flex-col md:flex-row">
            {{-- Sidebar dédiée au rôle connecté --}}
            <aside class="w-full md:w-64 bg-slate-900 text-white flex flex-col justify-between shrink-0">
                <div class="p-4 space-y-6">
                    <div class="border-b border-slate-800 pb-4">
                        <div class="font-bold text-lg tracking-tight">
                            SchoolLogs <span class="text-emerald-400 text-xs font-normal block">Module Passage</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-300">
                            {{ auth()->user()->prenom }} {{ auth()->user()->nom }}
                        </p>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[11px] font-semibold uppercase bg-slate-800 text-emerald-300">
                            {{ $role }}
                        </span>
                    </div>

                    <nav class="space-y-1 text-sm">
                        @if($role === 'agent')
                            <a href="{{ route('scan') }}"
                               class="block px-3 py-2 rounded-lg {{ request()->routeIs('scan') ? 'bg-emerald-600 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                Scan hors ligne &amp; sync
                            </a>
                        @elseif($role === 'responsable')
                            <a href="{{ route('tableau-de-bord') }}#section-pilotage"
                               class="block px-3 py-2 rounded-lg {{ request()->routeIs('tableau-de-bord') ? 'bg-emerald-600 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                Pilotage général
                            </a>
                            <a href="{{ route('tableau-de-bord') }}#section-badges"
                               class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                                Élèves &amp; badges
                            </a>
                            <a href="{{ route('tableau-de-bord') }}#section-passages"
                               class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                                Passages récents
                            </a>
                            <a href="{{ route('tableau-de-bord') }}#section-anomalies"
                               class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                                Anomalies
                            </a>
                            <a href="{{ route('tableau-de-bord') }}#section-messages"
                               class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
                                Messages parents
                            </a>
                        @elseif($role === 'parent')
                            <a href="{{ route('tableau-de-bord') }}"
                               class="block px-3 py-2 rounded-lg {{ request()->routeIs('tableau-de-bord') ? 'bg-emerald-600 text-white font-medium' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                Mes enfants
                            </a>
                        @endif
                    </nav>
                </div>

                <div class="p-4 border-t border-slate-800">
                    <form method="POST" action="{{ url('/deconnexion') }}">
                        @csrf
                        <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 px-3 py-2 rounded-lg text-xs font-medium text-white">
                            Déconnexion
                        </button>
                    </form>
                </div>
            </aside>

            {{-- Zone principale --}}
            <div class="flex-1 min-w-0">
                <main class="max-w-6xl mx-auto px-4 py-6">
                    @if(session('statut'))
                        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 text-sm">
                            {{ session('statut') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 text-sm">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    @endif
</body>
</html>