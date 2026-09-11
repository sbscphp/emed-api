<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A laboratory or radiology result can be released as a scanned report - an
 * image or a PDF - instead of, or alongside, the typed result rows. The file
 * lives on Cloudinary; these columns hold the secure URL it was stored under
 * and the name it was uploaded with, so the merged patient document list can
 * render it the same way it renders an uploaded patient document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_visit_lab', function (Blueprint $table) {
            $table->text('file_url')->nullable()->after('signature');
            $table->string('file_name')->nullable()->after('file_url');
        });

        Schema::table('patient_visit_radiology', function (Blueprint $table) {
            $table->text('file_url')->nullable()->after('department');
            $table->string('file_name')->nullable()->after('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('patient_visit_lab', function (Blueprint $table) {
            $table->dropColumn(['file_url', 'file_name']);
        });

        Schema::table('patient_visit_radiology', function (Blueprint $table) {
            $table->dropColumn(['file_url', 'file_name']);
        });
    }
};
