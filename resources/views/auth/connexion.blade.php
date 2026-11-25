<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — SchoolLogs Passage</title>
</head>
<body>
    <h1>Connexion</h1>

    @if ($errors->any())
        <p>{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="/connexion">
        @csrf
        <div>
            <label for="identifiant">Identifiant</label>
            <input id="identifiant" name="identifiant" type="text" value="{{ old('identifiant') }}" required>
        </div>
        <div>
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button type="submit">Se connecter</button>
    </form>
</body>
</html>