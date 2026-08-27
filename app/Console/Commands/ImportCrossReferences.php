<?php

namespace App\Console\Commands;

use App\Helpers;
use App\Models\Books\BookAbstract as Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCrossReferences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:import-cross-references
                            {file? : Path to cross_references.txt}
                            {--truncate : Truncate table before import}
                            {--chunk=1000 : Insert chunk size}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cross references from tab-delimited file into cross_references table';

    /**
     * @var array<string, int|null>
     */
    protected array $bookCache = [];

    /**
     * @var array<string, int>
     */
    protected array $bookFallbackMap = [
        'gen' => 1,
        'exod' => 2,
        'exo' => 2,
        'lev' => 3,
        'num' => 4,
        'deut' => 5,
        'josh' => 6,
        'judg' => 7,
        'ruth' => 8,
        '1sam' => 9,
        '2sam' => 10,
        '1kings' => 11,
        '2kings' => 12,
        '1chr' => 13,
        '2chr' => 14,
        'ezra' => 15,
        'neh' => 16,
        'esth' => 17,
        'job' => 18,
        'ps' => 19,
        'psalm' => 19,
        'psalms' => 19,
        'prov' => 20,
        'eccl' => 21,
        'song' => 22,
        'isa' => 23,
        'jer' => 24,
        'lam' => 25,
        'ezek' => 26,
        'dan' => 27,
        'hos' => 28,
        'joel' => 29,
        'amos' => 30,
        'obad' => 31,
        'jonah' => 32,
        'mic' => 33,
        'nah' => 34,
        'hab' => 35,
        'zeph' => 36,
        'hag' => 37,
        'zech' => 38,
        'mal' => 39,
        'matt' => 40,
        'mark' => 41,
        'luke' => 42,
        'john' => 43,
        'acts' => 44,
        'rom' => 45,
        '1cor' => 46,
        '2cor' => 47,
        'gal' => 48,
        'eph' => 49,
        'phil' => 50,
        'col' => 51,
        '1thess' => 52,
        '2thess' => 53,
        '1tim' => 54,
        '2tim' => 55,
        'titus' => 56,
        'phlm' => 57,
        'heb' => 58,
        'jas' => 59,
        '1pet' => 60,
        '2pet' => 61,
        '1john' => 62,
        '2john' => 63,
        '3john' => 64,
        'jude' => 65,
        'rev' => 66,
    ];

    public function handle(): int
    {
        $filePath = (string) ($this->argument('file') ?: base_path('bibles/misc/cross_references.txt'));
        // 11 columns per row, so the requested chunk can outrun the connection's bound-variable
        // ceiling - 999 on SQLite builds older than 3.32, 65535 on MySQL.
        $chunkSize = Helpers::getInsertChunkSize(11, NULL, max(1, (int) $this->option('chunk')));

        if(!is_file($filePath) || !is_readable($filePath)) {
            $this->error('File not found or not readable: ' . $filePath);
            return static::FAILURE;
        }

        if($this->option('truncate')) {
            DB::table('cross_references')->truncate();
            $this->info('Truncated cross_references table.');
        }

        $handle = fopen($filePath, 'r');

        if($handle === false) {
            $this->error('Unable to open file: ' . $filePath);
            return static::FAILURE;
        }

        $lineNumber = 0;
        $rowsRead = 0;
        $rowsInserted = 0;
        $rowsSkipped = 0;
        $batch = [];

        while(($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            if($line === '') {
                continue;
            }

            if(str_starts_with($line, 'From Verse')) {
                continue;
            }

            $rowsRead++;
            $columns = str_getcsv($line, "\t");

            if(!is_array($columns) || count($columns) < 2) {
                $rowsSkipped++;
                $this->warn('Skipping malformed row at line ' . $lineNumber);
                continue;
            }

            $votes = isset($columns[2]) ? (int) trim((string) $columns[2]) : 0;

            $parsed = $this->parseTokens(trim((string) $columns[0]), trim((string) $columns[1]));

            if($parsed === null) {
                $rowsSkipped++;
                continue;
            }

            $batch[] = [
                'from_book' => $parsed['from_book'],
                'from_chapter' => $parsed['from_chapter'],
                'from_verse' => $parsed['from_verse'],
                'to_book' => $parsed['to_book'],
                'to_chapter_start' => $parsed['to_chapter_start'],
                'to_verse_start' => $parsed['to_verse_start'],
                'to_chapter_end' => $parsed['to_chapter_end'],
                'to_verse_end' => $parsed['to_verse_end'],
                'votes' => $votes,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if(count($batch) >= $chunkSize) {
                DB::table('cross_references')->insert($batch);
                $rowsInserted += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if(!empty($batch)) {
            DB::table('cross_references')->insert($batch);
            $rowsInserted += count($batch);
        }

        $this->info('Cross references import complete.');
        $this->line('Rows read: ' . $rowsRead);
        $this->line('Rows inserted: ' . $rowsInserted);
        $this->line('Rows skipped: ' . $rowsSkipped);

        return static::SUCCESS;
    }

    /**
     * @return array<string, int>|null
     */
    public function parseTokens(string $fromToken, string $toToken): ?array
    {
        $from = $this->parseReferenceToken($fromToken);

        if($from === null) {
            return null;
        }

        $to = $this->parseRangeToken($toToken);

        if($to === null) {
            return null;
        }

        return [
            'from_book' => $from['book'],
            'from_chapter' => $from['chapter'],
            'from_verse' => $from['verse'],
            'to_book' => $to['book'],
            'to_chapter_start' => $to['chapter_start'],
            'to_verse_start' => $to['verse_start'],
            'to_chapter_end' => $to['chapter_end'],
            'to_verse_end' => $to['verse_end'],
        ];
    }

    /**
     * @return array{book:int,chapter:int,verse:int}|null
     */
    public function parseReferenceToken(string $token): ?array
    {
        $token = trim($token);

        if(!preg_match('/^([1-3]?[A-Za-z]+)\.(\d+)\.(\d+)$/', $token, $matches)) {
            return null;
        }

        $bookId = $this->resolveBookId($matches[1]);

        if($bookId === null) {
            return null;
        }

        return [
            'book' => $bookId,
            'chapter' => (int) $matches[2],
            'verse' => (int) $matches[3],
        ];
    }

    /**
     * @return array{book:int,chapter_start:int,verse_start:int,chapter_end:int,verse_end:int}|null
     */
    public function parseRangeToken(string $token): ?array
    {
        $token = trim($token);
        $parts = explode('-', $token);
        $startToken = trim($parts[0]);
        $endToken = trim($parts[1] ?? $parts[0]);

        $start = $this->parseReferenceToken($startToken);

        if($start === null) {
            return null;
        }

        $end = $this->parseReferenceToken($endToken);

        if($end === null) {
            return null;
        }

        if($start['book'] !== $end['book']) {
            return null;
        }

        return [
            'book' => $start['book'],
            'chapter_start' => $start['chapter'],
            'verse_start' => $start['verse'],
            'chapter_end' => $end['chapter'],
            'verse_end' => $end['verse'],
        ];
    }

    public function resolveBookId(string $bookToken): ?int
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $bookToken));

        if(array_key_exists($normalized, $this->bookCache)) {
            return $this->bookCache[$normalized];
        }

        $candidates = [
            $bookToken,
            preg_replace('/^([1-3])([A-Za-z])/', '$1 $2', $bookToken),
            strtoupper($bookToken),
        ];

        foreach($candidates as $candidate) {
            if(!$candidate) {
                continue;
            }

            $Book = Book::findByEnteredName((string) $candidate, 'en', false, true);

            if($Book) {
                $this->bookCache[$normalized] = (int) $Book->id;
                return $this->bookCache[$normalized];
            }
        }

        if(array_key_exists($normalized, $this->bookFallbackMap)) {
            $this->bookCache[$normalized] = $this->bookFallbackMap[$normalized];
            return $this->bookCache[$normalized];
        }

        $this->bookCache[$normalized] = null;
        return null;
    }
}
