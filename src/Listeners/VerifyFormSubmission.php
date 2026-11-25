<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Listeners;

use CaptchaCore\Statamic\Client;
use CaptchaCore\Statamic\Forms;
use Illuminate\Validation\ValidationException;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Site;

/**
 * Checks the widget token before Statamic stores a form submission.
 * A failed check becomes a validation error under "captchacore": Statamic
 * shows it in {{ errors }} / {{ error:captchacore }} (or as JSON for AJAX
 * forms) and neither stores the submission nor sends its emails.
 */
class VerifyFormSubmission
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function handle(FormSubmitted $event): void
    {
        // Test submissions generated in the Control Panel carry no token
        if (request()->routeIs('statamic.cp.*')) {
            return;
        }

        $handle = $event->submission->form()?->handle();
        if (! Forms::protects($handle)) {
            return;
        }

        $token = request()->input(Client::TOKEN_FIELD);
        // Strings only: captchacore_token[]=x must not cause a TypeError (500)
        $result = $this->client->verify(is_string($token) ? $token : null, Forms::formType($handle));

        if ($result->needsStepUp()) {
            $this->reject('step_up', $event);
        }

        if (! $result->valid) {
            // Reasons and score are logged by the client, never shown.
            $this->reject('failed', $event);
        }
    }

    private function reject(string $message, FormSubmitted $event): never
    {
        throw ValidationException::withMessages([
            'captchacore' => __('captchacore-statamic::messages.' . $message, [], $this->siteLocale($event)),
        ]);
    }

    /**
     * The form route runs without Statamic's Localize middleware, so the app
     * locale is the global one: take the language of the site the form was
     * sent from (Statamic 6 stores it on the submission, 5 via the referer).
     */
    private function siteLocale(FormSubmitted $event): ?string
    {
        $site = method_exists($event->submission, 'site') ? $event->submission->site() : null;
        if ($site === null) {
            $url = request()->header('referer') ?: session()->previousUrl();
            $site = is_string($url) && $url !== '' ? Site::findByUrl(url()->to($url)) : null;
        }
        $lang = $site?->lang();

        return is_string($lang) && $lang !== '' ? $lang : null;
    }
}
