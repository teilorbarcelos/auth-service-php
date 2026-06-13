<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Email\EmailProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailProviderTest extends TestCase
{
    public function testSendEmailWithMockMailer(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $provider = new EmailProvider($mailer, 'from@test.com');
        $provider->sendEmail('to@test.com', 'Subject', '<p>Body</p>');

        $this->assertTrue(true);
    }

    public function testSendEmailAsync(): void
    {
        $originalEnv = $_ENV['EMAIL_ASYNC'] ?? 'false';
        $_ENV['EMAIL_ASYNC'] = 'true';

        $mailer = $this->createMock(MailerInterface::class);
        $messaging = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $messaging->expects($this->once())->method('publish');

        $provider = new EmailProvider($mailer, 'from@test.com', $messaging);
        $provider->sendEmail('to@test.com', 'Subject', '<p>Body</p>');

        $_ENV['EMAIL_ASYNC'] = $originalEnv;
    }

    public function testSendEmailSyncFallback(): void
    {
        $originalEnv = $_ENV['EMAIL_ASYNC'] ?? 'false';
        $_ENV['EMAIL_ASYNC'] = 'false';

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $provider = new EmailProvider($mailer, 'from@test.com');
        $provider->sendEmail('to@test.com', 'Subject', '<p>Body</p>');

        $_ENV['EMAIL_ASYNC'] = $originalEnv;
    }

    public function testSendEmailHandlesException(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \Exception('SMTP error'));

        $provider = new EmailProvider($mailer, 'from@test.com');

        $provider->sendEmail('to@test.com', 'Subject', '<p>Body</p>');
        $this->assertTrue(true);
    }
}
