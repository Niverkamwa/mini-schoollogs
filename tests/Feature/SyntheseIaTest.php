<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SyntheseIaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyntheseIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthese_utilise_repli_deterministe_sans_cle_api(): void
    {
        config(['services.ia.key' => '', 'services.ia.url' => '']);

        $service = app(SyntheseIaService::class);
        $resultat = $service->genererSynthese([
            'date' => '2026-11-27',
            'total_passages' => 12,
            'passages_horloge_decalee' => 1,
            'total_anomalies' => 2,
            'anomalies_ouvertes' => 1,
            'anomalies_par_type' => [
                'hors_fenetre' => 1,
                'passage_manquant' => 1,
            ],
            'messages_envoyes' => 2,
        ]);

        $this->assertSame('deterministe', $resultat['source']);
        $this->assertStringContainsString('12 passage(s)', $resultat['texte']);
        $this->assertStringContainsString('2 anomalie(s)', $resultat['texte']);
        $this->assertStringContainsString('hors_fenetre (1)', $resultat['texte']);
        $this->assertStringContainsString('passage_manquant (1)', $resultat['texte']);
    }

    public function test_responsable_voit_la_synthese_sur_son_tableau_de_bord(): void
    {
        $responsable = User::create([
            'prenom' => 'Test',
            'nom' => 'Responsable',
            'identifiant' => 'resp_test',
            'email' => 'resp_test@example.org',
            'password' => bcrypt('secret1234'),
            'role' => 'responsable',
        ]);

        $reponse = $this->actingAs($responsable)->get(route('tableau-de-bord'));

        $reponse->assertOk();
        $reponse->assertSee('Synthèse journalière encadrée');
        $reponse->assertSee('Mode : Repli déterministe');
    }
}