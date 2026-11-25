<?php

declare(strict_types=1);

namespace CaptchaCore\Statamic;

class VerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly int $riskScore,
        public readonly string $action,
        public readonly ?string $error = null,
        public readonly ?float $confidence = null,
        public readonly array $reasons = [],
        public readonly ?array $stepUp = null,
    ) {}

    public function isAllowed(): bool { return $this->action === 'allow'; }
    public function isChallenged(): bool { return $this->action === 'challenge'; }
    public function isBlocked(): bool { return $this->action === 'block'; }
    public function isStepUp(): bool { return $this->action === 'step_up'; }
    public function passed(): bool { return $this->valid; }
    public function needsStepUp(): bool { return $this->stepUp !== null; }
}
