<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add historical client identity snapshots to sales.
     *
     * - sales.client_name: the client's name at the moment of the sale.
     * - sales.client_nit: the client's tax id at the moment of the sale.
     *
     * Both columns are nullable: walk-in sales carry no client identity, and
     * legacy rows whose client_id was already NULL before this migration can
     * not be reconstructed, so they keep an explicit "unknown" (NULL) state.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('client_id');
            $table->string('client_nit')->nullable()->after('client_name');
        });

        $this->backfillClientIdentity();
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_nit']);
        });
    }

    /**
     * Reconstruct the historical client identity from the live client row.
     *
     * This is a RECONSTRUCTED value, not a guaranteed originally captured
     * snapshot: it copies the client's CURRENT name and NIT, which may already
     * have changed since the sale happened. Rows with client_id = NULL are
     * deliberately left untouched instead of inventing an identity for them.
     */
    public function backfillClientIdentity(): void
    {
        DB::table('sales')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(500, function ($sales) {
                foreach ($sales as $sale) {
                    $client = DB::table('clients')->where('id', $sale->client_id)->first(['name', 'nit']);

                    if ($client !== null) {
                        DB::table('sales')->where('id', $sale->id)->update([
                            'client_name' => $client->name,
                            'client_nit' => $client->nit,
                        ]);
                    }
                }
            });
    }
};
