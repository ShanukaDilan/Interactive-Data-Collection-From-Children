<?php

namespace App\Http\Controllers;

use App\Models\Study;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Exports\Codebook;
use App\Services\Exports\ResponseExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Downloads of response data, using the same filters as the analytics page.
 * Files are written to a temporary file first (memory stays flat on large studies), then sent.
 */
class ExportController extends Controller
{
    public const FILES = ['responses.csv', 'taps.csv', 'codebook.csv', 'legacy.csv', 'workbook.xlsx'];

    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        Gate::authorize('export-responses');

        $user = $request->user();
        $filters = AnalyticsFilters::fromArray($request->query());
        $studyIds = $user->accessibleStudies()->pluck('id');

        if ($filters->studyId !== null) {
            abort_unless($studyIds->contains($filters->studyId), 404);
        }

        $exporter = new ResponseExporter($studyIds, $filters);
        [$name, $extension] = explode('.', $file);
        $path = storage_path('app/private/exports/'.Str::uuid().'.'.$extension);
        @mkdir(dirname($path), 0775, true);

        try {
            $writer = SimpleExcelWriter::create($path);
            $counts = match ($name) {
                'responses' => ['responses' => self::write($writer, array_keys(Codebook::responses()), $exporter->responses())],
                'taps' => ['taps' => self::write($writer, array_keys(Codebook::taps()), $exporter->taps())],
                'codebook' => ['codebook' => self::write($writer, ['file', 'column', 'type', 'description', 'values'], Codebook::rows())],
                'legacy' => ['responses' => self::write($writer, Codebook::LEGACY_HEADER, $exporter->legacy())],
                'workbook' => $this->workbook($writer, $exporter, $filters, $user->name),
            };
            $writer->close();
        } catch (Throwable $e) {
            @unlink($path); // never leave a half-written file of child data behind

            throw $e;
        }

        activity('export')
            ->causedBy($user)
            ->withProperties(['file' => $file, 'filters' => array_filter(get_object_vars($filters)), 'rows' => $counts])
            ->log('exported');

        return response()
            ->download($path, $this->downloadName($name, $extension, $filters), [
                'Cache-Control' => 'no-store, private',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<array<array-key, mixed>>  $rows
     */
    private static function write(SimpleExcelWriter $writer, array $header, iterable $rows): int
    {
        $writer->addHeader($header);
        $count = 0;

        foreach ($rows as $row) {
            $writer->addRow(array_values($row));
            $count++;
        }

        return $count;
    }

    /**
     * One Excel file with every table plus the codebook and a description of the filters.
     *
     * @return array<string, int>
     */
    private function workbook(SimpleExcelWriter $writer, ResponseExporter $exporter, AnalyticsFilters $filters, string $userName): array
    {
        $writer->setHeaderStyle((new Style)->setFontBold());

        $writer->nameCurrentSheet('Responses');
        $counts = ['responses' => self::write($writer, array_keys(Codebook::responses()), $exporter->responses())];

        $writer->addNewSheetAndMakeItCurrent('Taps');
        $counts['taps'] = self::write($writer, array_keys(Codebook::taps()), $exporter->taps());

        $writer->addNewSheetAndMakeItCurrent('Codebook');
        self::write($writer, ['file', 'column', 'type', 'description', 'values'], Codebook::rows());

        $writer->addNewSheetAndMakeItCurrent('About');
        self::write($writer, ['item', 'value'], array_map(ResponseExporter::clean(...), [
            ['Exported', now()->format('Y-m-d H:i:s').' ('.config('app.timezone').')'],
            ['Exported by', $userName],
            ['Filters', $this->describeFilters($filters)],
            ['Answers', $counts['responses']],
            ['Taps', $counts['taps']],
            ['Accuracy', 'Use first_try_correct. In Find the Answer is_correct is always 1, because children keep trying until they are right.'],
            ['Positions', 'question_position, selected_position and screen_position start at 1.'],
            ['Privacy', 'Children appear only by pseudonymous code. Keep this file secure and delete it when no longer needed, as required by the study\'s ethics approval.'],
        ]));

        return $counts;
    }

    private function describeFilters(AnalyticsFilters $filters): string
    {
        $parts = array_filter([
            $filters->studyId ? 'study: '.Study::whereKey($filters->studyId)->value('name') : 'all accessible studies',
            $filters->category ? 'category: '.$filters->category : null,
            $filters->age ? 'age: '.$filters->age : null,
            $filters->mode ? 'mode: '.($filters->mode === 'free' ? 'Free Choice' : 'Find the Answer') : null,
            $filters->from ? 'from: '.$filters->from->toDateString() : null,
            $filters->to ? 'to: '.$filters->to->toDateString() : null,
        ]);

        return implode('; ', $parts);
    }

    private function downloadName(string $name, string $extension, AnalyticsFilters $filters): string
    {
        $study = $filters->studyId ? Study::whereKey($filters->studyId)->value('slug') : 'all-studies';

        return sprintf('learning-fun_%s_%s_%s.%s', $study, $name, now()->format('Ymd_His'), $extension);
    }
}
