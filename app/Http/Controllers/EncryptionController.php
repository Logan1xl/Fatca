<?php

namespace App\Http\Controllers;

use App\Services\EncryptionService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur alternatif pour la gestion du chiffrement.
 */
class EncryptionController extends Controller
{
    protected $encryption;

    /**
     * Initialise le contrôleur.
     */
    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Affiche l'état des clés de chiffrement.
     */
    public function index()
    {
        $keysExist = $this->encryption->keysExist();
        $publicKey = $this->encryption->getPublicKey();
        return view('encryption.index', compact('keysExist', 'publicKey'));
    }

    /**
     * Génère une nouvelle paire de clés RSA et log l'action.
     */
    public function generateKeys(Request $request)
    {
        $this->encryption->generateKeys();

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'Génération Clés RSA',
            'details' => 'Nouvelle paire de clés RSA générée.',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Nouvelle paire de clés RSA générée avec succès.');
    }
}
