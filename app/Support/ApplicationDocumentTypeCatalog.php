<?php

namespace App\Support;

use DomainException;

class ApplicationDocumentTypeCatalog
{
    /** @return list<array{code: string, label: string, allows_multiple: bool}> */
    public function options(): array
    {
        $options = [];

        foreach (config('application_documents.types', []) as $code => $definition) {
            if (! is_string($code)
                || ! is_array($definition)
                || ($definition['active'] ?? false) !== true
                || ! is_string($definition['label'] ?? null)) {
                continue;
            }

            $options[] = [
                'code' => $code,
                'label' => $definition['label'],
                'allows_multiple' => ($definition['allows_multiple'] ?? false) === true,
            ];
        }

        return $options;
    }

    /** @return list<string> */
    public function activeCodes(): array
    {
        return array_column($this->options(), 'code');
    }

    /** @return array{code: string, label: string, allows_multiple: bool} */
    public function resolve(string $code): array
    {
        $option = collect($this->options())->firstWhere('code', $code);

        if (! is_array($option)) {
            throw new DomainException('The selected applicant document type is unavailable.');
        }

        return $option;
    }

    public function revision(): string
    {
        return (string) config('application_documents.catalog_revision', 'unversioned');
    }
}
