<?php

namespace App\Http\Controllers;

use App\Models\BlockData;
use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use ceLTIc\LTI\Platform;
use GuzzleHttp\Client;
use LonghornOpen\LaravelCelticLTI\LtiTool;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as FacadesRoute;
use Illuminate\Support\Facades\Session;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;


class LtiController extends Controller
{

    public function getJWKS()
    {
        header('Access-Control-Allow-Origin: ' . env('URL_FRONT'));
        $tool = LtiTool::getLtiTool();
        return $tool->getJWKS();
    }
    // Función que obtiene datos del LMS, los almacena en la base de datos (TEMPORAL) y redirige al front
    public function ltiMessage()
    {
        $tool = LtiTool::getLtiTool();
        // dd($tool);
        $tool->handleRequest();
        // dd($tool);
        $fire = $tool->getMessageParameters();
        // dd($fire);
        $fechaActual = date('Y-m-d H:i:s');
        // dd(url('/'));
        // dd($fire['ext_sakai_server'], $fire['ext_sakai_serverid']);
        // $sessionId = $this->createSession($fire['ext_sakai_server'], $fire['ext_sakai_serverid']);
        // dd($this->getContents($fire['context_id'], $sessionId));
        // dd($this->getAssignments($fire['context_id'], $sessionId));
        // dd($this->getForums($fire['context_id'], $sessionId));
        // dd($this->getLessons($fire['context_id'], $sessionId));
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
                    'profile_url' => $this->getImgUser($fire['platform_id'], $fire['user_id']),
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
                    'session_id' =>  $this->createSession($fire['ext_sakai_server'], $fire['ext_sakai_serverid']),
                    'launch_presentation_return_url' => $fire['launch_presentation_return_url'],
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
        return redirect()->to(env('URL_FRONT'));
        // exit;
    }
    public function createSession($sakaiURL, $sakaiServerId)
    {
        $client = new Client();

        $response = $client->request('GET', $sakaiURL . '/sakai-ws/rest/login/login?id=' . env('SAKAI_USER') . '&pw=' . env('SAKAI_PASSWORD'));
        $content = $response->getBody()->getContents();
        $userId = $content . '.' . $sakaiServerId;
        return $userId;
    }
    // Función que devuelve los datos del usuario y del curso
    public function getSession()
    {
        header('Access-Control-Allow-Origin: ' . env('URL_FRONT'));
        $lastInserted = DB::table('lti_info')->latest()->first();
        // dd($lastInserted);
        $data = [];
        switch ($lastInserted->tool_consumer_info_product_family_code) {
            case 'moodle':
                $data = [
                    [
                        'user_id' => $lastInserted->user_id,
                        'name' => $lastInserted->lis_person_name_full,
                        'profile_url' => $lastInserted->profile_url,
                        'roles' => $lastInserted->roles
                    ],
                    [
                        'platform' => $lastInserted->tool_consumer_info_product_family_code,
                        'course_id' => $lastInserted->context_id,
                        'name' => $lastInserted->context_title,
                        'return_url' => $lastInserted->launch_presentation_return_url,
                        'sections' => $this->getSections($lastInserted->platform_id, $lastInserted->context_id),
                        'groups' => $this->getGroups($lastInserted->platform_id, $lastInserted->context_id),
                        'grupings' => $this->getGrupings($lastInserted->platform_id, $lastInserted->context_id),
                        'badges' => $this->getBadges($lastInserted->context_id),
                    ],
                    $this->getCourse(
                        $lastInserted->context_id,
                        $lastInserted->tool_consumer_info_product_family_code,
                        $lastInserted->platform_id
                    )
                ];
                break;
            case 'sakai':
                $data = [
                    [
                        'name' => $lastInserted->lis_person_name_full,
                        'profile_url' => $lastInserted->profile_url,
                        'roles' => $lastInserted->roles
                    ],
                    [
                        'name' => $lastInserted->context_title,
                        'course_id' => $lastInserted->context_id,
                        'session_id' => $lastInserted->session_id,
                        'platform' => $lastInserted->tool_consumer_info_product_family_code,
                        'return_url' => $lastInserted->launch_presentation_return_url,
                    ],
                    $this->getCourse(
                        $lastInserted->context_id,
                        $lastInserted->tool_consumer_info_product_family_code,
                        $lastInserted->platform_id
                    )
                ];
                break;
            default:
                break;
        }

        // DB::table('lti_info')->truncate();
        return $data;
    }
    public function getLessons($lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $lms . '/direct/lessons/site/' . $contextId . '.json', [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        dd($data);
        $lessons = [];
        foreach ($data->lessons_collection as $Lesson) {
            $response2 = $client->request('GET', $lms . '/direct/lessons/lesson/' . $Lesson->id . '.json', [
                'headers' => [
                    'Cookie' => 'JSESSIONID=' . $sessionId,
                ],
            ]);
            $content2 = $response2->getBody()->getContents();
            $data2 = json_decode($content2);
            dd($data2->contentsList);
            // array_push($modules, [
            //     'id' => htmlspecialchars($module->id),
            //     'name' => htmlspecialchars($module->name)
            // ]);
        }
        // dd($data->lessons_collection[0]->id);
        return $data;
    }
    // Función que devuelve las tareas de un curso de Sakai
    public function getAssignments($lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $lms . '/direct/assignment/site/' . $contextId . '.json', [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $dataAssignments = json_decode($content);
        // dd($dataAssignments);
        $assignments = [];
        foreach ($dataAssignments->assignment_collection as $assignment) {
            $assignments[] = array(
                'id' => $assignment->entityId,
                'name' => $assignment->title
            );
        }
        return $assignments;
    }
    // Función que devuelve los foros de un curso de Sakai
    public function getForums($lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $lms . '/direct/forums/site/' . $contextId . '.json', [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $dataForums = json_decode($content);
        // dd($dataForums);
        $forums = [];
        foreach ($dataForums->forums_collection as $forum) {
            $forums[] = array(
                'id' => $forum->entityId,
                'name' => $forum->title
            );
        }
        return $forums;
    }
    // Función que devuelve los recursos de un curso de Sakai dependiendo de su tipo
    public function getResources($lms, $contextId, $sessionId, $type)
    {
        $client = new Client();

        $response = $client->request('GET', $lms . '/direct/content/resources/group/' . $contextId . '.json?depth=3', [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $dataContents = json_decode($content);
        // dd($dataContents->content_collection);
        $resources = [];
        if ($type === 'resource') {
            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                // dd($resource);
                switch ($resource->mimeType) {
                    case 'text/plain':
                    case 'text/html':
                    case 'text/url':
                    case null:
                        break;
                    default:
                        array_push($resources, [
                            'id' => htmlspecialchars($resource->resourceId),
                            'name' => htmlspecialchars($resource->name)
                        ]);
                        break;
                }
            }
            // dd($data);
            return $resources;
        } else {
            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                // dd($resource);
                if ($resource->mimeType === $type) {
                    // dd($resource->resourceId);
                    array_push($resources, [
                        'id' => htmlspecialchars($resource->resourceId),
                        'name' => htmlspecialchars($resource->name)
                    ]);
                }
            }
            // dd($data);
            return $resources;
        }
    }
    // Función que devuelve la url de la imagen del usuario
    public function getImgUser($lms, $id)
    {
        header('Access-Control-Allow-Origin: ' . env('URL_FRONT'));
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
    // Función que devuelve los grupos de un curso
    public function getGroups($lms, $id)
    {
        header('Access-Control-Allow-Origin:' . env('URL_FRONT'));
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
    public function getGrupings($lms, $id)
    {
        header('Access-Control-Allow-Origin: ' . env('URL_FRONT'));
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
    // Función que devuelve TODOS los modulos de un curso
    public function getModules(Request $request)
    {
        header('Access-Control-Allow-Origin:' . env('URL_FRONT'));
        // dd($request);
        $instance = Instance::select('platform', 'url_lms')
            ->where('id', $request->instance)
            ->first();
        // dd($instance);
        if ($instance->exists) {
            // dd($request->course);
            switch ($instance->platform) {
                case 'moodle':
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

                    foreach ($data as $indexS => $section) {
                        // dd($section);
                        foreach ($section->modules as $indexM => $module) {
                            // dd($module);
                            $hasCalifications = false;
                            $hasCompletions = false;
                            $calificable = false;
                            if ($module->id == 83)
                                // dd($module);

                                if ($module->completion > 0) {
                                    $hasCompletions = true;
                                }

                            $client2 = new Client([
                                'base_uri' => $instance->url_lms . '/webservice/rest/server.php',
                                'timeout' => 2.0,
                            ]);
                            $response2 = $client2->request('GET', '', [
                                'query' => [
                                    'wstoken' => env('WSTOKEN'),
                                    'wsfunction' => 'core_course_get_course_module',
                                    'cmid' => $module->id,
                                    'moodlewsrestformat' => 'json'
                                ]
                            ]);
                            $content2 = $response2->getBody()->getContents();
                            $data2 = json_decode($content2);

                            if (isset($data2->cm->grade) && $data2->cm->grade > 0 && $module->id == 83) {
                                // dd($data2);
                                $calificable = true;
                            }

                            if ($hasCompletions && $calificable) {
                                $hasCalifications = true;
                            }

                            array_push($modules, [
                                'name' => htmlspecialchars($module->name),
                                'modname' => htmlspecialchars($module->modname),
                                'id' => htmlspecialchars($module->id),
                                'has_califications' => $hasCalifications,
                                'order' => $indexM,
                                'section' => $indexS,
                                'indent' => $module->indent,
                                'visible' => ($module->visible >= 1) ? 'show_unconditionally' : 'hidden_until_access'
                                // 'section' => 
                            ]);
                        }
                    }
                    // dd($calificable);
                    return $modules;
                    break;
                case 'sakai':
                    return $this->getLessons($instance->url_lms, $request->course, $request->session);
                    // $forums = $this->getForums($request->course, $request->session);
                    // dd($forums);
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
        header('Access-Control-Allow-Origin:' . env('URL_FRONT'));
        // dd(intVal($request->course), $request->type);
        switch ($request->platform) {
            case 'moodle':
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
                foreach ($data as $section) {
                    // dd($section);
                    foreach ($section->modules as $module) {
                        array_push($modules, [
                            'id' => htmlspecialchars($module->id),
                            'name' => htmlspecialchars($module->name)
                        ]);
                    }
                }
                return $modules;
                break;
            case 'sakai':
                switch ($request->type) {
                    case 'forum':
                        return $this->getForums($request->lms, $request->course, $request->session);
                        break;
                    case 'assign':
                        return $this->getAssignments($request->lms, $request->course, $request->session);
                        break;
                    case 'text':
                        return $this->getResources($request->lms, $request->course, $request->session, 'text/plain');
                        break;
                    case 'url':
                        return $this->getResources($request->lms, $request->course, $request->session, 'text/url');
                        break;
                    case 'html':
                        return $this->getResources($request->lms, $request->course, $request->session, 'text/html');
                        break;
                    case 'folder':
                        return $this->getResources($request->lms, $request->course, $request->session, null);
                        break;
                    case 'resource':
                        return $this->getResources($request->lms, $request->course, $request->session, 'resource');
                        break;
                    default:
                        # code...
                        break;
                }
                break;
            default:
                # code...
                break;
        }
        // dd($request);
    }
    // Función que devuelve TODAS las secciones de un curso
    public function getSections($lms, $id)
    {
        header('Access-Control-Allow-Origin:' . env('URL_FRONT'));
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
    public function getGradereport($lms, $id)
    {
        header('Access-Control-Allow-Origin:' . env('URL_FRONT'));
        $client = new Client([
            'base_uri' => $lms . '/webservice/rest/server.php',
            'timeout' => 2.0,
        ]);
        $response = $client->request('GET', '', [
            'query' => [
                'wstoken' => env('WSTOKEN'),
                'wsfunction' => 'gradereport_user_get_grade_items',
                'courseid' => $id,
                'moodlewsrestformat' => 'json'
            ]
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        dd($data);
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
    // Función que devuelve las medallas de un curso
    public function getBadges($id)
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
    public function getCourse($course_id, $platform, $url_lms)
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
        $dataMaps = Map::select('id', 'course_id', 'name', 'updated_at')
            ->where('course_id', $dataCourse->id)
            ->get();
        $maps = [];
        foreach ($dataMaps as $map) {
            $dataVersions = Version::select('id', 'map_id', 'name', 'updated_at', 'default')
                ->where('map_id', $map->id)
                ->get();
            $versions = [];
            foreach ($dataVersions as $version) {
                $dataBlocksDatas = BlockData::select('id', 'version_id', 'id_block_data', 'position', 'type', 'parentNode', 'espandParent', 'data')
                    ->where('version_id', $version->id)
                    ->get();
                $blocksDatas = [];
                foreach ($dataBlocksDatas as $blockData) {
                    array_push($blocksDatas, [
                        'id' => $blockData->id,
                        'version_id' => $blockData->version_id,
                        'id_block_data' => $blockData->id_block_data,
                        'position' => $blockData->position,
                        'type' => $blockData->type,
                        'parentNode' => $blockData->parentNode,
                        'expandParent' => $blockData->expandParent,
                        'data' => $blockData->data,
                    ]);
                }
                array_push($versions, [
                    'id' => $version->id,
                    'map_id' => $version->map_id,
                    'name' => $version->name,
                    'updated_at' => $version->updated_at,
                    'default' => $version->default,
                    'blocksData' => $blocksDatas,
                ]);
            }
            array_push($maps, [
                'id' => $map->id,
                'course_id' => $map->course_id,
                'name' => $map->name,
                'versions' => $versions,
            ]);
        }
        $course = [
            'instance_id' => $dataInstance->id,
            'platform' => $dataInstance->platform,
            'url_lms' => $dataInstance->url_lms,
            'course_id' =>  $dataCourse->course_id,
            'maps' => $maps,
        ];

        return $course;
    }
    public function storeVersion(Request $request)
    {
        $course = Course::where('instance_id', $request->saveData['instance_id'])
            ->where('course_id', $request->saveData['course_id'])
            ->first();
        $mapData = $request->saveData['map'];
        $map = Map::firstOrNew(
            ['course_id' =>  $course->id, 'name' => $mapData['name'], 'lesson_id' => $request->saveData['instance_id']]
        );
        if (!$map->exists) {
            $map->save();
        }
        $versionData = $mapData['versions'];
        $version = Version::updateOrCreate(
            ['map_id' => $map->id, 'name' => $versionData['name']],
            ['default' => boolval($versionData['default'])]
        );

        return json_encode($mapData);
    }
}
