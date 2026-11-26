<?php

namespace Tests\Feature;

use App\Models\Anomalie;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\MessageSortant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_message_neutre_et_applique_le_plafond_journalier(): void
    {
        config(['schoollogs.plafond_notifications_jour_par_eleve' => 2]);

        $ecole = Etablissement::create(['nom' => 'École Notif']);
        $parent = User::create([
            'nom' => 'Parent',
            'prenom' => 'Test',
            'identifiant' => 'par_notif_01',
            'password' => Hash::make('Passer1234!'),
            'role' => 'parent',
            'telephone' => '+237699000001',
        ]);

        $eleve = Eleve::create([
            'nom' => 'Eleve',
            'prenom' => 'Léo',
            'classe' => '6e',
            'etablissement_id' => $ecole->id,
            'etat' => 'actif',
        ]);
        $eleve->parents()->attach($parent->id);

        $service = app(NotificationService::class);

        // 1er message -> envoyé
        $a1 = Anomalie::create([
            'eleve_id' => $eleve->id,
            'type' => 'hors_fenetre',
            'date_locale' => '2026-11-26',
            'fenetre_id' => null,
            'passage_id' => null,
            'etat_traitement' => 'ouverte',
        ]);
        $m1 = $service->notifierPourAnomalie($a1)[0];
        $this->assertSame('envoye', $m1->etat);

        // 2e message -> envoyé (atteint le plafond de 2)
        $a2 = Anomalie::create([
            'eleve_id' => $eleve->id,
            'type' => 'double_scan',
            'date_locale' => '2026-11-26',
            'fenetre_id' => null,
            'passage_id' => null,
            'etat_traitement' => 'ouverte',
        ]);
        $m2 = $service->notifierPourAnomalie($a2)[0];
        $this->assertSame('envoye', $m2->etat);

        // 3e anomalie le même jour -> non_envoye_plafond avec motif renseigné
        $a3 = Anomalie::create([
            'eleve_id' => $eleve->id,
            'type' => 'hors_heures_etablissement',
            'date_locale' => '2026-11-26',
            'fenetre_id' => null,
            'passage_id' => null,
            'etat_traitement' => 'ouverte',
        ]);
        $m3 = $service->notifierPourAnomalie($a3)[0];

        $this->assertSame('non_envoye_plafond', $m3->etat);
        $this->assertNotNull($m3->motif_non_envoi);
        $this->assertEquals(3, MessageSortant::count());
    }
}