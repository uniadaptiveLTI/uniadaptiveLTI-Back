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
use Mockery\Undefined;

use function PHPUnit\Framework\assertIsNotInt;

class LtiController extends Controller
{
    /**
     * @return array
     */
    public function getJWKS()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        $tool = LtiTool::getLtiTool();
        return $tool->getJWKS();
    }
    /**
     * Function that obtains data from the LMS, stores it in the database (TEMPORARY) and redirects to the front end.
     * 
     * @return array
     */
    public function saveSession()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
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
        $tool = LtiTool::getLtiTool();
        $tool->handleRequest();
        $jwt = $tool->getJWT();
        $fire = $tool->getMessageParameters();
        $platform = $fire['tool_consumer_info_product_family_code'];
        $token_request = LtiController::getLmsToken($fire['platform_id'], $platform, true);
        if ($token_request == '') {
            return response('The token used not valid or file multiple_lms_config.php doesn`t exist');
        } elseif ($platform == "moodle") {
            $result = MoodleController::checkToken($fire['platform_id'], $token_request);
            if (!$result['ok']) {
                return response()->json([
                    'error' => $result['data']['error'],
                    'error_code' => $result['data']['error_code'],
                    'message' => $result['data']['message']
                ]);
            }
        }


        $session = DB::table('lti_info')->where([
            ['user_id', '=', $fire['user_id']],
            ['platform_id', '=', $fire['platform_id']],
            ['context_id', '=', $fire['context_id']],
            ['expires_at', '>=', intval(Carbon::now()->valueOf())],
        ])->first();
        if ($session) {
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

                    $sessionIdRequest = SakaiController::createSession($fire['platform_id'], $sakai_serverid, $token_request);

                    if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                        $session_id = $sessionIdRequest['data']['user_id'];
                    } else {
                        return response()->json(['ok' => false, 'data' => ['error' => "CREATE_SESSION_ERROR"]]);
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
                        'launch_presentation_locale' => $fire['launch_presentation_locale'],
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                        'user_id' => (string) $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'] == '' ? 'Usuario' : $fire['lis_person_name_full'],
                        'profile_url' => MoodleController::getImgUser($fire['platform_id'], $fire['user_id']),
                        'roles' => $fire['roles'],
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,
                    ]);
                    break;
                case 'sakai':

                    $jwtPayload = $jwt->getPayload();
                    $locale = $jwtPayload->locale;
                    $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;

                    $sessionIdRequest = SakaiController::createSession($fire['platform_id'], $sakai_serverid, $token_request);

                    if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                        $session_id = $sessionIdRequest['data']['user_id'];
                    } else {
                        return response()->json(['ok' => false, 'data' => ['error' => "CREATE_SESSION_ERROR"]]);
                    }
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' => $fire['context_id'],
                        'context_title' => $fire['context_title'],
                        'launch_presentation_locale' => $locale,
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'ext_sakai_serverid' => $sakai_serverid,
                        'session_id' => $session_id,
                        'launch_presentation_return_url' => $fire['platform_id'] . '/portal/site/' . $fire['context_id'],
                        'user_id' => $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'],
                        'profile_url' => SakaiController::getUrl($fire['platform_id'], $fire['context_id'], SakaiController::getId($fire['user_id'])),
                        'roles' => $fire['roles'],
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,
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
            return response()->json([
                'error' => 'ERROR_TO_REDIRECT',
                'error_code' => 500,
                'message' => 'It is not possible to redirect to the Front. Check that the front is working correctly. Check the address in the .env or if it has been launched correctly.'
            ]);
        }
    }

    /**
     * @param Request $request
     * 
     * @return array
     */
    public function getTime(Request $request)
    {
        $token = $request->token;
        $time = DB::table('lti_info')->where([['token', '=', $token]])->first();
        return response()->json(['time' => $time->session_active]);
    }
    /**
     * Function that returns the user and course data.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getSession(Request $request)
    {
        // header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));

        if ($this->checkToken($request->token)) {

            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();

            $this->registerLog('getSession', $sessionData);
            $platform = $sessionData->tool_consumer_info_product_family_code;
            $token_request = LtiController::getLmsToken($sessionData->platform_id, $platform, true);
            $platformName = LtiController::getLmsName($sessionData->platform_id);

            if ($token_request != '' && is_string($platformName)) {
                $sessionData->platform_name = $platformName;
            }

            switch ($platform) {
                case 'moodle':

                    return MoodleController::getSession($sessionData, $token_request);
                    break;
                case 'sakai':
                    return SakaiController::getSession($sessionData);
                    break;
                default:
                    return response()->json(['ok' => false, 'data' => ['error' => 'PLATFORM_NOT_SUPPORTED']]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function obtains data from a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getMap(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $dataMap = Map::where('created_id', $request->map_id)
                ->first();
            if ($dataMap == null) {

                return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_MAP']]);
            }
            $dataMap = json_decode($dataMap);
            return response()->json(['ok' => true, 'data' => $dataMap]);
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function obtains data from a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getVersions(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $mapId = Map::select('id')
                ->where('created_id', $request->map_id)
                ->first();
            // dd($mapId);
            $dataVersions = Version::select('id', 'map_id', 'name')
                ->where('map_id', $mapId->id)
                ->get();

            if ($dataVersions == null) {
                return response()->json(['ok' => false,  'data' => ['error' => 'INVALID_VERSION', 'request' => $dataVersions]]);
            }
            return response()->json(['ok' => true, 'data' => $dataVersions->toArray()]);
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }

    /**
     * This function obtains data from a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $dataVersion = Version::where('id', $request->version_id)
                ->first();
            if ($dataVersion == null) {
                return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_VERSION']]);
            }
            $dataVersion->blocks_data = json_decode($dataVersion->blocks_data);
            return response()->json(['ok' => true, 'data' => $dataVersion]);
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * Function that returns ALL the modules of a course.
     * 
     * @param Request $request
     * 
     * @return array
     */
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
                        return SakaiController::getModules($sessionData->platform_id, $request->lesson, $sessionData->session_id, $sessionData->context_id);
                    } else {
                        return response()->json(['ok' => false, 'data' => ['error' => 'LESSON_NOT_VALID']]);
                    }
                    break;
                default:
                    return response()->json(['ok' => false, 'data' => ['error' => 'PLATFORM_NOT_SUPPORTED']]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * Function that returns the modules with a specific type of a course.
     * 
     * @param Request $request
     * 
     * @return array
     */
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
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }

    /**
     * This function saves a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function storeVersion(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($request);
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
                    Version::updateOrCreate(
                        ['map_id' => $map->id, 'name' => $versionData['name']],
                        ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocks_data'])]
                    );
                }
                return response()->json(['ok' => true]);
            } catch (\Exception $e) {
                error_log($e);
                abort(500, $e->getMessage());
                return response()->json(['ok' => false, 'data' => ['error' => 'ERROR_SAVING_VERSION']]);
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function add a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function addVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('addVersion', $sessionData);
            $version = $request->version;
            $dataMap = Map::where('created_id', $version['map_id'])
                ->first();
            try {
                Version::create([
                    'map_id' => $dataMap->id,
                    'name' => $version['name'],
                    'default' => boolval($version['default']),
                    'blocks_data' => json_encode($version['blocks_data'])
                ]);
                return response()->json(['ok' => true]);
            } catch (\Exception $e) {
                error_log($e);
                abort(500, $e->getMessage());
                return response()->json(['ok' => false, 'data' => ['error' => 'ERROR_SAVING_VERSION']]);
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function exports a version of a map to a course of a learning platform.
     * 
     * @param Request $request
     * 
     * @return array
     */
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
                    return response()->json(['ok' => false, 'data' => ['error' => 'PLATFORM_NOT_SUPPORTED']]);
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function deletes a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
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
                error_log($e);
                return response()->json(['ok' => false, 'data' => ['error' => 'FAILED_TO_REMOVE_VERSION']]);
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function deletes a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
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
                error_log($e);
                return response()->json(['ok' => false, 'error' => 'FAILED_TO_REMOVE_MAP']);
            }
        } else {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_OR_EXPIRED_TOKEN']]);
        }
    }
    /**
     * This function checks if a token is valid to access the data of a session.
     * 
     * @param string $token
     * 
     * @return array
     */
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
    /**
     * Define the function that gets the data from the database.
     * 
     * @param string $case
     * @param object $userData
     * 
     * @return array
     */
    public function registerLog(string $case, object $userData)
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
    /**
     * This function obtains the current date and time.
     * 
     * @return array
     */
    public function getDate()
    {
        return response()->json(['ok' => true, 'data' => date('Y-m-d\TH:i')]);
    }

    /**
     * This function authenticates the user as administrator.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function auth(Request $request)
    {
        $parameter = $request->password;
        $adminPassword = env('ADMIN_PASSWORD');
        try {
            if ($adminPassword == $parameter) {
                return response()->json(['ok' => true]);
            } else {
                return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_PASSWORD']]);
            }
        } catch (Error $e) {
            return response()->json(['ok' => false, 'data' => ['error' => 'INVALID_REQUEST']]);
        }
    }
    /**
     * This function obtains resource usage data from the server.
     * 
     * @return array
     */
    public function getResource()
    {
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => getrusage()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'data' => ['error' => 'CANT_CONNECT_TO_SERVER']]);
        }
    }
    /**
     * This function obtains the server's start date and time.
     * 
     * @return array
     */
    public function getServerInfo()
    {
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => Carbon::createFromTimestamp(filemtime('/proc/uptime'))->toDateTimeString()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'data' => ['error' => 'CANT_CONNECT_TO_SERVER']]);
        }
    }
    /**
     * Function that returns the name (if it exists) of the url added in the function
     * 
     * @param string $url_lms
     * 
     * @return array
     */
    public static function getLmsName(string $url_lms)
    {
        // Obtains from the multiple_lms_config.php configuration the lms_data that contains all the LMS grouped by url and token
        $multiple_lms_config = config('multiple_lms_config.lms_data');
        foreach ($multiple_lms_config as $name => $lms_data) {
            if ($lms_data['url'] == $url_lms) {
                return $name;
            }
        }
    }
    /**
     * Function that returns the token (if it exists) of the url added in the function
     * 
     * @param string $url_lms
     * @param string $platform
     * 
     * @return mixed
     */
    public static function getLmsToken(string $url_lms, string $platform)
    {
        // header('Content-Type: application/json');
        if (!config()->has('multiple_lms_config')) {
            return '';
        } else {
            // Obtains from the multiple_lms_config.php configuration the lms_data that contains all the LMS grouped by url and token
            $multiple_lms_config = config('multiple_lms_config.lms_data');
            switch ($platform) {
                case 'moodle':
                    foreach ($multiple_lms_config as $lms_data) {
                        if ($lms_data['url'] == $url_lms) {
                            return trim($lms_data['token']);
                        }
                    }
                    return '';
                    break;
                case 'sakai':
                    foreach ($multiple_lms_config as $lms_data) {
                        if ($lms_data['url'] == $url_lms) {
                            // dd($lms_data);

                            $token = [
                                'user' => trim($lms_data['user']),
                                'password' => trim($lms_data['password']),
                            ];

                            // dd($token);
                            if (isset($lms_data['cookieName'])) {
                                $token['cookieName'] = trim($lms_data['cookieName']);
                            }
                            return $token;
                        }
                    }
                    return '';
                    break;
                default:
                    return '';
                    break;
            }
        }
    }
    /**
     * Gets the front-end style configuration
     * 
     * @return mixed
     */
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

    /**
     * Modifies the front-end style settings
     * 
     * @param Request $request
     * 
     * @return array
     */
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
            return ['ok' => false, 'data' => ['error' => 'FAILLURE_CHANGE_CONFIG']];
        }

        return ['ok' => false, 'data' => ['error' => 'INVALID_PASSWORD']];
    }

    /**
     * Pings
     * 
     * @param Request $request
     * 
     * @return array
     */
    public static function ping(Request $request)
    {
        if (isset($request->ping)) {
            $return = [
                'data' => 'pong',
            ];
            return $return;
        }
    }
}
