<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * MySQL limits identifiers to 64 characters, but SQLite (the
 * local test database) does not — an over-long auto-generated
 * index name once broke migration 000037 on MySQL only
 * (error 1059) while every local test stayed green. This test
 * recomputes the index and foreign-key names Laravel would
 * generate for every migration and fails when any of them
 * exceeds the MySQL limit, so the mistake cannot sneak back.
 */
class MigrationIndexNameTest extends TestCase
{
    private const MYSQL_IDENTIFIER_LIMIT = 64;

    public function test_all_migration_index_names_fit_mysql_identifier_limit(): void
    {
        $violations = [];

        foreach (glob(database_path('migrations/*.php')) as $file) {
            foreach ($this->generatedNames($file) as $name) {
                if (strlen($name) > self::MYSQL_IDENTIFIER_LIMIT) {
                    $violations[] = basename($file).': '.$name.' ('.strlen($name).' karakter)';
                }
            }
        }

        $this->assertSame([], $violations, "Nama identifier melebihi batas 64 karakter MySQL:\n".implode("\n", $violations));
    }

    /**
     * Recompute the identifier names Laravel generates inside
     * each Schema::create / Schema::table block of a migration:
     * auto-named unique/index (array and single-column forms),
     * fluent column unique/index, and foreignId constraints.
     *
     * @return string[]
     */
    private function generatedNames(string $file): array
    {
        $source = file_get_contents($file);
        $names = [];

        preg_match_all("/Schema::(?:create|table)\('([^']+)'/", $source, $blocks, PREG_OFFSET_CAPTURE);

        foreach ($blocks[0] as $i => $start) {
            $table = $blocks[1][$i][0];
            $block = substr(
                $source,
                $start[1],
                ($blocks[0][$i + 1][1] ?? strlen($source)) - $start[1],
            );

            // ->unique([...]) / ->index([...]), optional explicit name.
            preg_match_all("/->(unique|index)\(\[(.*?)\](?:\s*,\s*'([^']+)')?\)/s", $block, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                if (! empty($call[3])) {
                    $names[] = $call[3];
                    continue;
                }
                preg_match_all("/'([^']+)'/", $call[2], $cols);
                $names[] = $table.'_'.implode('_', $cols[1]).'_'.$call[1];
            }

            // ->unique('col') / ->index('col'), optional explicit name.
            preg_match_all("/->(unique|index)\('([^']+)'(?:\s*,\s*'([^']+)')?\)/", $block, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                $names[] = $call[3] ?? $table.'_'.$call[2].'_'.$call[1];
            }

            // Fluent ->string('col')->unique() / ->index().
            preg_match_all("/->string\('([^']+)'\)->(unique|index)\(\)/", $block, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                $names[] = $table.'_'.$call[1].'_'.$call[2];
            }

            // foreignId('col') yields an FK constraint name and,
            // when ->index() is chained, an index name.
            preg_match_all("/foreignId\('([^']+)'\)/", $block, $calls);
            foreach ($calls[1] as $column) {
                $names[] = $table.'_'.$column.'_foreign';
                $names[] = $table.'_'.$column.'_index';
            }

            // ->foreign('col').
            preg_match_all("/->foreign\('([^']+)'\)/", $block, $calls);
            foreach ($calls[1] as $column) {
                $names[] = $table.'_'.$column.'_foreign';
            }
        }

        return $names;
    }
}
