# CaptchaCore for Statamic

Spam and bot protection for Statamic forms — without image puzzles, without tracking cookies.

CaptchaCore checks every form submission before Statamic stores it or sends any email. Real
visitors usually notice nothing: the browser solves a small proof-of-work task in the background
while behavioural signals are evaluated as aggregated values. Only borderline cases see a short
click check.

- **Drop-in for Statamic forms** — one Antlers tag, every form submission is verified server-side
- **Settings page in the Control Panel** (Statamic 6) — site key, protected forms, strictness per
  form, widget look
- **No tracking cookies, no third-party trackers** in the widget; IP addresses are anonymised in
  the logs
- **Multilingual** — widget and error messages in English, German, French and Spanish, following
  the language of your site
- **Fails safe** — configurable behaviour when the service is unreachable
- Works with **Statamic 5 and 6**, PHP 8.2+

Requires a CaptchaCore account — a free plan is available at
[captchacore.eu](https://captchacore.eu).

## Installation

```bash
composer require captchacore/statamic
```

The package is also served from the CaptchaCore Composer repository, should you prefer it over
Packagist:

```json
"repositories": [
    { "type": "composer", "url": "https://captchacore.eu/packages" }
]
```

Then add the keys from the CaptchaCore customer area (**Sites → your site**) to your `.env`.
The domain of your Statamic site must be listed for that site.

```env
CAPTCHACORE_SITE_KEY=cc_pub_...
CAPTCHACORE_SECRET_KEY=cc_sec_...
```

## Usage

Place the tag inside your form:

```antlers
{{ form:contact }}
    {{ if success }}<p>Thank you!</p>{{ /if }}

    {{ if errors }}
        <ul>{{ errors }}<li>{{ value }}</li>{{ /errors }}</ul>
    {{ /if }}

    <input name="name" value="{{ old:name }}">
    <textarea name="message">{{ old:message }}</textarea>

    {{ captchacore }}

    <button type="submit">Send</button>
{{ /form:contact }}
```

That's it. The widget adds a hidden field `captchacore_token`; you don't need a field for it in the
form blueprint, and the token is never stored with the submission.

If the check fails, Statamic treats it as a validation error under the key `captchacore`: it
appears in `{{ errors }}` and `{{ error:captchacore }}` (AJAX forms: `error.captchacore` in the JSON
response), and the submission is neither stored nor emailed. The message follows the language of
the site the form was sent from.

> **By default every Statamic form is protected.** A protected form without `{{ captchacore }}`
> rejects every submission. Limit protection to specific forms in the settings page or with
> `CAPTCHACORE_STATAMIC_FORMS=contact,newsletter`.

In Blade templates use `<s:captchacore />`.

### Tag parameters

| Parameter   | Purpose                                                                 |
|-------------|-------------------------------------------------------------------------|
| `form`      | Form handle, only needed when the tag is not inside `{{ form:… }}`       |
| `form_type` | Override the form type (`contact`, `register`, `login`, `comment`, …)    |
| `mode`      | `interactive`, `visible` or `invisible` (default: customer area setting) |
| `lang`      | Widget language, e.g. `de` (default: the page's `<html lang>`)           |
| `nonce`     | CSP nonce for the widget script (default: Laravel's Vite nonce)          |

## Settings page (Statamic 6)

**Tools → Addons → CaptchaCore → Settings** in the Control Panel:

- site key and service URL, with a status check of both keys
- all forms or selected forms only
- default form type and a different type per form (e.g. check a newsletter like a registration)
- behaviour when the service is unreachable
- widget mode, theme, size, language and delivery (EU-only or global CDN)

A filled-in field wins over `.env`; an empty field keeps the `.env` / config value. The settings are
saved to `resources/addons/captchacore-statamic.yaml`.

The **secret key is not editable there** on purpose: that file usually goes into your Git
repository. Keep the secret key in `CAPTCHACORE_SECRET_KEY`; the page only shows whether it is set.

On Statamic 5 configure everything through `.env` or the config file below.

## Configuration

All options with their `.env` names:

```env
CAPTCHACORE_URL=https://api.captchacore.eu
CAPTCHACORE_SITE_KEY=cc_pub_...
CAPTCHACORE_SECRET_KEY=cc_sec_...
CAPTCHACORE_TIMEOUT=3                   # seconds
CAPTCHACORE_FAIL_OPEN=true              # let submissions through while the service is unreachable
CAPTCHACORE_STATAMIC_FORMS=*            # * = all forms, or e.g. contact,newsletter
CAPTCHACORE_STATAMIC_FORM_TYPE=contact  # form type for forms without their own entry
CAPTCHACORE_WIDGET_MODE=                # interactive | visible | invisible
CAPTCHACORE_WIDGET_THEME=               # auto | light | dark
CAPTCHACORE_WIDGET_SIZE=                # normal | compact
CAPTCHACORE_WIDGET_LANG=                # de | en | fr | es | …
CAPTCHACORE_WIDGET_ENDPOINT=eu          # eu (EU-only CDN) | global
```

Widget options left empty use the settings from the CaptchaCore customer area.

Form types per form handle go into the config file:

```bash
php artisan vendor:publish --tag=captchacore-statamic-config
```

```php
// config/captchacore-statamic.php
'form_types' => [
    'newsletter' => 'register',
],
```

The widget and the server check always use the same form type, taken from the form's handle.

### When the service is unreachable

Only service disturbances (5xx, 408, timeout, network error) let a submission through, and they are
logged as `warning`. A rejected request, a redirect or an unreadable answer always blocks. Set
`CAPTCHACORE_FAIL_OPEN=false` (or choose “Reject submissions” in the settings page) to block during
outages instead. Test submissions generated in the Control Panel are not checked.

## Privacy

The widget sets no tracking cookies and loads nothing from third parties. Behavioural signals are
sent as aggregated values, not as raw mouse or keyboard data. The submission content itself never
leaves your server — only the token, the form type, the visitor IP and the page URL without query
string are sent for verification. Details: [captchacore.eu](https://captchacore.eu).

## Support

- Documentation: [captchacore.eu/docs](https://captchacore.eu/docs)
- Contact: info@captchacore.eu

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT

---

## Deutsch

CaptchaCore schützt Statamic-Formulare vor Spam und Bots – ohne Bilderrätsel und ohne
Tracking-Cookies. Jede Einsendung wird geprüft, bevor Statamic sie speichert oder Mails verschickt.

1. `composer require captchacore/statamic`
2. Site Key und Secret Key aus dem CaptchaCore-Kundenbereich in die `.env` eintragen
   (`CAPTCHACORE_SITE_KEY`, `CAPTCHACORE_SECRET_KEY`).
3. `{{ captchacore }}` in das Formular-Template setzen.

Unter Statamic 6 finden Sie die Einstellungen im Control Panel unter **Werkzeuge → Add-ons →
CaptchaCore**. Den Secret Key tragen Sie bewusst nur in die `.env` ein. Widget, Fehlermeldungen
und Einstellungsseite gibt es auf Deutsch, Englisch, Französisch und Spanisch.
