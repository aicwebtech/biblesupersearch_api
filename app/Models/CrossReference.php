<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CrossReference extends Model
{
    protected $table = 'cross_references';

    protected const SOURCE_FILTER_CHUNK_SIZE = 250;

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * @param array<int, array{book:int,chapter:int,verse:int}> $sourceVerses
     * @return Collection<int, self>
     */
    public static function forSourceVerses(array $sourceVerses): Collection
    {
        if(empty($sourceVerses)) {
            return new Collection();
        }

        $groupedSourceVerses = static::normalizeSourceVerses($sourceVerses);

        if(empty($groupedSourceVerses)) {
            return new Collection();
        }

        $crossReferences = new Collection();

        foreach(array_chunk($groupedSourceVerses, static::SOURCE_FILTER_CHUNK_SIZE, true) as $groupChunk) {
            $query = static::query()->where(function($outerQuery) use ($groupChunk) {
                foreach($groupChunk as $book => $chapters) {
                    foreach($chapters as $chapter => $verses) {
                        $outerQuery->orWhere(function($verseQuery) use ($book, $chapter, $verses) {
                            $verseQuery->where('from_book', $book)
                                ->where('from_chapter', $chapter)
                                ->whereIn('from_verse', $verses);
                        });
                    }
                }
            });

            $crossReferences = $crossReferences->concat(
                $query
                    ->orderBy('from_book')
                    ->orderBy('from_chapter')
                    ->orderBy('from_verse')
                    ->orderBy('to_book')
                    ->orderBy('to_chapter_start')
                    ->orderBy('to_verse_start')
                    ->orderBy('to_chapter_end')
                    ->orderBy('to_verse_end')
                    ->get()
            );
        }

        return $crossReferences
            ->sortBy([
                ['from_book', 'asc'],
                ['from_chapter', 'asc'],
                ['from_verse', 'asc'],
                ['to_book', 'asc'],
                ['to_chapter_start', 'asc'],
                ['to_verse_start', 'asc'],
                ['to_chapter_end', 'asc'],
                ['to_verse_end', 'asc'],
            ])
            ->values();
    }

    /**
     * @param array<int, array{book:int,chapter:int,verse:int}> $sourceVerses
     * @return array<int, array<int, array<int, int>>>
     */
    protected static function normalizeSourceVerses(array $sourceVerses): array
    {
        $groupedSourceVerses = [];

        foreach($sourceVerses as $sourceVerse) {
            if(!isset($sourceVerse['book'], $sourceVerse['chapter'], $sourceVerse['verse'])) {
                continue;
            }

            $book = (int) $sourceVerse['book'];
            $chapter = (int) $sourceVerse['chapter'];
            $verse = (int) $sourceVerse['verse'];

            if($book < 1 || $chapter < 1 || $verse < 1) {
                continue;
            }

            if(!isset($groupedSourceVerses[$book])) {
                $groupedSourceVerses[$book] = [];
            }

            if(!isset($groupedSourceVerses[$book][$chapter])) {
                $groupedSourceVerses[$book][$chapter] = [];
            }

            $groupedSourceVerses[$book][$chapter][$verse] = $verse;
        }

        ksort($groupedSourceVerses);

        foreach($groupedSourceVerses as &$chapters) {
            ksort($chapters);

            foreach($chapters as &$verses) {
                ksort($verses);
                $verses = array_values($verses);
            }
        }

        return $groupedSourceVerses;
    }

    /**
     * @param array<int, array{book:int,chapter:int,verse:int}> $sourceVerses
     * @return array<int, array{
     *     from_book:int,
     *     from_chapter:int,
     *     from_verse:int,
     *     cross_references: array<int, array<string, int>>
     * }>
     */
    public static function groupedForSourceVerses(array $sourceVerses): array
    {
        $crossReferences = static::forSourceVerses($sourceVerses);

        return static::groupBySourceVerses($crossReferences);
    }

    /**
     * @param Collection<int, self> $crossReferences
     * @return array<int, array{
     *     from_book:int,
     *     from_chapter:int,
     *     from_verse:int,
     *     cross_references: array<int, array<string, int>>
     * }>
     */
    public static function groupBySourceVerses(Collection $crossReferences): array
    {

        if($crossReferences->isEmpty()) {
            return [];
        }

        return $crossReferences
            ->groupBy(function(self $crossReference) {
                return $crossReference->from_book . ':' . $crossReference->from_chapter . ':' . $crossReference->from_verse;
            })
            ->mapWithKeys(function(Collection $group, $key) {
                $first = $group->first();
                $key_new = $first->from_book . '_' . $first->from_chapter . '_' . $first->from_verse;

                return [
                    $key_new => [
                        'from_book' => (int) $first->from_book,
                        'from_chapter' => (int) $first->from_chapter,
                        'from_verse' => (int) $first->from_verse,
                        'cross_references' => $group->map(function(self $crossReference) {
                            return [
                                'to_book' => (int) $crossReference->to_book,
                            'to_chapter_start' => (int) $crossReference->to_chapter_start,
                            'to_verse_start' => (int) $crossReference->to_verse_start,
                            'to_chapter_end' => (int) $crossReference->to_chapter_end,
                            'to_verse_end' => (int) $crossReference->to_verse_end,
                            'votes' => (int) $crossReference->votes,
                        ];
                    })->values()->all(),
                ]];
            })
            // ->values()
            ->all();
    }

    public static function migrateFromCsv() 
    {
        $map = [
            'id',
            'from_book',	
            'from_chapter', 
            'from_verse', 
            'to_book', 
            'to_chapter_start', 
            'to_verse_start', 
            'to_chapter_end', 
            'to_verse_end', 
            'votes'
        ];

        \App\Importers\Database::importCSV('cross_references.csv', $map, '\\' . get_called_class(), 'id', null, 6000);
    }    
}