<?php

namespace App\Http\Middleware;

use App\Models\CoficarteCard;
use App\Models\CoficarteStockThreshold;
use App\Support\AppNavigation;
use App\Support\CoficarteAgenceAccess;
use App\Support\ModuleAccess;
use App\Support\NavModuleContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $roles = [];

        if ($user) {
            $user->load(['roles', 'agence']);
            $roles = $user->roles->pluck('slug')->toArray();
        }

        $coficarteAlerts = [];
        if ($user && ($user->isSuperAdmin() || $user->hasAnyRole(['monetique', 'monetique_ops', 'it', 'ca']))) {
            if (CoficarteAgenceAccess::canViewAll($user)) {
                $thresholdCentral = CoficarteStockThreshold::query()
                    ->where('cible', CoficarteStockThreshold::CIBLE_CENTRAL)
                    ->whereNull('agence_id')
                    ->first();
                if ($thresholdCentral !== null && $thresholdCentral->min_cards > 0) {
                    $cnt = CoficarteCard::query()
                        ->whereNull('agence_id')
                        ->where('status', CoficarteCard::STATUS_EN_STOCK)
                        ->count();
                    if ($cnt < $thresholdCentral->min_cards) {
                        $coficarteAlerts[] = [
                            'niveau' => 'warning',
                            'message' => "Stock central faible : {$cnt} carte(s) au siège (seuil : {$thresholdCentral->min_cards}).",
                        ];
                    }
                }
            }
            if ($user->agence_id) {
                $tAg = CoficarteStockThreshold::query()
                    ->where('cible', CoficarteStockThreshold::CIBLE_AGENCE)
                    ->where('agence_id', $user->agence_id)
                    ->first();
                if ($tAg !== null && $tAg->min_cards > 0) {
                    $cntA = CoficarteCard::query()
                        ->where('agence_id', $user->agence_id)
                        ->whereIn('status', [CoficarteCard::STATUS_EN_STOCK, CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT])
                        ->count();
                    if ($cntA < $tAg->min_cards) {
                        $coficarteAlerts[] = [
                            'niveau' => 'warning',
                            'message' => "Stock agence faible : {$cntA} carte(s) (seuil : {$tAg->min_cards}).",
                        ];
                    }
                }
            }
        }

        $activeModule = NavModuleContext::resolve($request);
        $onPortal = $request->routeIs('portal');

        return [
            ...parent::share($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'encaissement_bordereau' => $request->session()->get('encaissement_bordereau'),
                'bordereau_cc' => $request->session()->get('bordereau_cc'),
            ],
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'normalizedRoles' => $user ? ModuleAccess::normalizedRoleSlugs($user) : [],
                'modules' => $user ? ModuleAccess::accessibleModuleKeys($user) : [],
                'isSuperAdmin' => $user ? $user->isSuperAdmin() : false,
                'isInCommittee' => $user ? self::userIsInCommittee($user->id) : false,
                'canMonetiqueCentral' => $user ? CoficarteAgenceAccess::canViewAll($user) : false,
                'canResponsableMonetique' => $user ? CoficarteAgenceAccess::canResponsableMonetique($user) : false,
                'canInitiateCoficarteVente' => $user ? CoficarteAgenceAccess::canInitiateCoficarteVente($user) : false,
                'canInitiateCoficarteRecharge' => $user ? CoficarteAgenceAccess::canInitiateCoficarteRecharge($user) : false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'navigation' => [
                'groups' => AppNavigation::groups($user, $activeModule, $onPortal),
                'activeModule' => $activeModule,
                'onPortal' => $onPortal,
            ],
            'coficarteAlerts' => $coficarteAlerts,
        ];
    }

    private static function userIsInCommittee(int $userId): bool
    {
        if (! Schema::hasTable('comite_user')) {
            return false;
        }

        return DB::table('comite_user')->where('user_id', $userId)->exists();
    }
}
