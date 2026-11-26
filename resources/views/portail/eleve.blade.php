<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche élève — SchoolLogs Passage</title>
</head>
<body>
    <h1>{{ $eleve->prenom }} {{ $eleve->nom }}</h1>
    <p>Classe : {{ $eleve->classe }}</p>
    <p>Établissement : {{ $eleve->etablissement?->nom }}</p>
    <p>Itinéraire : {{ $eleve->itineraire?->libelle }}</p>
    <p><a href="/tableau-de-bord">Retour au tableau de bord</a></p>
</body>
</html>