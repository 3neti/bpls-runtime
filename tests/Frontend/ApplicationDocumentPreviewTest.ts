import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('staff and citizen document surfaces keep View and Download while previewing supported files inline', () => {
    const reference = readFileSync(
        'resources/js/components/permit-applications/ApplicantDocumentReference.vue',
        'utf8',
    );
    const pillbox = readFileSync(
        'resources/js/components/permit-applications/ApplicationDocumentPillbox.vue',
        'utf8',
    );
    const staffApplication = readFileSync(
        'resources/js/pages/permit-applications/Show.vue',
        'utf8',
    );
    const pdfPreview = readFileSync(
        'resources/js/components/permit-applications/AuthenticatedPdfPreview.vue',
        'utf8',
    );

    assert.match(reference, />\s*View\s*</);

    for (const source of [reference, pillbox]) {
        assert.match(source, /`View \$\{document\.label\}`/);
        assert.match(source, />\s*Download\s*</);
        assert.match(source, /<AuthenticatedPdfPreview/);
        assert.match(source, /<img/);
        assert.match(source, /This file type cannot be previewed here/);
    }

    assert.match(staffApplication, /<ApplicantDocumentReference/);
    assert.match(staffApplication, /without leaving the Application/);
    assert.match(pdfPreview, /getDocument/);
    assert.match(pdfPreview, /credentials: 'same-origin'/);
    assert.match(pdfPreview, /<canvas/);
    assert.match(pdfPreview, /data-testid="pdf-text-layer"/);
});
