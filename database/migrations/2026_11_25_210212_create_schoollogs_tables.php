<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
        });

        Schema::create('itineraires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->timestamps();
        });

        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('classe');
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('itineraire_id')->nullable()->constrained('itineraires')->nullOnDelete();
            $table->enum('etat', ['actif', 'inactif'])->default('actif');
            $table->timestamps();
        });

        Schema::create('parents_eleves', function (Blueprint $table) {
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->primary(['parent_id', 'eleve_id']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('public_id')->unique();
            $table->text('secret');
            $table->enum('etat', ['actif', 'suspendu', 'perdu', 'revoque'])->default('actif');
            $table->timestamp('date_emission');
            $table->foreignId('badge_remplace_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('points_controle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('libelle');
            $table->enum('type', ['portail', 'autobus']);
            $table->timestamps();
        });

        Schema::create('fenetres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itineraire_id')->constrained('itineraires')->cascadeOnDelete();
            $table->foreignId('point_controle_id')->constrained('points_controle')->cascadeOnDelete();
            $table->enum('sens', ['entree', 'sortie']);
            $table->unsignedInteger('ordre');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('jours_application')->default('1,2,3,4,5');
            $table->timestamps();
            $table->unique(['itineraire_id', 'ordre']);
        });

        Schema::create('exceptions_jour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['ferie', 'absence_justifiee', 'sortie_anticipee']);
            $table->string('motif')->nullable();
            $table->timestamps();
        });

        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->foreignId('eleve_id')->nullable()->constrained('eleves')->nullOnDelete();
            $table->foreignId('point_controle_id')->constrained('points_controle')->cascadeOnDelete();
            $table->string('code_brut');
            $table->timestamp('heure_locale');
            $table->timestamp('heure_corrigee');
            $table->timestamp('heure_serveur');
            $table->timestamp('heure_retenue');
            $table->string('appareil_id');
            $table->string('local_event_id');
            $table->string('etat_synchronisation')->default('synchronise');
            $table->string('resultat_controle');
            $table->string('motif_refus')->nullable();
            $table->boolean('horloge_decalee')->default(false);
            $table->boolean('heure_incoherente')->default(false);
            $table->timestamps();

            $table->unique(['appareil_id', 'local_event_id']);
            $table->index(['eleve_id', 'heure_retenue']);
        });

        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->enum('type', ['passage_manquant', 'hors_fenetre', 'point_hors_itineraire', 'double_scan', 'badge_non_actif']);
            $table->date('date_locale');
            $table->foreignId('fenetre_id')->nullable()->constrained('fenetres')->nullOnDelete();
            $table->foreignId('passage_id')->nullable()->constrained('passages')->nullOnDelete();
            $table->enum('etat_traitement', ['ouverte', 'traitee', 'levee_automatiquement'])->default('ouverte');
            $table->timestamps();

            $table->unique(['type', 'eleve_id', 'date_locale', 'fenetre_id']);
        });

        Schema::create('messages_sortants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('anomalie_id')->constrained('anomalies')->cascadeOnDelete();
            $table->text('texte');
            $table->enum('etat', ['en_attente', 'envoye', 'echec', 'non_envoye_plafond'])->default('en_attente');
            $table->string('motif_non_envoi')->nullable();
            $table->timestamp('date_envoi')->nullable();
            $table->timestamps();

            $table->unique(['anomalie_id', 'parent_id']);
        });

        Schema::create('syntheses', function (Blueprint $table) {
            $table->id();
            $table->string('classe');
            $table->date('date');
            $table->json('chiffres_calcules');
            $table->text('texte');
            $table->string('origine');
            $table->unsignedInteger('tentatives')->default(0);
            $table->timestamps();

            $table->index(['classe', 'date']);
        });

        Schema::create('appels_llm', function (Blueprint $table) {
            $table->id();
            $table->string('modele');
            $table->unsignedInteger('jetons_entree')->default(0);
            $table->unsignedInteger('jetons_sortie')->default(0);
            $table->decimal('cout_estime', 10, 6)->default(0);
            $table->string('statut');
            $table->string('empreinte')->index();
            $table->timestamps();
        });

        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('objet');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
        Schema::dropIfExists('appels_llm');
        Schema::dropIfExists('syntheses');
        Schema::dropIfExists('messages_sortants');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('passages');
        Schema::dropIfExists('exceptions_jour');
        Schema::dropIfExists('fenetres');
        Schema::dropIfExists('points_controle');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('parents_eleves');
        Schema::dropIfExists('eleves');
        Schema::dropIfExists('itineraires');
        Schema::dropIfExists('etablissements');
    }
};