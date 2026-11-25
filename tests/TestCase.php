<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Tests;

use CaptchaCore\Statamic\ServiceProvider;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('captchacore-statamic.service_url', 'https://captcha.test');
        $app['config']->set('captchacore-statamic.secret_key', 'cc_sec_test');
        $app['config']->set('captchacore-statamic.site_key', 'cc_pub_test');
        $app['config']->set('statamic.forms.forms', __DIR__ . '/__fixtures__/dev-null/forms');
    }

    protected function tearDown(): void
    {
        // Settings saved through the CP page land in the testbench app
        @unlink(resource_path('addons/captchacore-statamic.yaml'));

        parent::tearDown();
    }

    protected function makeForm(string $handle): void
    {
        Blueprint::make($handle)->setNamespace('forms')->setContents([
            'fields' => [
                ['handle' => 'name', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ],
        ])->save();

        Form::make($handle)->title(ucfirst($handle))->store(true)->save();
    }

    protected function submissions(string $handle): int
    {
        return Form::find($handle)->submissions()->count();
    }
}
