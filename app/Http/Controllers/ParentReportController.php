<?php

namespace App\Http\Controllers;

use App\Models\ReportLink;
use App\Services\Analytics\ChildReport;
use Illuminate\Http\Response;

/**
 * Read-only child report opened from a parent's private link (no account).
 * Invalid, expired and revoked links all return the same 404, so tokens cannot be probed.
 */
class ParentReportController extends Controller
{
    public function show(string $token): Response
    {
        $link = ReportLink::findValid($token);

        abort_if($link === null, 404);

        $link->forceFill([
            'view_count' => $link->view_count + 1,
            'last_viewed_at' => now(),
        ])->save();

        activity('report_link')->performedOn($link)->log('viewed');

        $participant = $link->participant()->with('study')->firstOrFail();

        return response()
            ->view('reports.parent', [
                'report' => ChildReport::build($participant, withPeers: false),
                'link' => $link,
                'childName' => $participant->display_name,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store');
    }
}
