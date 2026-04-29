<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Service gérant le chiffrement hybride (RSA + AES) pour la protection des rapports.
 */
class EncryptionService
{
    private string $keyPath = 'keys/';

    /**
     * Initialise le dossier de stockage des clés.
     */
    public function __construct()
    {
        if (!Storage::exists($this->keyPath)) {
            Storage::makeDirectory($this->keyPath);
        }
    }

    /**
     * Génère une nouvelle paire de clés RSA (2048 bits).
     */
    public function generateKeys(): array
    {
        $config = [
            "digest_alg" => "sha512",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        // On Windows, openssl_pkey_new often fails if openssl.cnf is not found.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $commonPaths = [
                'C:\Program Files\Git\mingw64\ssl\openssl.cnf',
                'C:\xampp\php\extras\ssl\openssl.cnf',
                'C:\php\extras\ssl\openssl.cnf',
            ];
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    $config['config'] = $path;
                    break;
                }
            }
        }

        $res = openssl_pkey_new($config);
        
        if (!$res) {
            throw new \Exception("Erreur OpenSSL: Impossible de générer les clés. Vérifiez la configuration OpenSSL sur votre serveur.");
        }

        openssl_pkey_export($res, $privateKey, null, $config);
        $publicKeyData = openssl_pkey_get_details($res);
        $publicKey = $publicKeyData["key"];

        Storage::put($this->keyPath . 'private.pem', $privateKey);
        Storage::put($this->keyPath . 'public.pem', $publicKey);

        return ['public' => $publicKey, 'private' => $privateKey];
    }

    /**
     * Chiffre un fichier en utilisant le chiffrement hybride (RSA + AES).
     * Permet de chiffrer des fichiers de n'importe quelle taille de façon sécurisée.
     */
    public function encryptFile(string $sourcePath, string $destinationPath): bool
    {
        $publicKey = Storage::get($this->keyPath . 'public.pem');
        if (!$publicKey) return false;

        $fullSourcePath = storage_path('app/public/' . $sourcePath);
        if (!file_exists($fullSourcePath)) return false;
        
        $data = file_get_contents($fullSourcePath);
        
        // 1. Generate a random AES key and IV
        $aesKey = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        
        // 2. Encrypt data with AES
        $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $aesKey, 0, $iv);
        
        // 3. Encrypt AES key with RSA
        if (!openssl_public_encrypt($aesKey, $encryptedAesKey, $publicKey)) {
            return false;
        }
        
        // 4. Store encrypted key, IV, and encrypted data together
        // Format: [length of encrypted key (4 bytes)] + [encrypted key] + [IV] + [encrypted data]
        $package = pack('N', strlen($encryptedAesKey)) . $encryptedAesKey . $iv . $encryptedData;
        
        Storage::put('public/' . $destinationPath, $package);
        return true;
    }

    /**
     * Déchiffre un fichier en utilisant le déchiffrement hybride.
     */
    public function decryptFile(string $sourcePath, string $destinationPath): bool
    {
        $privateKey = Storage::get($this->keyPath . 'private.pem');
        if (!$privateKey) return false;

        $package = Storage::get('public/' . $sourcePath);
        if (!$package) return false;
        
        // 1. Extract encrypted key length
        $meta = unpack('Nlen', substr($package, 0, 4));
        $keyLen = $meta['len'];
        
        // 2. Extract encrypted key, IV, and data
        $encryptedAesKey = substr($package, 4, $keyLen);
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($package, 4 + $keyLen, $ivLen);
        $encryptedData = substr($package, 4 + $keyLen + $ivLen);
        
        // 3. Decrypt AES key with RSA
        if (!openssl_private_decrypt($encryptedAesKey, $aesKey, $privateKey)) {
            return false;
        }
        
        // 4. Decrypt data with AES
        $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $aesKey, 0, $iv);
        
        if ($decryptedData === false) return false;
        
        Storage::put('public/' . $destinationPath, $decryptedData);
        return true;
    }

    /**
     * Vérifie si les clés publique et privée existent.
     */
    public function keysExist(): bool
    {
        return Storage::exists($this->keyPath . 'private.pem') && Storage::exists($this->keyPath . 'public.pem');
    }

    /**
     * Récupère le contenu de la clé publique.
     */
    public function getPublicKey(): ?string
    {
        return Storage::get($this->keyPath . 'public.pem');
    }
}
