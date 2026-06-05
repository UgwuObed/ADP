<?php

namespace App\Console\Commands;

use App\Models\DataPlan;
use App\Models\Network;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ZipArchive;

class ImportMtnHostIfDataPlans extends Command
{
    protected $signature = 'mtn:import-data-plans
        {file : Path to the MTN HostIF data bundle .xlsx file}
        {--dry-run : Preview the import without writing to the database}
        {--cost-discount=0 : Discount percentage to apply when setting cost_price}
        {--deactivate-missing : Deactivate existing MTN data plans not present in the workbook}';

    protected $description = 'Import MTN HostIF data bundle tariff IDs into data_plans';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!is_string($file) || !is_file($file)) {
            $this->error('Workbook not found: ' . (string) $file);
            return self::FAILURE;
        }

        $plans = $this->readPlans($file);

        if ($plans === []) {
            $this->warn('No valid MTN data plans were found in the workbook.');
            return self::SUCCESS;
        }

        $discount = max(0, min(100, (float) $this->option('cost-discount')));
        $dryRun = (bool) $this->option('dry-run');
        $seenKeys = [];
        $created = 0;
        $updated = 0;
        $network = null;

        if (!$dryRun) {
            $network = Network::updateOrCreate(
                ['code' => 'mtn'],
                [
                    'name' => 'MTN',
                    'logo' => '/images/networks/mtn.png',
                    'is_active' => true,
                    'airtime_enabled' => true,
                    'data_enabled' => true,
                ]
            );
        }

        foreach ($plans as $index => $plan) {
            $costPrice = round($plan['amount'] - ($plan['amount'] * $discount / 100), 2);
            $attributes = [
                'network_id' => $network?->id,
                'data_code' => $plan['tariff_type_id'],
                'amount' => $plan['amount'],
                'name' => $plan['name'],
            ];
            $values = [
                'cost_price' => $costPrice,
                'validity' => $plan['validity'],
                'plan_type' => $plan['plan_type'],
                'description' => $plan['description'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ];

            $seenKeys[] = $this->planKey($attributes);

            if ($dryRun) {
                continue;
            }

            $existing = DataPlan::where($attributes)->first();
            DataPlan::updateOrCreate($attributes, $values);
            $existing ? $updated++ : $created++;
        }

        $deactivated = 0;
        if (!$dryRun && $this->option('deactivate-missing')) {
            $deactivated = DataPlan::where('network_id', $network->id)
                ->get()
                ->reject(fn (DataPlan $plan) => in_array($this->planKey([
                    'network_id' => $plan->network_id,
                    'data_code' => $plan->data_code,
                    'amount' => (float) $plan->amount,
                    'name' => $plan->name,
                ]), $seenKeys, true))
                ->each(fn (DataPlan $plan) => $plan->update(['is_active' => false]))
                ->count();
        }

        $this->info(($dryRun ? 'Found' : 'Imported') . ' ' . count($plans) . ' MTN data plans.');

        if (!$dryRun) {
            $this->line("Created: {$created}");
            $this->line("Updated: {$updated}");
            $this->line("Deactivated: {$deactivated}");
        }

        $this->table(
            ['Name', 'Amount', 'TariffTypeId', 'Validity', 'Type'],
            collect($plans)->take(15)->map(fn (array $plan) => [
                $plan['name'],
                number_format($plan['amount'], 2),
                $plan['tariff_type_id'],
                $plan['validity'],
                $plan['plan_type'],
            ])->all()
        );

        if (count($plans) > 15) {
            $this->line('...and ' . (count($plans) - 15) . ' more.');
        }

        return self::SUCCESS;
    }

    private function readPlans(string $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return [];
        }

        $sharedStrings = $this->sharedStrings($zip);
        $plans = [];
        $seen = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!str_starts_with($name, 'xl/worksheets/sheet') || !str_ends_with($name, '.xml')) {
                continue;
            }

            foreach ($this->rows($zip, $name, $sharedStrings) as $sheetRows) {
                $headerIndex = $this->headerIndex($sheetRows);
                if ($headerIndex === null) {
                    continue;
                }

                $headers = $this->normalizeHeaders($sheetRows[$headerIndex]);

                for ($rowIndex = $headerIndex + 1; $rowIndex < count($sheetRows); $rowIndex++) {
                    $plan = $this->planFromRow($sheetRows[$rowIndex], $headers);
                    if (!$plan) {
                        continue;
                    }

                    $key = implode('|', [
                        Str::lower($plan['name']),
                        $plan['amount'],
                        $plan['tariff_type_id'],
                        Str::lower($plan['validity']),
                    ]);

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $plans[] = $plan;
                }
            }
        }

        $zip->close();

        return collect($plans)
            ->sortBy([
                ['amount', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        $root = simplexml_load_string($xml);
        foreach ($root?->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = [];
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function rows(ZipArchive $zip, string $sheet, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheet);
        if ($xml === false) {
            return [];
        }

        $root = simplexml_load_string($xml);
        $rows = [];

        foreach ($root?->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $values = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $ref = (string) $cell['r'];
                $column = $this->columnIndex($ref);
                $values[$column] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values !== []) {
                $max = max(array_keys($values));
                $rows[] = array_map(
                    fn (int $index) => trim((string) ($values[$index] ?? '')),
                    range(0, $max)
                );
            }
        }

        return [$rows];
    }

    private function headerIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $normalized = collect($row)->map(fn (string $value) => $this->normalizeHeader($value));
            if (($normalized->contains('tarifftypeid') || $normalized->contains('tarriftypeid')) && (
                $normalized->contains('productname')
                || $normalized->contains('pname')
                || $normalized->contains('plan')
            )) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $index => $value) {
            $headers[$this->normalizeHeader($value)] = $index;
        }

        return $headers;
    }

    private function planFromRow(array $row, array $headers): ?array
    {
        $name = $this->value($row, $headers, ['productname', 'pname', 'newname', 'plan']);
        $amount = $this->parseAmount($this->value($row, $headers, ['budleprice', 'bundleprice', 'amount']));
        $tariffTypeId = $this->digits($this->value($row, $headers, ['tarifftypeid', 'tarriftypeid']));
        $validity = $this->value($row, $headers, ['validity']);

        if ($name === '' || $amount <= 0 || $tariffTypeId === '') {
            return null;
        }

        $description = $this->value($row, $headers, ['productdescription', 'description', 'comment']);
        $type = $this->value($row, $headers, ['producttype', 'plan']);

        return [
            'name' => $name,
            'amount' => $amount,
            'tariff_type_id' => $tariffTypeId,
            'validity' => $validity !== '' ? $validity : 'N/A',
            'plan_type' => $this->normalizePlanType($type),
            'description' => $description !== '' ? $description : null,
        ];
    }

    private function value(array $row, array $headers, array $names): string
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $headers)) {
                return trim((string) ($row[$headers[$name]] ?? ''));
            }
        }

        return '';
    }

    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];
        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }
            return implode('', $parts);
        }

        return $value;
    }

    private function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $match);
        $letters = $match[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function parseAmount(string $value): float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value) ?? '';
        return $clean === '' ? 0 : (float) $clean;
    }

    private function digits(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    private function normalizeHeader(string $value): string
    {
        return Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '');
    }

    private function normalizePlanType(string $value): string
    {
        $value = Str::lower(trim($value));

        return match (true) {
            str_contains($value, 'social') => 'social',
            str_contains($value, 'broadband') || str_contains($value, 'fbb') => 'broadband',
            str_contains($value, 'xtravalue') => 'xtravalue',
            str_contains($value, 'value') => 'value',
            default => 'data',
        };
    }

    private function planKey(array $plan): string
    {
        return implode('|', [
            $plan['network_id'],
            $plan['data_code'],
            (float) $plan['amount'],
            Str::lower($plan['name']),
        ]);
    }
}
