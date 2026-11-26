<?php

namespace Tests\Feature;

use App\Models\Anomalie;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Fenetre;
use App\Models\Itineraire;
use App\Models\Passage;
use App\Models\PointControle;
use App\Services\AnomalieDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnomalieDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_detecte_passage_hors_fenetre_et_double_scan(): void
    {
        $tz = config('app.timezone', 'Africa/Douala');
        $date = Carbon::now($tz)->startOfDay()->nextWeekday(); // Lundi à vendredi
        $jourIso = (int) $date->dayOfWeekIso;

        $ecole = Etablissement::create(['nom' => 'École Test']);
        $portail = PointControle::create([
            'etablissement_id' => $ecole->id,
            'libelle' => 'Portail Principal',
            'type' => 'portail',
        ]);
        $itineraire = Itineraire::create(['libelle' => 'Standard']);
        $fenetre = Fenetre::create([
            'itineraire_id' => $itineraire->id,
            'point_controle_id' => $portail->id,
            'sens' => 'entree',
            'ordre' => 1,
            'heure_debut' => '07:00:00',
            'heure_fin' => '07:45:00',
            'jours_application' => (string) $jourIso,
        ]);

        $eleve = Eleve::create([
            'nom' => 'Test',
            'prenom' => 'Eleve',
            'classe' => '6e',
            'etablissement_id' => $ecole->id,
            'itineraire_id' => $itineraire->id,
            'etat' => 'actif',
        ]);
        $badge = Badge::create([
            'eleve_id' => $eleve->id,
            'public_id' => Str::lower(Str::random(16)),
            'secret' => bin2hex(random_bytes(32)),
            'etat' => 'actif',
            'date_emission' => now(),
        ]);

        // Passage hors fenêtre (08:30)
        $h1 = $date->copy()->setTime(8, 30, 0)->utc();
        $p1 = Passage::create([
            'badge_id' => $badge->id,
            'eleve_id' => $eleve->id,
            'point_controle_id' => $portail->id,
            'sens' => 'entree',
            'code_brut' => 'v1.test.1.sig',
            'local_event_id' => (string) Str::uuid(),
            'client_event_id' => (string) Str::uuid(),
            'etat_synchronisation' => 'synchronise',
            'resultat_controle' => 'accepte',
            'heure_locale' => $h1,
            'heure_corrigee' => $h1,
            'heure_serveur' => $h1,
            'heure_retenue' => $h1,
        ]);

        // Second passage 20s plus tard -> double_scan
        $h2 = $date->copy()->setTime(8, 30, 20)->utc();
        $p2 = Passage::create([
            'badge_id' => $badge->id,
            'eleve_id' => $eleve->id,
            'point_controle_id' => $portail->id,
            'sens' => 'entree',
            'code_brut' => 'v1.test.2.sig',
            'local_event_id' => (string) Str::uuid(),
            'client_event_id' => (string) Str::uuid(),
            'etat_synchronisation' => 'synchronise',
            'resultat_controle' => 'accepte',
            'heure_locale' => $h2,
            'heure_corrigee' => $h2,
            'heure_serveur' => $h2,
            'heure_retenue' => $h2,
        ]);

        $service = app(AnomalieDetectionService::class);
        $service->analyserPassage($p1);
        $service->analyserPassage($p2);

        $this->assertDatabaseHas('anomalies', [
            'eleve_id' => $eleve->id,
            'type' => 'hors_fenetre',
            'fenetre_id' => $fenetre->id,
            'date_locale' => $date->toDateString(),
        ]);

        $this->assertDatabaseHas('anomalies', [
            'eleve_id' => $eleve->id,
            'type' => 'double_scan',
            'date_locale' => $date->toDateString(),
        ]);
    }

    public function test_detecte_passage_manquant_apres_fenetre(): void
    {
        $tz = config('app.timezone', 'Africa/Douala');
        $date = Carbon::now($tz)->startOfDay()->nextWeekday();
        $jourIso = (int) $date->dayOfWeekIso;

        $ecole = Etablissement::create(['nom' => 'École Test']);
        $portail = PointControle::create([
            'etablissement_id' => $ecole->id,
            'libelle' => 'Portail Principal',
            'type' => 'portail',
        ]);
        $itineraire = Itineraire::create(['libelle' => 'Standard']);
        $fenetre = Fenetre::create([
            'itineraire_id' => $itineraire->id,
            'point_controle_id' => $portail->id,
            'sens' => 'entree',
            'ordre' => 1,
            'heure_debut' => '07:00:00',
            'heure_fin' => '07:45:00',
            'jours_application' => (string) $jourIso,
        ]);

        $eleve = Eleve::create([
            'nom' => 'Absent',
            'prenom' => 'Paul',
            'classe' => '6e',
            'etablissement_id' => $ecole->id,
            'itineraire_id' => $itineraire->id,
            'etat' => 'actif',
        ]);

        $service = app(AnomalieDetectionService::class);
        $service->detecterPassagesManquants($date->toDateString(), $date->copy()->setTime(10, 0, 0));

        $this->assertTrue(
            Anomalie::where('eleve_id', $eleve->id)
                ->where('type', 'passage_manquant')
                ->where('fenetre_id', $fenetre->id)
                ->whereDate('date_locale', $date->toDateString())
                ->exists()
        );
    }
    public function test_detecte_scan_hors_heures_etablissement(): void
{
    $tz = config('app.timezone', 'Africa/Douala');
    $date = Carbon::now($tz)->startOfDay()->nextWeekday();

    $ecole = Etablissement::create(['nom' => 'École Test']);
    $portail = PointControle::create([
        'etablissement_id' => $ecole->id,
        'libelle' => 'Portail Principal',
        'type' => 'portail',
    ]);
    $eleve = Eleve::create([
        'nom' => 'Nuit',
        'prenom' => 'Marc',
        'classe' => '5e',
        'etablissement_id' => $ecole->id,
        'etat' => 'actif',
    ]);
    $badge = Badge::create([
        'eleve_id' => $eleve->id,
        'public_id' => Str::lower(Str::random(16)),
        'secret' => bin2hex(random_bytes(32)),
        'etat' => 'actif',
        'date_emission' => now(),
    ]);

    // Scan à 23h15 (hors plage d'ouverture 06:00 - 20:00)
    $heureNuit = $date->copy()->setTime(23, 15, 0)->utc();
    $passage = Passage::create([
        'badge_id' => $badge->id,
        'eleve_id' => $eleve->id,
        'point_controle_id' => $portail->id,
        'sens' => 'entree',
        'code_brut' => 'v1.nuit.1.sig',
        'local_event_id' => (string) Str::uuid(),
        'client_event_id' => (string) Str::uuid(),
        'etat_synchronisation' => 'synchronise',
        'resultat_controle' => 'accepte',
        'heure_locale' => $heureNuit,
        'heure_corrigee' => $heureNuit,
        'heure_serveur' => $heureNuit,
        'heure_retenue' => $heureNuit,
    ]);

    app(AnomalieDetectionService::class)->analyserPassage($passage);

    $this->assertDatabaseHas('anomalies', [
        'eleve_id' => $eleve->id,
        'type' => 'hors_heures_etablissement',
        'passage_id' => $passage->id,
        'date_locale' => $date->toDateString(),
    ]);
}
}