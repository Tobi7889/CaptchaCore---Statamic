<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic\Tests;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\View;

class TagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeForm('contact');
        $this->makeForm('newsletter');
        config(['captchacore-statamic.form_types' => ['newsletter' => 'register']]);
    }

    /** Rendered as a real view: Antlers::parse() treats its input as untrusted user content. */
    private function render(string $template): string
    {
        $dir = sys_get_temp_dir() . '/captchacore-statamic-views-' . getmypid();
        @mkdir($dir);
        $name = 'tpl' . md5($template);
        file_put_contents("{$dir}/{$name}.antlers.html", $template);
        View::addLocation($dir);

        return view($name)->render();
    }

    #[Test]
    public function the_widget_takes_the_form_type_of_the_surrounding_form(): void
    {
        $html = $this->render('{{ form:contact }}{{ captchacore }}{{ /form:contact }}{{ form:create in="newsletter" }}{{ captchacore }}{{ /form:create }}');

        $this->assertSame(2, preg_match_all('/data-captchacore-widget/', $html));
        $this->assertMatchesRegularExpression('/action="[^"]*contact".*data-form-type="contact".*action="[^"]*newsletter".*data-form-type="register"/s', $html);
        // The script is included once, with the site key
        $this->assertSame(1, substr_count($html, '<script src='));
        $this->assertStringContainsString('data-site-key="cc_pub_test"', $html);
    }

    #[Test]
    public function parameters_override_the_form_type_and_set_widget_options(): void
    {
        $html = $this->render('{{ captchacore form="newsletter" mode="visible" lang="fr" }}{{ captchacore form_type="login" }}{{ captchacore form_type="<b>" }}');

        $this->assertStringContainsString('data-form-type="register"', $html);
        $this->assertStringContainsString('data-mode="visible"', $html);
        $this->assertStringContainsString('data-lang="fr"', $html);
        $this->assertStringContainsString('data-form-type="login"', $html);
        $this->assertStringContainsString('data-form-type="custom"', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    #[Test]
    public function outside_a_form_the_default_form_type_is_used(): void
    {
        $this->assertStringContainsString('data-form-type="contact"', $this->render('{{ captchacore }}'));
    }

    #[Test]
    public function a_nonce_is_passed_to_the_script(): void
    {
        $this->assertStringContainsString('nonce="abc123"', $this->render('{{ captchacore nonce="abc123" }}'));
    }
}
