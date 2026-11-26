<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord — SchoolLogs Passage</title>
</head>
<body>
    <h1>Tableau de bord ({{ $user->role }})</h1>
    <p>Bonjour {{ $user->prenom }} {{ $user->nom }}.</p>

    <form method="POST" action="/deconnexion">
        @csrf
        <button type="submit">Déconnexion</button>
    </form>

    <h2>Élèves accessibles</h2>
    <ul>
        @foreach ($eleves as $eleve)
            <li>
                <a href="/eleves/{{ $eleve->id }}">{{ $eleve->prenom }} {{ $eleve->nom }} ({{ $eleve->classe }})</a>
            </li>
        @endforeach
    </ul>

    <h2>Anomalies</h2>
    <ul>
        @foreach ($anomalies as $anomalie)
            <li>{{ $anomalie->type }} — {{ $anomalie->eleve?->prenom }} {{ $anomalie->eleve?->nom }} ({{ $anomalie->etat_traitement }})</li>
        @endforeach
    </ul>
</body>
</html>