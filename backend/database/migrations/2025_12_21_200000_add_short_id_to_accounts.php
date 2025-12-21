<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column exists first to be safe, though usage implies it doesn't
        if (!Schema::hasColumn('accounts', 'short_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('short_id', 30)->nullable()->after('id');
            });

            // Backfill existing accounts
            DB::table('accounts')->orderBy('id')->chunk(100, function ($accounts) {
                foreach ($accounts as $account) {
                    if (empty($account->short_id)) {
                        DB::table('accounts')
                            ->where('id', $account->id)
                            ->update(['short_id' => 'acc_' . Str::random(13)]);
                    }
                }
            });

            // Make it required and unique
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('short_id', 30)->nullable(false)->unique()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'short_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('short_id');
            });
        }
    }
};
