<?php

namespace App\Http\Controllers;

use App\Models\Map;
use App\Models\Version;
use Error;
use LonghornOpen\LaravelCelticLTI\LtiTool;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        if (env('APP_PROXY') != '') {
            $_SERVER['SERVER_NAME'] = env('APP_PROXY');
            $_SERVER['SERVER_PORT'] = env('APP_PROXY_PORT');
        }
        if (env('APP_HTTPS') != '') {
            $_SERVER['HTTPS'] = env('APP_HTTPS');
        }
        $tool = LtiTool::getLtiTool();
        $tool->handleRequest();
        $jwt = $tool->getJWT();
        $fire = $tool->getMessageParameters();
        $session = DB::table('lti_info')->where([
            ['user_id', '=', $fire['user_id']],
            ['platform_id', '=', $fire['platform_id']],
            ['context_id', '=', $fire['context_id']],
            ['expires_at', '>=', intval(Carbon::now()->valueOf())]
        ])->first();
        if ($session) {
            switch ($fire['tool_consumer_info_product_family_code']) {
                case 'moodle':
                    DB::table('lti_info')->where([
                        ['user_id', '=', $fire['user_id']],
                        ['platform_id', '=', $fire['platform_id']],
                        ['context_id', '=', $fire['context_id']],
                        ['expires_at', '>=', intval(Carbon::now()->valueOf())]
                    ])->update([
                                'profile_url' => MoodleController::getImgUser(
                                    $fire['platform_id'],
                                    $fire['user_id']
                                ),
                                'lis_person_name_full' => $fire['lis_person_name_full']
                            ]);
                    break;
                case 'sakai':
                default:
                    break;
            }
        } else {
            $currentDate = date('Y-m-d H:i:s');
            $expDate = intval(Carbon::now()->addSeconds(3000)->valueOf());
            $session = DB::table('lti_info')->where([
                ['user_id', '=', $fire['user_id']],
                ['platform_id', '=', $fire['platform_id']],
                ['context_id', '=', $fire['context_id']]
            ])->delete();
            switch ($fire['tool_consumer_info_product_family_code']) {
                case 'moodle':
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' => $fire['context_id'],
                        'context_title' => $fire['context_title'], //
                        'launch_presentation_locale' => $fire['launch_presentation_locale'], //
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                        'user_id' => $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'], //
                        'profile_url' => MoodleController::getImgUser($fire['platform_id'], $fire['user_id']),
                        'roles' => $fire['roles'], //
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate, //
                    ]);
                    break;
                case 'sakai':

                    $jwtPayload = $jwt->getPayload();
                    $locale = $jwtPayload->locale;
                    $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' => $fire['context_id'],
                        'context_title' => $fire['context_title'], //
                        'launch_presentation_locale' => $locale, //
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'ext_sakai_serverid' => $sakai_serverid,
                        'session_id' => SakaiController::createSession($fire['platform_id'], $sakai_serverid), //
                        'launch_presentation_return_url' => $fire['platform_id'] . '/portal/site/' . $fire['context_id'],
                        'user_id' => $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'], //
                        'profile_url' => 'default',
                        'roles' => $fire['roles'], //
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate, //
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
            ['expires_at', '>=', intval(Carbon::now()->valueOf())]
        ])->first();
        // dd($session);
        $headers = @get_headers(env('FRONT_URL'));
        $canSee = false;
        foreach ($headers as $header) {
            if (strpos($header, '200 OK')) {
                $canSee = true;
                break;
            }
        }
        if ($canSee) {
            // URL is available
            // Generate redirect response
            // dd($session->token);
            return redirect()->to(env('FRONT_URL') . '?token=' . $session->token);
        } else {
            // URL is not available
            // Handle error
            return response('Error: La url no está disponible', 500);
        }
    }
    // Function that returns the user and course data.
    public function getSession(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
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
                        return SakaiController::getModules($sessionData->platform_id, $request->lesson, $sessionData->session_id);
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
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('storeVersion', $sessionData);
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    return MoodleController::storeVersion($request->saveData, $request->token);
                    break;
                case 'sakai':
                    if (isset($request->lesson)) {
                        // return SakaiController::storeVersion();
                    } else {
                        return response()->json(['ok' => false, 'error_type' => 'LESSON_NOT_VALID', 'data' => []]);
                    }
                    break;
                default:
                    error_log('The platform you are using is not supported.');
                    return response()->json(['ok' => false, 'error_type' => 'PLATFORM_NOT_SUPPORTED', 'data' => []]);
                    break;
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
    function deleteMap(Request $request)
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
    function checkToken($token)
    {
        $nowDate = intval(Carbon::now()->valueOf());
        $sessionData = DB::table('lti_info')
            ->where('token', '=', $token)
            ->where('expires_at', '>=', $nowDate)
            ->first();
        if ($sessionData != null) {
            return true;
        } else {
            return false;
        }
    }
    // Define the function that gets the data from the database.
    function registerLog($case, $userData)
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
            // case 'getVersion':
            //         $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to obtain the versions of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
            //     break;
            // case 'getModules':
            //         $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to obtain the modules of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
            //     break;
            // case 'getModulesByType':
            //         $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to obtain the modules list of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
            //     break;
            case 'storeVersion':
                $message = 'User ID: ' . $userData->user_id . ' ("' . $userData->lis_person_name_full . '") from LMS: "' . $userData->platform_id . '" has made a request to store version of course ID: ' . $userData->context_id . ' ("' . $userData->context_title . '")';
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
        $password = $request->password;
        $adminPassword = env('ADMIN_PASSWORD');

        try {
            if ($adminPassword == $password || $adminPassword == '') {
                return response()->json(['ok' => true]);
            } else {
                return response()->json(['ok' => false, 'error_type' => 'INVALID_PASSWORD']);
            }
        } catch (Error $e) {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_REQUEST']);
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

}