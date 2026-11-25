<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic;

use CaptchaCore\Statamic\Listeners\VerifyFormSubmission;
use Statamic\Events\FormSubmitted;
use Statamic\Forms\Tags as FormTags;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public const VERSION = '1.2.0';

    protected $tags = [
        Tags\CaptchaCore::class,
    ];

    // Statamic would also discover it in Listeners/ (and skips duplicates)
    protected $listen = [
        FormSubmitted::class => [
            VerifyFormSubmission::class,
        ],
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/captchacore-statamic.php', 'captchacore-statamic');

        // Config is read on first use: the CP settings are applied by then
        $this->app->singleton(Client::class, fn () => new Client(
            serviceUrl: (string) config('captchacore-statamic.service_url', 'https://api.captchacore.eu'),
            secretKey: (string) config('captchacore-statamic.secret_key', ''),
            timeout: (int) config('captchacore-statamic.timeout', 3),
            failOpen: (bool) config('captchacore-statamic.fail_open', true),
        ));
        // A fake from a previous test must not leak into the next one
        Client::clearFake();
    }

    public function bootAddon(): void
    {
        // Registered explicitly: Statamic only autoloads these for some addon setups
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'captchacore-statamic');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'captchacore-statamic');

        // Settings page in the Control Panel — Statamic 6 only; on 5 the
        // configuration stays in .env / config/captchacore-statamic.php
        if (method_exists($this, 'registerSettingsBlueprint')) {
            $this->registerSettingsBlueprint(fn () => ControlPanelSettings::blueprint());
        }
        $addon = $this->getAddon();
        if (method_exists($addon, 'settings')) {
            ControlPanelSettings::apply($addon->settings()->all());
            // Reading the settings built the blueprint, still in the boot
            // locale: build it again for the page, in the user's CP language
            $this->app->forgetInstance("statamic.addons.{$addon->slug()}.settings_blueprint");
        }

        // Tell {{ captchacore }} which form it sits in: the form tag opens the
        // <form> (after-open) before it renders its content with this context.
        FormTags::hook('after-open', function ($payload, $next) {
            $handle = $this->formHandle();
            $this->context['captchacore_form'] = is_string($handle) ? $handle : null;

            return $next($payload);
        });

        $this->publishes([
            __DIR__ . '/../config/captchacore-statamic.php' => config_path('captchacore-statamic.php'),
        ], 'captchacore-statamic-config');
    }
}
