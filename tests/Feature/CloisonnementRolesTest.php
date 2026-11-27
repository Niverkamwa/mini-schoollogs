<?php

namespace Tests\Feature;

use App\Models\Eleve;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloisonnementRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_parent_voit_uniquement_ses_enfants_et_recoit_403_sur_autre_eleve(): void
    {
        $parent1 = User::where('identifiant', 'parent01')->firstOrFail();
        $enfantAutorise = Eleve::where('prenom', 'Junior')->firstOrFail();
        $enfantInterdit = Eleve::where('prenom', 'Kevin')->firstOrFail();

        $res = $this->actingAs($parent1)->get('/tableau-de-bord');
        $res->assertStatus(200);
        $res->assertSee('Junior');
        $res->assertDontSee('Kevin');

        $this->actingAs($parent1)
            ->get("/eleves/{$enfantAutorise->id}")
            ->assertStatus(200);

        $this->actingAs($parent1)
            ->get("/eleves/{$enfantInterdit->id}")
            ->assertStatus(403);
    }

    public function test_responsable_voit_tous_les_eleves_et_agent_est_redirige_vers_scan(): void
    {
        $responsable = User::where('identifiant', 'resp01')->firstOrFail();
        $agent = User::where('identifiant', 'agent01')->firstOrFail();

        $resResp = $this->actingAs($responsable)->get('/tableau-de-bord');
        $resResp->assertStatus(200);
        $resResp->assertSee('Junior Mbarga');
        $resResp->assertSee('Kevin Ngono');

        $resAgent = $this->actingAs($agent)->get('/tableau-de-bord');
        $resAgent->assertStatus(302);
        $resAgent->assertRedirect('/scan');

        $this->actingAs($agent)
            ->get('/scan')
            ->assertStatus(200);
    }
}