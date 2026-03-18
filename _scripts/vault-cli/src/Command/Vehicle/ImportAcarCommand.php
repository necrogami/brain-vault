<?php

declare(strict_types=1);

namespace Vault\Command\Vehicle;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vault\AcarCsvParser;
use Vault\Database;
use Vault\DocumentIndexer;
use Vault\FrontmatterParser;

#[AsCommand(name: 'vehicle:import-acar', description: 'Import aCar/Fuelly CSV export — creates/updates vehicle, monthly, and service docs')]
final class ImportAcarCommand extends Command
{
    private DocumentIndexer $indexer;

    public function __construct(
        private readonly Database $db,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
        $this->indexer = new DocumentIndexer($db);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv', InputArgument::REQUIRED, 'Path to aCar CSV export file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview what would be created without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csvPath = $input->getArgument('csv');
        $dryRun = $input->getOption('dry-run');

        if (!file_exists($csvPath)) {
            $output->writeln("<error>File not found: {$csvPath}</error>");

            return Command::FAILURE;
        }

        $output->writeln("<info>Parsing aCar export:</info> {$csvPath}");

        $parser = new AcarCsvParser();
        $data = $parser->parse($csvPath);

        if ($data['vehicles'] === []) {
            $output->writeln('<error>No vehicles found in CSV.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Found %d vehicle(s)</info>', count($data['vehicles'])));
        $output->writeln('');

        $totalFiles = 0;

        foreach ($data['vehicles'] as $vehicle) {
            $name = $vehicle['Name'];
            $fillups = array_values(array_filter($data['fillups'], fn ($f) => $f['Vehicle'] === $name));
            $events = array_values(array_filter($data['events'], fn ($e) => $e['Vehicle'] === $name));

            $totalFiles += $this->processVehicle($vehicle, $fillups, $events, $dryRun, $output);
        }

        $output->writeln('');

        if ($dryRun) {
            $output->writeln("<comment>Dry run complete. {$totalFiles} file(s) would be written.</comment>");
        } else {
            $output->writeln("<info>Done. {$totalFiles} file(s) written and indexed.</info>");
        }

        return Command::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────
    //  Core processing
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param array<string,string>       $vehicle
     * @param list<array<string,string>> $fillups
     * @param list<array<string,string>> $events
     */
    private function processVehicle(array $vehicle, array $fillups, array $events, bool $dryRun, OutputInterface $output): int
    {
        $name = $vehicle['Name'];
        $year = $vehicle['Year'];
        $make = $vehicle['Make'];
        $model = $vehicle['Model'];
        $submodel = $vehicle['SubModel'];
        $shortSlug = $this->slugify($name);

        $output->writeln(sprintf('<info>[%s]</info> %s %s %s %s', $name, $year, $make, $model, $submodel));

        // Sort chronologically
        usort($fillups, fn ($a, $b) => $this->parseDate($a['Date']) <=> $this->parseDate($b['Date']));
        usort($events, fn ($a, $b) => $this->parseDate($a['Date']) <=> $this->parseDate($b['Date']));

        // Find or assign vehicle ID
        $existing = $this->findExisting('vehicle', ['acar_name' => $name]);
        $vehicleId = $existing['id'] ?? $this->generateId($this->slugify("{$year}-{$make}-{$model}-{$submodel}"));
        $isNew = $existing === null;

        // Latest odometer across all records
        $latestOdo = $this->latestOdometer($fillups, $events);

        // Group fill-ups by month
        $monthlyFillups = [];

        foreach ($fillups as $f) {
            $monthlyFillups[$this->parseDate($f['Date'])->format('Y-m')][] = $f;
        }

        // Group events by month (for embedding in monthly docs)
        $monthlyEvents = [];

        foreach ($events as $e) {
            $monthlyEvents[$this->parseDate($e['Date'])->format('Y-m')][] = $e;
        }

        // ── Monthly docs ──
        $monthlyIds = [];
        $monthlyCreated = 0;
        $monthlyUpdated = 0;

        foreach ($monthlyFillups as $month => $mFillups) {
            $monthId = $this->monthlyId($shortSlug, $month);
            $existed = $this->docExists($monthId);
            $mEvents = $monthlyEvents[$month] ?? [];

            if (!$dryRun) {
                $this->writeMonthlyDoc($monthId, $vehicleId, $month, $mFillups, $mEvents, $vehicle);
            }

            $monthlyIds[] = $monthId;
            $existed ? $monthlyUpdated++ : $monthlyCreated++;
        }

        // ── Service docs ──
        $serviceIds = [];
        $serviceCreated = 0;
        $serviceUpdated = 0;

        foreach ($events as $event) {
            $serviceDate = $this->parseDate($event['Date'])->format('Y-m-d');
            $existingSvc = $this->findExisting('vehicle-service', [
                'vehicle' => $vehicleId,
                'service_date' => $serviceDate,
            ]);

            $serviceId = $existingSvc['id'] ?? $this->serviceId($shortSlug, $event);
            $existed = $existingSvc !== null;

            if (!$dryRun) {
                $this->writeServiceDoc($serviceId, $vehicleId, $event, $vehicle, $shortSlug);
            }

            $serviceIds[] = $serviceId;
            $existed ? $serviceUpdated++ : $serviceCreated++;
        }

        // ── Vehicle master doc ──
        if (!$dryRun) {
            $this->writeVehicleDoc(
                $vehicleId,
                $vehicle,
                $latestOdo,
                $monthlyIds,
                $serviceIds,
                $fillups,
                $events,
                $shortSlug,
            );
        }

        // ── Report ──
        $output->writeln(sprintf('  Vehicle doc: %s → %s', $isNew ? 'created' : 'updated', $vehicleId));
        $output->writeln(sprintf('  Monthly docs: %d created, %d updated', $monthlyCreated, $monthlyUpdated));
        $output->writeln(sprintf('  Service docs: %d created, %d updated', $serviceCreated, $serviceUpdated));

        if ($fillups !== []) {
            $first = $this->parseDate($fillups[0]['Date'])->format('Y-m');
            $last = $this->parseDate(end($fillups)['Date'])->format('Y-m');
            $output->writeln(sprintf('  Fill-ups: %d (%s → %s)', count($fillups), $first, $last));
        }

        $output->writeln(sprintf('  Current odometer: %s mi', number_format($latestOdo)));

        return 1 + count($monthlyIds) + count($serviceIds);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Document writers
    // ──────────────────────────────────────────────────────────────────

    private function writeVehicleDoc(
        string $id,
        array $vehicle,
        float $latestOdo,
        array $monthlyIds,
        array $serviceIds,
        array $fillups,
        array $events,
        string $shortSlug,
    ): void {
        $year = $vehicle['Year'];
        $make = $vehicle['Make'];
        $model = $vehicle['Model'];
        $submodel = $vehicle['SubModel'];
        $title = trim("{$year} {$make} {$model}" . ($submodel ? " {$submodel}" : ''));

        $fuelType = $this->detectFuelType($vehicle, $fillups);
        $tankCap = $this->parseNumber($vehicle['Fuel Tank Capacity']);
        $tankStr = $tankCap > 0 ? sprintf('%.1f', $tankCap) : 'unknown';

        $children = array_merge(
            array_map(fn ($sid) => "\"{$sid}\"", $serviceIds),
            array_map(fn ($mid) => "\"{$mid}\"", $monthlyIds),
        );
        sort($children);
        $childrenYaml = $this->yamlArray($children);

        $tags = $this->yamlInlineArray(['vehicle', $this->slugify($fuelType), $this->slugify($make)]);

        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
        $existingCreated = $this->getExistingCreated($id) ?? $now;

        $licensePlate = trim($vehicle['License Plate'] ?? '');
        $insurance = trim($vehicle['Insurance Policy'] ?? '');

        $frontmatter = <<<YAML
---
id: "{$id}"
title: "{$title}"
domain: hobbies
subdomain: automotive
status: growing
created: {$existingCreated}
modified: {$now}
tags: {$tags}
priority: p3-low

links:
  parent: []
  children: {$childrenYaml}
  related: []
  inspired_by: []
  blocks: []
  blocked_by: []
  evolved_into: []

sources: []

confidence: high
effort: null
revisit_date: null
close_reason: null
summary: "{$title} — {$fuelType}, fuel and maintenance tracking"

meta:
  type: vehicle
  acar_name: "{$vehicle['Name']}"
  year: {$year}
  make: "{$make}"
  model: "{$model}"
  submodel: "{$submodel}"
  engine: "{$vehicle['Engine']}"
  transmission: "{$vehicle['Transmission']}"
  drive_type: "{$vehicle['Drive Type']}"
  body_type: "{$vehicle['Body Type']}"
  fuel_type: "{$fuelType}"
  tank_capacity_gal: {$tankStr}
  current_odometer: {$this->intStr($latestOdo)}
  license_plate: {$this->yamlScalar($licensePlate)}
  insurance: {$this->yamlScalar($insurance)}

todos: []
---
YAML;

        // ── Body ──
        $body = "## Vehicle Details\n\n";
        $body .= "- **Year/Make/Model:** {$title}\n";
        $body .= "- **Engine:** {$vehicle['Engine']}\n";
        $body .= "- **Transmission:** {$vehicle['Transmission']}\n";
        $body .= "- **Drivetrain:** {$vehicle['Drive Type']}\n";
        $body .= "- **Fuel:** {$fuelType} ({$tankStr} gal tank)\n";

        if ($licensePlate !== '') {
            $body .= "- **Plate:** {$licensePlate}\n";
        }

        $body .= sprintf("- **Current Odometer:** %s mi\n", number_format($latestOdo));

        // Service history
        if ($events !== []) {
            $body .= "\n## Service History\n\n";

            foreach ($events as $e) {
                $d = $this->parseDate($e['Date'])->format('Y-m-d');
                $odo = $this->parseNumber($e['Odometer Reading']);
                $cost = $this->parseNumber($e['Total Cost']);
                $subs = $e['Sub Types'] ?? '';
                $provider = trim($e['Place Name'] ?? '');
                $providerStr = $provider !== '' ? " @ {$provider}" : '';
                $svcId = $this->findServiceId($serviceIds, $d, $shortSlug, $e);
                $link = $this->mdLink($id, $svcId, $svcId);
                $body .= sprintf(
                    "- %s — %s (%s mi): %s — \$%s%s\n",
                    $link,
                    $d,
                    number_format($odo),
                    $subs,
                    number_format($cost, 2),
                    $providerStr,
                );
            }
        }

        // Monthly index
        $body .= "\n## Monthly Status\n\n";
        $sortedMonthly = $monthlyIds;
        sort($sortedMonthly);

        foreach (array_reverse($sortedMonthly) as $mid) {
            $month = substr($mid, -7);
            $link = $this->mdLink($id, $mid, $mid);
            $body .= "- {$link} — {$month}\n";
        }

        $notes = $this->preserveNotes($id);
        $content = $frontmatter . "\n" . $body . $notes;

        $this->writeAndIndex($id, $content);
    }

    private function writeMonthlyDoc(
        string $id,
        string $vehicleId,
        string $month,
        array $fillups,
        array $monthEvents,
        array $vehicle,
    ): void {
        $name = $vehicle['Name'];
        $make = $vehicle['Make'];
        $title = ucfirst($name) . " — {$month} Status";

        $tags = $this->yamlInlineArray(['vehicle', 'fuel-log', $this->slugify($make)]);

        // Aggregates
        $totalGal = 0.0;
        $totalCost = 0.0;
        $mpgValues = [];
        $mpgWeights = [];
        $odometers = [];

        foreach ($fillups as $f) {
            $gal = $this->parseNumber($f['Volume']);
            $cost = $this->parseNumber($f['Total Cost']);
            $mpg = $this->parseNumber($f['Fuel Efficiency']);
            $odo = $this->parseNumber($f['Odometer Reading']);

            $totalGal += $gal;
            $totalCost += $cost;
            $odometers[] = $odo;

            if ($mpg > 0) {
                $mpgValues[] = $mpg;
                $mpgWeights[] = $gal;
            }
        }

        $minOdo = $odometers !== [] ? min($odometers) : 0;
        $maxOdo = $odometers !== [] ? max($odometers) : 0;
        $miles = $maxOdo - $minOdo;
        $avgPpg = $totalGal > 0 ? $totalCost / $totalGal : 0;
        $avgMpg = 0.0;

        if ($mpgWeights !== []) {
            $weightedSum = 0;
            $weightTotal = array_sum($mpgWeights);

            foreach ($mpgValues as $i => $v) {
                $weightedSum += $v * $mpgWeights[$i];
            }

            $avgMpg = $weightTotal > 0 ? $weightedSum / $weightTotal : 0;
        }

        $costPerMile = $miles > 0 ? $totalCost / $miles : 0;
        $fillupCount = count($fillups);

        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
        $existingCreated = $this->getExistingCreated($id) ?? "{$month}-01T00:00:00";

        $frontmatter = <<<YAML
---
id: "{$id}"
title: "{$title}"
domain: hobbies
subdomain: automotive
status: mature
created: {$existingCreated}
modified: {$now}
tags: {$tags}
priority: p3-low

links:
  parent: ["{$vehicleId}"]
  children: []
  related: []
  inspired_by: []
  blocks: []
  blocked_by: []
  evolved_into: []

sources: []

confidence: high
effort: null
revisit_date: null
close_reason: null
summary: "{$title} — {$this->intStr($miles)} mi, {$this->fmtNum($totalGal)} gal, \${$this->fmtNum($totalCost)}"

meta:
  type: vehicle-monthly
  vehicle: "{$vehicleId}"
  month: "{$month}"
  start_odometer: {$this->intStr($minOdo)}
  end_odometer: {$this->intStr($maxOdo)}
  miles_driven: {$this->intStr($miles)}
  fill_ups: {$fillupCount}
  total_gallons: {$this->fmtNum($totalGal)}
  total_fuel_cost: {$this->fmtNum($totalCost)}
  avg_price_per_gal: {$this->fmtNum($avgPpg)}
  avg_mpg: {$this->fmtNum($avgMpg)}
  cost_per_mile: {$this->fmtNum($costPerMile)}

todos: []
---
YAML;

        // Body
        $body = "## Summary\n\n";
        $body .= "| Metric | Value |\n";
        $body .= "|--------|-------|\n";
        $body .= sprintf("| Odometer | %s → %s mi |\n", number_format($minOdo), number_format($maxOdo));
        $body .= sprintf("| Miles | ~%s |\n", number_format($miles));
        $body .= sprintf("| Fill-Ups | %d |\n", count($fillups));
        $body .= sprintf("| Gallons | %s |\n", $this->fmtNum($totalGal));
        $body .= sprintf("| Fuel Cost | \$%s |\n", number_format($totalCost, 2));
        $body .= sprintf("| Avg \$/gal | \$%s |\n", number_format($avgPpg, 3));

        if ($avgMpg > 0) {
            $body .= sprintf("| Avg MPG | %s |\n", $this->fmtNum($avgMpg));
        }

        if ($costPerMile > 0) {
            $body .= sprintf("| Cost/Mile | \$%s |\n", number_format($costPerMile, 2));
        }

        // Fill-up table
        $body .= "\n## Fill-Ups\n\n";
        $body .= "| Date | Odometer | Gal | \$/gal | Total | MPG | P | Station | Location |\n";
        $body .= "|------|----------|-----|-------|-------|-----|---|---------|----------|\n";

        foreach (array_reverse($fillups) as $f) {
            $d = $this->parseDate($f['Date'])->format('n/j');
            $odo = number_format($this->parseNumber($f['Odometer Reading']));
            $gal = $this->fmtNum($this->parseNumber($f['Volume']));
            $ppg = '$' . number_format($this->parseNumber($f['Price per Unit']), 3);
            $tot = '$' . number_format($this->parseNumber($f['Total Cost']), 2);
            $mpg = $this->parseNumber($f['Fuel Efficiency']);
            $mpgStr = $mpg > 0 ? $this->fmtNum($mpg) : '—';
            $partial = $f['Partial Fill-Up?'] === 'Yes' ? '✓' : '';
            $station = trim($f['Fuel Brand'] ?? '');
            $city = trim($f['Place City'] ?? '');
            $state = trim($f['Place State'] ?? '');
            $loc = $city !== '' ? ($state !== '' ? "{$city}, {$state}" : $city) : '';

            $body .= "| {$d} | {$odo} | {$gal} | {$ppg} | {$tot} | {$mpgStr} | {$partial} | {$station} | {$loc} |\n";
        }

        // Services in this month
        if ($monthEvents !== []) {
            $body .= "\n## Services\n\n";

            foreach ($monthEvents as $e) {
                $d = $this->parseDate($e['Date'])->format('n/j');
                $subs = $e['Sub Types'] ?? '';
                $cost = $this->parseNumber($e['Total Cost']);
                $provider = trim($e['Place Name'] ?? '');
                $providerStr = $provider !== '' ? " @ {$provider}" : '';
                $body .= sprintf("- **%s:** %s — \$%s%s\n", $d, $subs, number_format($cost, 2), $providerStr);
            }
        }

        $notes = $this->preserveNotes($id);
        $content = $frontmatter . "\n" . $body . $notes;

        $this->writeAndIndex($id, $content);
    }

    private function writeServiceDoc(
        string $id,
        string $vehicleId,
        array $event,
        array $vehicle,
        string $shortSlug,
    ): void {
        $name = $vehicle['Name'];
        $make = $vehicle['Make'];
        $date = $this->parseDate($event['Date']);
        $dateStr = $date->format('Y-m-d');
        $subs = trim($event['Sub Types'] ?? 'Service');
        $title = ucfirst($name) . " — {$dateStr}: {$subs}";
        $odo = $this->parseNumber($event['Odometer Reading']);
        $cost = $this->parseNumber($event['Total Cost']);
        $payment = trim($event['Payment'] ?? '');
        $provider = trim($event['Place Name'] ?? '');
        $address = trim($event['Place Address'] ?? '');
        $city = trim($event['Place City'] ?? '');
        $state = trim($event['Place State'] ?? '');

        $tags = $this->yamlInlineArray(['vehicle', 'maintenance', $this->slugify($make)]);

        $subtypes = array_map('trim', explode(',', $subs));
        $subtypesYaml = $this->yamlInlineArray($subtypes);

        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
        $existingCreated = $this->getExistingCreated($id) ?? $date->format('Y-m-d\TH:i:s');

        // Find the monthly doc for this service's month (for related link)
        $monthKey = $date->format('Y-m');
        $monthlyId = $this->monthlyId($shortSlug, $monthKey);
        $relatedYaml = $this->docExists($monthlyId) ? "[\"$monthlyId\"]" : '[]';

        $frontmatter = <<<YAML
---
id: "{$id}"
title: "{$title}"
domain: hobbies
subdomain: automotive
status: mature
created: {$existingCreated}
modified: {$now}
tags: {$tags}
priority: p3-low

links:
  parent: ["{$vehicleId}"]
  children: []
  related: {$relatedYaml}
  inspired_by: []
  blocks: []
  blocked_by: []
  evolved_into: []

sources: []

confidence: high
effort: null
revisit_date: null
close_reason: null
summary: "{$dateStr}: {$subs} at {$this->intStr($odo)} mi — \${$this->fmtNum($cost)}"

meta:
  type: vehicle-service
  vehicle: "{$vehicleId}"
  service_date: "{$dateStr}"
  odometer: {$this->intStr($odo)}
  service_types: {$subtypesYaml}
  total_cost: {$this->fmtNum($cost)}
  provider: {$this->yamlScalar($provider)}
  payment: {$this->yamlScalar($payment)}

todos: []
---
YAML;

        $body = "## Service Details\n\n";
        $body .= "- **Date:** {$dateStr}\n";
        $body .= sprintf("- **Odometer:** %s mi\n", number_format($odo));
        $body .= "- **Work:** {$subs}\n";
        $body .= sprintf("- **Cost:** \$%s\n", number_format($cost, 2));

        if ($provider !== '') {
            $body .= "- **Provider:** {$provider}\n";
        }

        if ($address !== '') {
            $body .= "- **Address:** {$address}\n";
        } elseif ($city !== '') {
            $loc = $state !== '' ? "{$city}, {$state}" : $city;
            $body .= "- **Location:** {$loc}\n";
        }

        if ($payment !== '') {
            $body .= "- **Payment:** {$payment}\n";
        }

        $notes = $this->preserveNotes($id);
        $content = $frontmatter . "\n" . $body . $notes;

        $this->writeAndIndex($id, $content);
    }

    // ──────────────────────────────────────────────────────────────────
    //  File I/O and DB indexing
    // ──────────────────────────────────────────────────────────────────

    private function writeAndIndex(string $id, string $content): void
    {
        $filePath = $this->filePath($id);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, $content);

        // Parse back and index
        $parser = new FrontmatterParser();
        $parsed = $parser->parse($filePath);

        if ($parsed !== null) {
            $relativePath = ltrim(str_replace($this->projectRoot, '', $filePath), '/');
            $this->indexer->index($parsed['frontmatter'], $relativePath);
        }
    }

    /**
     * Build the absolute file path for a document ID.
     * Extracts YYYY/MM from the ID prefix for the directory structure.
     */
    private function filePath(string $id): string
    {
        $year = substr($id, 0, 4);
        $month = substr($id, 4, 2);

        return "{$this->projectRoot}/vault/hobbies/automotive/{$year}/{$month}/{$id}.md";
    }

    /**
     * Build a relative markdown link from one doc to another.
     * Both live under vault/hobbies/automotive/YYYY/MM/, so the
     * relative path is always ../../{targetYYYY}/{targetMM}/{targetId}.md
     *
     * @param string $fromId Source document ID (determines current directory)
     * @param string $toId   Target document ID
     * @param string $label  Link text
     */
    private function mdLink(string $fromId, string $toId, string $label): string
    {
        $fromYear = substr($fromId, 0, 4);
        $fromMonth = substr($fromId, 4, 2);
        $toYear = substr($toId, 0, 4);
        $toMonth = substr($toId, 4, 2);

        if ($fromYear === $toYear && $fromMonth === $toMonth) {
            return "[{$label}]({$toId}.md)";
        }

        return "[{$label}](../../{$toYear}/{$toMonth}/{$toId}.md)";
    }

    /**
     * Preserve any manually-written ## Notes section from an existing file.
     */
    private function preserveNotes(string $id): string
    {
        $path = $this->filePath($id);

        if (!file_exists($path)) {
            return "\n## Notes\n";
        }

        $content = file_get_contents($path);

        if ($content !== false && preg_match('/\n(## Notes\n.*)$/s', $content, $m)) {
            $notes = rtrim($m[1]);

            if ($notes !== '## Notes') {
                return "\n" . $notes . "\n";
            }
        }

        return "\n## Notes\n";
    }

    // ──────────────────────────────────────────────────────────────────
    //  DB lookups
    // ──────────────────────────────────────────────────────────────────

    /**
     * Find an existing document by meta type and matching fields.
     *
     * @return array{id: string, file_path: string}|null
     */
    private function findExisting(string $type, array $match): ?array
    {
        $sql = "SELECT id, file_path FROM documents WHERE json_extract(meta, '$.type') = :type";
        $params = [':type' => $type];
        $i = 0;

        foreach ($match as $key => $value) {
            $param = ":v{$i}";
            $sql .= " AND json_extract(meta, '\$.{$key}') = {$param}";
            $params[$param] = $value;
            $i++;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetchOne($sql, $params);
    }

    private function docExists(string $id): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM documents WHERE id = :id',
            [':id' => $id],
        ) !== null;
    }

