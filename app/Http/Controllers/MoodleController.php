<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MoodleController extends Controller
{
    // guarda la sesión del usuario en la base de datos y redirecciona al front
    public static function storeVersion(Request $request)
    {
        // error_log($request);
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
            Version::updateOrCreate(
                ['map_id' => $map->id, 'name' => $versionData['name']],
                ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocksData'])]
            );
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            error_log($e);
            abort(500, $e->getMessage());
            return response()->json(['ok' => false]);
        }
    }

    // devuelve la sesión almacenada en la base de datos de un usuario que se ha conectado a la lti
    public static function getSession(Object $lastInserted)
    {
        // MoodleController::getModulesNotSupported(MoodleController::getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id), $lastInserted->context_id);
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
                'badges' => MoodleController::getBadges($lastInserted->platform_id, $lastInserted->context_id),
                'grades' => MoodleController::getIdCoursegrades($lastInserted->platform_id, $lastInserted->context_id)
            ],
            MoodleController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id,
                $lastInserted->user_id
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
    public static function getCourse($course_id, $platform, $url_lms, $user_id)
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
        $dataMaps = Map::select('id', 'created_id','user_id' , 'course_id', 'name', 'updated_at')
            ->where('course_id', $dataCourse->id)
            ->where('user_id', $user_id)
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

    public static function getVersion($version_id)
    {
        $dataVersion = Version::select('id', 'map_id', 'name', 'blocks_data', 'updated_at', 'default')
                ->where('id', $version_id)
                ->first();
        if($dataVersion == null)
        return ['invalid' => true];
        
        $dataVersion->blocks_data = json_decode($dataVersion->blocks_data);
        
        return $dataVersion;
    }

    // Función que devuelve TODAS las secciones de un curso
    public static function getSections($url_lms, $course_id)
    {

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
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
            'timeout' => 20.0,
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
        $module_grades = MoodleController::getCoursegrades($url_lms,$course);

        foreach ($data as $indexS => $section) {

            foreach ($section->modules as $indexM => $module) {
                // dd($module_grades->module_grades);
                $has_grades = in_array($module->name, $module_grades->module_grades);
                // dd($has_grades);
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
            'timeout' => 20.0,
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
        $module_grades = MoodleController::getCoursegrades($request->url_lms, $request->course);
        $modules = [];
        foreach ($data as $indexM => $section) {
            // dd($section);
            foreach ($section->modules as $module) {
                $has_grades = in_array($module->name, $module_grades->module_grades);
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
            'timeout' => 20.0,
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
            'timeout' => 20.0,
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
    public static function getBadges($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_course_badges',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($badges);
        return $data;
    }

    // Función que devuelve la url de la imagen del usuario
    public static function getImgUser($url_lms, $user_id)
    {
        // header('Access-Control-Allow-Origin: *');
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
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
    public static function getCoursegrades($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_coursegrades',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        return $data;
    }

    // Devuelve un array con los IDs de los módulos del curso que tienen calificaciones
    public static function getIdCoursegrades($url_lms, $course_id)
    {
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
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
        $grades = MoodleController::getCoursegrades($url_lms,$course_id);
        // dd($grades);
        $modulesCalificateds = [];
        // dd($grades);
        // error_log('Grades: ' . $grades);
        foreach ($datas as $section) {
            foreach ($section->modules as $module) {
                if (in_array($module->name, $grades->module_grades)) {
                    array_push($modulesCalificateds, $module->id);
                }
            }
        }
        return $modulesCalificateds;
    }

    // public static function editModule($url_lms, $module_id)
    // {
    //     $client = new Client([
    //         'base_uri' => $url_lms . '/webservice/rest/server.php',
    //         'timeout' => 20.0,
    //     ]);
    //     $response1 = $client->request('POST', '', [
    //         'query' => [
    //             'wstoken' => env('WSTOKEN'),
    //             'wsfunction' => 'core_course_edit_module',
    //             'action' => 'hide',
    //             'id' => $module_id,
    //             'moodlewsrestformat' => 'json'
    //         ]
    //     ]);
    //     $response2 = $client->request('POST', '', [
    //         'query' => [
    //             'wstoken' => env('WSTOKEN'),
    //             'wsfunction' => 'core_course_edit_module',
    //             'action' => 'show',
    //             'id' => $module_id,
    //             'moodlewsrestformat' => 'json'
    //         ]
    //     ]);
        
    // }

    public static function exportVersion(Request $request)
    {
        $sections = MoodleController::getModulesListBySectionsCourse($request->instance, $request->course);
        // error_log('Sectiones antes del cambio: '.$sections[0]['id']);

        $nodes = $request->nodes;


        usort($nodes, function ($a, $b) {
            if ($a['section'] === $b['section']) {
                return $a['order'] - $b['order'];
            }
            return $a['section'] - $b['section'];
        });
        
        foreach ($nodes as $index => $data) {
            // error_log('NodeVisibility: '.$data['lmsVisibility']);
            
            $conditionVisibility = '';
            switch ($data['lmsVisibility']) {
                case 'show_unconditionally':
                    $nodes[$index]['lmsVisibility'] = 1;
                    $conditionVisibility = true;
                    break;
                case 'hidden_until_access':
                    $nodes[$index]['lmsVisibility'] = 1;
                    $conditionVisibility = false;
                    break;
                // case 'hidden_to_readers':
                //     $nodes[$index]['lmsVisibility'] = 0;
                //     $conditionVisibility = false;
                //     break;
                    
                default:
                    # code...
                    break;
            }

            // error_log(isset($nodes[$index]['c']));
            if(isset($nodes[$index]['c'])){
                // error_log('Condicion sin cambiar: '.json_encode($nodes[$index]['c']));
                $nodes[$index]['c'] = MoodleController::exportRecursiveConditionsChange($request->instance, $request->course, $nodes[$index]['c'], $conditionVisibility);
                // error_log('Condicion cambiada: '. json_encode($nodes[$index]['c']));
            }
            
            foreach ($sections->sections as $index => $section) {
                // error_log('SECCION: '.json_encode($sections->sections[$index]->sequence));
                $key = array_search($data['id'], $section->sequence);
                if($key !== false){
                    // error_log('BOCATA1: '.json_encode($section->sequence));
                    unset($section->sequence[$key]);
                    $sections->sections[$index]->sequence = array_values($section->sequence);
                    // error_log('BOCATA2: '.json_encode($sections[$index]->sequence));
                }
                
            }
            
            foreach ($sections->sections as $index => $section) {
                if($section->id == $data['section']){
                    array_splice($sections->sections[$index]->sequence, $data['order'], 0, $data['id']);
                }
            } 
        }
        // error_log('Secciones después del cambio: '.json_encode($sections));
        // error_log('Nodos: '.json_encode($nodes[3]));

        $status = MoodleController::setModulesListBySections($request->instance,$sections->sections, $nodes);
        

        if($status){
            // error_log('OK');s
            // $url_lms = MoodleController::getUrlLms($request->instance);
            // MoodleController::editModule($url_lms, MoodleController::getModules($url_lms, $request->course)[0]['id']);
            return response()->json(['ok' => true]);
        }else{
            // error_log('FAILURE');
            return response()->json(['ok' => false]);
        }
    } 

    public static function exportRecursiveConditionsChange($instance_id, $course_id, $data, $visibility, $json = [], $recActive = false)
    {
        
        switch ($data) {
            case isset($data['c']):
                $c = [];
                foreach ($data['c'] as $index => $condition) {
                    // error_log('INDEX: '.$index);
                    array_push($c, MoodleController::exportRecursiveConditionsChange($instance_id, $course_id, $data['c'][$index], $visibility, $json, true));
                    //  MoodleController::recursiveConditionsChange($instance_id, $course_id, $data['c'][$index], $visibility, $json, true);
                }
                // $json['op'] = $data->op;
                $data['c'] = $c;
                break;
            case isset($data['type']):
                // error_log('hola?: ');
                switch ($data['type']) {
                    
                    case 'completion':
                        // $queryMap = [
                        //     'completed' => 1,
                        //     'notCompleted' => 0,
                        //     'completedApproved' => 2,
                        //     'completedFailed' => 3
                        // ];
                        // $e = $queryMap[$data->query] ?? '';
                        // $dates = [
                        //     'type' => $data->type,
                        //     'cm' => (int)$data->op,
                        //     'e' => $e
                        // ];
                        // return $dates;
                        // $data['cm'] = (int)$data['cm'];
                        // $data['e'] = (int)$data['e'];
                        // return $data;
                        break;
                    case 'date':
                        // $queryMap = [
                        //     'dateFrom' => '>=',
                        //     'dateTo' => '<'
                        // ];
                        // $query = $queryMap[$data->query] ?? '';
                        // $dates = [
                        //     'type' => $data->type,
                        //     'd' => $query,
                        //     't' => strtotime($data->op)
                        // ];
                        // return $dates;
                        $data['t'] = strtotime($data['t']);
                        return $data;
                        break;
                    case 'grade':
                        // error_log('CONDITION: '.json_encode($data));
                        // error_log('CM: '.$data['cm']);
                        // $data['cm'] = MoodleController::getIdGrade($instance_id, MoodleController::getModuleById($instance_id, $data['cm']));
                        // error_log('hola?: '.json_encode($data));

                        // $dates = [
                        //     'type' => 'grade',
                        //     'id' => MoodleController::getIdGrade($instance_id, MoodleController::getModuleById($instance_id, (int)$data->op))
                        // ];
                        $data['id'] = MoodleController::getIdGrade($instance_id, MoodleController::getModuleById($instance_id, $data['id']));
                        // if (isset($data->objective)) {
                        //     $dates['min'] = (int)$data->objective;
                        // }
                        // if (isset($data->objective2)) {
                        //     $dates['max'] = (int)$data->objective2;
                        // }
                        return $data;
                        break;
                    case 'courseGrade':
                        // error_log('CONDITION: '.json_encode($data));
                        // error_log('CM: '.$data['cm']);
                        // $data['cm'] = MoodleController::getIdGrade($instance_id, MoodleController::getModuleById($instance_id, $data['cm']));
                        //error_log('Los datos: '.json_encode($data));
                        // error_log('Module: '.json_encode(MoodleController::getModuleById($instance_id, $data['courseId'])));
                        // dd(MoodleController::getModuleById($instance_id, $data['idcourseId']));
                        $dates = [
                            'type' => 'grade',
                            'id' => MoodleController::getIdCourseGrade($instance_id, $data['id'])
                        ];
                        // error_log('ID: '.$dates['id']);
                        if (isset($data['min'])) {
                            $dates['min'] = $data['min'];
                        }
                        if (isset($data['max'])) {
                            $dates['max'] = $data['max'];
                        }
                        // error_log('hola??'.json_encode($dates));
                        return $dates;
                        break;
                    default:
                        break;
                }
                break;
            default:
                break;
        }
        // if($recActive){
        //     return $json;
        // }else{
        //     $queryMap = [
        //         '&' => 'showc',
        //         '|' => 'show'
        //     ];
        //     $show = '';
        //     if($queryMap[$data->op] == 'showc'){
        //         $show = [];
        //         for ($i=0; $i < count($data->conditions); $i++) { 
        //             array_push($show, $visibility);
        //         }
        //     }
        //     elseif ($queryMap[$data->op] == 'show'){
        //         $show =  $visibility;
        //     }
        //     $json[$queryMap[$data->op]] = $show;
        //     return $json;
        // }
        return $data;
    }

    public static function getUrlLms($instance)
    {
        $url_lms = DB::table('instances')
        ->where('id',$instance)
        ->select('url_lms')
        ->first();
        return $url_lms->url_lms;
    }

    public static function getModuleById($instance, $item_id)
    {
        // error_log('INSTANCIA: '.$instance);
        // error_log('ID: '.$item_id);
        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 20.0,
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
        // error_log('MODULE: '.json_encode($data->cm));
        return $data;
    }

    public static function getIdGrade($instance, $module)
    {
        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        // error_log('MODULE ID: '.json_encode($module->cm));
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_id_grade',
                'courseid' => $module->cm->course,
                'cmname' => $module->cm->name,
                'cmmodname' => $module->cm->modname,
                'cminstance' => $module->cm->instance,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // error_log('GRADE: '.json_encode($data));
        return $data->grade_id;
    }

    public static function getIdCourseGrade($instance, $course_id)
    {
        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        // error_log('MODULE ID: '.json_encode($module->cm));
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_course_grade',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // error_log('GRADE: '.json_encode($data));
        return $data->grade_id;
    }

    public static function getModulesListBySectionsCourse($instance, $course_id)
    {
        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_modules_list_by_sections_course',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
    
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        //error_log('Sequence: '.json_encode($data));
        return $data;
    }

    public static function setModulesListBySections($instance, $sections, $modules) {
        //error_log("MODULES:" . json_encode($modules));
        foreach ($modules as &$module) {
            if (isset($module['c'])) {
                $module['c'] = json_encode($module['c']);
                // error_log('Condiciones: '.$module['c']);
            }
        }
        //error_log(json_encode($modules));
        unset($module);
        $client = new Client([
            'base_uri' => MoodleController::getURLLMS($instance) . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_set_modules_list_by_sections',
                'moodlewsrestformat' => 'json'
            ],
            'form_params' => [
                'sections' => json_decode(json_encode($sections)),
                'modules' => $modules
            ]
    
        ]);
    
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        //error_log('ERROR:'.json_encode($data));
        return $data->result;
    }
    
    public static function getModulesNotSupported($request){
        $sections = json_decode($request->sections);
        $client = new Client([
            'base_uri' => $request->url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'local_uniadaptive_get_course_modules',
                'courseid' => $request->course,
                'exclude' => explode(',',$request->supportedTypes),
                'invert' => false,
                'moodlewsrestformat' => 'json'
            ]
    
        ]);
    
        $content = $response->getBody()->getContents();
        // dd($content);
        $data = json_decode($content);
        // dd($data->modules[0]);
        $modules = [];
        foreach ($data->modules as $module) {
            // dd($module);
            $section_position = 0;
            foreach ($sections as $section) {
                if ($section->id == $module->section) {
                    $section_position = $section->position;
                    break;
                }
            }
            array_push($modules, [
                'id' => htmlspecialchars($module->id),
                'name' => htmlspecialchars($module->name),
                'section' => htmlspecialchars($section_position),
                'has_grades' => false
            ]);
        }
        // dd($modules);
        return $modules;
        
    }
    
    
    
}
