<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function GuzzleHttp\json_decode;

class MoodleController extends Controller
{
    // guarda la sesión del usuario en la base de datos y redirecciona al front
    public static function storeVersion(Request $request)
    {
        try {
            $course = Course::where('instance_id', $request->saveData['instance_id'])
                ->where('course_id', $request->saveData['course_id'])
                ->select('id')
                ->first();
            $mapData = $request->saveData['map'];
            
            $map = Map::updateOrCreate(
                ['created_id' => $mapData['id'],  'course_id' =>  $course->id, 'user_id' => intval($request->saveData['user_id'])],
                ['name' => $mapData['name'], 'lesson_id' => $request->saveData['instance_id']]
            );
            
            $versionData = $mapData['versions'];
            $version = Version::updateOrCreate(
                ['map_id' => $map->id, 'name' => $versionData['name']],
                ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocksData'])]
            );
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            
            abort(500, $e->getMessage());
            return response()->json(['ok' => false]);
        }
    }

    // devuelve la sesión almacenada en la base de datos de un usuario que se ha conectado a la lti
    public static function getSession(Object $lastInserted)
    {

        // dd($lastInserted);
        $data = [
            [
                'user_id' => $lastInserted->user_id,
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => $lastInserted->profile_url,
                'roles' => $lastInserted->roles
            ],
            [
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'instance_id' => MoodleController::getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'course_id' => $lastInserted->context_id,
                'name' => $lastInserted->context_title,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'sections' => MoodleController::getSections($lastInserted->platform_id, $lastInserted->context_id),
                'groups' => MoodleController::getGroups($lastInserted->platform_id, $lastInserted->context_id),
                'grupings' => MoodleController::getGrupings($lastInserted->platform_id, $lastInserted->context_id),
                'badges' => MoodleController::getBadges($lastInserted->context_id),
                'grades' => MoodleController::getIdCoursegrades($lastInserted->platform_id, $lastInserted->context_id)
            ],
            MoodleController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        return $data;
    }

    // Devuelve la istáncia
    public static function getinstance($platform, $url_lms)
    {
        // dd($url_lms);
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' =>  $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        }
            // dd($course);
        ;
        return $dataInstance->id;
    }

    // devuelve un array de los mapas de un curso con sus versiones y bloques
    public static function getCourse($course_id, $platform, $url_lms)
    {
        // dd($url_lms);
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' =>  $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        }
            // dd($course);
        ;

        $dataCourse = Course::firstOrCreate(
            ['instance_id' =>  $dataInstance->id, 'course_id' => $course_id],
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id, 'timestamps' => now()]
        );
        while (is_null($dataCourse->id)) {
            sleep(1);
        }
            // dd($course);
        ;
        $dataMaps = Map::select('id', 'created_id', 'course_id', 'name', 'updated_at')
            ->where('course_id', $dataCourse->id)
            ->get();
        $maps = [];
        foreach ($dataMaps as $map) {
            $dataVersions = Version::select('id', 'map_id', 'name', 'blocks_data', 'updated_at', 'default')
                ->where('map_id', $map->id)
                ->get();
            $versions = [];
            foreach ($dataVersions as $version) {

                array_push($versions, [
                    'id' => $version->id,
                    'map_id' => $version->map_id,
                    'name' => $version->name,
                    'updated_at' => $version->updated_at,
                    'default' => $version->default,
                    'blocksData' => json_decode($version->blocks_data),
                ]);
            }
            array_push($maps, [
                'id' => $map->created_id,
                'course_id' => $map->course_id,
                'name' => $map->name,
                'versions' => $versions,
            ]);
        }
        $course = [
            'maps' => $maps,
        ];

        return $course;
    }

    // Función que devuelve TODAS las secciones de un curso
    public static function getSections($url_lms, $course_id)
    {

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course_id,
                'options' => [
                    [
                        'name' => 'excludecontents',
                        'value' => 'true'
                    ],
                    [
                        'name' => 'excludemodules',
                        'value' => 'true'
                    ],
                    [
                        'name' => 'includestealthmodules',
                        'value' => 'true'
                    ]
                ],
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        $sections = [];
        foreach ($data as $section) {
            // dd($section);
            array_push($sections, [
                'id' => $section->id,
                'name' => $section->name,
                'position' => $section->section,

            ]);
        }
        return $sections;
    }

