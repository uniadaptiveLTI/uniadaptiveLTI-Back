<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;

use Error;
use GuzzleHttp\Client;
use LonghornOpen\LaravelCelticLTI\LtiTool;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function PHPUnit\Framework\isNan;

class LtiController extends Controller
{

    public function getJWKS() {
    header('Access-Control-Allow-Origin: *' /*. env('FRONT_URL')*/);
        $tool = LtiTool::getLtiTool();
        return $tool->getJWKS();
    }
    // Función que obtiene datos del LMS, los almacena en la base de datos (TEMPORAL) y redirige al front
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
        // dd($tool);
        $jwt = $tool->getJWT();
        $fire = $tool->getMessageParameters();
        $session = DB::table('lti_info')->where([
            ['user_id', '=', $fire['user_id']],
            ['platform_id', '=', $fire['platform_id']],
            ['context_id', '=', $fire['context_id']],
            ['expires_at', '>=', intval(Carbon::now()->valueOf())]
        ])->first();
        if($session){
            switch ($fire['tool_consumer_info_product_family_code']) {
                case 'moodle':
                    DB::table('lti_info')->where([
                        ['user_id', '=', $fire['user_id']],
                        ['platform_id', '=', $fire['platform_id']],
                        ['context_id', '=', $fire['context_id']],
                        ['expires_at', '>=', intval(Carbon::now()->valueOf())]
                    ])->update([
                        'profile_url' => MoodleController::getImgUser($fire['platform_id'],
                        $fire['user_id']),'lis_person_name_full' => $fire['lis_person_name_full']
                    ]);
                    break;
                case 'sakai':
                    default:
                    break;
            }
        }else{
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
                        'context_id' =>  $fire['context_id'],
                        'context_title' => $fire['context_title'],//
                        'launch_presentation_locale' => $fire['launch_presentation_locale'],//
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                        'user_id' =>  $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'],//
                        'profile_url' => MoodleController::getImgUser($fire['platform_id'], $fire['user_id']),
                        'roles' =>  $fire['roles'],//
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,//
                    ]);
                    break;
                case 'sakai':
                    
                    $jwtPayload = $jwt->getPayload();
                    $locale = $jwtPayload->locale;
                    $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;
                    DB::table('lti_info')->insert([
                        'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                        'context_id' =>  $fire['context_id'],
                        'context_title' => $fire['context_title'],//
                        'launch_presentation_locale' => $locale,//
                        'platform_id' => $fire['platform_id'],
                        'token' => Str::uuid()->toString(),
                        'ext_sakai_serverid' => $sakai_serverid,
                        'session_id' =>  SakaiController::createSession($fire['platform_id'], $sakai_serverid),//
                        'launch_presentation_return_url' => $fire['platform_id'] .'/portal/site/'.$fire['context_id'],
                        'user_id' =>  $fire['user_id'],
                        'lis_person_name_full' => $fire['lis_person_name_full'],//
                        'profile_url' => 'default',
                        'roles' =>  $fire['roles'],//
                        'expires_at' => $expDate,
                        'created_at' => $currentDate,
                        'updated_at' => $currentDate,//
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
            if(strpos($header, '200 OK')){
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

    // Función que devuelve los datos del usuario y del curso
    public function getSession(Request $request)
    {
    // header('Access-Control-Allow-Origin: *' /*. env('FRONT_URL')*/);
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




    public function getVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            // $this->registerLog('getVersion', $sessionData);
            return MoodleController::getVersion($request->version_id);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

    // Función que devuelve TODOS los modulos de un curso
    public function getModules(Request $request)
    {

        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            // $this->registerLog('getModules', $sessionData);
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    return MoodleController::getModules($sessionData->platform_id, $sessionData->context_id);
                    break;
                case 'sakai':
                    if(isset($request->lesson)){
                        return SakaiController::getModules($sessionData->platform_id, $request->lesson, $sessionData->session_id);
                    }else{
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
    // Función que devuelve los modulos con tipo en concreto de un curso
    public function getModulesByType(Request $request)
    {
        if ($this->checkToken($request->token)) {
            
            // dd($request);
            // dd(intVal($request->course), $request->type);
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            // $this->registerLog('getModulesByType', $sessionData);
            switch ($sessionData->tool_consumer_info_product_family_code) {
                case 'moodle':
                    // dd($request->type);
                    if ($request->type == 'unsupported') {
                        return MoodleController::getModulesNotSupported($request,$sessionData);
                    } else {
                        return MoodleController::getModulesByType($request,$sessionData);
                    }

                    break;
                case 'sakai':
                    return SakaiController::getModulesByType($request,$sessionData);
                    break;
                default:
                    error_log('La plataforma que está usando no está soportada');
                    break;
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
    public function storeVersion(Request $request)
    {

        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('storeVersion', $sessionData);
            return MoodleController::storeVersion($request->saveData,$request->token);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

    public function exportVersion(Request $request)
    {
        if ($this->checkToken($request->token)) {
            $sessionData = DB::table('lti_info')
                ->where('token', '=', $request->token)
                ->first();
            $this->registerLog('exportVersion', $sessionData);
            return MoodleController::exportVersion($request);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

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
                // Ocurrió un error, los cambios serán revertidos automáticamente
                error_log($e);
                return response()->json(['ok' => false, 'errorType' => 'FAILED_TO_REMOVE_VERSION']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }
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
                // Ocurrió un error, los cambios serán revertidos automáticamente
                error_log($e);
                return response()->json(['ok' => false, 'errorType' => 'FAILED_TO_REMOVE_MAP']);
            }
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

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

    // Definir la función que obtiene los datos de la base de datos
    function registerLog($case , $userData) {

        // Obtener la fecha actual en el formato d-m-Y
        $date = date("Y-m-d");
        $date2 = date("H:i:s");

        // Crear el nombre de la carpeta con la fecha
        $file = base_path("logs/");

        // Comprobar si la carpeta existe
        if (!file_exists($file)) {
            // Si no existe, crearla con permisos de lectura y escritura
            mkdir($file, 0777, true);
        }
         
        // Crear el nombre del archivo con la fecha
        $name = $file . "/".$date.".log";
        
        // Abrir el archivo en modo append
        $archive = fopen($name, "a");
        $message = '';
        switch ($case) {
            case 'getSession':
                    $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has accessed UNIAdaptive through course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
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
                    $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to store version of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
                break;
            case 'exportVersion':
                    $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to export version of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
                break;
            case 'deleteVersion':
                    $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to delete version of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
                break;
            case 'deleteMap':
                    $message = 'User ID: '.$userData->user_id.' ("'.$userData->lis_person_name_full.'") from LMS: "'.$userData->platform_id.'" has made a request to delete map of course ID: '.$userData->context_id.' ("'.$userData->context_title.'")';
                break;
            default:
                # code...
                break;
        }
        fwrite($archive, "[" . $date . " " . $date2 . "] ".$message." \n");
        fclose($archive);
    }

    public function getDate($token){
        
        if ($this->checkToken($token)) {
            return response()->json(['ok' => true, 'data' => date('Y-m-d\TH:i')]);
        } else {
            return response()->json(['ok' => false, 'error_type' => 'INVALID_OR_EXPIRED_TOKEN', 'data' => []]);
        }
    }

    //front backend configuration

    public function auth(Request $request){
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

    public function getResource(){
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => getrusage()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'error_type' => 'CANT_CONNECT_TO_SERVER']);
        }
        
    }

    public function getServerInfo(){
        header('Content-Type: application/json');
        try {
            return response()->json(['ok' => true, 'data' => Carbon::createFromTimestamp(filemtime('/proc/uptime'))->toDateTimeString()]);
        } catch (Error $e) {
            return response()->json(['ok' => false, 'error_type' => 'CANT_CONNECT_TO_SERVER']);
        }
    }
}
