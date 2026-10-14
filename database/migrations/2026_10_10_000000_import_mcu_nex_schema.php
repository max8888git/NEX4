<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Installs the project's canonical MySQL schema from database/mcu_nex_schema.sql.
 *
 * The registrar schema (tables, triggers, views and lookup rows) is maintained
 * as one raw SQL file shared with the legacy-api installers, so this migration
 * imports that file instead of duplicating it as individual schema builder
 * calls. The parser mirrors legacy-api/cli-install.php so DELIMITER blocks
 * (the meeting_slots triggers) execute correctly through PDO.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('colleges')) {
            // Already installed (e.g. through legacy-api/install.php).
            return;
        }

        $path = database_path('mcu_nex_schema.sql');
        $sql = is_file($path) ? file_get_contents($path) : false;
        if ($sql === false) {
            throw new RuntimeException("Schema file not found: {$path}");
        }

        $delimiter = ';';
        $buffer = '';
        $lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            if (preg_match('/^DELIMITER\s+(\S+)/i', $trim, $m)) {
                if (trim($buffer) !== '') {
                    DB::unprepared($buffer);
                    $buffer = '';
                }
                $delimiter = $m[1];
                continue;
            }
            $buffer .= $line."\n";
            $dlen = strlen($delimiter);
            $stripped = rtrim($buffer);
            if ($dlen > 0 && substr($stripped, -$dlen) === $delimiter) {
                $stmt = substr($stripped, 0, -$dlen);
                if (trim($stmt) !== '') {
                    DB::unprepared($stmt);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            DB::unprepared($buffer);
        }
    }

    /**
     * Reverse the migrations.
     *
     * The schema file is the source of truth; dropping its tables here would
     * also delete seeded registrar data, so removal is left to migrate:fresh
     * or the operators who own the database.
     */
    public function down(): void
    {
        //
    }
};
