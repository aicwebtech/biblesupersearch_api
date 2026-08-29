<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\ProcessManager;

/**
 * ProcessManager identifies a stored process by a canonical encoding of its form data, so two
 * requests that differ only in key order or pagination reuse the same Process row.
 *
 * That encoding and the hash generator are pure; the lookups around them need the database
 * and are not exercised here.
 */
class ProcessManagerTest extends TestCase
{
    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $parsing
     */
    private function processFormData(array $formData, array $parsing = []): string
    {
        $method = new \ReflectionMethod(ProcessManager::class, 'processFormData');

        return $method->invoke(new ProcessManager(), $formData, $parsing);
    }

    /**
     * Key order must not produce a different process, or an identical query submitted with
     * its fields in another order would create a duplicate row.
     */
    public function testFormDataIsSortedIntoACanonicalOrder(): void
    {
        $this->assertSame(
            $this->processFormData(['bible' => 'kjv', 'search' => 'faith']),
            $this->processFormData(['search' => 'faith', 'bible' => 'kjv'])
        );
    }

    public function testFormDataIsEncodedAsJson(): void
    {
        $encoded = $this->processFormData(['bible' => 'kjv', 'search' => 'faith']);

        $this->assertSame('{"bible":"kjv","search":"faith"}', $encoded);
    }

    public function testWithoutAParsingMapEveryFieldIsKept(): void
    {
        $encoded = $this->processFormData(['search' => 'faith', 'page' => 2]);

        $this->assertSame(['page' => 2, 'search' => 'faith'], json_decode($encoded, true));
    }

    /**
     * With a parsing map, only the fields it names survive - anything else the request
     * carried is irrelevant to identifying the process.
     */
    public function testAParsingMapRestrictsTheFieldsKept(): void
    {
        $encoded = $this->processFormData(
            ['search' => 'faith', 'bible' => 'kjv', 'irrelevant' => 'x'],
            ['search' => [], 'bible' => []]
        );

        $this->assertSame(['bible' => 'kjv', 'search' => 'faith'], json_decode($encoded, true));
    }

    /**
     * Pagination is explicitly excluded: page two of a search is the same process as page
     * one, so it must not spawn a second row.
     */
    public function testPaginationFieldsAreExcluded(): void
    {
        $encoded = $this->processFormData(
            ['search' => 'faith', 'page' => 2, 'page_all' => 1],
            ['search' => [], 'page' => [], 'page_all' => []]
        );

        $this->assertSame(['search' => 'faith'], json_decode($encoded, true));
    }

    public function testTwoPagesOfTheSameSearchEncodeIdentically(): void
    {
        $parsing = ['search' => [], 'page' => []];

        $this->assertSame(
            $this->processFormData(['search' => 'faith', 'page' => 1], $parsing),
            $this->processFormData(['search' => 'faith', 'page' => 7], $parsing)
        );
    }

    /**
     * A field named in the parsing map but absent from the request must not appear as null.
     */
    public function testFieldsMissingFromTheRequestAreOmitted(): void
    {
        $encoded = $this->processFormData(['search' => 'faith'], ['search' => [], 'bible' => []]);

        $this->assertSame(['search' => 'faith'], json_decode($encoded, true));
    }

    public function testEmptyFormDataEncodesAsAnEmptyObject(): void
    {
        $this->assertSame('[]', $this->processFormData([]));
    }

    /**
     * The hash is the public handle for a process, so its shape is a contract.
     */
    public function testGeneratedHashIsTenAlphanumericCharacters(): void
    {
        $method = new \ReflectionMethod(ProcessManager::class, '_generateHashHelper');

        $hash = $method->invoke(new ProcessManager());

        $this->assertSame(10, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $hash);
    }

    public function testGeneratedHashesDiffer(): void
    {
        $method = new \ReflectionMethod(ProcessManager::class, '_generateHashHelper');
        $manager = new ProcessManager();

        $hashes = [];

        for ($i = 0; $i < 25; $i++) {
            $hashes[] = $method->invoke($manager);
        }

        $this->assertGreaterThan(20, count(array_unique($hashes)), 'hashes should not repeat in a small sample');
    }
}
