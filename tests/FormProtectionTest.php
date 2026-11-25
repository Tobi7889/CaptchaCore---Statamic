<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Tests;

use CaptchaCore\Statamic\Client;
use CaptchaCore\Statamic\VerificationResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Form;
use Statamic\Facades\Site;

class FormProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeForm('contact');
    }

    private function submit(array $data = [], array $headers = [])
    {
        return $this->withHeaders($headers)->post('/!/forms/contact', array_merge(['name' => 'Max'], $data));
    }

    #[Test]
    public function a_valid_token_stores_the_submission(): void
    {
        Client::fake(new VerificationResult(true, 5, 'allow'));

        $this->submit(['captchacore_token' => 'token'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->submissions('contact'));
    }

    #[Test]
    public function a_rejected_token_shows_an_error_and_stores_nothing(): void
    {
        Client::fake(new VerificationResult(false, 90, 'block'));

        $this->submit(['captchacore_token' => 'token'])
            ->assertSessionHasErrors(['captchacore'], null, 'form.contact');

        $this->assertSame(0, $this->submissions('contact'));
    }

    #[Test]
    public function a_required_click_check_gets_its_own_message(): void
    {
        Client::fake(new VerificationResult(false, 50, 'step_up', stepUp: ['type' => 'click']));

        $this->submit(['captchacore_token' => 'token']);

        $errors = session('errors')->getBag('form.contact');
        $this->assertSame(__('captchacore-statamic::messages.step_up'), $errors->first('captchacore'));
        $this->assertSame(0, $this->submissions('contact'));
    }

    #[Test]
    public function a_missing_token_is_rejected_without_asking_the_service(): void
    {
        Http::fake();

        $this->submit()->assertSessionHasErrors(['captchacore'], null, 'form.contact');

        Http::assertNothingSent();
        $this->assertSame(0, $this->submissions('contact'));
    }

    #[Test]
    public function a_token_array_is_rejected_without_a_server_error(): void
    {
        Http::fake();

        $this->submit(['captchacore_token' => ['x']])->assertRedirect()
            ->assertSessionHasErrors(['captchacore'], null, 'form.contact');

        Http::assertNothingSent();
    }

    #[Test]
    public function the_token_is_verified_with_the_configured_form_type(): void
    {
        config(['captchacore-statamic.form_types' => ['contact' => 'register']]);
        Http::fake(['captcha.test/*' => Http::response(['valid' => true, 'risk_score' => 3, 'decision' => 'allow'])]);

        $this->submit(['captchacore_token' => 'token'])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://captcha.test/api/v2/verify'
            && $r['token'] === 'token' && $r['form_type'] === 'register'
            && $r->header('X-CaptchaCore-Key')[0] === 'cc_sec_test');
        $this->assertSame(1, $this->submissions('contact'));
    }

    #[Test]
    public function forms_outside_the_list_are_not_checked(): void
    {
        config(['captchacore-statamic.forms' => 'newsletter, signup']);
        Http::fake();

        $this->submit()->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(1, $this->submissions('contact'));
    }

    #[Test]
    public function listed_forms_are_checked(): void
    {
        config(['captchacore-statamic.forms' => ['newsletter', 'contact']]);
        Http::fake();

        $this->submit()->assertSessionHasErrors(['captchacore'], null, 'form.contact');
    }

    #[Test]
    public function ajax_forms_receive_the_error_as_json(): void
    {
        Client::fake(new VerificationResult(false, 90, 'block'));

        $this->submit(['captchacore_token' => 'token'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(400)
            ->assertJsonPath('error.captchacore', __('captchacore-statamic::messages.failed'));
    }

    #[Test]
    public function an_unreachable_service_lets_the_submission_through_when_fail_open(): void
    {
        Http::fake(['captcha.test/*' => Http::response('', 503)]);

        $this->submit(['captchacore_token' => 'token'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->submissions('contact'));
    }

    #[Test]
    public function an_unreachable_service_blocks_when_fail_closed(): void
    {
        config(['captchacore-statamic.fail_open' => false]);
        $this->app->forgetInstance(Client::class);
        Http::fake(['captcha.test/*' => Http::response('', 503)]);

        $this->submit(['captchacore_token' => 'token'])->assertSessionHasErrors(['captchacore'], null, 'form.contact');
        $this->assertSame(0, $this->submissions('contact'));
    }

    #[Test]
    public function the_message_follows_the_language_of_the_submitting_site(): void
    {
        Site::setSites(['default' => ['name' => 'Deutsch', 'url' => 'http://localhost/', 'locale' => 'de_DE', 'lang' => 'de']]);
        Client::fake(new VerificationResult(false, 90, 'block'));

        $this->submit(['captchacore_token' => 'token'], ['Referer' => 'http://localhost/kontakt']);

        $this->assertSame(
            'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte versuchen Sie es erneut.',
            session('errors')->getBag('form.contact')->first('captchacore'),
        );
    }

    protected function defineRoutes($router): void
    {
        // Stands in for the Control Panel's "generate fake submission" action
        $router->post('/cp-test', function () {
            $submission = Form::find('contact')->makeSubmission();

            return response()->json(['accepted' => FormSubmitted::dispatch($submission) !== false]);
        })->name('statamic.cp.forms.test');
    }

    #[Test]
    public function control_panel_test_submissions_are_not_checked(): void
    {
        Http::fake();

        $this->post('/cp-test')->assertOk()->assertJson(['accepted' => true]);
        Http::assertNothingSent();
    }
}
