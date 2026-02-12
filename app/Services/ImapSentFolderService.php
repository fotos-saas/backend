<?php

namespace App\Services;

use App\Models\SmtpAccount;

/**
 * IMAP Sent mappaba mentes service.
 *
 * Az SmtpAccount model-bol kiemelt IMAP logika:
 * - saveToSentFolder: email mentes a Sent mappaba
 * - buildRfc822Message: RFC 822 formatum epites
 */
class ImapSentFolderService
{
    /**
     * Save email to IMAP Sent folder using webklex/php-imap
     *
     * @param  array  $attachments  Array of ['content' => string, 'filename' => string, 'mime' => string]
     */
    public function saveToSentFolder(SmtpAccount $account, string $to, string $subject, string $body, array $headers = [], array $attachments = []): bool
    {
        \Log::info('IMAP saveToSentFolder called', [
            'smtp_account_id' => $account->id,
            'to' => $to,
            'subject' => $subject,
            'attachments_count' => count($attachments),
            'can_save' => $account->canSaveToSent(),
            'imap_host' => $account->imap_host,
            'imap_sent_folder' => $account->imap_sent_folder,
        ]);

        if (! $account->canSaveToSent()) {
            \Log::warning('IMAP canSaveToSent returned false', [
                'smtp_account_id' => $account->id,
                'imap_save_sent' => $account->imap_save_sent,
                'imap_host' => $account->imap_host,
                'imap_username' => $account->imap_username,
                'has_password' => ! empty($account->imap_password),
            ]);

            return false;
        }

        try {
            \Log::info('IMAP connecting...', ['smtp_account_id' => $account->id]);

            $cm = new \Webklex\PHPIMAP\ClientManager;
            $client = $cm->make([
                'host' => $account->imap_host,
                'port' => $account->imap_port,
                'encryption' => $account->imap_encryption ?: 'ssl',
                'validate_cert' => true,
                'username' => $account->imap_username,
                'password' => $account->imap_password,
                'protocol' => 'imap',
            ]);

            $client->connect();
            \Log::info('IMAP connected successfully', ['smtp_account_id' => $account->id]);

            // Build RFC 822 email message (with attachments if provided)
            $message = $this->buildRfc822Message($account, $to, $subject, $body, $headers, $attachments);
            \Log::info('IMAP message built', [
                'smtp_account_id' => $account->id,
                'message_length' => strlen($message),
                'has_attachments' => count($attachments) > 0,
            ]);

            // Get the Sent folder
            $folder = $client->getFolderByPath($account->imap_sent_folder);

            if (! $folder) {
                \Log::error('IMAP Sent folder not found', [
                    'smtp_account_id' => $account->id,
                    'folder' => $account->imap_sent_folder,
                ]);
                $client->disconnect();

                return false;
            }

            \Log::info('IMAP folder found, appending message...', [
                'smtp_account_id' => $account->id,
                'folder' => $account->imap_sent_folder,
            ]);

            // Append message to Sent folder
            $result = $folder->appendMessage($message, ['\\Seen']);

            \Log::info('IMAP appendMessage result', [
                'smtp_account_id' => $account->id,
                'result' => $result,
                'result_type' => gettype($result),
            ]);

            $client->disconnect();

            return (bool) $result;
        } catch (\Throwable $e) {
            \Log::error('IMAP save to sent failed', [
                'smtp_account_id' => $account->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Build RFC 822 formatted email message with optional attachments
     *
     * @param  array  $attachments  Array of ['content' => string, 'filename' => string, 'mime' => string]
     */
    protected function buildRfc822Message(SmtpAccount $account, string $to, string $subject, string $body, array $headers = [], array $attachments = []): string
    {
        $boundary = '----=_Part_'.md5(uniqid());

        $defaultHeaders = [
            'From' => "{$account->from_name} <{$account->from_address}>",
            'To' => $to,
            'Subject' => $subject,
            'Date' => date('r'),
            'MIME-Version' => '1.0',
        ];

        // Ha vannak csatolmanyok, multipart/mixed kell
        if (! empty($attachments)) {
            $defaultHeaders['Content-Type'] = "multipart/mixed; boundary=\"{$boundary}\"";
        } else {
            $defaultHeaders['Content-Type'] = 'text/html; charset=UTF-8';
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $headerString = '';
        foreach ($allHeaders as $name => $value) {
            $headerString .= "{$name}: {$value}\r\n";
        }

        // Ha nincsenek csatolmanyok, egyszeru uzenet
        if (empty($attachments)) {
            return $headerString."\r\n".$body;
        }

        // Multipart uzenet csatolmanyokkal
        $message = $headerString."\r\n";
        $message .= "This is a multi-part message in MIME format.\r\n\r\n";

        // HTML body part
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body."\r\n\r\n";

        // Csatolmanyok
        foreach ($attachments as $attachment) {
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['filename']}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$attachment['filename']}\"\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment['content']))."\r\n";
        }

        // Zaro boundary
        $message .= "--{$boundary}--\r\n";

        return $message;
    }
}
