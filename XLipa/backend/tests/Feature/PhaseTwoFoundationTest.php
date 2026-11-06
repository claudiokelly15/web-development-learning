<?php

namespace Tests\Feature;

use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhaseTwoFoundationTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        $pages = ['/', '/xlipahomepage.html', '/xlipasignin.html', '/xlipacreateaccount.html', '/about.html', '/contact.html', '/developers.html', '/disclaimer.html', '/privacy.html', '/prohibited-businesses.html', '/terms.html'];

        return array_combine($pages, array_map(fn (string $page): array => [$page], $pages));
    }

    #[DataProvider('pages')]
    public function test_existing_pages_render_without_reference_dependencies(string $page): void
    {
        $response = $this->get($page);

        $response->assertOk();
        $response->assertDontSee('bongopay', false);
        $response->assertDontSee('bongofey', false);
        $response->assertDontSee('{{ csrf_token() }}', false);
        $response->assertDontSee('Downloads → xlipa-system', false);

        $html = $response->getContent();
        $this->assertSame(substr_count($html, '<script'), substr_count($html, '</script>'));
    }

    public function test_reference_material_and_private_project_files_are_not_routes(): void
    {
        foreach (['/bongopay1.html', '/references/bongopay1.html.reference', '/.env', '/composer.json', '/database/mkulbzij_xlipa_site.sql'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_file_sessions_persist_between_store_instances(): void
    {
        $handler = new FileSessionHandler(app('files'), storage_path('framework/sessions'), 120);
        $id = Str::random(40);

        try {
            $first = new Store('phase-two-test', $handler, $id);
            $first->start();
            $first->put('phase_two_probe', 'local-only');
            $first->save();

            $second = new Store('phase-two-test', $handler, $id);
            $second->start();

            $this->assertSame('local-only', $second->get('phase_two_probe'));
        } finally {
            $handler->destroy($id);
        }
    }

    public function test_existing_sqlite_foundation_can_be_read_without_schema_changes(): void
    {
        $databasePath = database_path('database.sqlite');
        $beforeHash = hash_file('sha256', $databasePath);
        $database = new \SQLite3($databasePath, SQLITE3_OPEN_READONLY);
        $result = $database->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        $tables = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tables[] = $row['name'];
        }

        $database->close();

        foreach (['users', 'sessions', 'cache', 'jobs', 'migrations'] as $table) {
            $this->assertContains($table, $tables);
        }

        $this->assertSame($beforeHash, hash_file('sha256', $databasePath));
    }
}
