<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['provider']);
            $table->unique(['user_id', 'provider']);
        });

        // Assign existing keys to user 1
        DB::table('api_keys')->whereNull('user_id')->update(['user_id' => 1]);
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique('provider');
        });
    }
};
