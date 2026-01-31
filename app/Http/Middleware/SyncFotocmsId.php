<?php

namespace App\Http\Middleware;

use App\Models\TabloProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncFotocmsId
{
    /**
     * Ha a request tartalmaz fotocms_id-t és project_id-t (external_id),
     * és a projekt fotocms_id mezője még null, akkor beírjuk.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fotocmsId = $request->input('fotocms_id');
        $externalId = $request->input('project_id');

        if ($fotocmsId && $externalId) {
            $project = TabloProject::where('external_id', (string) $externalId)
                ->whereNull('fotocms_id')
                ->first();

            if ($project) {
                $project->fotocms_id = (int) $fotocmsId;
                $project->save();
            }
        }

        return $next($request);
    }
}
