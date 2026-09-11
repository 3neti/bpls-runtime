<?php

namespace App\Actions;

use App\Models\IpilHistoricalMediaEvidence;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BuildIpilHistoricalReadSurface
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $businesses = $this->businessDirectoryQuery($filters)
            ->orderBy(match ($sort) {
                'owner' => 'o.name',
                'barangay' => 'b.barangay_literal',
                'applications' => 'application_count',
                default => 'b.name',
            }, $direction)
            ->orderBy('b.id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (object $row): array => (array) $row);

        return [
            'businesses' => $businesses,
            'matches' => $search === '' ? $this->emptyMatches() : $this->search($search),
            'options' => [
                'years' => DB::table('ipil_historical_applications')->distinct()->orderByDesc('application_year')->pluck('application_year'),
                'types' => DB::table('ipil_historical_applications')->distinct()->orderBy('source_type')->pluck('source_type'),
                'statuses' => DB::table('ipil_historical_applications')->distinct()->orderBy('source_status')->pluck('source_status'),
                'barangays' => DB::table('ipil_historical_businesses')->whereNotNull('barangay_literal')->distinct()->orderBy('barangay_literal')->pluck('barangay_literal'),
            ],
            'anchors' => $this->anchors(),
        ];
    }

    /** @return array<string, mixed> */
    public function owner(int $id): array
    {
        $owner = DB::table('ipil_historical_owners')->where('id', $id)->first([
            'id', 'name', 'email', 'phone', 'address', 'barangay_literal', 'collision_candidate', 'operationally_eligible',
        ]);
        abort_if($owner === null, 404);

        return [
            'owner' => [...(array) $owner, 'collision_candidate' => (bool) $owner->collision_candidate, 'operationally_eligible' => (bool) $owner->operationally_eligible],
            'businesses' => DB::table('ipil_historical_businesses as b')
                ->where('b.ipil_historical_owner_id', $id)
                ->leftJoin('ipil_historical_applications as a', 'a.ipil_historical_business_id', '=', 'b.id')
                ->select(['b.id', 'b.name', 'b.registration_number', 'b.address', 'b.barangay_literal', 'b.collision_candidate'])
                ->selectRaw('count(a.id) as application_count')
                ->groupBy('b.id')->orderBy('b.name')->orderBy('b.id')->get()->map(fn ($row) => (array) $row),
            'applications' => $this->applicationSummaryQuery()->where('a.ipil_historical_owner_id', $id)
                ->orderByDesc('a.application_year')->orderByDesc('a.id')->get()->map(fn ($row) => (array) $row),
        ];
    }

    /** @return array<string, mixed> */
    public function business(int $id): array
    {
        $business = DB::table('ipil_historical_businesses as b')
            ->join('ipil_historical_owners as o', 'o.id', '=', 'b.ipil_historical_owner_id')
            ->where('b.id', $id)->first([
                'b.id', 'b.name', 'b.registration_number', 'b.address', 'b.barangay_literal', 'b.collision_candidate',
                'b.operationally_eligible', 'o.id as owner_id', 'o.name as owner_name',
            ]);
        abort_if($business === null, 404);

        return [
            'business' => [...(array) $business, 'collision_candidate' => (bool) $business->collision_candidate, 'operationally_eligible' => (bool) $business->operationally_eligible],
            'applications' => $this->applicationSummaryQuery()->where('a.ipil_historical_business_id', $id)
                ->orderByDesc('a.application_year')->orderByDesc('a.id')->get()->map(fn ($row) => (array) $row),
            'documents' => $this->documentsForBusiness($id),
        ];
    }

    /** @return array<string, mixed> */
    public function application(int $id): array
    {
        $application = $this->applicationSummaryQuery()->where('a.id', $id)->first();
        abort_if($application === null, 404);

        return [
            'application' => [...(array) $application, 'operationally_eligible' => (bool) $application->operationally_eligible, 'can_continue' => (bool) $application->can_continue],
            'classifications' => $this->rows('ipil_historical_classifications', 'ipil_historical_application_id', $id, ['id', 'source_literal', 'normalized_candidate', 'confidence']),
            'measurements' => $this->rows('ipil_historical_measurements', 'ipil_historical_application_id', $id, ['id', 'source_dataset', 'source_variable', 'source_quantity_lexeme']),
            'schedules' => $this->rows('ipil_historical_payment_schedules', 'ipil_historical_application_id', $id, ['id', 'source_status', 'total_amount_source_lexeme', 'total_amount_decimal', 'total_amount_cent_exact', 'paid_amount_source_lexeme', 'paid_amount_decimal', 'missing_application']),
            'payments' => DB::table('ipil_historical_payments as p')->leftJoin('ipil_historical_receipt_claims as r', 'r.ipil_historical_payment_id', '=', 'p.id')
                ->where('p.ipil_historical_application_id', $id)->orderByDesc('p.paid_at')->orderBy('p.id')->get([
                    'p.id', 'p.ipil_historical_payment_schedule_id as schedule_id', 'p.source_status', 'p.amount_source_lexeme', 'p.amount_decimal', 'p.transaction_number', 'p.receipt_number', 'p.paid_at', 'p.missing_application', 'p.missing_schedule', 'r.is_duplicate_claim',
                ])->map(fn ($row) => (array) $row),
            'permits' => $this->rows('ipil_historical_permit_claims', 'ipil_historical_application_id', $id, ['id', 'permit_number', 'source_status', 'missing_application', 'broken_business_edge', 'broken_owner_edge']),
            'clearances' => $this->rows('ipil_historical_clearance_claims', 'ipil_historical_application_id', $id, ['id', 'clearance_name', 'source_completed', 'broken_type_reference']),
            'documents' => $this->documentsForBusiness((int) $application->business_id),
        ];
    }

    public function document(int $businessId, int $evidenceId): IpilHistoricalMediaEvidence
    {
        $ids = $this->documentsForBusiness($businessId)->pluck('id')->all();

        return IpilHistoricalMediaEvidence::query()
            ->whereKey($evidenceId)
            ->whereIn('id', $ids)
            ->where('association_state', 'ASSOCIATED')
            ->where('import_accepted', true)
            ->where('spatie_imported', true)
            ->firstOrFail();
    }

    /** @param array<string, mixed> $filters */
    private function businessDirectoryQuery(array $filters): Builder
    {
        $query = DB::table('ipil_historical_businesses as b')
            ->join('ipil_historical_owners as o', 'o.id', '=', 'b.ipil_historical_owner_id')
            ->leftJoin('ipil_historical_applications as a', 'a.ipil_historical_business_id', '=', 'b.id')
            ->select(['b.id', 'b.name', 'b.registration_number', 'b.address', 'b.barangay_literal', 'b.collision_candidate', 'o.id as owner_id', 'o.name as owner_name'])
            ->selectRaw('count(distinct a.id) as application_count')
            ->groupBy('b.id', 'o.id');

        foreach (['year' => 'application_year', 'type' => 'source_type', 'status' => 'source_status'] as $input => $column) {
            if (($filters[$input] ?? null) !== null && $filters[$input] !== '') {
                $query->where("a.$column", $filters[$input]);
            }
        }
        if (($filters['barangay'] ?? '') !== '') {
            $query->where('b.barangay_literal', $filters['barangay']);
        }
        if (($filters['classification'] ?? '') !== '') {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('ipil_historical_classifications as c')
                ->whereColumn('c.ipil_historical_application_id', 'a.id')->whereRaw('lower(c.source_literal) like ?', ['%'.mb_strtolower((string) $filters['classification']).'%']));
        }

        return $query;
    }

    private function applicationSummaryQuery(): Builder
    {
        return DB::table('ipil_historical_applications as a')
            ->join('ipil_historical_businesses as b', 'b.id', '=', 'a.ipil_historical_business_id')
            ->join('ipil_historical_owners as o', 'o.id', '=', 'a.ipil_historical_owner_id')
            ->select(['a.id', 'a.source_application_number', 'a.source_type', 'a.source_status', 'a.application_year', 'a.total_fees_source_lexeme', 'a.total_fees_decimal', 'a.total_fees_cent_exact', 'a.submitted_at', 'a.operationally_eligible', 'a.can_continue', 'b.id as business_id', 'b.name as business_name', 'o.id as owner_id', 'o.name as owner_name']);
    }

    /** @return array<string, Collection<int, array<array-key, mixed>>> */
    private function search(string $search): array
    {
        $like = '%'.mb_strtolower($search).'%';
        $receiptHash = hash('sha256', mb_strtolower((string) preg_replace('/[^[:alnum:]]+/u', '', $search)));

        return [
            'businesses' => DB::table('ipil_historical_businesses')->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(registration_number) like ?', [$like]))->orderBy('name')->limit(8)->get(['id', 'name', 'registration_number'])->map(fn ($r): array => $this->row($r)),
            'owners' => DB::table('ipil_historical_owners')->whereRaw('lower(name) like ?', [$like])->orderBy('name')->limit(8)->get(['id', 'name', 'barangay_literal'])->map(fn ($r): array => $this->row($r)),
            'applications' => $this->applicationSummaryQuery()->whereRaw('lower(a.source_application_number) like ?', [$like])->orderByDesc('a.application_year')->limit(8)->get()->map(fn ($r): array => $this->row($r)),
            'permits' => DB::table('ipil_historical_permit_claims as p')->leftJoin('ipil_historical_applications as a', 'a.id', '=', 'p.ipil_historical_application_id')->whereRaw('lower(p.permit_number) like ?', [$like])->orderBy('p.permit_number')->limit(8)->get(['p.id', 'p.permit_number', 'p.source_status', 'p.ipil_historical_application_id as application_id', 'p.missing_application', 'a.source_application_number'])->map(fn ($r): array => $this->row($r)),
            'receipts' => DB::table('ipil_historical_receipt_claims as r')->join('ipil_historical_payments as p', 'p.id', '=', 'r.ipil_historical_payment_id')->leftJoin('ipil_historical_applications as a', 'a.id', '=', 'p.ipil_historical_application_id')->where('r.normalized_sha256', $receiptHash)->limit(8)->get(['r.id', 'r.receipt_number', 'r.is_duplicate_claim', 'p.amount_decimal', 'p.ipil_historical_application_id as application_id', 'a.source_application_number'])->map(fn ($r): array => $this->receiptRow($r)),
        ];
    }

    /** @return array<string, Collection<int, mixed>> */
    private function emptyMatches(): array
    {
        return ['businesses' => collect(), 'owners' => collect(), 'applications' => collect(), 'permits' => collect(), 'receipts' => collect()];
    }

    /** @param array<int, string> $columns
     * @return Collection<int, array<array-key, mixed>>
     */
    private function rows(string $table, string $foreignKey, int $id, array $columns): Collection
    {
        return DB::table($table)->where($foreignKey, $id)->orderBy('id')->get($columns)->map(fn ($row): array => $this->row($row));
    }

    /** @return Collection<int, array<array-key, mixed>> */
    private function documentsForBusiness(int $businessId): Collection
    {
        $payload = DB::table('ipil_historical_businesses')->where('id', $businessId)->value('source_payload_json');
        $documents = is_string($payload) ? (json_decode($payload, true)['documents'] ?? []) : [];
        $hashes = collect(is_array($documents) ? $documents : [])->pluck('storageId')->filter(fn ($id) => is_string($id) && $id !== '')->map(fn ($id) => hash('sha256', $id))->all();

        if ($hashes === []) {
            return collect();
        }

        return IpilHistoricalMediaEvidence::query()->where('source_dataset', 'businesses-media')->where('association_state', 'ASSOCIATED')
            ->where('import_accepted', true)->where('spatie_imported', true)->get()
            ->filter(fn (IpilHistoricalMediaEvidence $evidence) => in_array(data_get($evidence->source_manifest_entry, 'storage_identifier_sha256'), $hashes, true))
            ->map(fn (IpilHistoricalMediaEvidence $evidence): array => $this->documentRow($evidence));
    }

    /** @return array<array-key, mixed> */
    private function row(object $row): array
    {
        return (array) $row;
    }

    /** @return array<array-key, mixed> */
    private function receiptRow(object $row): array
    {
        return [...(array) $row, 'is_duplicate_claim' => (bool) data_get($row, 'is_duplicate_claim')];
    }

    /** @return array<array-key, mixed> */
    private function documentRow(IpilHistoricalMediaEvidence $evidence): array
    {
        return ['id' => $evidence->id, 'document_type' => $evidence->document_type, 'original_filename' => $evidence->original_filename, 'source_size_bytes' => $evidence->source_size_bytes];
    }

    /** @return array<string, int|string> */
    private function anchors(): array
    {
        $paid = BigDecimal::zero();
        foreach (DB::table('ipil_historical_payments')->where('source_status', 'completed')->pluck('amount_decimal') as $amount) {
            $paid = $paid->plus((string) $amount);
        }

        return [
            'owners' => DB::table('ipil_historical_owners')->count(), 'businesses' => DB::table('ipil_historical_businesses')->count(),
            'applications' => DB::table('ipil_historical_applications')->count(), 'payments' => DB::table('ipil_historical_payments')->count(),
            'receipts' => DB::table('ipil_historical_receipt_claims')->count(), 'permits' => DB::table('ipil_historical_permit_claims')->count(),
            'clearances' => DB::table('ipil_historical_clearance_claims')->count(), 'completed_payment_total' => (string) $paid->toScale(2),
        ];
    }
}
