<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Language;
use App\Features\FeatureDefinitions;

class FeatureController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('install');
        $this->middleware('auth:100');
    }

    /**
     * Display the Features page
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        Feature::syncFeatures();

        $bootstrap = $this->getAdminBootstrap();
        $bootstrap = json_encode($bootstrap);

        return view('admin.features', ['bootstrap' => $bootstrap]);
    }

    /**
     * Get grid data for features
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function grid(Request $request)
    {
        $data = $request->toArray();
        $rows = [];
        $rows_per_page = (int) ($data['rows'] ?? 25);
        $page = (int) ($data['page'] ?? 1);
        $sidx = $data['sidx'] ?? 'id';
        $sord = $data['sord'] ?? 'ASC';

        // Build query
        $Query = Feature::orderBy($sidx, $sord);

        // Apply search filters
        if (isset($data['language']) && $data['language']) {
            $Query->where('language', $data['language']);
        }

        if (isset($data['installed']) && ($data['installed'] === '0' || $data['installed'] === '1')) {
            $Query->where('installed', (int) $data['installed']);
        }

        if (isset($data['enabled']) && ($data['enabled'] === '0' || $data['enabled'] === '1')) {
            $Query->where('enabled', (int) $data['enabled']);
        }

        if (isset($data['code']) && $data['code']) {
            $Query->where('code', 'LIKE', '%' . $data['code'] . '%');
        }

        $Features = $Query->paginate($rows_per_page);

        // Transform rows to include definition data
        foreach ($Features as $Feature) {
            $row = $Feature->getAttributes();
            $definition = FeatureDefinitions::find($Feature->identifier);

            if ($definition) {
                $row['name'] = $definition['name'];
                $description = $definition['description'];
                $mode = FeatureDefinitions::getLanguageMode($definition);

                // Replace language placeholder in description
                if ($Feature->language && strpos($description, '{language}') !== false) {
                    $language = Language::where('code', $Feature->language)->first();
                    $languageName = $language ? $language->name : $Feature->language;
                    $description = str_replace('{language}', $languageName, $description);
                } elseif (!$Feature->language && strpos($description, '{language}') !== false) {
                    $description = str_replace('{language}', '', $description);
                }

                $row['description'] = $description;

                // Add language name
                if ($Feature->language) {
                    $language = Language::where('code', $Feature->language)->first();
                    $row['language_name'] = $language ? $language->name : $Feature->language;

                    if ($mode === FeatureDefinitions::LANGUAGE_MODE_MULTI && $row['language_name']) {
                        $row['name'] .= ' (' . $row['language_name'] . ')';
                    }
                } else {
                    $row['language_name'] = '—';
                }

                $rows[] = $row;
            }
        }

        $resp = [
            'total' => $Features->lastPage(),
            'page' => $Features->currentPage(),
            'rows' => $rows,
            'records' => $Features->total(),
            'post' => FALSE,
        ];

        return response($resp, 200);
    }

    /**
     * Install a feature
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function install(Request $request, $id)
    {
        $Feature = Feature::findOrFail($id);
        $enable = $request->boolean('enable', false);
        $resp = new \stdClass();
        $resp->success = TRUE;

        if (!$Feature->install($enable)) {
            $resp->success = FALSE;
            $resp->errors = ['Failed to install feature.'];
            return new Response($resp, 422);
        }

        return new Response($resp, 200);
    }

    public function enable(Request $request, $id)
    {
        $Feature = Feature::findOrFail($id);
        $resp = new \stdClass();
        $resp->success = TRUE;

        if (!$Feature->enable()) {
            $resp->success = FALSE;
            $resp->errors = ['Feature must be installed before it can be enabled.'];
            return new Response($resp, 422);
        }

        return new Response($resp, 200);
    }

    public function disable(Request $request, $id)
    {
        $Feature = Feature::findOrFail($id);
        $resp = new \stdClass();
        $resp->success = TRUE;

        if (!$Feature->disable()) {
            $resp->success = FALSE;
            $resp->errors = ['Failed to disable feature.'];
            return new Response($resp, 422);
        }

        return new Response($resp, 200);
    }

    /**
     * Uninstall a feature
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function uninstall(Request $request, $id)
    {
        $Feature = Feature::findOrFail($id);
        $resp = new \stdClass();
        $resp->success = TRUE;

        if (!$Feature->uninstall()) {
            $resp->success = FALSE;
            $resp->errors = ['Failed to uninstall feature.'];
            return new Response($resp, 422);
        }

        return new Response($resp, 200);
    }
}
