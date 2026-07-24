<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Infrastructure\WordPress;

use AppleKlinika\Buyback\Application\Port\BuybackRequestMailer;
use AppleKlinika\Buyback\Application\PublicRequest\PublicBuybackSubmissionResult;
use AppleKlinika\Buyback\Domain\Buyback\OfferModeDefinition;

/** Keeps Buyback on wp_mail(), with SMTP configured only through deployment environment. */
final class WordPressBuybackRequestMailer implements BuybackRequestMailer
{
    public function __construct(private readonly BuybackSmtpConfiguration $configuration)
    {
    }

    public function register(): void
    {
        if ($this->configuration->isConfigured()) {
            add_action('phpmailer_init', [$this, 'configurePhpMailer']);
        }
    }

    public function configurePhpMailer(\PHPMailer\PHPMailer\PHPMailer $mailer): void
    {
        $mailer->isSMTP();
        $mailer->Host = $this->configuration->host();
        $mailer->Port = $this->configuration->port();
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->configuration->username();
        $mailer->Password = $this->configuration->password();
        $mailer->SMTPSecure = $this->configuration->encryption();
        $mailer->SMTPAutoTLS = $this->configuration->encryption() === 'tls';
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($this->configuration->from(), $this->configuration->fromName(), false);
    }

    public function sendCustomer(PublicBuybackSubmissionResult $result, array $input): bool
    {
        if (! $this->configuration->isConfigured()) {
            return false;
        }

        $subject = 'Apple Klinika felvásárlási igény: ' . $result->requestNumber;
        $body = $this->customerBody($result);

        return wp_mail((string) $input['email'], $subject, $body, $this->headers($this->configuration->admin()));
    }

    public function sendAdmin(PublicBuybackSubmissionResult $result, array $input): bool
    {
        if (! $this->configuration->isConfigured()) {
            return false;
        }

        $subject = 'Új Apple Klinika felvásárlási igény: ' . $result->requestNumber;
        $body = $this->adminBody($result, $input);
        $replyTo = sprintf('%s <%s>', $this->headerValue((string) $input['full_name']), $this->headerValue((string) $input['email']));

        return wp_mail($this->configuration->admin(), $subject, $body, $this->headers($replyTo));
    }

    /** @return list<string> */
    private function headers(string $replyTo): array
    {
        return [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $this->headerValue($this->configuration->fromName()), $this->configuration->from()),
            'Reply-To: ' . $this->headerValue($replyTo),
        ];
    }

    private function headerValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function customerBody(PublicBuybackSubmissionResult $result): string
    {
        $prefix = "Megkaptuk felvásárlási igényedet.\n\nHivatkozási szám: {$result->requestNumber}\nKészülék: {$result->device}";
        if ($result->manualReview) {
            $reasons = $result->manualReviewReasons === [] ? '' : "\nRögzített okok: " . implode(' · ', $result->manualReviewReasons);
            return $prefix . "\n\nSzemélyes bevizsgálás szükséges. A pontos ajánlatot rövid személyes ellenőrzés után adjuk meg." . $reasons;
        }

        $mode = OfferModeDefinition::all()[$result->serviceMode ?? '']['label'] ?? (string) $result->serviceMode;
        $offer = number_format((int) $result->amountMinor, 0, ',', ' ') . ' Ft';
        return $prefix . "\nVálasztott lehetőség: {$mode}\nElőzetes ajánlat: {$offer}\n\nA végleges érték fizikai bevizsgálás után kerül megerősítésre.";
    }

    /** @param array<string,mixed> $input */
    private function adminBody(PublicBuybackSubmissionResult $result, array $input): string
    {
        $body = "Hivatkozási szám: {$result->requestNumber}\nÜgyfél: {$input['full_name']}\nE-mail: {$input['email']}\nTelefon: {$input['phone']}\nKészülék: {$result->device}";
        if ($result->manualReview) {
            $body .= "\nIgény típusa: Személyes bevizsgálást kérek\nElőzetes összeg: nincs";
            if ($result->manualReviewReasons !== []) {
                $body .= "\nRögzített okok: " . implode(' · ', $result->manualReviewReasons);
            }
        } else {
            $mode = OfferModeDefinition::all()[$result->serviceMode ?? '']['label'] ?? (string) $result->serviceMode;
            $body .= "\nVálasztott lehetőség: {$mode}";
        }

        return $body . "\n\nAdmin: " . admin_url('admin.php?page=appleklinika-buyback-requests');
    }
}
