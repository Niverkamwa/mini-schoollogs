<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Passage;
use App\Models\PointControle;
use App\Models\User;
use App\Services\BadgeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassageSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronisation_est_idempotente_sur_meme_client_event_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::where('identifiant', 'agent01')->firstOrFail();
        $badge = Badge::where('etat', 'actif')->firstOrFail();
        $point = PointControle::firstOrFail();
        $jeton = (new BadgeService())->genererJeton($badge);

        $payload = [
            'evenements' => [[
                'client_event_id' => 'evt-unique-001',
                'jeton' => $jeton,
                'point_controle_id' => $point->id,
                'sens' => 'entree',
                'horodatage_appareil' => now()->toIso8601String(),
            ]],
        ];

        $premiere = $this->actingAs($agent)->postJson('/api/passages/sync', $payload);
        $premiere->assertStatus(200)
            ->assertJsonPath('resultats.0.statut', 'accepte');

        $deuxieme = $this->actingAs($agent)->postJson('/api/passages/sync', $payload);
        $deuxieme->assertStatus(200)
            ->assertJsonPath('resultats.0.statut', 'doublon');

        $this->assertSame(1, Passage::where('client_event_id', 'evt-unique-001')->count());
    }

    public function test_jeton_invalide_est_rejete(): void
    {
        $this->seed(DatabaseSeeder::class);

        $agent = User::where('identifiant', 'agent01')->firstOrFail();
        $point = PointControle::firstOrFail();
        $avant = Passage::count();

        $res = $this->actingAs($agent)->postJson('/api/passages/sync', [
            'evenements' => [[
                'client_event_id' => 'evt-invalide-001',
                'jeton' => 'v1.inconnu.0.mauvaisehmac',
                'point_controle_id' => $point->id,
                'sens' => 'entree',
                'horodatage_appareil' => now()->toIso8601String(),
            ]],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('resultats.0.statut', 'rejete');
        $this->assertSame($avant, Passage::count());
    }
}