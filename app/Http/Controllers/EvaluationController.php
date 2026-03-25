<?php

namespace App\Http\Controllers;

use App\Models\Offre;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function create(Offre $offre)
    {
        $offre->load('appelOffre.criteres');

        return Inertia::render('Evaluations/ScoreBoard', [
            'offre' => $offre,
        ]);
    }

    public function store(Request $request, Offre $offre)
    {
        $validated = $request->validate([
            'evaluations' => 'required|array',
            'evaluations.*.critere_appel_offre_id' => 'required|exists:critere_appel_offres,id',
            'evaluations.*.note' => 'required|numeric|min:0',
            'evaluations.*.commentaire' => 'nullable|string',
        ]);

        DB::beginTransaction();

        foreach ($validated['evaluations'] as $evalData) {
            Evaluation::updateOrCreate(
                [
                    'offre_id' => $offre->id,
                    'user_id' => $request->user()->id,
                    'critere_appel_offre_id' => $evalData['critere_appel_offre_id'],
                ],
                [
                    'note' => $evalData['note'],
                    'commentaire' => $evalData['commentaire'] ?? null,
                ]
            );
        }

        DB::commit();

        return redirect()->back()->with('success', 'Notes enregistrées avec succès.');
    }
    public function compare(\App\Models\AppelOffre $appelOffre)
    {
        $appelOffre->load(['offres.evaluations', 'criteres', 'comite.membres']);
        
        $offres = $appelOffre->offres->map(function ($offre) use ($appelOffre) {
            $totalNote = 0;
            
            foreach ($appelOffre->criteres as $critere) {
                $evals = $offre->evaluations->where('critere_appel_offre_id', $critere->id);
                if ($evals->count() > 0) {
                    $avgNote = $evals->avg('note');
                    $totalNote += ($avgNote * $critere->ponderation);
                }
            }
            
            $offre->note_calculee = $totalNote;
            return $offre;
        })->sortByDesc('note_calculee')->values();
        
        return Inertia::render('Evaluations/Compare', [
            'appelOffre' => $appelOffre,
            'offres' => $offres,
        ]);
    }

    public function pvEvaluation(\App\Models\AppelOffre $appelOffre)
    {
        $appelOffre->load(['offres.evaluations', 'criteres', 'comite.membres']);
        
        $offres = $appelOffre->offres->map(function ($offre) use ($appelOffre) {
            $totalNote = 0;
            foreach ($appelOffre->criteres as $critere) {
                $evals = $offre->evaluations->where('critere_appel_offre_id', $critere->id);
                if ($evals->count() > 0) {
                    $totalNote += ($evals->avg('note') * $critere->ponderation);
                }
            }
            $offre->note_calculee = $totalNote;
            return $offre;
        })->sortByDesc('note_calculee')->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pv_evaluation', compact('appelOffre', 'offres'));
        return $pdf->download('PV_Evaluation_' . $appelOffre->reference . '.pdf');
    }
}