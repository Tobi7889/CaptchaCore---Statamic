<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Tags;

use CaptchaCore\Statamic\ServiceProvider;
use CaptchaCore\Statamic\Forms;
use Statamic\Tags\Tags;

/**
 * {{ captchacore }} — place it inside {{ form:create }}. The form type follows
 * the form's handle (config captchacore-statamic.form_types); outside a form
 * tag, or to override it, pass form="handle" or form_type="…".
 *
 * Optional: mode="visible|interactive|invisible", lang="de", nonce="…".
 */
class CaptchaCore extends Tags
{
    protected static $handle = 'captchacore';

    public function index(): string
    {
        $handle = $this->params->get('form') ?? $this->formHandleFromContext();
        $formType = $this->params->get('form_type') ?? Forms::formType(is_string($handle) ? $handle : null);

        $widget = (array) config('captchacore-statamic.widget', []);
        $cdn = $widget['cdn_url'] ?? null;
        if (! is_string($cdn) || $cdn === '') {
            $cdn = (($widget['endpoint'] ?? 'eu') === 'global' ? 'https://src.captchacore.eu' : 'https://src-eu.captchacore.eu') . '/widget';
        }

        return view('captchacore-statamic::widget', [
            'formType' => preg_match('/^[a-z0-9_-]{1,50}$/', (string) $formType) === 1 ? (string) $formType : 'custom',
            'serviceUrl' => rtrim((string) config('captchacore-statamic.service_url', 'https://api.captchacore.eu'), '/'),
            'siteKey' => (string) config('captchacore-statamic.site_key', ''),
            'scriptUrl' => rtrim($cdn, '/') . '/captchacore-v2.min.js?v=' . ServiceProvider::VERSION,
            // Strict CSP: the widget copies this nonce onto the <style> it injects
            'nonce' => $this->stringParam('nonce') ?? $this->viteNonce(),
            // Only configured values become data attributes; empty = customer area
            'options' => array_filter([
                'mode' => $this->stringParam('mode') ?? (string) ($widget['mode'] ?? ''),
                'theme' => (string) ($widget['theme'] ?? ''),
                'color' => (string) ($widget['color'] ?? ''),
                'label' => (string) ($widget['label'] ?? ''),
                'brand' => (string) ($widget['brand'] ?? ''),
                'size' => (string) ($widget['size'] ?? ''),
                'lang' => $this->stringParam('lang') ?? (string) ($widget['lang'] ?? ''),
            ], static fn (string $v): bool => $v !== ''),
        ])->render();
    }

    private function viteNonce(): ?string
    {
        if (! class_exists(\Illuminate\Foundation\Vite::class)) {
            return null;
        }
        $nonce = app(\Illuminate\Foundation\Vite::class)->cspNonce();

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }

    private function formHandleFromContext(): ?string
    {
        $handle = $this->context->get('captchacore_form');

        return is_string($handle) && $handle !== '' ? $handle : null;
    }

    private function stringParam(string $name): ?string
    {
        $value = $this->params->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
