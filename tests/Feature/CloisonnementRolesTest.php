<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloisonnementRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_voit_uniquement_ses_enfants_et_recoit_403_sur_autre_eleve(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Dans le seeder : élève 1 = Junior Mbarga (parent01), élève 3 = Kevin Ngono (parent02)
        $parent1 = \App\Models\User::where('identifiant', 'parent01')->firstOrFail();

        $resDashboard = $this->actingAs($parent1)->get('/tableau-de-bord');
        $resDashboard->assertStatus(200);
        $resDashboard->assertSee('Junior Mbarga');
        $resDashboard->assertSee('Sandra Mbarga');
        $resDashboard->assertDontSee('Kevin Ngono');

        // Accès interdit (403) sur la fiche d'un élève non rattaché (id 3)
        $this->actingAs($parent1)->get('/eleves/3')->assertStatus(403);
    }

    public function test_responsable_et_agent_voient_tous_les_eleves(): void
    {
        $this->seed(DatabaseSeeder::class);

        $responsable = \App\Models\User::where('identifiant', 'resp01')->firstOrFail();
        $agent = \App\Models\User::where('identifiant', 'agent01')->firstOrFail();

        foreach ([$responsable, $agent] as $compte) {
            $res = $this->actingAs($compte)->get('/tableau-de-bord');
            $res->assertStatus(200);
            $res->assertSee('Junior Mbarga');
            $res->assertSee('Kevin Ngono');
        }
    }
}