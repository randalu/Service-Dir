<?php

class TOTP {
    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getQRCodeURL($username, $secret, $issuer = 'RandaluWebs') {
        $label = rawurlencode("$issuer:$username");
        $params = "secret=$secret&issuer=" . rawurlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
        return "otpauth://totp/$label?$params";
    }

    public static function getQRCodeImage($username, $secret, $issuer = 'RandaluWebs') {
        $url = self::getQRCodeURL($username, $secret, $issuer);
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . rawurlencode($url);
    }

    public static function verifyCode($secret, $code) {
        $secret = strtoupper($secret);
        $secret = str_replace(' ', '', $secret);
        $decoded = self::base32Decode($secret);
        if ($decoded === false) return false;

        $timeSlice = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $expected = self::generateTOTP($decoded, $timeSlice + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    public static function generateRecoveryCodes($count = 5) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = bin2hex(random_bytes(4));
        }
        return $codes;
    }

    private static function generateTOTP($key, $timeSlice) {
        $counter = pack('N*', 0) . pack('N', $timeSlice);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24) |
                  ((ord($hash[$offset + 1]) & 0xff) << 16) |
                  ((ord($hash[$offset + 2]) & 0xff) << 8) |
                  (ord($hash[$offset + 3]) & 0xff);
        $otp = $binary % 1000000;
        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $output .= chr(($buffer >> ($bitsLeft - 8)) & 0xff);
                $bitsLeft -= 8;
                $buffer &= (1 << $bitsLeft) - 1;
            }
        }
        return $output;
    }
}
