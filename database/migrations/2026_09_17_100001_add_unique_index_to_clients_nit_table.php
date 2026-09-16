<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce NIT uniqueness at the database level.
     *
     * The rule already exists at the request level; this index makes it
     * authoritative and closes the race between concurrent writers. The column
     * is nullable and multiple NULLs remain allowed, so clients without a NIT
     * are unaffected.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('nit');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['nit']);
        });
    }
};
