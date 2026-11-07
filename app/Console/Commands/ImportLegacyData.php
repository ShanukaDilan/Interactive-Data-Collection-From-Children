<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;
use App\Services\LegacyQuestionImporter;
use App\Services\LegacyResponseImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Bring data from the original PHP app into the new database. Safe to re-run.
 */
class ImportLegacyData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quiz:import-legacy
        {--questions= : Path to the old data/questions.json}
        {--jsonl= : Path to the old data/responses.jsonl}
        {--from-db : Read answers from the old app\'s `responses` table (set LEGACY_DB_URL)}
        {--study=legacy-import : Slug of the study to import into (created if missing)}
        {--participants : Create pseudonymous participants from the old child IDs (names are stored encrypted)}
        {--dry-run : Show what would be imported, then roll everything back}';

    /**
     * @var string
     */
    protected $description = 'Import questions and answers from the original Learning Fun! PHP app';

    public function handle(LegacyQuestionImporter $questions, LegacyResponseImporter $responses): int
    {
        if (! $this->option('questions') && ! $this->option('jsonl') && ! $this->option('from-db')) {
            $this->error('Nothing to import. Pass --questions, --jsonl and/or --from-db.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $study = $this->study();
            $this->info("Importing into study “{$study->name}” ({$study->slug}).");

            if ($path = $this->option('questions')) {
                $count = $questions->importFile($this->existingFile($path), $study);
                $this->line("  Questions imported/updated: <info>{$count}</info>");
            }

            if ($path = $this->option('jsonl')) {
                $this->report('responses.jsonl', $responses->import(
                    $study,
                    LegacyResponseImporter::readJsonl($this->existingFile($path)),
                    (bool) $this->option('participants'),
                ));
            }

            if ($this->option('from-db')) {
                if (blank(config('database.connections.legacy.url')) && blank(config('database.connections.legacy.database'))) {
                    throw new RuntimeException('Set LEGACY_DB_URL to the old app\'s database URL to use --from-db.');
                }

                $this->report('old database', $responses->import(
                    $study,
                    LegacyResponseImporter::readDatabase('legacy'),
                    (bool) $this->option('participants'),
                ));
            }
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            DB::rollBack();
            $this->warn('Dry run — nothing was saved.');
        } else {
            DB::commit();
            $this->info('Import finished. Re-running is safe: existing sessions and answers are skipped.');
        }

        return self::SUCCESS;
    }

    private function study(): Study
    {
        $slug = (string) $this->option('study');

        return Study::firstOrCreate(['slug' => $slug], [
            'name' => $slug === 'legacy-import' ? 'Imported from the original app' : str($slug)->headline()->toString(),
            'description' => 'Questions and answers imported from the original Learning Fun! PHP app.',
            'owner_id' => User::role(Role::Admin->value)->orderBy('id')->value('id'),
            'is_active' => false,
        ]);
    }

    private function existingFile(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $path;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function report(string $source, array $stats): void
    {
        $this->line("  From {$source}:");
        $this->table(['', 'Count'], collect($stats)->map(fn (int $value, string $key) => [str($key)->replace('_', ' ')->ucfirst(), $value])->values()->all());

        if ($stats['invalid_rows'] > 0) {
            $this->warn("  {$stats['invalid_rows']} unreadable rows were skipped.");
        }
    }
}
