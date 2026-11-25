<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Tests;

use CaptchaCore\Statamic\Client;
use CaptchaCore\Statamic\ControlPanelSettings;
use CaptchaCore\Statamic\Forms;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Addon;
use Statamic\Facades\User;

class ControlPanelSettingsTest extends TestCase
{
    #[Test]
    public function empty_fields_keep_the_env_values(): void
    {
        config([
            'captchacore-statamic.site_key' => 'cc_pub_env',
            'captchacore-statamic.fail_open' => true,
            'captchacore-statamic.forms' => 'contact,newsletter',
            'captchacore-statamic.default_form_type' => 'register',
        ]);

        ControlPanelSettings::apply(['site_key' => '', 'protect' => null, 'fail_open' => null, 'form_types' => []]);

        $this->assertSame('cc_pub_env', config('captchacore-statamic.site_key'));
        $this->assertTrue(config('captchacore-statamic.fail_open'));
        $this->assertSame('contact,newsletter', config('captchacore-statamic.forms'));
        $this->assertSame('register', config('captchacore-statamic.default_form_type'));
    }

    #[Test]
    public function filled_fields_win_over_env(): void
    {
        ControlPanelSettings::apply([
            'site_key' => ' cc_pub_cp ',
            'service_url' => 'https://captcha.example.org',
            'protect' => 'selected',
            'forms' => ['contact'],
            'default_form_type' => 'comment',
            'form_types' => [['id' => 'x', 'form' => 'newsletter', 'type' => 'register'], ['form' => ['signup'], 'type' => 'login']],
            'fail_open' => 'closed',
            'widget_mode' => 'visible',
            'widget_lang' => 'fr',
            'widget_endpoint' => 'global',
        ]);

        $this->assertSame('cc_pub_cp', config('captchacore-statamic.site_key'));
        $this->assertSame('https://captcha.example.org', config('captchacore-statamic.service_url'));
        $this->assertFalse(config('captchacore-statamic.fail_open'));
        $this->assertSame('visible', config('captchacore-statamic.widget.mode'));
        $this->assertSame('fr', config('captchacore-statamic.widget.lang'));
        $this->assertSame('global', config('captchacore-statamic.widget.endpoint'));
        $this->assertTrue(Forms::protects('contact'));
        $this->assertFalse(Forms::protects('newsletter'));
        $this->assertSame('register', Forms::formType('newsletter'));
        $this->assertSame('login', Forms::formType('signup'));
        $this->assertSame('comment', Forms::formType('contact'));
    }

    #[Test]
    public function selected_without_forms_protects_none_and_bad_values_are_ignored(): void
    {
        ControlPanelSettings::apply(['protect' => 'selected', 'forms' => [], 'service_url' => 'javascript:alert(1)', 'fail_open' => 'maybe', 'form_types' => [['form' => '../x', 'type' => 'register']]]);

        $this->assertFalse(Forms::protects('contact'));
        $this->assertSame('https://captcha.test', config('captchacore-statamic.service_url'));
        $this->assertTrue(config('captchacore-statamic.fail_open'));
        $this->assertSame([], config('captchacore-statamic.form_types'));

        ControlPanelSettings::apply(['protect' => 'all']);
        $this->assertTrue(Forms::protects('anything'));
    }

    #[Test]
    public function the_secret_key_is_never_part_of_the_page(): void
    {
        config(['captchacore-statamic.secret_key' => 'cc_sec_0123456789abcdef0123456789abcdef']);
        $fields = collect(ControlPanelSettings::blueprint()['tabs']['main']['sections'])->flatMap(fn ($s) => $s['fields'])->pluck('handle');

        $this->assertNotContains('secret_key', $fields->all());
        $json = json_encode(ControlPanelSettings::blueprint());
        $this->assertStringContainsString('cc_sec_0123', $json); // only a short prefix as status
        $this->assertStringNotContainsString('cc_sec_01234567', $json);
    }

    #[Test]
    public function the_settings_page_saves_and_the_values_apply_on_the_next_request(): void
    {
        if (! method_exists(\Statamic\Addons\Addon::class, 'settings')) {
            $this->markTestSkipped('Addon settings exist from Statamic 6 on');
        }
        $this->makeForm('contact');
        $user = tap(User::make()->email('admin@example.test')->makeSuper())->save();

        $this->actingAs($user)->get('/cp/addons/captchacore-statamic/settings')->assertOk();
        $this->actingAs($user)->patchJson('/cp/addons/captchacore-statamic/settings', [
            'protect' => 'selected', 'forms' => ['contact'], 'fail_open' => 'closed', 'site_key' => 'cc_pub_saved',
        ])->assertSuccessful();

        $saved = Addon::get('captchacore/statamic')->settings()->raw();
        $this->assertSame('selected', $saved['protect']);
        $this->assertSame(['contact'], $saved['forms']);
        $this->assertArrayNotHasKey('secret_key', $saved);

        // The provider lays the stored values over the config at boot
        ControlPanelSettings::apply(Addon::get('captchacore/statamic')->settings()->all());
        $this->assertSame('cc_pub_saved', config('captchacore-statamic.site_key'));
        $this->assertFalse(config('captchacore-statamic.fail_open'));

        $this->app->forgetInstance(Client::class);
        Http::fake(['captcha.test/*' => Http::response('', 503)]);
        $this->post('/!/forms/contact', ['name' => 'Max', 'captchacore_token' => 'token'])
            ->assertSessionHasErrors(['captchacore'], null, 'form.contact');
    }

    #[Test]
    public function every_language_has_every_text(): void
    {
        $keys = fn (string $lang, string $file) => array_keys(\Illuminate\Support\Arr::dot(require __DIR__ . "/../lang/{$lang}/{$file}.php"));

        foreach (['settings', 'messages'] as $file) {
            foreach (['de', 'fr', 'es'] as $lang) {
                $this->assertSame([], array_values(array_diff($keys('en', $file), $keys($lang, $file))), "{$lang}/{$file}.php");
            }
        }
    }
}
