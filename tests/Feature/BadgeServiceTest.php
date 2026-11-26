<?php

namespace Tests\Feature;

use App\Services\BadgeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_jeton_valide_puis_expire_hors_fenetre_de_tolerance(): void
    {
        $this->seed(DatabaseSeeder::class);
        $service = new BadgeService();
        $badge = \App\Models\Badge::where('etat', 'actif')->firstOrFail();

        $t0 = Carbon::create(2026, 11, 26, 7, 15, 0, 'UTC');
        $jeton = $service->genererJeton($badge, $t0);

        $resValide = $service->verifierJeton($jeton, $t0->copy()->addSeconds(30));
        $this->assertTrue($resValide['valide']);

        $tExpire = $t0->copy()->addMinutes(5);
        $resExpire = $service->verifierJeton($jeton, $tExpire);
        $this->assertFalse($resExpire['valide']);
        $this->assertSame('jeton_expire', $resExpire['motif']);
    }

    public function test_badge_suspendu_ou_renouvele_est_rejete(): void
    {
        $this->seed(DatabaseSeeder::class);
        $service = new BadgeService();
        $badge = \App\Models\Badge::where('etat', 'actif')->firstOrFail();

        $jeton = $service->genererJeton($badge);
        $service->changerEtat($badge, 'suspendu');

        $resSuspendu = $service->verifierJeton($jeton);
        $this->assertFalse($resSuspendu['valide']);
        $this->assertSame('badge_suspendu', $resSuspendu['motif']);

        $nouveauBadge = $service->renouvelerBadge($badge->fresh());
        $this->assertSame('actif', $nouveauBadge->etat);
        $this->assertSame('revoque', $badge->fresh()->etat);
    }
}