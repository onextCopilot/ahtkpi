<?php
/**
 * Minimal WebAuthn / Passkey implementation — no external dependencies.
 * Supports ES256 (P-256 ECDSA) which covers all modern authenticators.
 */
class WebAuthn
{
    private string $rpName;
    private string $rpId;
    private string $origin;

    public function __construct(string $rpName, string $rpId, string $origin)
    {
        $this->rpName   = $rpName;
        $this->rpId     = $rpId;
        $this->origin   = $origin;
    }

    // ── Public helpers ──────────────────────────────────────────────────────

    public static function generateChallenge(): string
    {
        return random_bytes(32);
    }

    public static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function fromBase64url(string $b64url): string
    {
        $b64 = strtr($b64url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        return base64_decode($b64);
    }

    // ── Registration ────────────────────────────────────────────────────────

    /**
     * Returns ['challenge' => raw_bytes, 'options' => array_ready_for_json]
     * Caller must store challenge in session and json_encode options for the browser.
     */
    public function getCreateOptions(int $userId, string $username, string $displayName, array $existingCredIds = []): array
    {
        $challenge = self::generateChallenge();

        $options = [
            'rp'   => ['name' => $this->rpName, 'id' => $this->rpId],
            'user' => [
                'id'          => self::base64url(pack('N', $userId)),
                'name'        => $username,
                'displayName' => $displayName,
            ],
            'challenge'          => self::base64url($challenge),
            'pubKeyCredParams'   => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256 fallback
            ],
            'excludeCredentials' => array_map(fn($id) => [
                'type'       => 'public-key',
                'id'         => self::base64url($id),
                'transports' => ['internal', 'hybrid'],
            ], $existingCredIds),
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey'      => 'preferred',
            ],
            'timeout'     => 60000,
            'attestation' => 'none',
        ];

