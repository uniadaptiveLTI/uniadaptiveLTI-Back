<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
define('MOODLE_PLATFORM', 'moodle');

class MoodleController extends Controller
{
    // Saves the user's session in the database and redirects to the front end.
    public static function storeVersion($saveData, $token)
    {
        try {
            $course = Course::where('instance_id', $saveData['instance_id'])
                ->where('course_id', $saveData['course_id'])
                ->select('id')
                ->first();
            $mapData = $saveData['map'];

            $map = Map::updateOrCreate(
                ['created_id' => $mapData['id'], 'course_id' => $course->id, 'user_id' => intval($saveData['user_id'])],
                ['name' => $mapData['name'], 'lesson_id' => $saveData['instance_id']]
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
            return response()->json(['ok' => false, 'errorType' => 'ERROR_SAVING_VERSION']);
        }
    }
    // Returns the session stored in the database of a user who has logged on to the lti.
    public static function getSession(object $lastInserted)
    {
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
                'groupings' => MoodleController::getGrupings($lastInserted->platform_id, $lastInserted->context_id),
                'badges' => MoodleController::getBadges($lastInserted->platform_id, $lastInserted->context_id),
                'grades' => MoodleController::getIdCoursegrades($lastInserted->platform_id, $lastInserted->context_id),
                'role_list' => MoodleController::getRoles($lastInserted->platform_id, $lastInserted->context_id),
                'skills' => MoodleController::getCompetencies($lastInserted->platform_id, $lastInserted->context_id)
            ],
            MoodleController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id,
                $lastInserted->user_id
            )
        ];
        return response()->json(['ok' => true, 'data' => $data]);
    }
    // Returns the instance.
    public static function getinstance($platform, $url_lms)
    {
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' => $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        }
        ;
        return $dataInstance->id;
    }
    // Returns an array of the maps of a course with their versions and blocks.
    public static function getCourse($course_id, $platform, $url_lms, $user_id)
    {
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' => $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        }
        ;
        $dataCourse = Course::firstOrCreate(
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id],
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id, 'timestamps' => now()]
        );
        while (is_null($dataCourse->id)) {
            sleep(1);
        }
        ;
        $dataMaps = Map::select('id', 'created_id', 'user_id', 'course_id', 'name', 'updated_at')
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
    // This function obtains the data of a version of a map, with the version id as parameter.
    public static function getVersion($version_id)
    {
        $dataVersion = Version::select('id', 'map_id', 'name', 'blocks_data', 'updated_at', 'default')
            ->where('id', $version_id)
            ->first();
        if ($dataVersion == null)
            return response()->json(['ok' => false, 'errorType' => 'Invalid_version', 'data' => ['invalid' => true]]);
        $dataVersion->blocks_data = json_decode($dataVersion->blocks_data);
        return response()->json(['ok' => true, 'data' => $dataVersion]);
    }
    // Function returning ALL sections of a course.
    public static function getSections($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
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
        $sections = [];
        foreach ($data as $section) {
            array_push($sections, [
                'id' => $section->id,
                'name' => $section->name,
                'position' => $section->section,

            ]);
        }
        return $sections;
    }
    // Function that returns ALL modules of a course.
    public static function getModules($url_lms, $course)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
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
        $module_grades = MoodleController::getCoursegrades($url_lms, $course);
        foreach ($data as $indexS => $section) {
            foreach ($section->modules as $indexM => $module) {
                $has_grades = in_array($module->name, $module_grades->module_grades);
                $module_data = [
                    'name' => e($module->name),
                    'modname' => e($module->modname),
                    'id' => e($module->id),
                    'has_califications' => $has_grades,
                    'g' => MoodleController::getCalifications($url_lms, $module->id, $module->modname),
                    'order' => $indexM,
                    'section' => $indexS,
                    'indent' => $module->indent,
                    'visible' => ($module->visible >= 1) ? 'show_unconditionally' : 'hidden'
                ];
                // switch ($module->modname) {
                //     case 'quiz':
                //     case 'assign':
                //     case 'forum':
                //     case 'workshop':
                //         $module_data['g'] = MoodleController::getCalifications($url_lms, $module->id, $module->modname);
                //         break;

                //     default:
                //         break;
                // }
                if ($module->availability != null) {
                    $module_data['availability'] = MoodleController::importRecursiveConditionsChange($url_lms, json_decode($module->availability));
                }
                $modules[] = $module_data;
            }
        }
        return response()->json(['ok' => true, 'data' => $modules]);

    }
    // Function that returns the modules of a specific type of a course.
    public static function getModulesByType(Request $request, $sessionData)
    {
        $url_lms = $sessionData->platform_id;
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $milliseconds = round(microtime(true) * 1000);
        error_log('Comienzo de petición: ' . date('Y-m-d H:i:s', $milliseconds / 1000) . substr((string) $milliseconds, -3));
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);

        if ($request->type === "badge") {
            $badges = MoodleController::getBadges($sessionData->platform_id, $sessionData->context_id);
            if ($badges != null && count($badges) >= 1) {
                foreach ($badges as $badge) {
                    if (property_exists($badge, 'params')) {
                        unset($badge->params);
                        $badge->section = -1;
                        $badge->has_grades = false;
                    }
                }
            }

            $milliseconds = round(microtime(true) * 1000);
            error_log('Finalización de petición: ' . date('Y-m-d H:i:s', $milliseconds / 1000) . substr((string) $milliseconds, -3));
            return response()->json(['ok' => true, 'data' => $badges]);
        } else {
            $response = $client->request('GET', '', [
                'query' => [
                    'wstoken' => $token_request['data'],
                    'wsfunction' => 'core_course_get_contents',
                    'courseid' => $sessionData->context_id,
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
            $data = json_decode($content);
            $module_grades = MoodleController::getCoursegrades($sessionData->platform_id, $sessionData->context_id);
            $modules = [];
            foreach ($data as $indexM => $section) {
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
            $modules;
            $milliseconds = round(microtime(true) * 1000);
            error_log('Finalización de petición: ' . date('Y-m-d H:i:s', $milliseconds / 1000) . substr((string) $milliseconds, -3));
            return response()->json(['ok' => true, 'data' => $modules]);
        }
    }
    // Function that returns the groups of a course.
    public static function getGroups($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
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
    // Function that returns the groupings of groups in a course.
    public static function getGrupings($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
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
    // Function that returns the medals of a course.
    public static function getBadges($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_course_badges',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // Function that returns the url of the user's image.
    public static function getImgUser($url_lms, $user_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'core_user_get_users_by_field',
                'field' => 'id',
                'values[0]' => $user_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data[0]->profileimageurl;
    }
    // Returns an array with the names of all modules in the course that have grades.
    public static function getCoursegrades($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_coursegrades',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // Returns an array with the IDs of the course modules that have grades.
    public static function getIdCoursegrades($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $datas = json_decode($content);
        $grades = MoodleController::getCoursegrades($url_lms, $course_id);
        $modulesCalificateds = [];
        foreach ($datas as $section) {
            foreach ($section->modules as $module) {
                if (in_array($module->name, $grades->module_grades)) {
                    array_push($modulesCalificateds, $module->id);
                }
            }
        }
        return $modulesCalificateds;
    }
    // This function creates a Moodle version of the course with the request data.
    public static function exportVersion(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');

        $sections = MoodleController::getModulesListBySectionsCourse($request->instance, $request->course);
        // dd($sections);
        $nodes = $request->nodes;

        $badges = [];
        usort($nodes, function ($a, $b) {
            if (isset($a['section']) && isset($b['section'])) {
                if ($a['section'] === $b['section']) {
                    return $a['order'] - $b['order'];
                }
                return $a['section'] - $b['section'];
            } else if (isset($a['section'])) {
                return -1; // or other negative value
            } else if (isset($b['section'])) {
                return 1; // or other positive value
            }
            return 0;
        });

        foreach ($nodes as $index => $data) {
            if (isset($nodes[$index]['actionType'])) {
                unset($nodes[$index]['actionType']);
                if (isset($nodes[$index]['conditions']) && is_array($nodes[$index]['conditions']) && count($nodes[$index]['conditions']) >= 1) {
                    foreach ($nodes[$index]['conditions'] as $key => $condition) {
                        if ($condition['description'] === null) {
                            $nodes[$index]['conditions'][$key]['description'] = "";
                        }
                    }
                }
                array_push($badges, $nodes[$index]);
                unset($nodes[$index]);
            } else {
                switch ($data['lmsVisibility']) {
                    case 'show_unconditionally':
                        $nodes[$index]['lmsVisibility'] = 1;
                        break;
                    case 'hidden':
                        $nodes[$index]['lmsVisibility'] = 0;
                        break;
                    default:
                        break;
                }
                if (isset($nodes[$index]['c']['type'])) {
                    unset($nodes[$index]['c']['type']);
                }
                // if (isset($nodes[$index]['g'])) {
                //     // dd($nodes[$index]);
                //     unset($nodes[$index]['g']);
                // }
                if (isset($nodes[$index]['children'])) {
                    unset($nodes[$index]['children']);
                }
                if (isset($nodes[$index]['c']['c'])) {
                    $nodes[$index]['c'] = MoodleController::exportRecursiveConditionsChange($request->instance, $request->course, $nodes[$index]['c']);
                } else {
                    $nodes[$index]['c'] = null;
                }
                foreach ($sections->sections as $index => $section) {

                    if (!empty($section->sequence)) {
                        $key = array_search($data['id'], $section->sequence);
                        if ($key !== false) {
                            unset($section->sequence[$key]);
                            $sections->sections[$index]->sequence = array_values($section->sequence);
                        }
                    }
                }
                foreach ($sections->sections as $index => $section) {
                    if ($section->id == $data['section']) {
                        array_splice($sections->sections[$index]->sequence, $data['order'], 0, $data['id']);
                    }
                }
            }

        }
        foreach ($sections->sections as $index => $section) {
            if (count($section->sequence) == 0) {
                unset($section->sequence);
            }
            
        }
        foreach ($sections->sections as $index => $section) {
           if(count($section->sequence) == 0){
            unset($section->sequence);
           }
        }
        // dd($sections);
        $statusUpdate = MoodleController::updateCourse($request->instance, $sections->sections, $nodes, $badges);
        return response()->json(['ok' => $statusUpdate->status, 'errorType' => $statusUpdate->error]);
    }
    // This function changes the conditions of the nodes according to the URL and type.
    public static function importRecursiveConditionsChange($url_lms, $data)
    {
        switch ($data) {
            case isset($data->c):
                $c = [];
                foreach ($data->c as $index => $condition) {
                    array_push($c, MoodleController::importRecursiveConditionsChange($url_lms, $condition));
                }
                $data->c = $c;
                break;
            case isset($data->type):
                switch ($data->type) {
                    case 'grade':
                        $grade_module = MoodleController::getGradeModule($url_lms, $data->id);
                        if (isset($grade_module->itemtype) && $grade_module->itemtype === "course") {
                            $data->courseId = "$grade_module->itemid";
                            $data->type = "courseGrade";
                        }

                        $data->id = $grade_module->itemid;
                        return $data;
                        break;
                    default:
                        break;
                }
                break;
            default:
                break;
        }
        return $data;
    }
    // This function changes the conditions of the nodes according to the type and date.
    public static function exportRecursiveConditionsChange($instance_id, $course_id, $data, $json = [])
    {
        switch ($data) {
            case isset($data['c']):
                // Check if the conditions array is empty
                if (empty($data['c'])) {
                    // If it's empty, remove the entire conditions object
                    unset($data);
                } else {
                    $c = [];
                    $showc = [];
                    foreach ($data['c'] as $index => $condition) {
                        $result = MoodleController::exportRecursiveConditionsChange($instance_id, $course_id, $data['c'][$index], $json);
                        // Only add the result to the array if it's not null
                        if ($result !== null) {
                            array_push($c, $result);
                            // If the operator is & or !|, update the content of showc
                            if (isset($data['op']) && ($data['op'] == '&' || $data['op'] == '!|')) {
                                array_push($showc, true); // Add a boolean for each first-level condition
                            }
                        }
                    }
                    // If after deletion, the 'c' array is empty, remove the entire conditions object
                    if (empty($c)) {
                        unset($data);
                    } else {
                        $data['c'] = $c;
                        // Update the content of showc
                        if (!empty($showc)) {
                            $data['showc'] = $showc;
                        }
                    }
                }
                break;
            case isset($data['type']):
                switch ($data['type']) {
                    case 'date':
                        $data['t'] = strtotime($data['t']);
                        return $data;
                        break;
                    case 'grade':
                        $data['id'] = MoodleController::getIdGrade($instance_id, MoodleController::getModuleById($instance_id, $data['id']));
                        return $data;
                        break;
                    case 'courseGrade':
                        $dates = [
                            'type' => 'grade',
                            'id' => MoodleController::getIdCourseGrade($instance_id, $data['id'])
                        ];
                        if (isset($data['min'])) {
                            $dates['min'] = $data['min'];
                        }
                        if (isset($data['max'])) {
                            $dates['max'] = $data['max'];
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
        // Check if the conditions object has become empty after deletion
        if (isset($data) && isset($data['type']) && $data['type'] == 'conditionsGroup' && empty($data['c'])) {
            unset($data);
        }
        return isset($data) ? $data : null; // Return null if the entire conditions object was removed
    }
    // This function gets the URL of the LMS of an instance, with the instance id as parameter.
    public static function getUrlLms($instance)
    {
        $url_lms = DB::table('instances')
            ->where('id', $instance)
            ->select('url_lms')
            ->first();
        return $url_lms->url_lms;
    }
    // This function gets the data of a Moodle course module.
    public static function getModuleById($instance, $item_id)
    {
        $url_lms = MoodleController::getURLLMS($instance);
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'core_course_get_course_module',
                'cmid' => $item_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // This function gets the id of the grade of a module of a Moodle course.
    public static function getIdGrade($instance, $module)
    {
        $url_lms = MoodleController::getURLLMS($instance);
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
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
        return $data->grade_id;
    }
    // This function gets the id of the Moodle course grade.
    public static function getIdCourseGrade($instance, $course_id)
    {
        error_log(print_r(gettype(intval($course_id)), true));
        error_log((MoodleController::getURLLMS($instance) . '/webservice/rest/server.php'));

        $url_lms = MoodleController::getURLLMS($instance);
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_course_grade_id',
                'courseid' => intval($course_id),
                'moodlewsrestformat' => 'json'
            ]
        ]);

        $data = json_decode($response->getBody());
        error_log(print_r($response->getBody()->getContents(), true));
        // Access the course_grade_id

        return $data->course_grade_id;
    }
    // This function obtains the list of modules by sections of a Moodle course.
    public static function getModulesListBySectionsCourse($instance, $course_id)
    {
        $url_lms = MoodleController::getURLLMS($instance);
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_modules_list_by_sections_course',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        return $data;
    }
    // This function gets the modules of a Moodle course that are not compatible with the type of map you want to create.
    public static function getModulesNotSupported($request, $sessionData)
    {
        $url_lms = $sessionData->platform_id;
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        $sections = json_encode($request->sections);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_course_modules',
                'courseid' => $sessionData->context_id,
                'exclude' => explode(',', json_encode($request->supportedTypes)),
                'invert' => false,
                'moodlewsrestformat' => 'json'
            ]

        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        $modules = [];
        foreach ($data->modules as $module) {
            $section_position = 0;
            foreach (json_decode($sections) as $section) {
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
        return response()->json(['ok' => true, 'data' => $modules]);
    }
    // This function gets the item id of a Moodle course that corresponds to a grade id.
    public static function getGradeModule($url_lms, $gradeId)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_course_item_id_for_grade_id',
                'gradeid' => $gradeId,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // This function gets the assignable roles from a Moodle course.
    public static function getRoles($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_assignable_roles',
                'moodlewsrestformat' => 'json',
                'contextid' => $course_id
            ]
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // This function obtains the competencies of a Moodle course.
    public static function getCompetencies($url_lms, $course_id)
    {
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_course_competencies',
                'moodlewsrestformat' => 'json',
                'idnumber' => $course_id
            ]
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    // This function updates a Moodle course.
    public static function updateCourse($instance, $sections, $modules, $badges)
    {
        if($modules !== null && is_array($modules) && count($modules) > 0){
            foreach ($modules as &$module) {
                if (isset($module['c'])) {
                    $module['c'] = json_encode($module['c']);
                }
            }
        }

        $url_lms = MoodleController::getURLLMS($instance);
        $token_request = LtiController::getLmsToken($url_lms, MOODLE_PLATFORM, true);

        //dd($instance, $sections, $modules, $badges);
        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        // dd(json_decode(json_encode($sections)), $sections);
        $response = $client->request('POST', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_update_course',
                'data' => [
                    'sections' => $sections,
                    'modules' => $modules,
                    'badges' => $badges
                ],
                'moodlewsrestformat' => 'json'

            ],

        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        dd($data);
        return $data;
    }
    public static function getCalifications($url_lms, $module_id, $module_modname)
    {
        
        // header('Access-Control-Allow-Origin: *');
        $token_request = LtiController::getLmsToken($url_lms, 'moodle', true);
        // dd($token_request['data']);
        // dd($module_id, $module_modname);

        $client = new Client([
            'base_uri' => $url_lms . '/webservice/rest/server.php',
            'timeout' => 20.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => $token_request['data'],
                'wsfunction' => 'local_uniadaptive_get_module_data',
                'moduleid' => $module_id,
                'itemmodule' => $module_modname,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        // if($module_modname == "quiz")

        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        $data->data->data->min = (float) number_format($data->data->data->min, 5);
        $data->data->data->max = (float) number_format($data->data->data->max, 5);
        // error_log(json_encode($data));
        return $data->data;
    }
}