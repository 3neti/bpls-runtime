<?php

namespace App\Http\Controllers\Staff;

use App\Actions\BuildMunicipalWorkInbox;
use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class MunicipalWorkInboxController extends Controller
{
    public function index(Request $request, BuildMunicipalWorkInbox $buildInbox): Response
    {
        Gate::authorize(UserPermission::AccessStaff->value);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'task' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $inbox = $buildInbox->handle($request->user(), $filters);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 15;
        $items = $inbox['items'];
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('work-inbox/Index', [
            'workItems' => $paginator,
            'assignments' => $inbox['assignments'],
            'taskOptions' => $inbox['task_options'],
            'filters' => [
                'q' => trim((string) ($filters['q'] ?? '')),
                'task' => (string) ($filters['task'] ?? ''),
                'year' => isset($filters['year']) ? (int) $filters['year'] : null,
            ],
            'counts' => [
                'action_required' => $items->count(),
            ],
        ]);
    }
}
