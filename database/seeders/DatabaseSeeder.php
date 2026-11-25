<?php

namespace Database\Seeders;

use App\Models\Anomalie;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Fenetre;
use App\Models\Itineraire;
use App\Models\Passage;
use App\Models\PointControle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tz = config('app.timezone', 'Africa/Douala');
        $aujourdHui = Carbon::now($tz)->startOfDay();

        $ecole = Etablissement::create([
            'nom' => "Collège Pilote d'Afric'Éduc",
        ]);

        $responsable = User::create([
            'nom' => 'Kamwa',
            'prenom' => 'Responsable',
            'identifiant' => 'resp01',
            'password' => Hash::make('Passer1234!'),
            'role' => 'responsable',
            'telephone' => null,
        ]);

        $agent = User::create([
            'nom' => 'Tchoumi',
            'prenom' => 'Agent',
            'identifiant' => 'agent01',
            'password' => Hash::make('Passer1234!'),
            'role' => 'agent',
            'telephone' => null,
        ]);

        $parent1 = User::create([
            'nom' => 'Mbarga',
            'prenom' => 'Paul',
            'identifiant' => 'parent01',
            'password' => Hash::make('Passer1234!'),
            'role' => 'parent',
            'telephone' => '+237690000001',
        ]);

        $parent2 = User::create([
            'nom' => 'Ngono',
            'prenom' => 'Marie',
            'identifiant' => 'parent02',
            'password' => Hash::make('Passer1234!'),
            'role' => 'parent',
            'telephone' => '+237690000002',
        ]);

        $portailEntree = PointControle::create([
            'etablissement_id' => $ecole->id,
            'libelle' => "Portail d'entrée",
            'type' => 'portail',
        ]);

        $portailSortie = PointControle::create([
            'etablissement_id' => $ecole->id,
            'libelle' => 'Portail de sortie',
            'type' => 'portail',
        ]);

        $itineraire = Itineraire::create([
            'libelle' => 'Itinéraire Standard 6e',
        ]);

        $fenetreEntree = Fenetre::create([
            'itineraire_id' => $itineraire->id,
            'point_controle_id' => $portailEntree->id,
            'sens' => 'entree',
            'ordre' => 1,
            'heure_debut' => '07:00:00',
            'heure_fin' => '07:45:00',
            'jours_application' => '1,2,3,4,5',
        ]);

        $fenetreSortie = Fenetre::create([
            'itineraire_id' => $itineraire->id,
            'point_controle_id' => $portailSortie->id,
            'sens' => 'sortie',
            'ordre' => 2,
            'heure_debut' => '15:30:00',
            'heure_fin' => '16:15:00',
            'jours_application' => '1,2,3,4,5',
        ]);

        $elevesData = [
            ['nom' => 'Mbarga', 'prenom' => 'Junior', 'classe' => '6e A', 'parent' => $parent1],
            ['nom' => 'Mbarga', 'prenom' => 'Sandra', 'classe' => '6e A', 'parent' => $parent1],
            ['nom' => 'Ngono', 'prenom' => 'Kevin', 'classe' => '6e A', 'parent' => $parent2],
            ['nom' => 'Fotso', 'prenom' => 'Chloe', 'classe' => '6e A', 'parent' => $parent2],
            ['nom' => 'Atangana', 'prenom' => 'Lucas', 'classe' => '6e A', 'parent' => $parent2],
        ];

        $eleves = [];
        foreach ($elevesData as $data) {
            $eleve = Eleve::create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'classe' => $data['classe'],
                'etablissement_id' => $ecole->id,
                'itineraire_id' => $itineraire->id,
                'etat' => 'actif',
            ]);
            $eleve->parents()->attach($data['parent']->id);

            Badge::create([
                'eleve_id' => $eleve->id,
                'public_id' => Str::lower(Str::random(16)),
                'secret' => bin2hex(random_bytes(32)),
                'etat' => 'actif',
                'date_emission' => now(),
            ]);

            $eleves[] = $eleve;
        }

        // 1) Anomalie passage_manquant (élève 0)
        Anomalie::create([
            'eleve_id' => $eleves[0]->id,
            'type' => 'passage_manquant',
            'date_locale' => $aujourdHui->toDateString(),
            'fenetre_id' => $fenetreEntree->id,
            'passage_id' => null,
            'etat_traitement' => 'ouverte',
        ]);

        // 2) Passage hors fenêtre + anomalie hors_fenetre (élève 1)
        $heureHorsFenetre = $aujourdHui->copy()->setTime(8, 15, 0)->utc();
        $passageHorsFenetre = Passage::create([
            'badge_id' => $eleves[1]->badgeActif?->id,
            'eleve_id' => $eleves[1]->id,
            'point_controle_id' => $portailEntree->id,
            'code_brut' => 'v1.seed.1.sig',
            'heure_locale' => $heureHorsFenetre,
            'heure_corrigee' => $heureHorsFenetre,
            'heure_serveur' => $heureHorsFenetre,
            'heure_retenue' => $heureHorsFenetre,
            'appareil_id' => 'appareil-demo-01',
            'local_event_id' => (string) Str::uuid(),
            'etat_synchronisation' => 'synchronise',
            'resultat_controle' => 'accepte',
            'horloge_decalee' => false,
            'heure_incoherente' => false,
        ]);

        Anomalie::create([
            'eleve_id' => $eleves[1]->id,
            'type' => 'hors_fenetre',
            'date_locale' => $aujourdHui->toDateString(),
            'fenetre_id' => $fenetreEntree->id,
            'passage_id' => $passageHorsFenetre->id,
            'etat_traitement' => 'ouverte',
        ]);

        // 3) Double scan (< 60s) + anomalie double_scan (élève 2)
        $t1 = $aujourdHui->copy()->setTime(7, 20, 0)->utc();
        $t2 = $aujourdHui->copy()->setTime(7, 20, 30)->utc();

        Passage::create([
            'badge_id' => $eleves[2]->badgeActif?->id,
            'eleve_id' => $eleves[2]->id,
            'point_controle_id' => $portailEntree->id,
            'code_brut' => 'v1.seed.2a.sig',
            'heure_locale' => $t1,
            'heure_corrigee' => $t1,
            'heure_serveur' => $t1,
            'heure_retenue' => $t1,
            'appareil_id' => 'appareil-demo-01',
            'local_event_id' => (string) Str::uuid(),
            'etat_synchronisation' => 'synchronise',
            'resultat_controle' => 'accepte',
        ]);

        $passageDouble = Passage::create([
            'badge_id' => $eleves[2]->badgeActif?->id,
            'eleve_id' => $eleves[2]->id,
            'point_controle_id' => $portailEntree->id,
            'code_brut' => 'v1.seed.2b.sig',
            'heure_locale' => $t2,
            'heure_corrigee' => $t2,
            'heure_serveur' => $t2,
            'heure_retenue' => $t2,
            'appareil_id' => 'appareil-demo-01',
            'local_event_id' => (string) Str::uuid(),
            'etat_synchronisation' => 'synchronise',
            'resultat_controle' => 'accepte',
        ]);

        Anomalie::create([
            'eleve_id' => $eleves[2]->id,
            'type' => 'double_scan',
            'date_locale' => $aujourdHui->toDateString(),
            'fenetre_id' => $fenetreEntree->id,
            'passage_id' => $passageDouble->id,
            'etat_traitement' => 'ouverte',
        ]);

        // Garder une référence sur $fenetreSortie pour la suite
        unset($fenetreSortie, $responsable, $agent);
    }
}