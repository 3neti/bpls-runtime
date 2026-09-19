import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import * as Vue from 'vue';

const source = readFileSync(
    'resources/js/components/permit-applications/IpilExecutableDocument.vue',
    'utf8',
);
const { descriptor } = parse(source);
const render = new Function(
    'Vue',
    compile(descriptor.template!.content, {
        mode: 'function',
        prefixIdentifiers: true,
    }).code,
)(Vue);
const hash = 'abc123'.repeat(10) + 'abcd';
const addressFields = [
    'house_or_building_number',
    'building_name',
    'unit_number',
    'street',
    'barangay',
    'barangay_psgc_code',
    'subdivision',
    'city_municipality',
    'province',
    'telephone',
    'email',
];
const address = (prefix: string) =>
    Object.fromEntries(addressFields.map((key, i) => [key, `${prefix}-${i}`]));
const fixture = () => ({
    application: {
        tax_year: 2026,
        type: 'new',
        date_of_application: '2026-09-19',
        mode_of_payment: 'annually',
    },
    business: {
        name: 'Frozen Business',
        activity_description:
            'Frozen_description <script>not executable</script>',
    },
    applicant_business_activity_description: 'Compatibility description',
    organization: { organization_name: 'Frozen Organization' },
    business_address: address('business'),
    owner_address: address('owner'),
    establishment: {
        male_employees: 0,
        female_employees: 3,
        total_employees: 3,
        employees_residing_in_lgu: 2,
    },
    rental: {
        place_is_rented: false,
        monthly_rental_pesos: 4321,
        lessor: { address: address('lessor') },
    },
    lines_of_business: [] as Array<Record<string, unknown>>,
    applicant_documents_manifest: {
        digest: 'manifest-digest',
        documents: [
            {
                document_id: 29,
                version: 3,
                original_name: 'frozen-file.pdf',
                checksum_sha256: 'file-digest',
                label: 'DTI',
                document_type: 'dti_registration',
            },
        ],
    },
});
async function html(snapshot = fixture(), commissionedPath = false) {
    return renderToString(
        Vue.createSSRApp({
            render,
            components: {
                IpilMunicipalProcessingSheet: { template: '<div />' },
            },
            data: () => ({
                page: 'page_1',
                recentCertificationOffice: null,
                document: {
                    commissioned_path: commissionedPath,
                    identity: {
                        tax_year: 2099,
                        tracking_reference: 'SUB-FROZEN',
                    },
                    declaration: {
                        snapshot,
                        state: 'frozen',
                        snapshot_hash: hash,
                    },
                    business: {
                        applicant_activity_description:
                            'MUTABLE MUST NOT APPEAR',
                    },
                },
                applicantLodgingSignature: {
                    facsimile_data_url: 'data:image/png;base64,TEST',
                },
                value: (path: string) =>
                    path
                        .split('.')
                        .reduce<any>(
                            (current, key) => current?.[key],
                            snapshot,
                        ),
                shown: (value: unknown) =>
                    value === true
                        ? 'Yes'
                        : value === false
                          ? 'No'
                          : value == null || value === ''
                            ? '—'
                            : String(value).replaceAll('_', ' '),
                money: (value: unknown) => String(value ?? '—'),
            }),
        }),
    );
}
test('frozen facts render without consulting mutable facts or losing zero/false values', async () => {
    const output = await html();

    for (const text of [
        'Frozen_description &lt;script&gt;not executable&lt;/script&gt;',
        'Frozen Organization',
        'Male Employees:</strong> 0',
        'Female Employees:</strong> 3',
        'Place of Business Rented:</strong> No',
        '4321',
        'TAX YEAR: 2026',
        hash,
        'frozen-file.pdf',
        'DTI',
        'dti registration',
        'Document 29 · Version 3',
        'manifest-digest',
        'file-digest',
        'Applicant signature facsimile',
    ]) {
        assert.ok(output.includes(text), text);
    }

    for (const prefix of ['business', 'owner', 'lessor']) {
        for (let i = 0; i < addressFields.length; i++) {
            assert.ok(output.includes(`${prefix}-${i}`));
        }
    }

    for (const absent of [
        'MUTABLE MUST NOT APPEAR',
        '2099',
        'Compatibility description',
        'Lines of Business',
    ]) {
        assert.ok(!output.includes(absent), absent);
    }
});
test('legacy frozen description and applicant LOBs remain supported', async () => {
    const snapshot = fixture();
    delete (snapshot.business as Partial<typeof snapshot.business>)
        .activity_description;
    snapshot.lines_of_business = [
        {
            code: 'OLD',
            name: 'Historical declared LOB',
            number_of_units: 17,
            capitalization_cents: 12345,
            essential_gross_sales_cents: 23456,
            non_essential_gross_sales_cents: 34567,
            // Aggregate sales are internal; the historical form prints the two components.
            declared_gross_sales_cents: 58023,
        },
    ];
    const output = await html(snapshot);
    assert.ok(output.includes('Compatibility description'));
    assert.ok(output.includes('Historical declared LOB'));

    for (const amount of ['17', '12345', '23456', '34567']) {
        assert.ok(output.includes(amount));
    }

    const commissionedOutput = await html(snapshot, true);
    assert.ok(!commissionedOutput.includes('Historical declared LOB'));
    assert.ok(!commissionedOutput.includes('Lines of Business'));
});
test('full frozen hash wraps without page clipping', async () => {
    const output = await html();
    assert.match(
        output,
        /class="[^"]*w-full[^"]*min-w-0[^"]*break-all[^"]*"[^>]*>SHA-256/,
    );
    assert.ok(!output.includes('overflow-hidden'));
    assert.ok(output.includes('grid-cols-[minmax(0,1fr)_minmax(0,1fr)]'));
});

