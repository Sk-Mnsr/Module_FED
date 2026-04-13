<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('auth/Login', [
        'canRegister' => Features::enabled(Features::registration()),
        'canResetPassword' => true,
    ]);
})->name('home');

use App\Http\Controllers\DashboardController;

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

use App\Http\Controllers\UserController;
use App\Http\Controllers\FedController;
use App\Http\Controllers\N1FedController;
use App\Http\Controllers\AchatsFedController;
use App\Http\Controllers\FacilitiesFedController;
use App\Http\Controllers\CGFedController;
use App\Http\Controllers\DafFedController;
use App\Http\Controllers\DgaFedController;
use App\Http\Controllers\BonDeCommandeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\TypologieDepenseController;
use App\Http\Controllers\CategorieDepenseController;
use App\Http\Controllers\TypeDepenseController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\EcritureComptableController;

use App\Http\Controllers\Configuration\ArticleController;
use App\Http\Controllers\Configuration\AgenceController;
use App\Http\Controllers\Configuration\FamilleController;
use App\Http\Controllers\Configuration\CategorieController;
use App\Http\Controllers\Configuration\SousCategorieController;
use App\Http\Controllers\AppelOffreController;
use App\Http\Controllers\OffreController;
use App\Http\Controllers\ComiteController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\DemandeApprovisionnementController;

    // Routes pour les utilisateurs
    // - SuperAdmin uniquement : toutes les opérations (fait partie de Configuration)
    Route::middleware(['auth'])->group(function () {
    Route::resource('users', UserController::class)->middleware('role:it');
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle')->middleware('role:it');
    Route::resource('departments', DepartmentController::class)->middleware('role:it');
    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('budgets/n1', [BudgetController::class, 'indexForN1'])
        ->name('budgets.n1')
        ->middleware('role:n_plus_1');
    Route::get('budgets/create', [BudgetController::class, 'create'])->name('budgets.create')->middleware('role:it');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store')->middleware('role:it');
    Route::get('budgets/{budget}/edit', [BudgetController::class, 'edit'])->name('budgets.edit')->middleware('role:it');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update')->middleware('role:it');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy')->middleware('role:it');
    Route::put('budget-lines/{line}', [BudgetController::class, 'updateLine'])->name('budget-lines.update')->middleware('role:it');
    Route::delete('budget-lines/{line}', [BudgetController::class, 'destroyLine'])->name('budget-lines.destroy')->middleware('role:it');
    Route::resource('typologies', TypologieDepenseController::class)->middleware('role:it');
    Route::resource('categories', CategorieDepenseController::class)->middleware('role:it');
    Route::get('budgets/export/excel', [BudgetController::class, 'exportExcel'])
        ->name('budgets.export.excel')
        ->middleware('role:it');
    Route::get('budgets/export/pdf', [BudgetController::class, 'exportPdf'])
        ->name('budgets.export.pdf')
        ->middleware('role:it');

    // Entités partagées (IT et Responsable Achats)
    Route::middleware('role:it,responsable_achats')->group(function () {
        // Fournisseurs
        Route::get('fournisseurs', [\App\Http\Controllers\FournisseurController::class, 'index'])->name('fournisseurs.index');
        Route::post('fournisseurs', [\App\Http\Controllers\FournisseurController::class, 'store'])->name('fournisseurs.store');
        Route::put('fournisseurs/{fournisseur}', [\App\Http\Controllers\FournisseurController::class, 'update'])->name('fournisseurs.update');
        Route::delete('fournisseurs/{fournisseur}', [\App\Http\Controllers\FournisseurController::class, 'destroy'])->name('fournisseurs.destroy');
    });

    // Nouvelles routes pour les entités de base (IT uniquement)
    Route::middleware('role:it')->group(function () {
        // Banques
        Route::get('banques', [\App\Http\Controllers\BanqueController::class, 'index'])->name('banques.index');
        Route::post('banques', [\App\Http\Controllers\BanqueController::class, 'store'])->name('banques.store');
        Route::put('banques/{banque}', [\App\Http\Controllers\BanqueController::class, 'update'])->name('banques.update');
        Route::delete('banques/{banque}', [\App\Http\Controllers\BanqueController::class, 'destroy'])->name('banques.destroy');

        // Types de dépenses
        Route::get('type-depenses', [TypeDepenseController::class, 'index'])->name('type-depenses.index');
        Route::post('type-depenses', [TypeDepenseController::class, 'store'])->name('type-depenses.store');
        Route::put('type-depenses/{typeDepense}', [TypeDepenseController::class, 'update'])->name('type-depenses.update');
        Route::delete('type-depenses/{typeDepense}', [TypeDepenseController::class, 'destroy'])->name('type-depenses.destroy');
        
        // Fiches d'intégration
        Route::get('fiche-integrations/export', [\App\Http\Controllers\FicheIntegrationController::class, 'export'])->name('fiche-integrations.export');
        Route::post('fiche-integrations/import', [\App\Http\Controllers\FicheIntegrationController::class, 'import'])->name('fiche-integrations.import');
        Route::get('fiche-integrations', [\App\Http\Controllers\FicheIntegrationController::class, 'index'])->name('fiche-integrations.index');
        Route::post('fiche-integrations', [\App\Http\Controllers\FicheIntegrationController::class, 'store'])->name('fiche-integrations.store');
        Route::put('fiche-integrations/{ficheIntegration}', [\App\Http\Controllers\FicheIntegrationController::class, 'update'])->name('fiche-integrations.update');
        Route::delete('fiche-integrations/{ficheIntegration}', [\App\Http\Controllers\FicheIntegrationController::class, 'destroy'])->name('fiche-integrations.destroy');

        // Articles & Agences & Familles
        Route::resource('articles', ArticleController::class);
        Route::resource('agences', AgenceController::class);

        // Familles → Catégories → Sous-Catégories
        Route::get('familles/export-template', [FamilleController::class, 'exportTemplate'])->name('familles.template');
        Route::post('familles/import', [FamilleController::class, 'import'])->name('familles.import');
        Route::resource('familles', FamilleController::class);
        Route::get('familles/{famille}/categories', [FamilleController::class, 'categories'])->name('familles.categories');
        Route::get('categories/{categorie}/sous-categories', [FamilleController::class, 'sousCategories'])->name('categories.sous-categories');
        Route::resource('categories', CategorieController::class)->except(['index', 'show']);
        Route::resource('sous-categories', SousCategorieController::class)->except(['index', 'show']);
    });

    // Routes pour les validations N+1 (AVANT le resource pour éviter que "n1" soit interprété comme {fed})
    Route::get('feds/n1', [N1FedController::class, 'index'])->name('feds.n1.index')->middleware('role:n_plus_1');
    Route::get('feds/n1/{fed}', [N1FedController::class, 'show'])->name('feds.n1.show')->middleware('role:n_plus_1');
    Route::post('feds/n1/{fed}/approve', [N1FedController::class, 'approve'])->name('feds.n1.approve')->middleware('role:n_plus_1');
    Route::post('feds/n1/{fed}/reject', [N1FedController::class, 'reject'])->name('feds.n1.reject')->middleware('role:n_plus_1');
    Route::post('feds/n1/{fed}/needs-info', [N1FedController::class, 'needsInfo'])->name('feds.n1.needs_info')->middleware('role:n_plus_1');
    Route::post('feds/n1/{fed}/expert-opinion', [N1FedController::class, 'expertOpinion'])->name('feds.n1.expert_opinion')->middleware('role:n_plus_1');

    // Routes pour le responsable Achats
    Route::get('achats/tableaux-comparatifs', fn () => Inertia::render('achats/TableauxComparatifs'))
        ->name('achats.tableaux-comparatifs')->middleware(['auth', 'role:responsable_achats']);
    Route::get('feds/achats', [AchatsFedController::class, 'index'])->name('feds.achats.index')->middleware('role:responsable_achats');
    Route::get('feds/achats/{fed}', [AchatsFedController::class, 'show'])->name('feds.achats.show')->middleware('role:responsable_achats');
    Route::get('feds/achats/{fed}/cotation', [AchatsFedController::class, 'cotation'])->name('feds.achats.cotation')->middleware('role:responsable_achats');
    Route::post('feds/achats/{fed}/offres', [AchatsFedController::class, 'storeOffres'])->name('feds.achats.offres')->middleware('role:responsable_achats');
    Route::post('feds/achats/{fed}/transmit', [AchatsFedController::class, 'transmitToFacilities'])->name('feds.achats.transmit')->middleware('role:responsable_achats');
    Route::post('feds/achats/{fed}/reject', [AchatsFedController::class, 'reject'])->name('feds.achats.reject')->middleware('role:responsable_achats');
    Route::post('feds/achats/{fed}/needs-info', [AchatsFedController::class, 'needsInfo'])->name('feds.achats.needs_info')->middleware('role:responsable_achats');

    // Routes pour le responsable Facilities
    Route::get('feds/facilities', [FacilitiesFedController::class, 'index'])->name('feds.facilities.index')->middleware('role:responsable_facilities');
    Route::get('feds/facilities/{fed}', [FacilitiesFedController::class, 'show'])->name('feds.facilities.show')->middleware('role:responsable_facilities');
    Route::post('feds/facilities/{fed}/approve', [FacilitiesFedController::class, 'approve'])->name('feds.facilities.approve')->middleware('role:responsable_facilities');
    Route::post('feds/facilities/{fed}/reject', [FacilitiesFedController::class, 'reject'])->name('feds.facilities.reject')->middleware('role:responsable_facilities');
    Route::post('feds/facilities/{fed}/needs-info', [FacilitiesFedController::class, 'needsInfo'])->name('feds.facilities.needs_info')->middleware('role:responsable_facilities');

    Route::get('feds/cg', [CGFedController::class, 'index'])->name('feds.cg.index')->middleware('role:controle_de_gestion');
    Route::get('feds/cg/{fed}', [CGFedController::class, 'show'])->name('feds.cg.show')->middleware('role:controle_de_gestion');
    Route::get('feds/cg/{fed}/reclasser', [CGFedController::class, 'showReclasser'])->name('feds.cg.reclasser')->middleware('role:controle_de_gestion');
    Route::post('feds/cg/{fed}/treat', [CGFedController::class, 'treat'])->name('feds.cg.treat')->middleware('role:controle_de_gestion');
    Route::post('feds/cg/{fed}/reclassify-transfer', [CGFedController::class, 'reclassifyTransfer'])->name('feds.cg.reclassify_transfer')->middleware('role:controle_de_gestion');
    Route::post('feds/cg/{fed}/change-budget-line', [CGFedController::class, 'changeBudgetLine'])->name('feds.cg.change_budget_line')->middleware('role:controle_de_gestion');

    // Routes pour le DAF
    Route::get('feds/daf', [DafFedController::class, 'index'])->name('feds.daf.index')->middleware('role:daf');
    Route::get('feds/daf/{fed}', [DafFedController::class, 'show'])->name('feds.daf.show')->middleware('role:daf');
    Route::post('feds/daf/{fed}/approve', [DafFedController::class, 'approve'])->name('feds.daf.approve')->middleware('role:daf');
    Route::post('feds/daf/{fed}/reject', [DafFedController::class, 'reject'])->name('feds.daf.reject')->middleware('role:daf');
    Route::post('feds/daf/{fed}/approve-reclass', [DafFedController::class, 'approveReclass'])->name('feds.daf.approve_reclass')->middleware('role:daf');
    Route::post('feds/daf/{fed}/reject-reclass', [DafFedController::class, 'rejectReclass'])->name('feds.daf.reject_reclass')->middleware('role:daf');

    // Routes pour le DGA
    Route::get('feds/dga', [DgaFedController::class, 'index'])->name('feds.dga.index')->middleware('role:dga');
    Route::get('feds/dga/{fed}', [DgaFedController::class, 'show'])->name('feds.dga.show')->middleware('role:dga');
    Route::post('feds/dga/{fed}/approve', [DgaFedController::class, 'approve'])->name('feds.dga.approve')->middleware('role:dga');
    Route::post('feds/dga/{fed}/reject', [DgaFedController::class, 'reject'])->name('feds.dga.reject')->middleware('role:dga');

    // Routes pour les Paramètres (IT uniquement)
    Route::get('settings/app', [AppSettingController::class, 'index'])->name('settings.app.index')->middleware('role:it');
    Route::put('settings/app', [AppSettingController::class, 'update'])->name('settings.app.update')->middleware('role:it');

    // Routes pour les bons de commande
    Route::get('bons-de-commande', [BonDeCommandeController::class, 'index'])->name('bons-de-commande.index');
    Route::get('bons-de-commande/{fed}', [BonDeCommandeController::class, 'show'])->name('bons-de-commande.show');

    // Routes pour les écritures comptables
    Route::get('ecritures-comptables/export', [EcritureComptableController::class, 'export'])->name('ecritures-comptables.export');
    Route::get('ecritures-comptables', [EcritureComptableController::class, 'index'])->name('ecritures-comptables.index');

    // Routes pour les FED (demandeur)
    Route::resource('feds', FedController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('feds/{fed}/submit', [FedController::class, 'submit'])->name('feds.submit');

    // Routes pour les Appels d'Offres (TDR)
    Route::resource('appel-offres', AppelOffreController::class);
    Route::post('appel-offres/{appelOffre}/publish', [AppelOffreController::class, 'publish'])->name('appel-offres.publish');
    Route::get('appel-offres/{appelOffre}/opening-session', [AppelOffreController::class, 'openingSession'])->name('appel-offres.opening-session');
    Route::post('appel-offres/{appelOffre}/start-evaluation', [AppelOffreController::class, 'startEvaluation'])->name('appel-offres.start-evaluation');
    Route::get('appel-offres/{appelOffre}/pv-ouverture', [AppelOffreController::class, 'pvOuverture'])->name('appel-offres.pv-ouverture');
    Route::get('appel-offres/{appelOffre}/compare', [EvaluationController::class, 'compare'])->name('evaluations.compare');
    Route::get('appel-offres/{appelOffre}/pv-evaluation', [EvaluationController::class, 'pvEvaluation'])->name('evaluations.pv_evaluation');
    
    // Routes pour le Comité
    Route::get('comites', [ComiteController::class, 'index'])->name('comites.index');
    Route::get('appel-offres/{appelOffre}/comites/create', [ComiteController::class, 'create'])->name('comites.create');
    Route::post('appel-offres/{appelOffre}/comites', [ComiteController::class, 'store'])->name('comites.store');
    Route::get('comites/{comite}/edit', [ComiteController::class, 'edit'])->name('comites.edit');
    Route::put('comites/{comite}', [ComiteController::class, 'update'])->name('comites.update');

    // Routes pour les Offres
    Route::get('appel-offres/{appelOffre}/offres/create', [OffreController::class, 'create'])->name('offres.create');
    Route::post('appel-offres/{appelOffre}/offres', [OffreController::class, 'store'])->name('offres.store');

    // Routes pour les Évaluations
    Route::get('offres/{offre}/evaluations/create', [EvaluationController::class, 'create'])->name('evaluations.create');
    Route::post('offres/{offre}/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');

    // Routes pour la Gestion de Stock
    Route::middleware('role:it,responsable_achats,responsable_stock')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::post('stock/movements', [StockController::class, 'store'])->name('stock.movements.store');
        
        Route::post('demandes-approvisionnement/{demande}/status', [DemandeApprovisionnementController::class, 'updateStatus'])->name('demandes-approvisionnement.update-status');
    });

    Route::resource('demandes-approvisionnement', DemandeApprovisionnementController::class)->parameters([
        'demandes-approvisionnement' => 'demande'
    ]);


});

// Routes publiques pour les Fournisseurs (Soumission via URL signée)
Route::middleware(['signed'])->group(function () {
    Route::get('fournisseur/soumission/{appelOffre}/{fournisseur}', [\App\Http\Controllers\PublicSoumissionController::class, 'create'])->name('public.soumission.create');
    Route::post('fournisseur/soumission/{appelOffre}/{fournisseur}', [\App\Http\Controllers\PublicSoumissionController::class, 'store'])->name('public.soumission.store');
});
