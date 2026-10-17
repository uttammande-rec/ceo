<?php
/**
 * StudentOrbit — minimal self-contained SMTP mailer.
 *
 * No Composer / PHPMailer dependency: speaks just enough SMTP (EHLO, STARTTLS,
 * AUTH LOGIN, MAIL/RCPT/DATA) to send a plain-text message through a provider
 * such as Gmail. Credentials come from config['smtp'] (env-driven).
 *
 * Gmail note: use an **App Password** (needs 2-Step Verification), never the
 * account password. Host smtp.gmail.com, port 587 (secure=tls / STARTTLS) or
 * port 465 (secure=ssl).
 */

declare(strict_types=1);

class Mailer
{
    /** @return array{host:string,port:int,secure:string,user:string,pass:string,from:string,from_name:string} */
    private static function cfg(): array
    {
        $cfg = require __DIR__ . '/../config/config.php';
        return $cfg['smtp'];
    }

    /** True when SMTP user+pass are configured so sending can actually work. */
    public static function isConfigured(): bool
    {
        $s = self::cfg();
        return $s['user'] !== '' && $s['pass'] !== '';
    }

    /**
     * Send a plain-text email. Throws RuntimeException on any SMTP failure.
     */
    public static function send(string $toEmail, string $subject, string $body): void
    {
        $s = self::cfg();
        if (!self::isConfigured()) {
            throw new RuntimeException('SMTP is not configured (set SO_SMTP_USER / SO_SMTP_PASS).');
        }

        $transport = ($s['secure'] === 'ssl') ? 'ssl://' : 'tcp://';
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(
            "{$transport}{$s['host']}:{$s['port']}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT
        );
        if (!$fp) {
            throw new RuntimeException("Cannot connect to SMTP {$s['host']}:{$s['port']} — {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp): array {
            // Read a (possibly multi-line) SMTP reply; return [code, text].
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                // Final line has a space after the 3-digit code: "250 OK"
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return [(int) substr($data, 0, 3), trim($data)];
        };
        $write = function (string $cmd) use ($fp): void {
            fwrite($fp, $cmd . "\r\n");
        };
        $expect = function (int $got, array $ok, string $text, string $stage) use ($fp): void {
            if (!in_array($got, $ok, true)) {
                fclose($fp);
                throw new RuntimeException("SMTP {$stage} failed: {$text}");
            }
        };

        [$code, $text] = $read();
        $expect($code, [220], $text, 'greeting');

        $ehloHost = $s['host'];
        $write("EHLO {$ehloHost}");
        [$code, $text] = $read();
        $expect($code, [250], $text, 'EHLO');

        if ($s['secure'] === 'tls') {
            $write('STARTTLS');
            [$code, $text] = $read();
            $expect($code, [220], $text, 'STARTTLS');
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($fp, true, $crypto)) {
                fclose($fp);
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }
            // Must re-issue EHLO after the TLS upgrade.
            $write("EHLO {$ehloHost}");
            [$code, $text] = $read();
            $expect($code, [250], $text, 'EHLO (post-TLS)');
        }

        $write('AUTH LOGIN');
        [$code, $text] = $read();
        $expect($code, [334], $text, 'AUTH');
        $write(base64_encode($s['user']));
        [$code, $text] = $read();
        $expect($code, [334], $text, 'AUTH user');
        $write(base64_encode($s['pass']));
        [$code, $text] = $read();
        $expect($code, [235], $text, 'AUTH password');

        $from     = $s['from'] !== '' ? $s['from'] : $s['user'];
        $fromName  = $s['from_name'] !== '' ? $s['from_name'] : 'StudentOrbit';

        $write("MAIL FROM:<{$from}>");
        [$code, $text] = $read();
        $expect($code, [250], $text, 'MAIL FROM');

        $write("RCPT TO:<{$toEmail}>");
        [$code, $text] = $read();
        $expect($code, [250, 251], $text, 'RCPT TO');

        $write('DATA');
        [$code, $text] = $read();
        $expect($code, [354], $text, 'DATA');

        $headers = [
            'From: ' . self::encodeHeader($fromName) . " <{$from}>",
            "To: <{$toEmail}>",
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        // Dot-stuff any line that begins with "." per RFC 5321.
        $safeBody = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $body));
        $safeBody = str_replace("\n", "\r\n", $safeBody);
        $message  = implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.";
        $write($message);
        [$code, $text] = $read();
        $expect($code, [250], $text, 'message body');

        $write('QUIT');
        fclose($fp);
    }

    private static function encodeHeader(string $value): string
    {
        // RFC 2047 encode only when non-ASCII is present.
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
