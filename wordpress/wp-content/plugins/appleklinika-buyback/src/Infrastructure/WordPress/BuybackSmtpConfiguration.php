<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

/** Environment-only SMTP configuration. Secrets are deliberately never persisted. */
final class BuybackSmtpConfiguration
{
    /** @param array<string,string|false> $environment */
    public function __construct(private readonly array $environment)
    {
    }

    public static function fromEnvironment(): self
    {
        $values = [];
        foreach (self::requiredKeys() as $key) {
            $values[$key] = getenv($key);
        }
        return new self($values);
    }

    /** @return list<string> */
    public static function requiredKeys(): array
    {
        return ['BUYBACK_SMTP_HOST', 'BUYBACK_SMTP_PORT', 'BUYBACK_SMTP_ENCRYPTION', 'BUYBACK_SMTP_USERNAME', 'BUYBACK_SMTP_PASSWORD', 'BUYBACK_MAIL_FROM', 'BUYBACK_MAIL_FROM_NAME', 'BUYBACK_ADMIN_EMAIL'];
    }

    public function isConfigured(): bool
    {
        return $this->missing() === [];
    }

    /** @return list<string> */
    public function missing(): array
    {
        $missing = [];
        foreach (self::requiredKeys() as $key) {
            if ($this->value($key) === '') {
                $missing[] = $key;
            }
        }
        if ($this->host() !== '' && preg_match('/^[A-Za-z0-9.-]+$/', $this->host()) !== 1) {
            $missing[] = 'BUYBACK_SMTP_HOST (érvénytelen)';
        }
        if ($this->value('BUYBACK_SMTP_PORT') !== '' && ($this->port() < 1 || $this->port() > 65535)) {
            $missing[] = 'BUYBACK_SMTP_PORT (érvénytelen)';
        }
        if ($this->encryption() !== '' && ! in_array($this->encryption(), ['tls', 'ssl'], true)) {
            $missing[] = 'BUYBACK_SMTP_ENCRYPTION (tls vagy ssl)';
        }
        if (($this->from() !== '' && ! is_email($this->from())) || ($this->admin() !== '' && ! is_email($this->admin()))) {
            $missing[] = 'BUYBACK_MAIL_FROM vagy BUYBACK_ADMIN_EMAIL (érvénytelen)';
        }
        return array_values(array_unique($missing));
    }

    public function host(): string { return $this->value('BUYBACK_SMTP_HOST'); }
    public function port(): int { return (int) $this->value('BUYBACK_SMTP_PORT'); }
    public function encryption(): string { return strtolower($this->value('BUYBACK_SMTP_ENCRYPTION')); }
    public function username(): string { return $this->value('BUYBACK_SMTP_USERNAME'); }
    public function password(): string { return $this->value('BUYBACK_SMTP_PASSWORD'); }
    public function from(): string { return $this->value('BUYBACK_MAIL_FROM'); }
    public function fromName(): string { return $this->value('BUYBACK_MAIL_FROM_NAME'); }
    public function admin(): string { return $this->value('BUYBACK_ADMIN_EMAIL'); }

    /** @return array{configured:bool,host:string,port:string,encryption:string,username:string,from:string,admin:string,missing:list<string>} */
    public function diagnostics(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'host' => $this->host() === '' ? '–' : $this->host(),
            'port' => $this->port() > 0 ? (string) $this->port() : '–',
            'encryption' => $this->encryption() === '' ? '–' : $this->encryption(),
            'username' => $this->maskedUsername(),
            'from' => $this->from() === '' ? '–' : $this->from(),
            'admin' => $this->admin() === '' ? '–' : $this->admin(),
            'missing' => $this->missing(),
        ];
    }

    private function value(string $key): string
    {
        $value = $this->environment[$key] ?? false;
        return is_string($value) ? trim(str_replace(["\r", "\n"], '', $value)) : '';
    }

    private function maskedUsername(): string
    {
        $username = $this->username();
        if ($username === '') {
            return '–';
        }
        return mb_substr($username, 0, 1) . str_repeat('•', max(3, mb_strlen($username) - 1));
    }
}
