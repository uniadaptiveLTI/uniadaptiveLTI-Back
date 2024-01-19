<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Map;
use App\Models\Version;
use Error;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LonghornOpen\LaravelCelticLTI\LtiTool;

class LtiController extends Controller
{
    public function getJWKS()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        $tool = LtiTool::getLtiTool();
        return $tool->getJWKS();
    }
    // Function that obtains data from the LMS, stores it in the database (TEMPORARY) and redirects to the front end.
    public function saveSession()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        // dd('hla');
        if (env('APP_PROXY') != '') {
            $proxy = env('APP_PROXY');
            // Check if 'http://' or 'https://' is present
            if (strpos($proxy, 'http://') === false && strpos($proxy, 'https://') === false) {
                $proxy = 'https://' . $proxy; // Default to 'https' if no protocol is specified
            }
            // Remove 'http://' or 'https://'
            $serverName = str_replace(['http://', 'https://'], '', $proxy);
            $_SERVER['SERVER_NAME'] = $serverName;
            $_SERVER['SERVER_PORT'] = env('APP_PROXY_PORT');
            // If 'APP_PROXY' starts with 'https', set 'HTTPS' to 'on'
            if (strpos($proxy, 'https://') === 0) {
                $_SERVER['HTTPS'] = 'on';
            }
        }
        // if (env('APP_HTTPS') != '') {
        //     $_SERVER['HTTPS'] = env('APP_HTTPS');
        // }
        $tool = LtiTool::getLtiTool();
        // dd($tool);
        $tool->handleRequest();

        $jwt = $tool->getJWT();
        $fire = $tool->getMessageParameters();
        // dd($fire);
        $platform = $fire['tool_consumer_info_product_family_code'];
        $token_request = LtiController::getLmsToken($fire['platform_id'], $platform, true);
        if (!$token_request['ok']) {
            return response()->json(['ok' => false, 'data' => $token_request['data'], 'error' => $token_request['error']]);
        }

        $session = DB::table('lti_info')->where([
            ['user_id', '=', $fire['user_id']],
            ['platform_id', '=', $fire['platform_id']],
            ['context_id', '=', $fire['context_id']],
            ['expires_at', '>=', intval(Carbon::now()->valueOf())],
        ])->first();
        if ($session) {
            // dd($session);
            switch ($fire['tool_consumer_info_product_family_code']) {
                case 'moodle':
                    DB::table('lti_info')->where([
                        ['user_id', '=', $fire['user_id']],
                        ['platform_id', '=', $fire['platform_id']],
                        ['context_id', '=', $fire['context_id']],
                        ['expires_at', '>=', intval(Carbon::now()->valueOf())],
                    ])->update([
                        'profile_url' => MoodleController::getImgUser(
                            $fire['platform_id'],
                            $fire['user_id']
                        ),
                        'lis_person_name_full' => $fire['lis_person_name_full'],
                    ]);
                    break;
                case 'sakai':
                    $jwtPayload = $jwt->getPayload();
                    $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;

                    $sessionIdRequest = SakaiController::createSession($fire['platform_id'], $sakai_serverid, $token_request['data']);

                    if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                        $session_id = $sessionIdRequest['data']['user_id'];
                    } else {
                        return response()->json(['ok' => false, 'errorType' => "CREATE_SESSION_ERROR"]);
                    }

                    DB::table('lti_info')->where([
                        ['user_id', '=', $fire['user_id']],
                        ['platform_id', '=', $fire['platform_id']],
                        ['context_id', '=', $fire['context_id']],
                        ['expires_at', '>=', intval(Carbon::now()->valueOf())],
                    ])->update([
                        'profile_url' => SakaiController::getUrl($fire['platform_id'], $fire['context_id'], SakaiController::getId($fire['user_id'])),
                        'lis_person_name_full' => $fire['lis_person_name_full'],
                        'session_id' => $session_id,
                    ]);
                    // dd($session);
                default:
                    break;
            }
        } else {
            $currentDate = date('Y-m-d H:i:s');
            $expDate = intval(Carbon::now()->addSeconds(30000)->valueOf());
            $session = DB::table('lti_info')->where([
                ['user_id', '=', $fire['user_id']],
                ['platform_id', '=', $fire['platform_id']],
                ['context_id', '=', $fire['context_id']],
            ])->delete();
            switch ($fire['tool_consumer_info_product_family_code']) {
                case 'moodle':
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' => $fire['context_id'],
                        'context_title' => $fire['context_title'],
                        //
                        'launch_presentation_locale' => $fire['launch_presentation_locale'],
                        //
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                        'user_id' => (string) $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'] == '' ? 'Usuario' : $fire['lis_person_name_full'],
                        //
                        'profile_url' => MoodleController::getImgUser($fire['platform_id'], $fire['user_id']),
                        'roles' => $fire['roles'],
                        //
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,
                        //
                    ]);
                    break;
                case 'sakai':

                    $jwtPayload = $jwt->getPayload();
                    $locale = $jwtPayload->locale;
                    $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;

                    $sessionIdRequest = SakaiController::createSession($fire['platform_id'], $sakai_serverid, $token_request['data']);

                    if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                        $session_id = $sessionIdRequest['data']['user_id'];
                    } else {
                        return response()->json(['ok' => false, 'errorType' => "CREATE_SESSION_ERROR"]);
                    }
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' => $fire['context_id'],
                        'context_title' => $fire['context_title'],
                        //
                        'launch_presentation_locale' => $locale,
                        //
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'ext_sakai_serverid' => $sakai_serverid,
                        'session_id' => $session_id,
                        //
                        'launch_presentation_return_url' => $fire['platform_id'] . '/portal/site/' . $fire['context_id'],
                        'user_id' => $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'],
                        //
                        'profile_url' => SakaiController::getUrl($fire['platform_id'], $fire['context_id'], SakaiController::getId($fire['user_id'])),
                        'roles' => $fire['roles'],
                        //
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,
                        //
                    ]);
                    break;
                default:
                    break;
            }
        }
        $session = DB::table('lti_info')->where([
            ['user_id', '=', $fire['user_id']],
            ['platform_id', '=', $fire['platform_id']],
            ['context_id', '=', $fire['context_id']],
            ['expires_at', '>=', intval(Carbon::now()->valueOf())],
        ])->first();
        $headers = @get_headers(env('FRONT_URL'));
        $canSee = false;

        if ($headers) {
            foreach ($headers as $header) {
                if (strpos($header, '200 OK')) {
                    $canSee = true;
                    break;
                }
            }
        }

        if ($canSee) {
            return redirect()->to(env('FRONT_URL') . '?token=' . $session->token);
        } else {
            return response('Error: No es posible redirigir al Front. Compruebe que el front esté funcionando correctamente. Compruebe la dirección en el .env o si se ha lanzado correctamente.', 500);
        }
    }

    public function getTime(Request $request)
    {
        $token = $request->token;
        $time = DB::table('lti_info')->where([['token', '=', $token]])->first();
        return response()->json(['time' => $time->session_active]);
    }
    // Function that returns the user and course data.
    public function getSession(Request $request)
    {
        // header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));

        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            // dd($sessionData);
            $this->registerLog('getSession', $sessionData);
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    return MoodleController::getSession($sessionData);
                    break;
                case 'sakai':
                    return SakaiController::getSession($sessionData);
                    break;
                default:
                    return response()->json(['ok' => false, 'error_type' => 'PLATFORM_NOT_SUPPORTED', 'data' => []]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function obtains data from a map.
    public function getMap(Request $request)
    {

        if ($this->checkToken($request->token)) {
            return MoodleController::getMap($request->map_id);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function obtains data from a version of a map.
    public function getVersions(Request $request)
    {
        if ($this->checkToken($request->token)) {
            return MoodleController::getVersions($request->map_id);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function obtains data from a version of a map.
    public function getVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            return MoodleController::getVersion($request->version_id);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // Function that returns ALL the modules of a course.
    public function getModules(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    return MoodleController::getModules($sessionData->platform_id, $sessionData->context_id);
                    break;
                case 'sakai':
                    if (isset($request->lesson)) {
                        return SakaiController::getModules($sessionData->platform_id, $sessionData->context_id, $request->lesson, $sessionData->session_id, $sessionData->context_id);
                    } else {
                        return response()->json(['ok' => false, 'error_type' => 'LESSON_NOT_VALID', 'data' => []]);
                    }
                    break;
                default:
                    error_log('La plataforma que está usando no está soportada');
                    return response()->json(['ok' => false, 'error_type' => 'PLATFORM_NOT_SUPPORTED', 'data' => []]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // Function that returns the modules with a specific type of a course.
    public function getModulesByType(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($request->type);
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    if ($request->type == 'unsupported') {
                        return MoodleController::getModulesNotSupported($request, $sessionData);
                    } else {
                        return MoodleController::getModulesByType($request, $sessionData);
                    }
                    break;
                case 'sakai':
                    return SakaiController::getModulesByType($request, $sessionData);
                    break;
                default:
                    error_log('The platform you are using is not supported.');
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function saves a version of a map.
    public function storeVersion(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($request);
        // error_log('LLAMO A STOREVERSION!!');
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('storeVersion', $sessionData);
            $saveData = $request->saveData;
            try {
                $course = Course::where('instance_id', $saveData['instance_id'])
                    ->where('course_id', $saveData['course_id'])
                    ->select('id')
                    ->first();

                $mapData = $saveData['map'];

                $map = Map::updateOrCreate(
                    ['created_id' => $mapData['id'], 'course_id' => $course->id, 'user_id' => (string)$saveData['user_id']],
                    ['name' => $mapData['name']]
                );

                $versionsData = $mapData['versions'];
                foreach ($versionsData as $versionData) {
                    // dd($versionData);
                    Version::updateOrCreate(
                        ['map_id' => $map->id, 'name' => $versionData['name']],
                        ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocks_data'])]
                    );
                }
                return response()->json(['ok' => true, 'errorType' => '', 'data' => []]);
            } catch (\Exception $e) {
                // dd($e);
                error_log($e);
                abort(500, $e->getMessage());
                return response()->json(['ok' => false, 'errorType' => 'ERROR_SAVING_VERSION']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function add a version of a map.
    public function addVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('addVersion', $sessionData);
            // header('Access-Control-Allow-Origin: *');
            $version = $request->version;
            // dd($request);
            $dataMap = Map::where('created_id', $version['map_id'])
                ->first();
            // dd($dataMap->id);
            try {
                Version::create([
                    'map_id' => $dataMap->id,
                    'name' => $version['name'],
                    'default' => boolval($version['default']),
                    'blocks_data' => json_encode($version['blocks_data'])
                ]);
                return response()->json(['ok' => true, 'errorType' => '', 'data' => []]);
            } catch (\Exception $e) {
                // dd($e);
                error_log($e);
                abort(500, $e->getMessage());
                return response()->json(['ok' => false, 'errorType' => 'ERROR_SAVING_VERSION']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function exports a version of a map to a course of a learning platform.
    public function exportVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('exportVersion', $sessionData);
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    return MoodleController::exportVersion($request);
                    break;
                case 'sakai':
                    return SakaiController::exportVersion($request, $sessionData);
                    break;
                default:
                    return response()->json(['ok' => false, 'error_type' => 'PLATFORM_NOT_SUPPORTED', 'data' => []]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function deletes a version of a map.
    public function deleteVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('deleteVersion', $sessionData);
            try {
                Version::destroy($request->id);
                return response()->json(['ok' => true]);
            } catch (\Exception $e) {
                // An error occurred, the changes will be reverted automatically.
                error_log($e);
                return response()->json(['ok' => false, 'errorType' => 'FAILED_TO_REMOVE_VERSION']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function deletes a map.
    public function deleteMap(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('deleteMap', $sessionData);
            try {
                $map = Map::where('created_id', $request->id);
                $map->delete();
                return response()->json(['ok' => true]);
            } catch (\Exception $e) {
                // An error occurred, the changes will be reverted automatically.
                error_log($e);
                return response()->json(['ok' => false, 'errorType' => 'FAILED_TO_REMOVE_MAP']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    // This function checks if a token is valid to access the data of a session.
    public function checkToken($token)
    {
        $nowDate = intval(Carbon::now()->valueOf());
        $sessionData = DB::table('lti_info')
            ->where('token', '=', $token)
            ->where('expires_at', '>=', $nowDate)
            ->first();
        if ($sessionData != null) {
            DB::table('lti_info')->where('token', '=', $token)
                ->update([
                    'session_active' => intval(Carbon::now()->addMinutes(env('TIME_LIMIT'))->valueOf()),
                ]);
            return true;
        } else {
            return false;
        }
    }
    // Define the function that gets the data from the database.
    public function registerLog($case, $userData)
    {
        // Obtain the current date in the format d-m-Y.
        $date = date("Y-m-d");
        $date2 = date("H:i:s");
        // Create folder name with date
        $file = base_path("logs/");
        // Check if the folder exists.
        if (!file_exists($file)) {
            // If it does not exist, create it with read and write permissions.
            mkdir($file, 0777, true);
        }
        // Create the file name with the date.
        $name = $file . "/" . $date . ".log";
        // Open the file in append mode.
        $archive = fopen($name, "a");
        $message = '';
        switch ($case) {
            case 'getSession':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has accessed UNIAdaptive through course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            case 'storeVersion':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to store version of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            case 'addVersion':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to add version of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            case 'exportVersion':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to export version of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            case 'deleteVersion':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to delete version of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            case 'deleteMap':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to delete map of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
                break;
            default:
                # code...
                break;
        }
        fwrite($archive, "[" . $date . " " . $date2 . "] " . $message . " \n");
        fclose($archive);
    }
    // This function obtains the current date and time.
    public function getDate($token)
    {
        if ($this->checkToken($token)) {
            return response()->json(['ok' => true, 'data' => date('Y-m-d\TH:i')]);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

    //front backend configuration
    // This function authenticates the user as administrator.
    public function auth(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');

        $parameter = $request->password;

        $adminPassword = env('ADMIN_PASSWORD');
        // dd($parameters, $adminPassword);

        try {
            if ($adminPassword == $parameter) {
                return response()->json(['valid' => true]);
            } else {
                return response()->json(['valid' => false, 'error_type' => 'INVALID_PASSWORD']);
            }
        } catch (Error $e) {
            return response()->json(['valid' => false, 'error_type' => 'INVALID_REQUEST']);
        }
    }
    // This function obtains resource usage data from the server.
    public function getResource()
    {
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => getrusage()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'error_type' => 'CANT_CONNECT_TO_SERVER']);
        }
    }
    // This function obtains the server's start date and time.
    public function getServerInfo()
    {
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => Carbon::createFromTimestamp(filemtime('/proc/uptime'))->toDateTimeString()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'error_type' => 'CANT_CONNECT_TO_SERVER']);
        }
    }

    // Function that returns the token (if it exists) of the url added in the function
    public static function getLmsToken($url_lms, $platform, $validated = null)
    {
        // header('Content-Type: application/json');
        $multiple_lms_config = '';
        if (!config()->has('multiple_lms_config')) {
            $return = [
                'ok' => false,
                'data' => "[TokenNotConfigured]",
                'error' => "A token has not been configured for this LMS, you must add the token generated in ({$url_lms}) in the configuration file.",
            ];
            return $return;
        } else {
            // Obtains from the multiple_lms_config.php configuration the lms_data that contains all the LMS grouped by url and token
            $multiple_lms_config = config('multiple_lms_config.lms_data');

            switch ($platform) {
                case 'moodle':
                    foreach ($multiple_lms_config as $lms_data) {
                        if ($lms_data['url'] == $url_lms) {

                            if ($validated) {
                                $token = trim($lms_data['token']);
                                if (!$token) {
                                    $return = [
                                        'ok' => false,
                                        'data' => "[TokenNotConfigured]",
                                        'error' => "A token has not been configured for this LMS, you must add the token generated in ({$url_lms}) in the configuration file.",
                                    ];
                                    return $return;
                                }
                                $return = [
                                    'ok' => true,
                                    'data' => $token,
                                ];
                                return $return;
                            }

                            $token = trim($lms_data['token']);

                            if (!$token) {
                                break;
                            }

                            $client = new Client([
                                'base_uri' => $url_lms . '/webservice/rest/server.php',
                                'timeout' => 20.0,
                            ]);
                            $response = $client->request('GET', '', [
                                'query' => [
                                    'wstoken' => $token,
                                    'wsfunction' => 'local_uniadaptive_get_assignable_roles',
                                    'contextid' => 0,
                                    'moodlewsrestformat' => 'json',
                                ],
                            ]);

                            $content = $response->getBody()->getContents();
                            $data = json_decode($content);

                            if (isset($data->exception)) {
                                $return = [
                                    'ok' => false,
                                    'data' => "[$data->errorcode]",
                                    'error' => "$data->message",
                                ];
                                return $return;
                            }

                            $return = [
                                'ok' => true,
                                'data' => $token,
                            ];
                            return $return;
                        }
                    }

                    $return = [
                        'ok' => false,
                        'data' => "[TokenNotConfigured]",
                        'error' => "A token has not been configured for this LMS, you must add the token generated in ({$url_lms}) in the configuration file.",
                    ];
                    return $return;
                    break;
                case 'sakai':
                    foreach ($multiple_lms_config as $lms_data) {
                        if ($lms_data['url'] == $url_lms) {
                            // dd($lms_data);
                            if ($validated) {
                                $token = [
                                    'user' => trim($lms_data['user']),
                                    'password' => trim($lms_data['password']),
                                ];
                                // dd($token);
                                if (!isset($token['user']) || !isset($token['password'])) {
                                    $return = [
                                        'ok' => false,
                                        'data' => "[TokenNotConfigured]",
                                        'error' => "A token has not been configured for this LMS, you must add the token generated in ({$url_lms}) in the configuration file.",
                                    ];
                                    return $return;
                                }
                                $return = [
                                    'ok' => true,
                                    'data' => $token,
                                ];
                                return $return;
                            }
                        }
                    }

                    $return = [
                        'ok' => false,
                        'data' => "[TokenNotConfigured]",
                        'error' => "A token has not been configured for this LMS, you must add the token generated in ({$url_lms}) in the configuration file.",
                    ];
                    return $return;
                    break;
                default:
                    # code...
                    break;
            }
        }
    }
    public static function getConfig()
    {
        $archivos = [
            base_path('/config/frontendConfiguration.json'),
            base_path('/config/frontendDefaultConfiguration.json'),
        ];

        foreach ($archivos as $archivo) {
            if (file_exists($archivo)) {

                $contenido = file_get_contents($archivo);
                $json = json_decode($contenido, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }
        }
        return null;
    }

    public static function setConfig(Request $request)
    {
        $password = $request->password;

        $json = $request->settings;

        if ($password == env('ADMIN_PASSWORD')) {
            // $decodedJson = json_encode($json, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $archivo = base_path('/config/frontendConfiguration.json');
                if (file_put_contents($archivo, json_encode($json, true)) !== false) {

                    return ['ok' => true];
                }
            }
            return ['ok' => false, 'error' => 'FAILLURE_CHANGE_CONFIG'];
        }

        return ['ok' => false, 'error' => 'INVALID_PASSWORD'];
    }

    public static function ping(Request $request)
    {
        if (isset($request->ping)) {
            $return = [
                'pong' => 'pong',
            ];
            return $return;
        }
    }
}
