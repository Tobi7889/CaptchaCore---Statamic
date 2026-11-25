<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic;

/**
 * Settings page in the Control Panel (Statamic 6: Tools → Addons → CaptchaCore).
 * Statamic stores the values in resources/addons/captchacore-statamic.yaml;
 * apply() lays them over the config: a filled-in field wins over .env, an
 * empty one keeps the .env/config value (no field has a default for that).
 *
 * The secret key is deliberately not editable here: the YAML file usually
 * goes into the site's Git repository. It stays in CAPTCHACORE_SECRET_KEY.
 */
final class ControlPanelSettings
{
    public const FORM_TYPES = ['contact', 'register', 'login', 'comment', 'checkout', 'custom'];

    /** @return array<string, mixed> */
    public static function blueprint(): array
    {
        $t = static fn (string $key): string => __('captchacore-statamic::settings.' . $key);
        $options = static fn (array $keys, string $prefix): array => array_combine($keys, array_map(static fn ($k) => $t($prefix . '.' . $k), $keys));

        return [
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'display' => $t('connection'),
                            'fields' => [
                                ['handle' => 'status', 'field' => ['type' => 'info', 'content' => self::statusText(), 'state' => self::isConfigured() ? 'success' : 'warning']],
                                ['handle' => 'site_key', 'field' => ['type' => 'text', 'display' => 'Site Key', 'instructions' => $t('site_key_help'), 'placeholder' => self::envValue('captchacore-statamic.site_key') ?: 'cc_pub_…']],
                                ['handle' => 'service_url', 'field' => ['type' => 'text', 'display' => $t('service_url'), 'instructions' => $t('service_url_help'), 'placeholder' => self::envValue('captchacore-statamic.service_url') ?: 'https://api.captchacore.eu']],
                            ],
                        ],
                        [
                            'display' => $t('forms'),
                            'fields' => [
                                ['handle' => 'protect', 'field' => ['type' => 'select', 'display' => $t('protect'), 'instructions' => $t('protect_help'), 'clearable' => true, 'placeholder' => $t('from_env'), 'options' => ['all' => $t('protect_all'), 'selected' => $t('protect_selected')]]],
                                ['handle' => 'forms', 'field' => ['type' => 'form', 'display' => $t('selected_forms'), 'mode' => 'select', 'if' => ['protect' => 'selected']]],
                                ['handle' => 'default_form_type', 'field' => ['type' => 'select', 'display' => $t('default_form_type'), 'instructions' => $t('form_type_help'), 'clearable' => true, 'placeholder' => $t('from_env'), 'options' => $options(self::FORM_TYPES, 'types')]],
                                ['handle' => 'form_types', 'field' => ['type' => 'grid', 'display' => $t('form_types'), 'instructions' => $t('form_types_help'), 'mode' => 'table', 'add_row' => $t('add_form_type'), 'fields' => [
                                    ['handle' => 'form', 'field' => ['type' => 'form', 'display' => $t('form'), 'max_items' => 1, 'mode' => 'select']],
                                    ['handle' => 'type', 'field' => ['type' => 'select', 'display' => $t('form_type'), 'default' => 'register', 'options' => $options(self::FORM_TYPES, 'types')]],
                                ]]],
                                ['handle' => 'fail_open', 'field' => ['type' => 'select', 'display' => $t('fail_open'), 'instructions' => $t('fail_open_help'), 'clearable' => true, 'placeholder' => $t('from_env'), 'options' => ['open' => $t('fail_open_open'), 'closed' => $t('fail_open_closed')]]],
                            ],
                        ],
                        [
                            'display' => $t('widget'),
                            'instructions' => $t('widget_help'),
                            'fields' => [
                                ['handle' => 'widget_mode', 'field' => ['type' => 'select', 'display' => $t('mode'), 'clearable' => true, 'placeholder' => $t('from_customer_area'), 'width' => 50, 'options' => $options(['interactive', 'visible', 'invisible'], 'modes')]],
                                ['handle' => 'widget_theme', 'field' => ['type' => 'select', 'display' => $t('theme'), 'clearable' => true, 'placeholder' => $t('from_customer_area'), 'width' => 50, 'options' => $options(['auto', 'light', 'dark'], 'themes')]],
                                ['handle' => 'widget_size', 'field' => ['type' => 'select', 'display' => $t('size'), 'clearable' => true, 'placeholder' => $t('from_customer_area'), 'width' => 50, 'options' => $options(['normal', 'compact'], 'sizes')]],
                                ['handle' => 'widget_lang', 'field' => ['type' => 'select', 'display' => $t('lang'), 'clearable' => true, 'placeholder' => $t('from_page'), 'width' => 50, 'options' => ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands', 'pl' => 'Polski']]],
                                ['handle' => 'widget_endpoint', 'field' => ['type' => 'select', 'display' => $t('endpoint'), 'instructions' => $t('endpoint_help'), 'clearable' => true, 'placeholder' => $t('from_env'), 'options' => ['eu' => $t('endpoint_eu'), 'global' => $t('endpoint_global')]]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lay the saved settings over the config. Only filled-in values count.
     *
     * @param array<string, mixed> $s
     */
    public static function apply(array $s): void
    {
        $config = [];

        foreach (['site_key' => 'captchacore-statamic.site_key', 'service_url' => 'captchacore-statamic.service_url', 'widget_mode' => 'captchacore-statamic.widget.mode',
            'widget_theme' => 'captchacore-statamic.widget.theme', 'widget_size' => 'captchacore-statamic.widget.size', 'widget_lang' => 'captchacore-statamic.widget.lang',
            'widget_endpoint' => 'captchacore-statamic.widget.endpoint'] as $field => $key) {
            $value = self::string($s[$field] ?? null);
            if ($value !== null) {
                $config[$key] = $value;
            }
        }
        if (isset($config['captchacore-statamic.service_url']) && ! preg_match('#^https?://#i', $config['captchacore-statamic.service_url'])) {
            unset($config['captchacore-statamic.service_url']);
        }

        $failOpen = self::string($s['fail_open'] ?? null);
        if ($failOpen === 'open' || $failOpen === 'closed') {
            $config['captchacore-statamic.fail_open'] = $failOpen === 'open';
        }

        if (($s['protect'] ?? null) === 'selected') {
            // No form chosen: protect none rather than fall back to all
            $config['captchacore-statamic.forms'] = self::handles($s['forms'] ?? []) ?: ['-'];
        } elseif (($s['protect'] ?? null) === 'all') {
            $config['captchacore-statamic.forms'] = '*';
        }

        $default = self::string($s['default_form_type'] ?? null);
        if ($default !== null) {
            $config['captchacore-statamic.default_form_type'] = $default;
        }

        $types = [];
        foreach ((array) ($s['form_types'] ?? []) as $row) {
            $handle = self::handles(is_array($row) ? ($row['form'] ?? null) : null)[0] ?? null;
            $type = self::string(is_array($row) ? ($row['type'] ?? null) : null);
            if ($handle !== null && $type !== null) {
                $types[$handle] = $type;
            }
        }
        if ($types !== []) {
            $config['captchacore-statamic.form_types'] = array_merge((array) config('captchacore-statamic.form_types', []), $types);
        }

        config($config);
    }

    public static function isConfigured(): bool
    {
        return (string) config('captchacore-statamic.site_key') !== '' && (string) config('captchacore-statamic.secret_key') !== '';
    }

    private static function statusText(): string
    {
        $t = static fn (string $key, array $r = []): string => __('captchacore-statamic::settings.' . $key, $r);
        $secret = (string) config('captchacore-statamic.secret_key');

        return implode("\n\n", [
            $secret !== '' ? $t('secret_set', ['prefix' => substr($secret, 0, 11) . '…']) : $t('secret_missing'),
            (string) config('captchacore-statamic.site_key') !== '' ? $t('site_key_set') : $t('site_key_missing'),
        ]);
    }

    /** Value from .env/config before this page's settings were applied. */
    private static function envValue(string $key): string
    {
        $env = ['captchacore-statamic.site_key' => 'CAPTCHACORE_SITE_KEY', 'captchacore-statamic.service_url' => 'CAPTCHACORE_URL'][$key] ?? null;

        return $env !== null ? (string) env($env, '') : '';
    }

    /** @return list<string> */
    private static function handles(mixed $value): array
    {
        $handles = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_string($item) && preg_match('/^[A-Za-z0-9_-]+$/', $item)) {
                $handles[] = $item;
            }
        }

        return $handles;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
