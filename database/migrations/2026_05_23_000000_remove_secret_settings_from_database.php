<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'sso.secret',
                'iam.sso_secret',
                'iam.jwt_secret',
                'iam.signing_key',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally left blank. These values should remain config-only.
    }
};
