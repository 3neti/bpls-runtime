<?php

namespace App\LifecycleScenarios;

final class ScenarioSummaryRenderer
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function html(array $manifest): string
    {
        $steps = collect($manifest['steps'] ?? [])
            ->map(fn (array $step): string => '<tr><td>'.e($step['key'] ?? '').'</td><td>'.e($step['action'] ?? '').'</td><td>'.e($step['passed'] ? 'passed' : 'failed').'</td></tr>')
            ->implode("\n");
        $screenshots = collect($manifest['artifacts']['screenshots'] ?? [])
            ->map(fn (string $path, string $label): string => '<figure><img src="'.e($path).'" alt="'.e($label).'"><figcaption>'.e($label).'</figcaption></figure>')
            ->implode("\n");

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>'.e($manifest['scenario']['label']).'</title>
<style>
body{font-family:Arial,sans-serif;margin:32px;color:#18181b;background:#fff}
h1{font-size:24px;margin:0 0 8px}
h2{font-size:16px;margin-top:28px}
.result{display:inline-block;padding:4px 8px;border-radius:4px;background:#ecfdf3;color:#166534;font-weight:700}
table{border-collapse:collapse;width:100%;margin-top:12px}
td,th{border:1px solid #d4d4d8;padding:8px;text-align:left;vertical-align:top}
figure{margin:16px 0}
img{max-width:100%;border:1px solid #d4d4d8}
code{background:#f4f4f5;padding:2px 4px;border-radius:3px}
</style>
</head>
<body>
<h1>'.e($manifest['scenario']['label']).'</h1>
<p class="result">'.e(($manifest['result']['passed'] ?? false) ? 'PASSED' : 'FAILED').'</p>
<p>Run ID: <code>'.e($manifest['run_id']).'</code></p>
<p>Environment: '.e($manifest['environment']).'</p>
<h2>Resource</h2>
<p>'.e($manifest['resources']['record_type'] ?? '').' #'.e((string) ($manifest['resources']['record_id'] ?? '')).' - '.e($manifest['resources']['public_reference'] ?? '').'</p>
<h2>Actors</h2>
<pre>'.e(json_encode($manifest['actors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</pre>
<h2>Checks</h2>
<table><thead><tr><th>Key</th><th>Action</th><th>Result</th></tr></thead><tbody>'.$steps.'</tbody></table>
<h2>Screenshot Storyboard</h2>
'.$screenshots.'
<h2>Raw Artifacts</h2>
<ul>
<li><a href="manifest.json">manifest.json</a></li>
<li><a href="terminal/prepare.json">terminal/prepare.json</a></li>
<li><a href="terminal/audit.json">terminal/audit.json</a></li>
<li><a href="browser/report.json">browser/report.json</a></li>
</ul>
</body>
</html>';
    }

    public function reviewMarkdown(): string
    {
        return "Reviewer status: Pending\nReviewer:\nReviewed at:\nNotes:\n";
    }
}
