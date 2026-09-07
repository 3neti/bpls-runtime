<?php

namespace App\References;

use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

final class NelsonTreasuryLobFeeCatalog
{
    /** @return array<string, mixed> */
    public function load(): array
    {
        $path = config('ipil_references.nelson_treasury_lob_fee_catalog.path');
        if (! is_string($path) || ! is_file($path)) {
            throw new UnexpectedValueException('The Nelson Treasury LOB preview fee catalog is unavailable.');
        }

        $contents = file_get_contents($path);
        $catalog = is_string($contents)
            ? Yaml::parse($contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE)
            : null;
        if (! is_array($catalog)
            || ($catalog['schema_version'] ?? null) !== 1
            || ($catalog['classification'] ?? null) !== 'synthetic_preview'
            || ($catalog['production_authority'] ?? null) !== false
            || ($catalog['production_catalog_status'] ?? null) !== 'awaiting_nelson_source'
            || ($catalog['currency'] ?? null) !== 'PHP') {
            throw new UnexpectedValueException('The Nelson Treasury LOB preview catalog boundary is invalid.');
        }

        $lines = $catalog['lines_of_business'] ?? null;
        if (! is_array($lines) || ! array_is_list($lines) || $lines === []) {
            throw new UnexpectedValueException('The Nelson Treasury preview catalog requires Lines of Business.');
        }
        $codes = [];
        $feeCodes = [];
        foreach ($lines as $line) {
            if (! is_array($line) || ! filled($line['code'] ?? null) || ! filled($line['name'] ?? null)) {
                throw new UnexpectedValueException('Each Treasury preview Line of Business requires a code and name.');
            }
            if (in_array($line['code'], $codes, true)) {
                throw new UnexpectedValueException('Treasury preview Line of Business codes must be unique.');
            }
            $codes[] = $line['code'];
            $items = $line['payment_items'] ?? null;
            if (! is_array($items) || ! array_is_list($items) || $items === []) {
                throw new UnexpectedValueException('Each Treasury preview Line of Business requires a default payment item.');
            }
            foreach ($items as $item) {
                if (! is_array($item)
                    || ! filled($item['code'] ?? null)
                    || ! filled($item['label'] ?? null)
                    || ! is_int($item['default_amount_minor'] ?? null)
                    || $item['default_amount_minor'] < 0
                    || in_array($item['code'], $feeCodes, true)) {
                    throw new UnexpectedValueException('Treasury preview payment items require unique identities and non-negative minor-unit defaults.');
                }
                $identity = str($item['code'].' '.$item['label'])->lower()->replace(['-', '_'], ' ')->squish()->toString();
                if (str_contains($identity, 'business tax') || str_contains($identity, 'inspection')) {
                    throw new UnexpectedValueException('Business Tax and Inspection are prohibited in the Nelson New Treasury preview catalog.');
                }
                $feeCodes[] = $item['code'];
            }
        }

        return [...$catalog, 'source_path' => $path, 'source_name' => basename($path), 'digest_sha256' => hash('sha256', (string) $contents)];
    }
}
