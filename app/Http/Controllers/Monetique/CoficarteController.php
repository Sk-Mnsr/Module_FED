<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteCard;
use App\Support\CoficarteAgenceAccess;
use Inertia\Inertia;

class CoficarteController extends Controller
{
    public function index()
    {
        $enStock = CoficarteCard::query()->where('status', CoficarteCard::STATUS_EN_STOCK);
        $vendus = CoficarteCard::query()->where('status', CoficarteCard::STATUS_VENDU);
        CoficarteAgenceAccess::applyCardScope($enStock, auth()->user());
        CoficarteAgenceAccess::applyCardScope($vendus, auth()->user());

        $stats = [
            'en_stock' => $enStock->count(),
            'vendus' => $vendus->count(),
        ];

        return Inertia::render('monetique/Coficarte/Index', [
            'stats' => $stats,
            'chef_agence_portal' => auth()->user()
                && ! CoficarteAgenceAccess::canViewAll(auth()->user())
                && auth()->user()->hasRole('ca'),
            'cc_delester_chef_visible' => auth()->user()
                && auth()->user()->hasRole('cc'),
        ]);
    }
}
