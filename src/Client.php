<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side verification against the CaptchaCore API (same behaviour as
 * the captchacore/laravel client, kept here so the addon installs from
 * Packagist without further packages).
 */
class Client
{
    /** The widget always submits its token under this name. */
    public const TOKEN_FIELD = 'captchacore_token';

    private static ?VerificationResult $fakeResult = null;

    public function __construct(
        private readonly string $serviceUrl,
        private readonly string $secretKey,
        private readonly int $timeout = 3,
        private readonly bool $failOpen = true,
    ) {}

    public function verify(?string $token, string $formType = 'custom'): VerificationResult
    {
        // Fake mode for testing
        if (self::$fakeResult !== null) {
            return self::$fakeResult;
        }

        $result = $this->request($token, $formType);

        if (! $result->valid) {
            // Reasons and score only go to the log (never the token itself):
            // returning them would tell a bot which detection fired.
            Log::info('CaptchaCore verification failed', [
                'error' => $result->error,
                'reasons' => $result->reasons,
                'risk_score' => $result->riskScore,
                'form_type' => $formType,
            ]);
        }

        return $result;
    }

    private function request(?string $token, string $formType): VerificationResult
    {
        if ($token === null || $token === '') {
            return new VerificationResult(false, 100, 'block', 'empty_token');
        }

        try {
            // Keine Weiterleitungen: Guzzle gaebe den eigenen Header
            // X-CaptchaCore-Key (Secret Key) an ein fremdes Ziel weiter, und
            // aus dem POST wuerde ein GET.
            $response = Http::timeout($this->timeout)
                ->withoutRedirecting()
                ->acceptJson()
                ->withHeaders([
                    'X-CaptchaCore-Key' => $this->secretKey,
                    // V2.1: Forward client headers for server-side bot detection
                    'X-Forwarded-Sec-Fetch-Site'  => (string) request()->header('Sec-Fetch-Site', ''),
                    'X-Forwarded-Accept-Language' => (string) request()->header('Accept-Language', ''),
                ])
                ->post(rtrim($this->serviceUrl, '/') . '/api/v2/verify', [
                    'token' => $token,
                    'form_type' => $formType,
                    'client_ip' => request()->ip(),
                    // Ohne Query-String (Datensparsamkeit, Art. 25 DSGVO):
                    // Parameter wie E-Mail-Adressen oder signierte Links
                    // verlassen den Server nicht.
                    'page_url' => mb_substr(request()->url(), 0, 500),
                ]);

            if (! $response->successful()) {
                // Fail-open only while the service is disturbed (5xx, timeout,
                // overload). A 4xx means it answered and rejected the request —
                // passing that through would let a crafted token slip past.
                $status = $response->status();
                if ($response->redirect()) {
                    Log::warning('CaptchaCore service URL redirects — set CAPTCHACORE_URL to the final https address', [
                        'status' => $status,
                        'location' => $response->header('Location'),
                    ]);
                }
                // 429 is no outage (exhausting the limit with junk must not let requests through)
                if ($status < 500 && $status !== 408) {
                    Log::warning('CaptchaCore verify rejected the request', ['status' => $status, 'form_type' => $formType]);

                    return new VerificationResult(false, 100, 'block', 'http_' . $status);
                }

                return $this->handleFailure('http_' . $status, $formType);
            }

            // Eine 200 ohne verwertbares JSON (Wartungs-, WAF- oder
            // Challenge-Seite einer Zwischenschicht) ist kein Ausfall des
            // Dienstes: blockieren statt Fail-Open (nur 5xx/408/Timeout).
            $data = $response->json();
            if (! is_array($data) || ! array_key_exists('valid', $data)) {
                Log::warning('CaptchaCore verify returned an unreadable response', ['status' => $response->status(), 'form_type' => $formType]);

                return new VerificationResult(false, 100, 'block', 'invalid_response');
            }

            // Compatible with both V1 and V2 response formats
            return new VerificationResult(
                valid: (bool) ($data['valid'] ?? false),
                riskScore: (int) ($data['risk_score'] ?? 100),
                action: (string) ($data['decision'] ?? $data['action'] ?? 'block'),
                confidence: isset($data['confidence']) ? (float) $data['confidence'] : null,
                reasons: (array) ($data['reasons'] ?? []),
                stepUp: isset($data['step_up']) && is_array($data['step_up']) ? $data['step_up'] : null,
            );
        } catch (\Throwable $e) {
            return $this->handleFailure('service_error', $formType, $e->getMessage());
        }
    }

    private function handleFailure(string $reason, string $formType, ?string $detail = null): VerificationResult
    {
        Log::warning($this->failOpen
            ? 'CaptchaCore unreachable, request let through (fail-open)'
            : 'CaptchaCore unreachable, request blocked (fail-closed)', array_filter([
            'reason' => $reason,
            'error' => $detail,
            'form_type' => $formType,
        ]));

        if ($this->failOpen) {
            return new VerificationResult(true, 0, 'allow', $reason);
        }

        return new VerificationResult(false, 100, 'block', $reason);
    }

    /**
     * Enable fake mode for testing — all verify() calls return this result.
     * Reset automatically whenever the application boots (i.e. per test).
     */
    public static function fake(?VerificationResult $result = null): void
    {
        self::$fakeResult = $result ?? new VerificationResult(true, 0, 'allow');
    }

    /**
     * Disable fake mode.
     */
    public static function clearFake(): void
    {
        self::$fakeResult = null;
    }
}
