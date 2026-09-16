<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add historical monetary snapshots to sales and sale items.
     *
     * - sales.tax_rate: the IVA rate applied to this sale.
     * - sale_items.cost: the unit acquisition cost at the moment of the sale.
     *
     * Both columns are nullable so legacy rows keep an explicit "unknown" state.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('unit_price');
        });

        $this->backfillTaxRates();
        $this->backfillItemCosts();
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }

    /**
     * Reconstruct the historical IVA rate from the stored monetary values.
     *
     * This is a RECONSTRUCTED value, not a guaranteed originally captured snapshot:
     * it is derived from the amounts that actually produced the stored tax. Legacy
     * rows with subtotal = 0 or with tax_amount = 0 are ambiguous (the rate can not
     * be proven from the money alone) and are deliberately left as NULL instead of
     * inventing a 0% historical rate.
     *
     * The rate is rounded to one decimal place, which neutralises the per-sale
     * rounding noise produced by computing tax on unrounded subtotals.
     */
    public function backfillTaxRates(): void
    {
        DB::table('sales')
            ->where('subtotal', '>', 0)
            ->where('tax_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($sales) {
                foreach ($sales as $sale) {
                    $rate = round(((float) $sale->tax_amount / (float) $sale->subtotal) * 100, 1);

                    if ($rate > 0 && $rate <= 999.99) {
                        DB::table('sales')->where('id', $sale->id)->update(['tax_rate' => $rate]);
                    }
                }
            });
    }

    /**
     * Reconstruct the historical unit cost for existing sale items.
     *
     * The cost is copied from the product's stored production cost. For the demo
     * data this is exact, because it was generated from the current product values.
     * For arbitrary legacy data this is a RECONSTRUCTION, not the true historical
     * acquisition cost; items whose product row no longer exists keep NULL.
     */
    public function backfillItemCosts(): void
    {
        DB::table('sale_items')
            ->orderBy('id')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    $cost = DB::table('products')->where('id', $item->product_id)->value('production_cost');

                    if ($cost !== null) {
                        DB::table('sale_items')->where('id', $item->id)->update(['cost' => $cost]);
                    }
                }
            });
    }
};