    // Función que devuelve TODOS los modulos de un curso
    public static function getModules($url_lms, $course)
    {

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course,
                'options' => [
                    [
                        'name' => 'includestealthmodules',
                        'value' => true,
                    ]
                ],
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        $modules = [];
        $module_grades = MoodleController::getCoursegrades($course);

        foreach ($data as $indexS => $section) {

            foreach ($section->modules as $indexM => $module) {
                // dd();
                $has_grades = isset($module_grades[$module->name]);
                $module_data = [
                    'name' => e($module->name),
                    'modname' => e($module->modname),
                    'id' => e($module->id),
                    'has_califications' => $has_grades,
                    'order' => $indexM,
                    'section' => $indexS,
                    'indent' => $module->indent,
                    'visible' => ($module->visible >= 1) ? 'show_unconditionally' : 'hidden_until_access'
                ];
                if ($module->availability != null) {
                    $module_data['availability'] = json_decode($module->availability);
                }
                $modules[] = $module_data;
            }
        }
        return $modules;
    }

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request)
    {
        // dd($request->lms);
        $client = new Client([
            'base_uri' => $request->url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $request->course,
                'options' => [
                    [
                        'name' => 'modname',
                        'value' => $request->type,
                    ]
                ],
                'moodlewsrestformat' => 'json'
            ]
        ]);

        $content = $response->getBody()->getContents();
        // dd($content);
        $data = json_decode($content);
        // dd($data);
        $module_grades = MoodleController::getCoursegrades($request->course);
        $modules = [];
        foreach ($data as $indexM => $section) {
            // dd($section);
            foreach ($section->modules as $module) {
                $has_grades = isset($module_grades[$module->name]);
                array_push($modules, [
                    'id' => htmlspecialchars($module->id),
                    'name' => htmlspecialchars($module->name),
                    'section' => htmlspecialchars($indexM),
                    'has_grades' => $has_grades
                ]);
            }
        }
        // dd($modules);
        return $modules;
    }

    // Función que devuelve los grupos de un curso
    public static function getGroups($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_group_get_course_groups',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        $groups = array();
        foreach ($data as $group) {
            $groups[] = array(
                'id' => $group->id,
                'name' => $group->name
            );
        }
        return $groups;
    }

    // Función que devuelve las agrupaciones de grupos de un curso
    public static function getGrupings($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_group_get_course_groupings',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        $grupings = array();
        foreach ($data as $gruping) {
            $grupings[] = array(
                'id' => $gruping->id,
                'name' => $gruping->name
            );
        }
        return $grupings;
    }

    // Función que devuelve las medallas de un curso
    public static function getBadges($course_id)
    {
        $dataBadges = DB::connection('moodle')
            ->table('mdl_badge')
            ->select('id', 'name')
            ->where('courseid', $course_id)
            ->get();
        $badges = [];
        foreach ($dataBadges as $badge) {
            $dataConditions = DB::connection('moodle')
                ->table('mdl_badge_criteria')
                ->select('id', 'criteriatype', 'method', 'descriptionformat')
                ->where('badgeid', $badge->id)
                ->get();
            $conditions = [];
            foreach ($dataConditions as $criterial) {
                $dataParams = DB::connection('moodle')
                    ->table('mdl_badge_criteria_param')
                    ->select('id', 'name', 'value')
                    ->where('critid', $criterial->id)
                    ->get();
                $params = [];
                foreach ($dataParams as $param) {
                    array_push($params, [
                        'id' => $param->id,
                        'name' => $param->name,
                        'value' => $param->value,
                    ]);
                }
                array_push($conditions, [
                    'id' => $criterial->id,
                    'criteriatype' => $criterial->criteriatype,
                    'method' => $criterial->method,
                    'descriptionformat' => $criterial->descriptionformat,
                    'params' => $params,
                ]);
            }
            array_push($badges, [
                'id' => $badge->id,
                'name' => $badge->name,
                'conditions' => $conditions,
            ]);
        }
        // dd($badges);
        return $badges;
    }

    // Función que devuelve la url de la imagen del usuario
    public static function getImgUser($url_lms, $user_id)
    {
        // header('Access-Control-Allow-Origin: *');
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_user_get_users_by_field',
                'field' => 'id',
                'values[0]' => $user_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        return $data[0]->profileimageurl;
    }

    // Devuelve un array con los nombres de todos los módulos del curso que tienen calificaciones 
    public static function getCoursegrades($course_id)
    {
        $module_grades = DB::connection('moodle')
            ->table('mdl_grade_items')
            ->join('mdl_grade_grades', 'mdl_grade_grades.itemid', '=', 'mdl_grade_items.id')
            ->where('mdl_grade_items.courseid', $course_id)
            ->where('mdl_grade_items.itemtype', 'mod')
            ->whereNotNull('mdl_grade_grades.rawgrade')
            ->groupBy('mdl_grade_items.itemname')
            ->select('mdl_grade_items.itemname')
            ->get()
            ->pluck('itemname')
            ->flip()
            ->all();
        return $module_grades;
    }

    // Devuelve un array con los IDs de los módulos del curso que tienen calificaciones
    public static function getIdCoursegrades($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $datas = json_decode($content);
        // dd($data);
        $grades = MoodleController::getCoursegrades($course_id);

        $modulesCalificateds = [];
        // dd($grades);
        // error_log('Grades: ' . $grades);
        foreach ($datas as $section) {
            foreach ($section->modules as $module) {
                if (isset($grades[$module->name])) {
                    array_push($modulesCalificateds, $module->id);
                }
            }
        }
        return $modulesCalificateds;
    }

    public static function editModule($url_lms)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_edit_module',
                'action' => 'show',
                'id' => 57,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $response2 = $client->request('POST', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_edit_module',
                'action' => 'hide',
                'id' => 57,
                'moodlewsrestformat' => 'json'
            ]
        ]);
    }

    public static function exportVersion(Request $request)
    {
        $sections = MoodleController::getModulesListBySectionsCourse($request->course);
        error_log('Sectiones antes del cambio: '.json_encode($sections));
        $position = [];

        $nodes = $request->nodes;

        usort($nodes, function ($a, $b) {
            if ($a['section'] === $b['section']) {
                return $a['order'] - $b['order'];
            }
            return $a['section'] - $b['section'];
        });
        
        foreach ($nodes as $index => $data) {
            $node = json_encode($data);
            $decodedNode = json_decode($node);
            if($request->course == '8'){
                // error_log('NodeSection: '.$nodes[$index]['section']);
                switch ($data['section']) {
                    case 0:
                        $nodes[$index]['section'] = 25;
                        break;
                    case 1:
                        $nodes[$index]['section'] = 26;
                        break;
                    case 2:
                        $nodes[$index]['section'] = 27;
                        break;
                    case 3:
                        $nodes[$index]['section'] = 28;
                        break;
                    default:
                        break;
                }
            }
            foreach ($sections as $section) {
                $key = array_search($data['id'], $section->sequence);
                if($key !== false){
                    // error_log('BOCATA1: '.json_encode($section->sequence));
                    unset($section->sequence[$key]);
                    $section->sequence = array_values($section->sequence);
                    // error_log('BOCATA2: '.json_encode($section->sequence));
                }
            }
            if(isset($decodedNode->conditions)){
                // error_log('Condicion sin pasear: '.json_encode($nodes[$index]['conditions']));
                $nodes[$index]['conditions'] = MoodleController::recursiveConditionsChange($request->instance, $request->course, $decodedNode->conditions);
                // error_log('Condicion cambiada: '. json_encode($nodes[$index]['conditions']));
            }
        }
        foreach ($nodes as $data) {
            foreach ($sections as $index => $section) {
                if($section->id == $data['section']){
                    array_splice($sections[$index]->sequence, $data['order'], 0, $data['id']);
                }
               
            }
        }
        error_log('Secciones después del cambio: '.json_encode($sections));
        // $status = MoodleController::setModulesListBySections($sections, $nodes);
        

        // if($status){
        //     error_log('OK');
        // }else{
        //     error_log('FAILURE');
        // }

        MoodleController::editModule(MoodleController::getUrlLms($request->instance));
    } 

    public static function recursiveConditionsChange($instance_id, $course_id, $data, $json = [], $recActive = false){
        switch ($data) {
            case isset($data->conditions):
                $c = [];
                foreach ($data->conditions as $condition) {
                    array_push($c, MoodleController::recursiveConditionsChange($instance_id, $course_id, $condition, $json, true));
                }
                $json['op'] = $data->op;
                $json['c'] = $c;
                break;
            case isset($data->type):
                switch ($data->type) {
                    case 'completion':
                        $queryMap = [
                            'completed' => 1,
                            'notCompleted' => 0,
                            'completedApproved' => 2,
                            'completedFailed' => 3
                        ];
                        $e = $queryMap[$data->query] ?? '';
                        $dates = [
                            'type' => $data->type,
                            'cm' => (int)$data->op,
                            'e' => $e
                        ];
                        return $dates;
                        break;
                    case 'date':
                        $queryMap = [
                            'dateFrom' => '>=',
                            'dateTo' => '<'
                        ];
                        $query = $queryMap[$data->query] ?? '';
                        $dates = [
                            'type' => $data->type,
                            'd' => $query,
                            't' => strtotime($data->op)
                        ];
                        return $dates;
                        break;
                    case 'qualification':
                        $dates = [
                            'type' => 'grade',
                            'id' => MoodleController::getIdGrade($instance_id, (int)$data->op)
                        ];
                        
                        if (isset($data->objective)) {
                            $dates['min'] = (int)$data->objective;
                        }
                        if (isset($data->objective2)) {
                            $dates['max'] = (int)$data->objective2;
                        }
                        return $dates;
                        break;
                    default:
                        break;
                }
                break;
            default:
                break;
        }
        if($recActive){
            return $json;
        }else{
            $queryMap = [
                '&' => 'showc',
                '|' => 'show'
            ];
            $show = '';
            if($queryMap[$data->op] == 'showc'){
                $show = [];
                for ($i=0; $i < count($data->conditions); $i++) { 
                    array_push($show,true);
                }
            }
            elseif ($queryMap[$data->op] == 'show'){
                $show = true;
            }
            $json[$queryMap[$data->op]] = $show;
            return $json;
        }
    }

    public static function getUrlLms($instance){
        $url_lms = DB::table('instances')
        ->where('id',$instance)
        ->select('url_lms')
        ->first();
        return $url_lms->url_lms;
    }

    public static function getModuleById($instance, $item_id){

        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_course_module',
                'cmid' => $item_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
     
    public static function getIdGrade($instance, $item_id){

        $module = MoodleController::getModuleById($instance, $item_id);

        $grade_id = DB::connection('moodle')
            ->table('mdl_grade_items')
            ->where('mdl_grade_items.courseid', $module->cm->course)
            ->where('mdl_grade_items.itemname', $module->cm->name)
            ->where('mdl_grade_items.itemmodule', $module->cm->modname)
            ->where('mdl_grade_items.iteminstance', $module->cm->instance)
            ->where('mdl_grade_items.itemtype', 'mod')
            ->select('mdl_grade_items.id')
            ->first();
        return $grade_id->id;
    }

    public static function getModulesListBySectionsCourse($course_id){
        $sections = DB::connection('moodle')
        ->table('mdl_course_sections')
        ->where('course', $course_id)
        ->select('id','sequence')
        ->get();
        error_log('ARRAYSECTION: '.$sections[0]->sequence);
        foreach ($sections as $section) {
            $array = explode(",", $section->sequence);
            $section->sequence = array_map('intval', $array);
        }
        return $sections;
    }

    public static function setModulesListBySections($sections, $modules){
        try {
            DB::connection('moodle')->transaction(function () use ($sections, $modules) {
                foreach ($sections as $section) {
                    DB::connection('moodle')
                    ->table('mdl_course_sections')
                    ->where('id', $section->id)
                    ->update(['sequence' => implode(',', $section->sequence)]);
                }
                foreach ($modules as $module) {
                    $conditions = '';
                    if(isset($module['conditions'])){
                        $conditions = $module['conditions'];
                    } 
                    DB::connection('moodle')
                    ->table('mdl_course_modules')
                    ->where('id',$module['id'])
                    ->update([
                        'section' => $module['section'],
                        'indent' => $module['identation'],
                        'availability' => $conditions,
                        'visible' => 1
                    ]);
                }
            });
            return true;
        } catch (\Exception $e) {
            // Ocurrió un error, los cambios serán revertidos automáticamente
            error_log($e);
            return false;
        }
    }
    
    
}
