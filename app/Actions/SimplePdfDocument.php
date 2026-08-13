<?php

namespace App\Actions;

use RuntimeException;

final class SimplePdfDocument
{
    public const PageWidth = 595;

    public const PageHeight = 842;

    public const LeftMargin = 42;

    public const RightMargin = 553;

    public const ContentTop = 728;

    public const ContentBottom = 68;

    /**
     * @var array<int, array{section: string, commands: array<int, string>}>
     */
    private array $pages = [];

    public function __construct(
        private readonly string $title,
        private readonly string $documentCode,
        private readonly string $subtitle,
        private readonly string $footerNote,
    ) {}

    public function addPage(string $section = ''): int
    {
        $this->pages[] = [
            'section' => $section,
            'commands' => [],
        ];

        return count($this->pages) - 1;
    }

    public function text(
        int $page,
        string $text,
        float $x,
        float $y,
        float $size = 10,
        bool $bold = false,
        string $align = 'left',
        bool $monospace = false,
    ): void {
        $font = $monospace ? 'F3' : ($bold ? 'F2' : 'F1');
        $encoded = $this->encode($text);
        $position = match ($align) {
            'center' => $x - ($this->textWidth($text, $size, $monospace) / 2),
            'right' => $x - $this->textWidth($text, $size, $monospace),
            default => $x,
        };

        $this->command(
            $page,
            sprintf(
                '0.08 0.08 0.08 rg BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
                $font,
                $size,
                $position,
                $y,
                $encoded,
            ),
        );
    }

    public function wrappedText(
        int $page,
        string $text,
        float $x,
        float $y,
        float $width,
        float $size = 10,
        float $leading = 13,
        bool $bold = false,
        bool $monospace = false,
    ): float {
        foreach ($this->wrap($text, $width, $size, $monospace) as $line) {
            $this->text($page, $line, $x, $y, $size, $bold, monospace: $monospace);
            $y -= $leading;
        }

        return $y;
    }

    /**
     * @return array<int, string>
     */
    public function wrap(string $text, float $width, float $size = 10, bool $monospace = false): array
    {
        $characterWidth = $size * ($monospace ? 0.60 : 0.52);
        $characters = max(1, (int) floor($width / $characterWidth));
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($normalized === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $normalized) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $chunks = mb_str_split($word, $characters);

            foreach ($chunks as $chunkIndex => $chunk) {
                $candidate = $line === '' ? $chunk : $line.' '.$chunk;

                if (mb_strlen($candidate) <= $characters) {
                    $line = $candidate;

                    continue;
                }

                $lines[] = $line;
                $line = $chunk;

                if ($chunkIndex < count($chunks) - 1) {
                    $lines[] = $line;
                    $line = '';
                }
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    public function line(
        int $page,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $width = 0.7,
        float $gray = 0.25,
    ): void {
        $this->command(
            $page,
            sprintf(
                '%.2F %.2F %.2F RG %.2F w %.2F %.2F m %.2F %.2F l S',
                $gray,
                $gray,
                $gray,
                $width,
                $x1,
                $y1,
                $x2,
                $y2,
            ),
        );
    }

    public function rectangle(
        int $page,
        float $x,
        float $y,
        float $width,
        float $height,
        float $gray = 0.92,
        bool $fill = true,
    ): void {
        $operator = $fill ? 'f' : 'S';
        $color = $fill ? 'rg' : 'RG';

        $this->command(
            $page,
            sprintf(
                '%.2F %.2F %.2F %s %.2F %.2F %.2F %.2F re %s',
                $gray,
                $gray,
                $gray,
                $color,
                $x,
                $y,
                $width,
                $height,
                $operator,
            ),
        );
    }

    public function render(): string
    {
        if ($this->pages === []) {
            $this->addPage();
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
        ];
        $nextObject = 6;
        $pageObjects = [];
        $pageCount = count($this->pages);

        foreach ($this->pages as $index => $page) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageObjects[] = $pageObject;
            $resources = '<< /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >>';
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources %s /Contents %d 0 R >>',
                self::PageWidth,
                self::PageHeight,
                $resources,
                $contentObject,
            );
            $content = implode("\n", [
                $this->headerCommands($page['section']),
                ...$page['commands'],
                $this->footerCommands($index + 1, $pageCount),
            ]);
            $objects[$contentObject] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }

        $kids = collect($pageObjects)
            ->map(fn (int $object): string => "{$object} 0 R")
            ->implode(' ');
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>";
        ksort($objects);

        return $this->serialize($objects);
    }

    private function headerCommands(string $section): string
    {
        $sectionText = $section === '' ? '' : $this->encode($section);

        return implode("\n", [
            '0.12 0.30 0.18 rg 0 836 198 6 re f',
            '0.93 0.72 0.08 rg 198 836 199 6 re f',
            '0.68 0.10 0.13 rg 397 836 198 6 re f',
            '0.12 0.12 0.12 rg',
            'BT /F2 7 Tf 42 818 Td (REPUBLIC OF THE PHILIPPINES) Tj ET',
            'BT /F2 9 Tf 42 804 Td (MUNICIPALITY OF IPIL) Tj ET',
            sprintf('BT /F2 18 Tf 42 779 Td (%s) Tj ET', $this->encode($this->title)),
            sprintf('BT /F1 8 Tf 42 762 Td (%s) Tj ET', $this->encode($this->subtitle)),
            $sectionText === '' ? '' : sprintf('BT /F2 8 Tf %.2F 762 Td (%s) Tj ET', self::RightMargin - $this->textWidth($section, 8), $sectionText),
            '0.25 0.25 0.25 RG 0.8 w 42 746 m 553 746 l S',
        ]);
    }

    private function footerCommands(int $page, int $pageCount): string
    {
        $left = $this->encode($this->footerNote);
        $center = $this->encode(mb_substr($this->documentCode, 0, 48));
        $right = $this->encode("Page {$page} of {$pageCount}");

        return implode("\n", [
            '0.45 0.45 0.45 RG 0.5 w 42 54 m 553 54 l S',
            "0.30 0.30 0.30 rg BT /F1 6.2 Tf 42 40 Td ({$left}) Tj ET",
            sprintf(
                '0.30 0.30 0.30 rg BT /F1 6.2 Tf %.2F 40 Td (%s) Tj ET',
                self::RightMargin - $this->textWidth($right, 6.2),
                $right,
            ),
            sprintf(
                '0.30 0.30 0.30 rg BT /F1 6.2 Tf %.2F 28 Td (%s) Tj ET',
                (self::PageWidth / 2) - ($this->textWidth($center, 6.2) / 2),
                $center,
            ),
        ]);
    }

    private function command(int $page, string $command): void
    {
        if (! array_key_exists($page, $this->pages)) {
            throw new RuntimeException("PDF page [{$page}] does not exist.");
        }

        $this->pages[$page]['commands'][] = $command;
    }

    private function textWidth(string $text, float $size, bool $monospace = false): float
    {
        return mb_strlen($text) * $size * ($monospace ? 0.60 : 0.49);
    }

    private function encode(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        if ($encoded === false) {
            $encoded = $value;
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\(', '\)', ' ', ' '],
            $encoded,
        );
    }

    /**
     * @param  non-empty-array<int, string>  $objects
     */
    private function serialize(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= str_pad((string) ($offsets[$number] ?? 0), 10, '0', STR_PAD_LEFT).' 00000 n '.PHP_EOL;
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
