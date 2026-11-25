<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic;

/**
 * Which Statamic forms are protected and which form type they report.
 * Tag and listener both read it here, so widget and verify always agree.
 */
final class Forms
{
    public static function protects(?string $handle): bool
    {
        $forms = config('captchacore-statamic.forms', '*');
        if (is_string($forms)) {
            $forms = array_map('trim', explode(',', $forms));
        }
        $forms = array_values(array_filter((array) $forms, static fn ($f): bool => is_string($f) && $f !== ''));

        return in_array('*', $forms, true) || ($handle !== null && in_array($handle, $forms, true));
    }

    public static function formType(?string $handle): string
    {
        $types = (array) config('captchacore-statamic.form_types', []);
        $type = $handle !== null ? ($types[$handle] ?? null) : null;
        if (! is_string($type) || $type === '') {
            $type = (string) config('captchacore-statamic.default_form_type', 'contact');
        }

        // Same rule as the service (form_type: [a-z0-9_-], max 50)
        return preg_match('/^[a-z0-9_-]{1,50}$/', $type) === 1 ? $type : 'custom';
    }
}
