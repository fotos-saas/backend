<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailStatistic;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class EmailTrackingController extends Controller
{
    /**
     * Track email open via 1x1 tracking pixel
     */
    public function trackOpen(string $token): Response
    {
        $emailLog = EmailLog::where('tracking_token', $token)->first();

        if ($emailLog) {
            // Update email log
            if (! $emailLog->opened_at) {
                $emailLog->update([
                    'opened_at' => now(),
                    'open_count' => 1,
                ]);
            } else {
                $emailLog->increment('open_count');
            }

            // Update statistics
            $stat = EmailStatistic::getOrCreateForNow(
                $emailLog->smtp_account_id,
                $emailLog->email_template_id
            );
            $stat->incrementOpened();
        }

        // Return 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track email link click and redirect to original URL
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function trackClick(string $token, string $linkHash)
    {
        $emailLog = EmailLog::where('tracking_token', $token)->first();

        if ($emailLog) {
            // Update email log
            if (! $emailLog->clicked_at) {
                $emailLog->update([
                    'clicked_at' => now(),
                    'click_count' => 1,
                ]);
            } else {
                $emailLog->increment('click_count');
            }

            // Update statistics
            $stat = EmailStatistic::getOrCreateForNow(
                $emailLog->smtp_account_id,
                $emailLog->email_template_id
            );
            $stat->incrementClicked();
        }

        // Retrieve original URL from cache
        $originalUrl = Cache::get("track_link_{$token}_{$linkHash}");

        if ($originalUrl) {
            return redirect($originalUrl);
        }

        // Fallback if URL not found - redirect to frontend instead of backend
        $frontendUrl = config('app.frontend_url', config('app.url'));
        return redirect($frontendUrl);
    }
}
