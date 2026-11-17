<?php

namespace FirewallGui\LaravelFirewallGui\Mail;

use FirewallGui\LaravelFirewallGui\Models\FirewallNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FirewallAccessNotification extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $matchedRule
     * @param  array<string, mixed>|null  $matchedGroup
     */
    public function __construct(
        public string $ipAddress,
        public string $method,
        public string $path,
        public ?string $userAgent,
        public string $accessedAt,
        public array $matchedRule,
        public ?array $matchedGroup = null,
        public string $decisionAction = 'allowed',
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public ?int $notificationLogId = null,
    ) {}

    public function failed(Throwable $exception): void
    {
        if ($this->notificationLogId === null) {
            return;
        }

        try {
            FirewallNotificationLog::query()
                ->whereKey($this->notificationLogId)
                ->update([
                    'status' => FirewallNotificationLog::STATUS_FAILED,
                    'error_message' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            // Ignore logging errors during failed job handling
        }
    }

    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Laravel');
        $statusLabel = $this->decisionAction === 'blocked' ? 'BLOCKED' : 'ACCESS';

        return new Envelope(
            from: $this->fromAddress ? new Address($this->fromAddress, $this->fromName) : null,
            subject: "[{$appName}] Firewall {$statusLabel} Alert: {$this->ipAddress}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'firewall-gui::emails.access-notification',
        );
    }
}
