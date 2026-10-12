<?php

namespace App\Services;

use Illuminate\Support\Str;

class TwoFactorAuthenticationService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new Base32 secret key.
     */
    public function generateSecretKey(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Generate the standard otpauth URL for Google Authenticator / Authy.
     */
    public function getQrCodeUrl(string $company, string $email, string $secret): string
    {
        $label = rawurlencode($company).':'.rawurlencode($email);

        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode($company).'&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Verify a 6-digit TOTP code against the secret key within a time window.
     */
    public function verifyKey(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $secretBinary = $this->base32Decode($secret);
        if ($secretBinary === null) {
            return false;
        }

        $currentTime = floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $timeSlot = $currentTime + $offset;
            $packedTime = pack('N*', 0).pack('N*', $timeSlot);
            $hash = hash_hmac('sha1', $packedTime, $secretBinary, true);

            $dynamicOffset = ord($hash[19]) & 0x0F;
            $truncated = (
                ((ord($hash[$dynamicOffset]) & 0x7F) << 24) |
                ((ord($hash[$dynamicOffset + 1]) & 0xFF) << 16) |
                ((ord($hash[$dynamicOffset + 2]) & 0xFF) << 8) |
                (ord($hash[$dynamicOffset + 3]) & 0xFF)
            );

            $calculatedCode = str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an array of single-use recovery codes.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $amount = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $amount; $i++) {
            $codes[] = sprintf(
                '%s-%s-%s',
                Str::upper(Str::random(4)),
                Str::upper(Str::random(4)),
                Str::upper(Str::random(4))
            );
        }

        return $codes;
    }

    /**
     * Decode a Base32 string into raw binary.
     */
    private function base32Decode(string $secret): ?string
    {
        $secret = strtoupper(trim($secret));
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            $val = strpos(self::BASE32_CHARS, $char);

            if ($val === false) {
                return null;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }
}
