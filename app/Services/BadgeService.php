<?php

namespace App\Services;

use App\Models\Badge;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BadgeService
{
    public const PERIODE_SECONDES = 60;

    public function pasDeTemps(?CarbonInterface $instant = null): int
    {
        $ts = ($instant ?? Carbon::now('UTC'))->getTimestamp();

        return intdiv($ts, self::PERIODE_SECONDES);
    }

    public function genererJeton(Badge $badge, ?CarbonInterface $instant = null): string
    {
        $pas = $this->pasDeTemps($instant);
        $charge = $badge->public_id.'|'.$pas;
        $signature = hash_hmac('sha256', $charge, (string) $badge->secret);

        return 'v1.'.$badge->public_id.'.'.$pas.'.'.$signature;
    }

    /**
     * @return array{valide: bool, motif: ?string, badge: ?Badge}
     */
    public function verifierJeton(string $jeton, ?CarbonInterface $instant = null, int $tolerancePas = 1): array
    {
        $parties = explode('.', $jeton);
        if (count($parties) !== 4 || $parties[0] !== 'v1') {
            return ['valide' => false, 'motif' => 'format_invalide', 'badge' => null];
        }

        [, $publicId, $pasStr, $signatureFournie] = $parties;
        if (! ctype_digit($pasStr)) {
            return ['valide' => false, 'motif' => 'format_invalide', 'badge' => null];
        }

        $badge = Badge::where('public_id', $publicId)->first();
        if (! $badge) {
            return ['valide' => false, 'motif' => 'badge_inconnu', 'badge' => null];
        }

        if ($badge->etat !== 'actif') {
            return ['valide' => false, 'motif' => 'badge_'.$badge->etat, 'badge' => $badge];
        }

        $pasActuel = $this->pasDeTemps($instant);
        $pasFourni = (int) $pasStr;

        if (abs($pasActuel - $pasFourni) > $tolerancePas) {
            return ['valide' => false, 'motif' => 'jeton_expire', 'badge' => $badge];
        }

        $charge = $badge->public_id.'|'.$pasFourni;
        $signatureAttendue = hash_hmac('sha256', $charge, (string) $badge->secret);

        if (! hash_equals($signatureAttendue, $signatureFournie)) {
            return ['valide' => false, 'motif' => 'signature_invalide', 'badge' => $badge];
        }

        return ['valide' => true, 'motif' => null, 'badge' => $badge];
    }

    public function changerEtat(Badge $badge, string $nouvelEtat): Badge
    {
        $etatsAutorises = ['actif', 'suspendu', 'perdu', 'revoque'];
        if (! in_array($nouvelEtat, $etatsAutorises, true)) {
            throw new \InvalidArgumentException("État de badge invalide : {$nouvelEtat}");
        }

        $badge->update(['etat' => $nouvelEtat]);

        return $badge->fresh();
    }

    public function renouvelerBadge(Badge $badge): Badge
    {
        $badge->update(['etat' => 'revoque']);

        return Badge::create([
            'eleve_id' => $badge->eleve_id,
            'public_id' => Str::lower(Str::random(16)),
            'secret' => bin2hex(random_bytes(32)),
            'etat' => 'actif',
            'date_emission' => now(),
        ]);
    }
}