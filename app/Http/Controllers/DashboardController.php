<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DocumentManagement\DocumentInboxController;
use App\Queries\Log\DocumentDownloadActivityQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DocumentInboxController $documentInboxController,
        DocumentDownloadActivityQuery $activityQuery,
    ): View
    {
        $counts = $documentInboxController->dashboardCounts($request);

        return view('main.dashboard', [
            'summaryCards' => [
                [
                    'label' => 'Perlu Saya Proses',
                    'value' => $counts['needs_process'],
                    'hint' => 'Dokumen menunggu tindakan',
                    'tab' => 'needs-process',
                ],
                [
                    'label' => 'Riwayat yang Saya Proses',
                    'value' => $counts['processed_history'],
                    'hint' => 'Dokumen yang sudah diproses',
                    'tab' => 'processed-history',
                ],
            ],
            'activities' => $activityQuery->dashboardRows(),
        ]);
    }
}
