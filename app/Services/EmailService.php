<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailEvent;
use App\Models\EmailLog;
use App\Models\EmailStatistic;
use App\Models\EmailTemplate;
use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailService
{
    public function __construct(
        protected EmailVariableService $variableService,
    ) {}

    public function sendFromTemplate(EmailTemplate $template, string $recipientEmail, array $variables = [], ?User $recipientUser = null, ?string $eventType = null, array $attachments = []): void
    {
        $resolvedSubject = $this->variableService->replaceVariables($template->subject, $variables);
        $resolvedBody = $this->variableService->replaceVariables($template->body, $variables);

        // Select SMTP account
        $smtpAccount = $this->selectSmtpAccount($template, $template->priority ?? 'normal');

        // Check rate limit
        if (! $smtpAccount->isWithinRateLimit()) {
            Log::warning('SMTP account rate limit reached', [
                'smtp_account_id' => $smtpAccount->id,
                'smtp_account_name' => $smtpAccount->name,
            ]);

            // TODO: Queue email for later or use fallback SMTP
            return;
        }

        // Generate tracking token
        $trackingToken = Str::uuid()->toString();

        // Embed tracking pixel and links
        $trackedBody = $this->embedTracking($resolvedBody, $trackingToken);

        // Dev módban átirányítjuk az emailt a super adminhoz
        $actualRecipient = $this->resolveRecipientEmail($recipientEmail);

        $log = EmailLog::create([
            'email_template_id' => $template->id,
            'smtp_account_id' => $smtpAccount->id,
            'priority' => $template->priority ?? 'normal',
            'event_type' => $eventType,
            'recipient_email' => $recipientEmail, // Eredeti címzett logolva
            'recipient_user_id' => $recipientUser?->id,
            'subject' => $resolvedSubject,
            'body' => $trackedBody,
            'attachments' => $attachments,
            'status' => 'queued',
            'tracking_token' => $trackingToken,
        ]);

        $mail = new TemplateMail($resolvedSubject, $trackedBody, $attachments);

        try {
            $this->sendViaSmtp($smtpAccount, $mail, $actualRecipient);

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Save to IMAP Sent folder if configured (with attachments)
            if ($smtpAccount->canSaveToSent()) {
                // Transform attachments to the format expected by saveToSentFolder
                $imapAttachments = [];
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $imapAttachments[] = [
                            'content' => file_get_contents($attachment['path']),
                            'filename' => $attachment['name'] ?? basename($attachment['path']),
                            'mime' => $attachment['mime'] ?? mime_content_type($attachment['path']) ?: 'application/octet-stream',
                        ];
                    } elseif (isset($attachment['data'])) {
                        $imapAttachments[] = [
                            'content' => $attachment['data'],
                            'filename' => $attachment['name'] ?? 'attachment',
                            'mime' => $attachment['mime'] ?? 'application/octet-stream',
                        ];
                    }
                }
                $smtpAccount->saveToSentFolder($actualRecipient, $resolvedSubject, $trackedBody, [], $imapAttachments);
            }

            // Update statistics
            $this->updateStatistics($smtpAccount->id, $template->id, 'sent');

        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            // Update statistics
            $this->updateStatistics($smtpAccount->id, $template->id, 'failed');

            Log::error('Email sending failed', [
                'log_id' => $log->id,
                'smtp_account_id' => $smtpAccount->id,
                'recipient' => $recipientEmail,
                'exception' => $exception,
            ]);
        }
    }

    public function resolveRecipientEmail(string $originalEmail): string
    {
        if (config('app.debug') && config('mail.override_to')) {
            return (string) config('mail.override_to');
        }

        return $originalEmail;
    }

    public function getEventRecipients(EmailEvent $event, array $context = []): array
    {
        $recipients = [];

        switch ($event->recipient_type) {
            case 'user':
                if (isset($context['user']) && $context['user'] instanceof User) {
                    $recipients[] = $context['user'];
                }
                break;
            case 'album_users':
                if (isset($context['album']) && $context['album']?->users) {
                    $recipients = $context['album']->users()->get();
                }
                break;
            case 'order_user':
                if (isset($context['order']) && $context['order']?->user) {
                    $recipients[] = $context['order']->user;
                }
                break;
            case 'custom':
                $recipients = collect($event->custom_recipients ?? [])
                    ->map(function (string $email) {
                        $user = User::whereEmail($email)->first();

                        return $user ?? $email;
                    })
                    ->all();
                break;
        }

        return $recipients;
    }

    /**
     * Select SMTP account based on template and priority
     */
    public function selectSmtpAccount(EmailTemplate $template, string $priority): SmtpAccount
    {
        // 1. If template has specific SMTP account, use it
        if ($template->smtp_account_id) {
            $account = SmtpAccount::find($template->smtp_account_id);

            if ($account && $account->is_active) {
                return $account->getEffectiveAccount();
            }
        }

        // 2. Use active SMTP for current environment
        $isProd = ! config('app.debug');

        $account = SmtpAccount::query()
            ->where('is_prod', $isProd)
            ->where('is_active', true)
            ->orderBy('priority', 'asc')
            ->first();

        if (! $account) {
            throw new \RuntimeException('No active SMTP account found for environment: '.($isProd ? 'prod' : 'dev'));
        }

        return $account->getEffectiveAccount();
    }

    /**
     * Send email via specific SMTP account
     */
    public function sendViaSmtp(SmtpAccount $account, TemplateMail $mail, string $recipient): void
    {
        $mailerName = $account->getDynamicMailerName();

        Mail::mailer($mailerName)->to($recipient)->send($mail);
    }

    /**
     * Embed tracking pixel and convert links to tracked links
     */
    public function embedTracking(string $body, string $trackingToken): string
    {
        // Convert all links to tracking links
        $body = preg_replace_callback(
            '/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i',
            function ($matches) use ($trackingToken) {
                $originalUrl = $matches[1];
                $linkHash = md5($originalUrl);
                $trackingUrl = url("/track/click/{$trackingToken}/{$linkHash}");

                // Store original URL mapping (could be cached or in DB)
                cache()->put("track_link_{$trackingToken}_{$linkHash}", $originalUrl, now()->addDays(30));

                return str_replace($matches[1], $trackingUrl, $matches[0]);
            },
            $body
        );

        // Add tracking pixel at the end
        $trackingPixel = '<img src="'.url("/track/open/{$trackingToken}").'" width="1" height="1" style="display:none" alt="" />';
        $body .= $trackingPixel;

        return $body;
    }

    /**
     * Update email statistics
     */
    protected function updateStatistics(int $smtpAccountId, int $emailTemplateId, string $type): void
    {
        $stat = EmailStatistic::getOrCreateForNow($smtpAccountId, $emailTemplateId);

        switch ($type) {
            case 'sent':
                $stat->incrementSent();
                break;
            case 'failed':
                $stat->incrementFailed();
                break;
            case 'opened':
                $stat->incrementOpened();
                break;
            case 'clicked':
                $stat->incrementClicked();
                break;
            case 'bounced':
                $stat->incrementBounced();
                break;
            case 'unsubscribed':
                $stat->incrementUnsubscribed();
                break;
        }
    }
}
