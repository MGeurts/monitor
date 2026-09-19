<?php

namespace App\Http\Controllers;

use App\Services\Stock\BolOfferSummaryService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BolOfferSummaryService $summaries): View
    {
        return view('dashboard', ['bolSummaries' => $summaries->forDashboard()]);
    }
}
