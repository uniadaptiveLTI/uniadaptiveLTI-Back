<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use GuzzleHttp\Client;
use LonghornOpen\LaravelCelticLTI\LtiTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LtiController extends Controller
{

    public function getJWKS()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        $tool = LtiTool::getLtiTool();
        return $tool->getJWKS();
    }
    // Función que obtiene datos del LMS, los almacena en la base de datos (TEMPORAL) y redirige al front
    public function saveSession()
    {
        $tool = LtiTool::getLtiTool();
        
        $tool->handleRequest();
        // dd($tool);
        $fire = $tool->getMessageParameters();
        // dd($fire);
        $fechaActual = date('Y-m-d H:i:s');
        // dd($fire);
        switch ($fire['tool_consumer_info_product_family_code']) {
            case 'moodle':
                DB::table('lti_info')->insert([
                    'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                    'context_id' =>  $fire['context_id'],
                    'context_title' => $fire['context_title'],
                    'launch_presentation_locale' => $fire['launch_presentation_locale'],
                    'platform_id' => $fire['platform_id'],
                    'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                    'user_id' =>  $fire['user_id'],
                    'lis_person_name_full' => $fire['lis_person_name_full'],
                    'profile_url' => MoodleController::getImgUser($fire['platform_id'], $fire['user_id']),
                    'roles' =>  $fire['roles'],
                    'created_at' => $fechaActual,
                    'updated_at' => $fechaActual,
                ]);
                break;
            case 'sakai':
                DB::table('lti_info')->insert([
                    'tool_consumer_info_product_family_code' => $fire['tool_consumer_info_product_family_code'],
                    'context_id' =>  $fire['context_id'],
                    'context_title' => $fire['context_title'],
                    'launch_presentation_locale' => $fire['launch_presentation_locale'],
                    'platform_id' => $fire['ext_sakai_server'],
                    'ext_sakai_serverid' => $fire['ext_sakai_serverid'],
                    'session_id' =>  SakaiController::createSession($fire['ext_sakai_server'], $fire['ext_sakai_serverid']),
                    'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
                    'user_id' =>  $fire['user_id'],
                    'lis_person_name_full' => $fire['lis_person_name_full'],
                    'profile_url' => $fire['user_image'],
                    'roles' =>  $fire['roles'],
                    'created_at' => $fechaActual,
                    'updated_at' => $fechaActual,
                ]);
                break;
            default:
                break;
        }
        $headers = @get_headers(env('FRONT_URL'));
        // dd(env('FRONT_URL'));
        if ($headers && strpos($headers[0], '200')) {
            // URL is available
            // Generate redirect response
            return redirect()->to(env('FRONT_URL'));
        } else {
            // URL is not available
            // Handle error
            return response('Error: La url no está disponible', 500);
        }
    }

    // Función que devuelve los datos del usuario y del curso
    public function getSession()
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));


        
        // $client = new Client([
        //     'base_uri' => 'http://localhost/moodle-3.11.13/webservice/rest/server.php',
        //     'timeout' => 2.0,
        // ]);
        // $response = $client->request('GET', '', [
        //     'query' => [
        //         'wstoken' => env('WSTOKEN'),
        //         'wsfunction' => 'local_uniadaptive_get_coursegrades',
        //         'course_id' => 8,
        //         'moodlewsrestformat' => 'json'
        //     ]
        // ]);
        // $content = $response->getBody()->getContents();
        // $data = json_decode($content);

        // dd($data);




        $lastInserted = DB::table('lti_info')->latest()->first();
        // dd($lastInserted);
        switch ($lastInserted->tool_consumer_info_product_family_code) {
            case 'moodle':
                return MoodleController::getSession($lastInserted);
                break;
            case 'sakai':
                return SakaiController::getSession($lastInserted);
                break;
            default:
                error_log('La plataforma que está usando no está soportada');
                break;
        }
    }
    public function getVersion(Request $request){
    header('Access-Control-Allow-Origin: *'/* . env('FRONT_URL')*/);
    // dd($request);
        return MoodleController::getVersion($request->version_id);
    }

    // Función que devuelve TODOS los modulos de un curso
    public function getModules(Request $request)
    {
        header('Access-Control-Allow-Origin: *'/* . env('FRONT_URL')*/);
        // dd($request);



        $instance = Instance::select('platform', 'url_lms')
            ->where('id', $request->instance)
            ->first();
        // dd($instance);
        if ($instance->exists) {
            // dd($request->course);
            switch ($instance->platform) {
                case 'moodle':
                    return MoodleController::getModules($instance->url_lms, $request->course);
                    break;
                case 'sakai':
                    return SakaiController::getLessons($instance->url_lms, $request->course, $request->session);
                    break;
                default:
                    error_log('La plataforma que está usando no está soportada');
                    break;
            }
        } else {
            error_log('No existe la instancia');
        }
    }
    // Función que devuelve los modulos con tipo en concreto de un curso
    public function getModulesByType(Request $request)
    {
        header('Access-Control-Allow-Origin: *'/* . env('FRONT_URL')*/);
        // dd($request);
        // dd(intVal($request->course), $request->type);
        switch ($request->platform) {
            case 'moodle':
                // dd($request->type);
                if($request->type == 'unsupported'){
                    return MoodleController::getModulesNotSupported($request);
                }else{
                    return MoodleController::getModulesByType($request);
                }
                
                break;
            case 'sakai':
                return SakaiController::getModulesByType($request);
                break;
            default:
                error_log('La plataforma que está usando no está soportada');
                break;
        }
        // dd($request);
    }
    public function storeVersion(Request $request)
    {
        MoodleController::storeVersion($request);
    }

    public function exportVersion(Request $request)
    {
        MoodleController::exportVersion($request);
    }

    public function deleteVersion(Request $request){
        error_log('hola deleteVersion');

        try {
            Version::destroy($request->id);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            // Ocurrió un error, los cambios serán revertidos automáticamente
            error_log($e);
            return response()->json(['ok' => false]);
        }
        
    }
    function deleteMap(Request $request) {
        error_log($request->id);
        try {
            
            $map = Map::where('created_id', $request->id);
            $map->delete();
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            // Ocurrió un error, los cambios serán revertidos automáticamente
            error_log($e);
            return response()->json(['ok' => false]);
        }
    }
}
