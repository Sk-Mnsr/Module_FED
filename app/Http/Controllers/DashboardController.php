<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $userId = auth()->id();

        $total = Fed::where('requester_id', $userId)->count();
        $enCours = Fed::where('requester_id', $userId)
            ->whereIn('status', [Fed::STATUS_PENDING_VALIDATION, Fed::STATUS_N1_NEEDS_INFO])
            ->count();
        $cloturees = Fed::where('requester_id', $userId)
            ->whereIn('status', [Fed::STATUS_N1_APPROVED, Fed::STATUS_N1_REJECTED])
            ->count();
        $enAttente = Fed::where('requester_id', $userId)
            ->where('status', Fed::STATUS_PENDING_VALIDATION)
            ->count();

        return Inertia::render('Dashboard', [
            'today' => $today,
            'stats' => [
                'total' => $total,
                'enCours' => $enCours,
                'cloturees' => $cloturees,
                'enAttente' => $enAttente,
            ],
        ]);
    }
}

