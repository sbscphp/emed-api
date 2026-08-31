<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * =========================
         * ALTER WARDS TABLE
         * =========================
         */
        Schema::connection('tenant')->table('wards', function (Blueprint $table) {
            // Rename bed_cost to cost defensively
            if (Schema::connection('tenant')->hasColumn('wards', 'bed_cost')) {
                $table->renameColumn('bed_cost', 'cost');
            }
        });

        /**
         * Modify ENUM type column
         * Laravel cannot directly modify ENUM easily,
         * so use raw SQL.
         */
        DB::connection('tenant')->statement("
            ALTER TABLE wards
            MODIFY type ENUM(
                'Children',
                'Adult',
                'General',
                'Private'
            ) NOT NULL
        ");

        /**
         * =========================
         * ALTER BEDS TABLE
         * =========================
         */
        Schema::connection('tenant')->table('beds', function (Blueprint $table) {
            // Drop foreign key first defensively
            $foreignKeys = collect(Schema::connection('tenant')->getForeignKeys('beds'))->pluck('name')->toArray();
            if (in_array('beds_ward_id_foreign', $foreignKeys)) {
                $table->dropForeign(['ward_id']);
            }
        });

        Schema::connection('tenant')->table('beds', function (Blueprint $table) {
            // Then drop unique index defensively
            $indexes = collect(Schema::connection('tenant')->getIndexes('beds'))->pluck('name')->toArray();
            if (in_array('beds_ward_id_bed_number_unique', $indexes)) {
                $table->dropUnique('beds_ward_id_bed_number_unique');
            }

            // Rename number_of_available to available_bed_number defensively
            if (Schema::connection('tenant')->hasColumn('beds', 'number_of_available')) {
                $table->renameColumn('number_of_available', 'available_bed_number');
            }

            // Modify column
            $table->unsignedBigInteger('ward_id')
                ->nullable()
                ->change();

            // Re-add foreign key only if it doesn't already exist
            $foreignKeys = collect(Schema::connection('tenant')->getForeignKeys('beds'))->pluck('name')->toArray();
            if (!in_array('beds_ward_id_foreign', $foreignKeys)) {
                $table->foreign('ward_id')
                    ->references('id')
                    ->on('wards')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        /**
         * =========================
         * REVERT WARDS TABLE
         * =========================
         */

        DB::connection('tenant')->statement("
            ALTER TABLE wards
            MODIFY type ENUM(
                'Children',
                'Adult',
                'General'
            ) NOT NULL
        ");

        Schema::connection('tenant')->table('wards', function (Blueprint $table) {
            // Rename cost back to bed_cost defensively
            if (Schema::connection('tenant')->hasColumn('wards', 'cost') && !Schema::connection('tenant')->hasColumn('wards', 'bed_cost')) {
                $table->renameColumn('cost', 'bed_cost');
            }
        });

        /**
         * =========================
         * REVERT BEDS TABLE
         * =========================
         */
        Schema::connection('tenant')->table('beds', function (Blueprint $table) {
            $foreignKeys = collect(Schema::connection('tenant')->getForeignKeys('beds'))->pluck('name')->toArray();
            if (in_array('beds_ward_id_foreign', $foreignKeys)) {
                $table->dropForeign(['ward_id']);
            }
        });

        Schema::connection('tenant')->table('beds', function (Blueprint $table) {
            $table->unsignedBigInteger('ward_id')
                ->nullable(false)
                ->change();

            // Rename available_bed_number back to number_of_available defensively
            if (Schema::connection('tenant')->hasColumn('beds', 'available_bed_number') && !Schema::connection('tenant')->hasColumn('beds', 'number_of_available')) {
                $table->renameColumn('available_bed_number', 'number_of_available');
            }

            $foreignKeys = collect(Schema::connection('tenant')->getForeignKeys('beds'))->pluck('name')->toArray();
            if (!in_array('beds_ward_id_foreign', $foreignKeys)) {
                $table->foreign('ward_id')
                    ->references('id')
                    ->on('wards')
                    ->onDelete('cascade');
            }

            $indexes = collect(Schema::connection('tenant')->getIndexes('beds'))->pluck('name')->toArray();
            if (!in_array('beds_ward_id_bed_number_unique', $indexes)) {
                $table->unique(['ward_id', 'bed_number']);
            }
        });
    }
};
