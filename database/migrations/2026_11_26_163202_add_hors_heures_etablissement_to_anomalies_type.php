<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropUnique('anomalies_type_eleve_id_date_locale_fenetre_id_unique');
                $table->string('type_new')->nullable();
            });

            DB::statement('UPDATE anomalies SET type_new = type;');

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('anomalies', function (Blueprint $table) {
                $table->enum('type', [
                    'passage_manquant',
                    'hors_fenetre',
                    'point_hors_itineraire',
                    'double_scan',
                    'badge_non_actif',
                    'hors_heures_etablissement',
                ])->default('hors_fenetre')->after('eleve_id');
                $table->unique(['type', 'eleve_id', 'date_locale', 'fenetre_id']);
            });

            DB::statement('UPDATE anomalies SET type = type_new;');

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropColumn('type_new');
            });

            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            Schema::table('anomalies', function (Blueprint $table) {
                $table->enum('type', [
                    'passage_manquant',
                    'hors_fenetre',
                    'point_hors_itineraire',
                    'double_scan',
                    'badge_non_actif',
                    'hors_heures_etablissement',
                ])->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');

            DB::table('anomalies')
                ->where('type', 'hors_heures_etablissement')
                ->update(['type' => 'hors_fenetre']);

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropUnique('anomalies_type_eleve_id_date_locale_fenetre_id_unique');
                $table->string('type_new')->nullable();
            });

            DB::statement('UPDATE anomalies SET type_new = type;');

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('anomalies', function (Blueprint $table) {
                $table->enum('type', [
                    'passage_manquant',
                    'hors_fenetre',
                    'point_hors_itineraire',
                    'double_scan',
                    'badge_non_actif',
                ])->after('eleve_id');
                $table->unique(['type', 'eleve_id', 'date_locale', 'fenetre_id']);
            });

            DB::statement('UPDATE anomalies SET type = type_new;');

            Schema::table('anomalies', function (Blueprint $table) {
                $table->dropColumn('type_new');
            });

            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            Schema::table('anomalies', function (Blueprint $table) {
                $table->enum('type', [
                    'passage_manquant',
                    'hors_fenetre',
                    'point_hors_itineraire',
                    'double_scan',
                    'badge_non_actif',
                ])->change();
            });
        }
    }
};