<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ValidationError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur gérant l'affichage du tableau de bord principal.
 */
class DashboardController extends Controller
{
    /**
     * Calcule les statistiques globales et affiche la vue du tableau de bord.
     */
    public function index()
    {
        $stats = [
            'total_reports' => Report::count(),
            'total_errors' => ValidationError::where('severity', 'error')->count(),
            'total_warnings' => ValidationError::where('severity', 'warning')->count(),
            'valid_reports' => Report::where('status', 'valid')->count(),
            'avg_compliance' => Report::count() > 0 ? Report::all()->avg(function($r) { return $r->compliance_rate; }) : 100,
        ];

        $recentReports = Report::with('user')->latest()->take(5)->get();

        // Chart data: errors by category
        $errorCategories = ValidationError::select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get();

        return view('dashboard', compact('stats', 'recentReports', 'errorCategories'));
    }
}
