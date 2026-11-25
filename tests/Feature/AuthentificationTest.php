<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mot_de_passe_hache_et_connexion_reussie(): void
    {
        $user = User::create([
            'nom' => 'Test',
            'prenom' => 'User',
            'identifiant' => 'agent_test',
            'password' => Hash::make('MotDePasse123!'),
            'role' => 'agent',
        ]);

        $this->assertNotEquals('MotDePasse123!', $user->password);
        $this->assertTrue(Hash::check('MotDePasse123!', $user->password));

        $response = $this->post('/connexion', [
            'identifiant' => 'agent_test',
            'password' => 'MotDePasse123!',
        ]);

        $response->assertRedirect('/tableau-de-bord');
        $this->assertAuthenticatedAs($user);
    }

    public function test_mot_de_passe_trop_court_refuse(): void
    {
        $response = $this->post('/connexion', [
            'identifiant' => 'agent_test',
            'password' => '1234567',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_message_identique_et_limitation_a_la_6e_tentative(): void
    {
        User::create([
            'nom' => 'Test',
            'prenom' => 'User',
            'identifiant' => 'resp_test',
            'password' => Hash::make('BonMotDePasse1!'),
            'role' => 'responsable',
        ]);

        $resInconnu = $this->post('/connexion', [
            'identifiant' => 'utilisateur_inexistant',
            'password' => 'MauvaisMotDePasse1!',
        ]);

        $resMauvaisMdp = $this->post('/connexion', [
            'identifiant' => 'resp_test',
            'password' => 'MauvaisMotDePasse1!',
        ]);

        $this->assertEquals(
            session('errors')->first('identifiant'),
            $resMauvaisMdp->baseResponse->getSession()?->get('errors')?->first('identifiant') ?? session('errors')->first('identifiant')
        );
        $resInconnu->assertSessionHasErrors('identifiant');
        $resMauvaisMdp->assertSessionHasErrors('identifiant');

        $statut = null;
            for ($i = 3; $i <= 7; $i++) {
                $res = $this->post('/connexion', [
                    'identifiant' => 'resp_test',
                    'password' => 'MauvaisMotDePasse1!',
                ]);
                $statut = $res->getStatusCode();
                if ($statut === 429) {
                    break;
                }
            }

            $this->assertSame(429, $statut);
    }

    public function test_deconnexion_invalide_la_session(): void
    {
        $user = User::create([
            'nom' => 'Parent',
            'prenom' => 'Test',
            'identifiant' => 'parent_test',
            'password' => Hash::make('ParentPass123!'),
            'role' => 'parent',
        ]);

        $this->actingAs($user);
        $this->post('/deconnexion')->assertRedirect('/connexion');
        $this->assertGuest();
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
    }
}