<?php

namespace App\Http\Controllers;

use App\Services\EncryptionService;
use Illuminate\Http\Request;

/**
 * Contrôleur gérant les paramètres de sécurité et les clés RSA.
 */
class SettingsController extends Controller
{
    protected $encryption;

    /**
     * Initialise le contrôleur avec le service de chiffrement.
     */
    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Affiche la page des paramètres de sécurité et l'état des clés.
     */
    public function index()
    {
        $keysExist = $this->encryption->keysExist();
        $publicKey = $keysExist ? $this->encryption->getPublicKey() : null;

        return view('settings.index', compact('keysExist', 'publicKey'));
    }

    /**
     * Déclenche la génération d'une nouvelle paire de clés RSA 2048-bits.
     */
    public function generateKeys()
    {
        try {
            $this->encryption->generateKeys();
            return back()->with('success', 'Paire de clés RSA 2048-bit générée avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération des clés : ' . $e->getMessage());
        }
    }
}
