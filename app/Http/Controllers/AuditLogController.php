<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Contrôleur gérant la consultation des journaux d'audit.
 */
class AuditLogController extends Controller
{
    /**
     * Affiche l'historique des actions utilisateur (Audit Trail).
     */
    public function index()
    {
        $logs = AuditLog::with('user')->latest()->paginate(20);
        return view('audit_logs.index', compact('logs'));
    }
}
