<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // no-op as Stripe has been removed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //no-op
    }
};
