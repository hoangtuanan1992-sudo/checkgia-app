<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_canonical')->nullable()->after('email');
        });

        $users = DB::table('users')->select('id', 'email')->orderBy('id')->get();

        foreach ($users as $u) {
            $email = mb_strtolower(trim((string) $u->email));

            $local = $email;
            $domain = '';
            if (str_contains($email, '@')) {
                [$local, $domain] = explode('@', $email, 2);
            }

            if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
                $local = preg_replace('/\+.*/', '', $local) ?? $local;
                $local = str_replace('.', '', $local);
                $domain = 'gmail.com';
            }

            $canonical = $domain ? $local.'@'.$domain : $email;

            DB::table('users')->where('id', $u->id)->update([
                'email_canonical' => $canonical,
            ]);
        }

        $dupes = DB::table('users')
            ->select('email_canonical', DB::raw('COUNT(*) as c'))
            ->whereNotNull('email_canonical')
            ->groupBy('email_canonical')
            ->having('c', '>', 1)
            ->limit(1)
            ->get();

        if ($dupes->isEmpty()) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email_canonical');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email_canonical']);
            $table->dropColumn('email_canonical');
        });
    }
};
