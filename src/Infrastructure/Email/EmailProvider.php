<?php

declare(strict_types=1);

namespace App\Infrastructure\Email;

use App\Infrastructure\Messaging\RabbitMQProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class EmailProvider
{
    private MailerInterface $mailer;
    private string $from;

    public function __construct(
        ?MailerInterface $mailer = null,
        ?string $from = null,
        private readonly ?RabbitMQProvider $messaging = null,
    ) {
        $this->from = $from ?? $_ENV['SMTP_FROM'] ?? 'no-reply@example.com';
        if ($mailer !== null) {
            $this->mailer = $mailer;
            // @codeCoverageIgnoreStart
        } else {
            $host = $_ENV['SMTP_HOST'] ?? 'localhost';
            $port = $_ENV['SMTP_PORT'] ?? '1025';
            $user = $_ENV['SMTP_USER'] ?? '';
            $pass = $_ENV['SMTP_PASS'] ?? '';
            $dsn = sprintf('smtp://%s:%s@%s:%s', urlencode($user), urlencode($pass), $host, $port);
            $transport = Transport::fromDsn($dsn);
            $this->mailer = new Mailer($transport);
        // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $html
     * @return void
     */
    public function sendEmail(string $to, string $subject, string $html): void
    {
        if ($this->messaging && ($_ENV['EMAIL_ASYNC'] ?? 'false') === 'true') {
            $this->messaging->publish('email', [
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
                'from' => $this->from,
            ]);
            return;
        }

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Error sending email: ' . $e->getMessage());
        }
    }
}
