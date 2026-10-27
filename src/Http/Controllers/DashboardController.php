<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Services\DashboardService;
use SCMS\Finance\Support\Abilities;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        $this->authorizeAbility(Abilities::DASHBOARD);

        return $this->view('dashboard', [
            'summary' => $dashboard->summary($request->user(), $this->defaultBranch()),
        ]);
    }
}