    private function getExistingCreated(string $id): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT created_at FROM documents WHERE id = :id',
            [':id' => $id],
        );

        return $row['created_at'] ?? null;
    }

    // ──────────────────────────────────────────────────────────────────
    //  ID generation
    // ──────────────────────────────────────────────────────────────────

    private function generateId(string $slug): string
    {
        return (new \DateTimeImmutable())->format('Ymd-His') . '-' . $slug;
    }

    /**
     * Deterministic monthly doc ID: YYYYMM01-000000-{slug}-YYYY-MM
     */
    private function monthlyId(string $shortSlug, string $month): string
    {
        $parts = explode('-', $month);

        return "{$parts[0]}{$parts[1]}01-000000-{$shortSlug}-{$month}";
    }

    /**
     * Deterministic service doc ID based on service date/time.
     */
    private function serviceId(string $shortSlug, array $event): string
    {
        $date = $this->parseDate($event['Date']);
        $time = $event['Time'] ?? '00:00';
        $timeParts = explode(':', $time);
        $hh = str_pad($timeParts[0] ?? '0', 2, '0', STR_PAD_LEFT);
        $mm = str_pad($timeParts[1] ?? '0', 2, '0', STR_PAD_LEFT);

        return $date->format('Ymd') . "-{$hh}{$mm}00-{$shortSlug}-service";
    }

    /**
     * Find the service doc ID that matches a given date.
     */
    private function findServiceId(array $serviceIds, string $dateStr, string $shortSlug, array $event): string
    {
        $candidate = $this->serviceId($shortSlug, $event);

        if (in_array($candidate, $serviceIds, true)) {
            return $candidate;
        }

        // Fallback: find by date prefix
        $prefix = str_replace('-', '', $dateStr);

        foreach ($serviceIds as $sid) {
            if (str_starts_with($sid, $prefix)) {
                return $sid;
            }
        }

        return $candidate;
    }

    // ──────────────────────────────────────────────────────────────────
    //  Parsing helpers
    // ──────────────────────────────────────────────────────────────────

    private function parseDate(string $dateStr): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('n/j/Y', trim($dateStr));

        if ($d === false) {
            $d = \DateTimeImmutable::createFromFormat('m/d/Y', trim($dateStr));
        }

        if ($d === false) {
            throw new \RuntimeException("Cannot parse date: {$dateStr}");
        }

        return $d;
    }

    private function parseNumber(string $value): float
    {
        $cleaned = str_replace(['$', ','], '', trim($value));

        return $cleaned !== '' ? (float) $cleaned : 0.0;
    }

    private function slugify(string $text, int $maxLength = 60): string
    {
        $slug = strtolower(trim($text));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return substr($slug, 0, $maxLength);
    }

    private function detectFuelType(array $vehicle, array $fillups): string
    {
        foreach ($fillups as $f) {
            $cat = $f['Fuel Category'] ?? '';

            if (stripos($cat, 'Diesel') !== false) {
                return 'Diesel';
            }

            if (stripos($cat, 'Gasoline') !== false) {
                return 'Gasoline';
            }
        }

        $engine = strtolower($vehicle['Engine'] ?? '');

        if (str_contains($engine, 'diesel')) {
            return 'Diesel';
        }

        return 'Gasoline';
    }

    private function latestOdometer(array $fillups, array $events): float
    {
        $max = 0.0;

        foreach ($fillups as $f) {
            $odo = $this->parseNumber($f['Odometer Reading']);

            if ($odo > $max) {
                $max = $odo;
            }
        }

        foreach ($events as $e) {
            $odo = $this->parseNumber($e['Odometer Reading']);

            if ($odo > $max) {
                $max = $odo;
            }
        }

        return $max;
    }

    // ──────────────────────────────────────────────────────────────────
    //  YAML formatting helpers
    // ──────────────────────────────────────────────────────────────────

    private function yamlScalar(string $value): string
    {
        if ($value === '') {
            return 'null';
        }

        return "\"{$value}\"";
    }

    /**
     * @param list<string> $items
     */
    private function yamlInlineArray(array $items): string
    {
        $quoted = array_map(fn ($v) => $this->slugify($v) !== '' ? $v : null, $items);
        $quoted = array_filter($quoted, fn ($v) => $v !== null);

        return '[' . implode(', ', $quoted) . ']';
    }

    /**
     * @param list<string> $items Items that may already be quoted
     */
    private function yamlArray(array $items): string
    {
        if ($items === []) {
            return '[]';
        }

        return '[' . implode(', ', $items) . ']';
    }

    private function intStr(float $value): string
    {
        return (string) (int) $value;
    }

    private function fmtNum(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');
    }
}