        return ['challenge' => $challenge, 'options' => $options];
    }

    /**
     * Verifies the registration response from the browser.
     * Returns ['credentialId' => bytes, 'publicKey' => PEM string, 'signCount' => int]
     */
    public function verifyRegistration(string $clientDataJSONb64, string $attestationObjectb64, string $challenge): array
    {
        $clientDataJSON = self::fromBase64url($clientDataJSONb64);
        $clientData     = json_decode($clientDataJSON, true);

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new Exception('WebAuthn: invalid type');
        }
        if (self::fromBase64url($clientData['challenge']) !== $challenge) {
            throw new Exception('WebAuthn: challenge mismatch');
        }
        if (($clientData['origin'] ?? '') !== $this->origin) {
            throw new Exception('WebAuthn: origin mismatch — got ' . ($clientData['origin'] ?? '') . ', expected ' . $this->origin);
        }

        $attestationObject = self::fromBase64url($attestationObjectb64);
        $decoded           = self::cborDecode($attestationObject);
        $authData          = $decoded['authData'];

        $parsed = self::parseAuthData($authData);

        if ($parsed['rpIdHash'] !== hash('sha256', $this->rpId, true)) {
            throw new Exception('WebAuthn: rpId hash mismatch');
        }
        if (!($parsed['flags'] & 0x01)) {
            throw new Exception('WebAuthn: user presence flag not set');
        }
        if (!isset($parsed['credentialId'], $parsed['publicKey'])) {
            throw new Exception('WebAuthn: no credential data in authData');
        }

        return [
            'credentialId' => $parsed['credentialId'],
            'publicKey'    => $parsed['publicKey'],
            'signCount'    => $parsed['signCount'],
        ];
    }

    // ── Authentication ──────────────────────────────────────────────────────

    /**
     * Returns ['challenge' => raw_bytes, 'options' => array_ready_for_json]
     */
    public function getGetOptions(array $credentialIds): array
    {
        $challenge = self::generateChallenge();

        $options = [
            'rpId'             => $this->rpId,
            'challenge'        => self::base64url($challenge),
            'allowCredentials' => array_map(fn($id) => [
                'type'       => 'public-key',
                'id'         => self::base64url($id),
                'transports' => ['internal', 'hybrid'],
            ], $credentialIds),
            'userVerification' => 'preferred',
            'timeout'          => 60000,
        ];

        return ['challenge' => $challenge, 'options' => $options];
    }

    /**
     * Verifies an authentication assertion from the browser.
     * Returns the new signCount (caller should persist it).
     */
    public function verifyAuthentication(
        string $clientDataJSONb64,
        string $authenticatorDatab64,
        string $signatureb64,
        string $challenge,
        string $publicKeyPem,
        int    $storedSignCount
    ): int {
        $clientDataJSON    = self::fromBase64url($clientDataJSONb64);
        $clientData        = json_decode($clientDataJSON, true);
        $authenticatorData = self::fromBase64url($authenticatorDatab64);
        $signature         = self::fromBase64url($signatureb64);

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new Exception('WebAuthn: invalid type');
        }
        if (self::fromBase64url($clientData['challenge']) !== $challenge) {
            throw new Exception('WebAuthn: challenge mismatch');
        }
        if (($clientData['origin'] ?? '') !== $this->origin) {
            throw new Exception('WebAuthn: origin mismatch');
        }

        if (substr($authenticatorData, 0, 32) !== hash('sha256', $this->rpId, true)) {
            throw new Exception('WebAuthn: rpId hash mismatch');
        }
        $flags = ord($authenticatorData[32]);
        if (!($flags & 0x01)) {
            throw new Exception('WebAuthn: user presence flag not set');
        }

        $signCount = unpack('N', substr($authenticatorData, 33, 4))[1];
        if ($signCount > 0 && $storedSignCount > 0 && $signCount <= $storedSignCount) {
            throw new Exception('WebAuthn: sign count regression — possible replay attack');
        }

        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData     = $authenticatorData . $clientDataHash;

        $result = openssl_verify($signedData, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            throw new Exception('WebAuthn: signature verification failed');
        }

        return $signCount;
    }

    // ── Internal: authData parsing ──────────────────────────────────────────

    private static function parseAuthData(string $authData): array
    {
        $result              = [];
        $result['rpIdHash']  = substr($authData, 0, 32);
        $result['flags']     = ord($authData[32]);
        $result['signCount'] = unpack('N', substr($authData, 33, 4))[1];

        // AT flag (bit 6) — attested credential data present
        if ($result['flags'] & 0x40) {
            $offset = 37 + 16; // skip aaguid
            $credIdLen            = unpack('n', substr($authData, $offset, 2))[1];
            $offset              += 2;
            $result['credentialId'] = substr($authData, $offset, $credIdLen);
            $offset              += $credIdLen;
            $coseKey              = self::cborDecode(substr($authData, $offset));
            $result['publicKey']  = self::coseKeyToPem($coseKey);
        }

        return $result;
    }

    // ── Internal: COSE public key → PEM ────────────────────────────────────

    private static function coseKeyToPem(array $coseKey): string
    {
        $kty = $coseKey[1] ?? null;

        if ($kty === 2) {
            // EC2 key (ES256 — P-256)
            $x = $coseKey[-2] ?? null;
            $y = $coseKey[-3] ?? null;
            if ($x === null || $y === null) throw new Exception('WebAuthn: missing EC key coords');

            $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
            $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);

            // DER SEQUENCE { SEQUENCE { OID ecPublicKey, OID prime256v1 }, BIT STRING { 04 x y } }
            $der =
                "\x30\x59"
                . "\x30\x13"
                . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"      // OID ecPublicKey
                . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"  // OID prime256v1
                . "\x03\x42\x00\x04"
                . $x . $y;

            return "-----BEGIN PUBLIC KEY-----\n"
                . chunk_split(base64_encode($der), 64, "\n")
                . "-----END PUBLIC KEY-----";
        }

        if ($kty === 3) {
            // RSA key (RS256)
            $n = $coseKey[-1] ?? null;
            $e = $coseKey[-2] ?? null;
            if ($n === null || $e === null) throw new Exception('WebAuthn: missing RSA key components');

            $nDer   = self::derUint($n);
            $eDer   = self::derUint($e);
            $rsaSeq = "\x30" . self::derLen(strlen($nDer) + strlen($eDer)) . $nDer . $eDer;

            $inner  = "\x03" . self::derLen(strlen($rsaSeq) + 1) . "\x00" . $rsaSeq;
            $alg    = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
            $der    = "\x30" . self::derLen(strlen($alg) + strlen($inner)) . $alg . $inner;

            return "-----BEGIN PUBLIC KEY-----\n"
                . chunk_split(base64_encode($der), 64, "\n")
                . "-----END PUBLIC KEY-----";
        }

        throw new Exception('WebAuthn: unsupported COSE key type: ' . $kty);
    }

    // ── Internal: minimal CBOR decoder ─────────────────────────────────────

    private static function cborDecode(string $data, int &$offset = 0): mixed
    {
        if ($offset >= strlen($data)) throw new Exception('CBOR: unexpected end of data');

        $byte      = ord($data[$offset++]);
        $majorType = ($byte & 0xe0) >> 5;
        $addInfo   = $byte & 0x1f;

        if ($addInfo < 24) {
            $value = $addInfo;
        } elseif ($addInfo === 24) {
            $value = ord($data[$offset++]);
        } elseif ($addInfo === 25) {
            $value = unpack('n', substr($data, $offset, 2))[1]; $offset += 2;
        } elseif ($addInfo === 26) {
            $value = unpack('N', substr($data, $offset, 4))[1]; $offset += 4;
        } elseif ($addInfo === 27) {
            $hi = unpack('N', substr($data, $offset, 4))[1]; $offset += 4;
            $lo = unpack('N', substr($data, $offset, 4))[1]; $offset += 4;
            $value = $hi * 4294967296 + $lo;
        } else {
            $value = 0;
        }

        switch ($majorType) {
            case 0: return $value;                              // uint
            case 1: return -1 - $value;                        // negint
            case 2:                                            // bytes
                $r = substr($data, $offset, $value); $offset += $value; return $r;
            case 3:                                            // text
                $r = substr($data, $offset, $value); $offset += $value; return $r;
            case 4:                                            // array
                $arr = [];
                for ($i = 0; $i < $value; $i++) $arr[] = self::cborDecode($data, $offset);
                return $arr;
            case 5:                                            // map
                $map = [];
                for ($i = 0; $i < $value; $i++) {
                    $k = self::cborDecode($data, $offset);
                    $v = self::cborDecode($data, $offset);
                    $map[$k] = $v;
                }
                return $map;
            case 6: return self::cborDecode($data, $offset);   // tagged
            case 7:
                if ($addInfo === 20) return false;
                if ($addInfo === 21) return true;
                if ($addInfo === 22) return null;
                return $value;
        }
        throw new Exception('CBOR: unknown major type ' . $majorType);
    }

    // ── Internal: DER helpers ───────────────────────────────────────────────

    private static function derLen(int $len): string
    {
        if ($len < 128)  return chr($len);
        if ($len < 256)  return "\x81" . chr($len);
        return "\x82" . chr($len >> 8) . chr($len & 0xff);
    }

    private static function derUint(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') $bytes = "\x00";
        if (ord($bytes[0]) >= 0x80) $bytes = "\x00" . $bytes;
        return "\x02" . self::derLen(strlen($bytes)) . $bytes;
    }
}
