@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-xl shadow p-6 border border-slate-200">
    <h1 class="text-xl font-bold mb-1">Connexion</h1>
    <p class="text-sm text-slate-500 mb-5">Connectez-vous avec votre identifiant SchoolLogs.</p>

    <form method="POST" action="{{ url('/connexion') }}" class="space-y-4">
        @csrf
        <div>
            <label for="identifiant" class="block text-sm font-medium text-slate-700 mb-1">Identifiant</label>
            <input id="identifiant" name="identifiant" type="text" value="{{ old('identifiant') }}" required autofocus
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-800">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mot de passe</label>
            <input id="password" name="password" type="password" required
                   class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-800">
        </div>

        <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-2.5 rounded-lg text-sm">
            Se connecter
        </button>
    </form>

    <div class="mt-6 pt-4 border-t border-slate-200 text-xs text-slate-600 space-y-1">
        <p class="font-semibold text-slate-800">Comptes de test (mot de passe : <code>Passer1234!</code>)</p>
        <p>Responsable : <code>resp01</code></p>
        <p>Agent de portail : <code>agent01</code></p>
        <p>Parent 1 (2 enfants) : <code>parent01</code></p>
        <p>Parent 2 (3 enfants) : <code>parent02</code></p>
    </div>
</div>
@endsection