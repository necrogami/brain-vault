<?php

declare(strict_types=1);

namespace Vault;

/**
 * Parses aCar/Fuelly multi-section CSV exports.
 *
 * The CSV contains named sections (Metadata, Vehicles, Fill-Up Records,
 * Event Records, Trip Records) separated by blank lines, each with its
 * own header row.
 */
final class AcarCsvParser
{
    private const array SECTION_NAMES = [
        'Metadata',
        'Vehicles',
        'Fill-Up Records',
        'Event Records',
        'Trip Records',
    ];

    /**
     * @return array{vehicles: list<array<string,string>>, fillups: list<array<string,string>>, events: list<array<string,string>>}
     */
    public function parse(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }

        $lines = explode("\n", $content);
        $sections = $this->splitSections($lines);

        return [
            'vehicles' => $this->parseSectionRows($sections['Vehicles'] ?? []),
            'fillups' => $this->parseSectionRows($sections['Fill-Up Records'] ?? []),
            'events' => $this->parseSectionRows($sections['Event Records'] ?? []),
        ];
    }

    /**
     * @param list<string> $lines
     *
     * @return array<string, list<string>>
     */
    private function splitSections(array $lines): array
    {
        $sections = [];
        $current = null;
        $buffer = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (in_array($trimmed, self::SECTION_NAMES, true)) {
                if ($current !== null) {
                    $sections[$current] = $buffer;
                }

                $current = $trimmed;
                $buffer = [];
            } elseif ($trimmed !== '' && $current !== null) {
                $buffer[] = $trimmed;
            }
        }

        if ($current !== null) {
            $sections[$current] = $buffer;
        }

        return $sections;
    }

    /**
     * @param list<string> $lines First line is the header row
     *
     * @return list<array<string, string>>
     */
    private function parseSectionRows(array $lines): array
    {
        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv($lines[0], ',', '"', '');
        $headerCount = count($headers);
        $rows = [];

        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $values = str_getcsv($lines[$i], ',', '"', '');

            // Pad short rows (aCar sometimes omits trailing empty fields)
            while (count($values) < $headerCount) {
                $values[] = '';
            }

            $rows[] = array_combine($headers, array_slice($values, 0, $headerCount));
        }

        return $rows;
    }
}