test('all declared applicant fields remain visible in the Page 1 contract', async () => {
    const snapshot = fixture();
    const paths = [
        'application.type',
        'application.mode_of_payment',
        'application.date_of_application',
        'registration.number',
        'registration.reference_number',
        'registration.registered_on',
        'organization.type',
        'organization.organization_name',
        'organization.ctc_number',
        'organization.tin',
        'organization.tax_incentive_entity',
        'taxpayer.last_name',
        'taxpayer.first_name',
        'taxpayer.middle_name',
        'business.name',
        'business.plate_number',
        'business.trade_name',
        'business.activity_description',
        'corporate_officer.last_name',
        'corporate_officer.first_name',
        'corporate_officer.middle_name',
        'establishment.property_index_number',
        'establishment.business_area_square_meters',
        'establishment.total_employees',
        'establishment.employees_residing_in_lgu',
        'establishment.male_employees',
        'establishment.female_employees',
        'rental.monthly_rental_pesos',
        'rental.lessor.last_name',
        'rental.lessor.first_name',
        'rental.lessor.middle_name',
        'emergency_contact.name',
        'emergency_contact.telephone',
        'emergency_contact.mobile',
        'emergency_contact.email',
        'undertaking.applicant_printed_name',
        'undertaking.position_title',
    ];

    for (const [index, path] of paths.entries()) {
        const segments = path.split('.');
        const key = segments.pop()!;
        const parent = segments.reduce<any>(
            (node, segment) => (node[segment] ??= {}),
            snapshot,
        );
        parent[key] = `FIELD-${index}-SENTINEL`;
    }

    Object.assign(snapshot, {
        undertaking: { ...(snapshot as any).undertaking, accepted: true },
    });
    Object.assign(snapshot.organization, { tax_incentive_enjoyed: true });
    const output = await html(snapshot);

    for (const [index, path] of paths.entries()) {
        assert.ok(output.includes(`FIELD-${index}-SENTINEL`), path);
    }

    assert.match(output, /Oath of Undertaking:<\/strong> Accepted/);
    assert.match(output, /Tax incentive from Government Entity:<\/strong> Yes/);
});
