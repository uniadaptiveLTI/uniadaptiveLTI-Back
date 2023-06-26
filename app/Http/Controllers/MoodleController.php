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
    public static function storeVersion(Request $request)
    {
        try {
            $course = Course::where('instance_id', $request->saveData['instance_id'])
                ->where('course_id', $request->saveData['course_id'])
                ->first();
            $mapData = $request->saveData['map'];
            $map = Map::updateOrCreate(
                ['created_id' => $mapData['id'],  'course_id' =>  $course->id],
                ['name' => $mapData['name'], 'lesson_id' => $request->saveData['instance_id']]
            );
            $versionData = $mapData['versions'];
            $version = Version::updateOrCreate(
                ['map_id' => $map->id, 'name' => $versionData['name']],
                ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocksData'])]
            );
            return 0;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
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
            ],
            MoodleController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        return $data;
    }

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
    public static function getSections($lms, $id)
    {
        header('Access-Control-Allow-Origin:' . env('FRONT_URL'));
        $client = new Client([
            'base_uri' => $lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $id,
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
    public static function getModules(Request $request, Object $instance)
    {
        // dd($request);
        $client = new Client([
            'base_uri' => $instance->url_lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $request->course,
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
        // dd($data);
        // $this->getGradereport($instance->url_lms, $request->course);
        $modules = [];

        // Obtener información sobre las calificaciones de los módulos del curso
        $module_grades = DB::connection('moodle')
            ->table('mdl_grade_grades')
            ->join('mdl_grade_items', 'mdl_grade_items.id', '=', 'mdl_grade_grades.itemid')
            ->join('mdl_course_modules', function ($join) {
                $join->on('mdl_course_modules.instance', '=', 'mdl_grade_items.iteminstance')
                    ->on('mdl_course_modules.course', '=', 'mdl_grade_items.courseid');
            })
            ->where('mdl_grade_items.courseid', $request->course)
            ->whereNotNull('mdl_grade_grades.rawgrade')
            ->get();


        // dd($module_grades);

        // Crear un array para almacenar información sobre qué módulos tienen calificaciones
        $graded_modules = [];

        // Procesar las calificaciones de los módulos
        foreach ($module_grades as $grade) {
            // Verificar si el elemento de calificación corresponde a un módulo
            if ($grade->itemtype === 'mod') {
                // Agregar el ID del módulo al array
                $graded_modules[$grade->iteminstance] = true;
            }
        }


        // Obtener información sobre las restricciones de acceso para los módulos del curso
        $module_availability = DB::connection('moodle')
            ->table('mdl_course_modules')
            ->where('course', $request->course)
            ->get();

        // Crear un array para almacenar información sobre las restricciones de acceso para los módulos
        $module_restrictions = [];

        // Procesar las restricciones de acceso
        foreach ($module_availability as $availability) {
            // Agregar información sobre las restricciones de acceso al array
            $module_restrictions[$availability->id] = json_decode($availability->availability);
        }

        foreach ($data as $indexS => $section) {
            // dd($section);
            foreach ($section->modules as $indexM => $module) {
                // Verificar si el módulo tiene calificaciones
                $has_grades = isset($graded_modules[$module->id]);

                // Verificar si el módulo tiene restricciones de acceso
                $availability = isset($module_restrictions[$module->id]) ? $module_restrictions[$module->id] : null;

                array_push($modules, [
                    'name' => htmlspecialchars($module->name),
                    'modname' => htmlspecialchars($module->modname),
                    'id' => htmlspecialchars($module->id),
                    'has_califications' => $has_grades,
                    'availability' => $availability,
                    'order' => $indexM,
                    'section' => $indexS,
                    'indent' => $module->indent,
                    'visible' => ($module->visible >= 1) ? 'show_unconditionally' : 'hidden_until_access'
                    // 'section' =>
                ]);
            }
        }
        // dd($modules);
        return $modules;
    }



    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request)
    {
        // dd($request->lms);
        $client = new Client([
            'base_uri' => $request->lms . '/webservice/rest/server.php',
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
        $modules = [];
        foreach ($data as $indexM => $section) {
            // dd($section);
            foreach ($section->modules as $module) {
                array_push($modules, [
                    'id' => htmlspecialchars($module->id),
                    'name' => htmlspecialchars($module->name),
                    'section' => htmlspecialchars($indexM)
                ]);
            }
        }
        // dd($modules);
        return $modules;
    }

    // Función que devuelve los grupos de un curso
    public static function getGroups($lms, $id)
    {
        header('Access-Control-Allow-Origin:' . env('FRONT_URL'));
        $client = new Client([
            'base_uri' => $lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_group_get_course_groups',
                'courseid' => $id,
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
    public static function getGrupings($lms, $id)
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        $client = new Client([
            'base_uri' => $lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_group_get_course_groupings',
                'courseid' => $id,
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
    public static function getBadges($id)
    {
        $dataBadges = DB::connection('moodle')
            ->table('mdl_badge')
            ->select('id', 'name')
            ->where('courseid', $id)
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
        return $badges;
    }

    // Función que devuelve la url de la imagen del usuario
    public static function getImgUser($lms, $id)
    {
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        $client = new Client([
            'base_uri' => $lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'core_user_get_users_by_field',
                'field' => 'id',
                'values[0]' => $id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        return $data[0]->profileimageurl;
    }

    public static function getGradereport($id)
    {
        $dataGrade = DB::connection('moodle')
            ->table('mdl_grade_grades')
            ->select('id')
            ->where('itemid', $id)
            ->whereNotNull('rawgrade')
            ->first();

        return !is_null($dataGrade);
    }
}
