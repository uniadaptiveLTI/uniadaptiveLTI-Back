<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SakaiController extends Controller
{
    public static function getCourse($course_id, $platform, $url_lms)
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

    // Función que devuelve los datos del usuario y del curso
    public static function getSession(object $lastInserted)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        $data = [
            [
                'user_id' => SakaiController::getId($lastInserted->user_id),
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => SakaiController::getUrl($lastInserted->platform_id, $lastInserted->context_id, SakaiController::getId($lastInserted->user_id)),
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                // 'instance_id' => $this->getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'lessons' => SakaiController::getLessons($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id),
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'user_members' => SakaiController::getUserMembers($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id),
                'sakai_groups' => SakaiController::getGroups($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id)
            ],
            SakaiController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        // dd($data);
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public static function createSession($url_lms, $sakaiServerId)
    {
        $client = new Client();
        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . env('SAKAI_USER') . '&pw=' . env('SAKAI_PASSWORD'));
        $content = $response->getBody()->getContents();
        $user_id = $content . '.' . $sakaiServerId;
        return $user_id;
    }

    public static function getLessons($url_lms, $context_id, $session_id)
    {
        // header('Access-Control-Allow-Origin: *');
        $request = SakaiController::createClient($url_lms . '/direct/lessons/site/' . $context_id . '.json', $session_id);

        $data = json_decode(($request['requestBody']));
        $statusCode = $request['statusCode'];

        $lessons = [];
        if ($statusCode == 200) {
            foreach ($data->lessons_collection as $Lesson) {
                array_push($lessons, [
                    'id' => $Lesson->id,
                    'name' => $Lesson->lessonTitle,
                    'page_id' => SakaiController::getPageIdLesson($url_lms, $Lesson->id, $session_id)
                ]);
            }
        }
        return $lessons;
    }

    public static function getPageIdLesson($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/direct/lessons/lesson/' . $context_id . '.json', $session_id);
        $data = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];
        if ($statusCode == 200) {
            return $data->sakaiId;
        }
    }

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request, $sessionData)
    {
        switch ($request->type) {
            case 'forum':
                return SakaiController::getForums($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'exam':
                return SakaiController::getAssesments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'assign':
                return SakaiController::getAssignments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'text':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/plain');
                break;
            case 'url':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/url');
                break;
            case 'html':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/html');
                break;
            case 'folder':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, null);
                break;
            case 'resource':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'resource');
                break;
            default:
                return response()->json(['ok' => false, 'errorType' => 'TYPE_NOT_SUPPORTED', 'data' => '']);
                break;
        }
    }

    // Función que devuelve los foros de un curso de Sakai
    public static function getForums($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

        $dataForums = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $forums = [];
        if ($statusCode == 200) {
            foreach ($dataForums->forums_collection as $forum) {
                $forums[] = array(
                    'id' => $forum->entityId,
                    'name' => $forum->title
                );
            }
        }
        return response()->json(['ok' => true, 'data' => $forums]);
    }

    public static function getForumById($url_lms, $context_id, $session_id, $forumId)
    {
        $request = SakaiController::createClient($url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

        $dataForums = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            foreach ($dataForums->forums_collection as $forum) {
                if ($forum->id == $forumId) {
                    return $forum;
                }
            }
        }
    }

    // Función que devuelve las tareas de un curso de Sakai
    public static function getAssignments($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

        $dataAssignments = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];
        $assignments = [];
        if ($statusCode == 200) {
            foreach ($dataAssignments->assignment_collection as $assignment) {
                error_log(print_r($assignment, true));
                $assignments[] = array(
                    'id' => $assignment->entityId,
                    'name' => $assignment->entityTitle
                );
            }
        }
        return response()->json(['ok' => true, 'data' => $assignments]);
    }

    public static function getAssignmentById($url_lms, $context_id, $session_id, $assignmentId)
    {
        $request = SakaiController::createClient($url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

        $dataAssignments = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            foreach ($dataAssignments->assignment_collection as $assignment) {
                if ($assignment->id == $assignmentId) {
                    return $assignment;
                }
            }
        }
    }

    // Función que devuelve los recursos de un curso de Sakai dependiendo de su tipo
    public static function getResources($url_lms, $context_id, $session_id, $type)
    {
        $request = SakaiController::createClient($url_lms . '/direct/content/resources/group/' . $context_id . '.json?depth=3', $session_id);

        $dataContents = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $resources = [];

        if ($statusCode == 200) {
            function decode_unicode($str)
            {
                $str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                }, $str);
                return $str;
            }

            if ($type === 'resource') {
                function process_resource($resource, &$resources)
                {
                    $id = decode_unicode(str_replace('\/', '/', $resource->resourceId));

                    switch ($resource->mimeType) {
                        case 'text/html':
                        case 'text/url':
                        case null:
                            break;
                        default:
                            array_push($resources, [
                                'id' => htmlspecialchars($id),
                                'name' => htmlspecialchars($resource->name)
                            ]);
                            break;
                    }
                    error_log("223 " . print_r($resource, true));
                    if (count($resource->resourceChildren) >= 1) {
                        foreach ($resource->resourceChildren as $child) {
                            process_resource($child, $resources);
                        }
                    }
                }

                foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                    process_resource($resource, $resources);
                }
                return response()->json(['ok' => true, 'data' => $resources]);
            } else {
                foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                    if ($resource->mimeType === $type) {
                        array_push($resources, [
                            'id' => htmlspecialchars($resource->resourceId),
                            'name' => htmlspecialchars($resource->name)
                        ]);
                    }
                }
                return response()->json(['ok' => true, 'data' => $resources]);
            }
        } else {
            return response()->json(['ok' => true, 'data' => $resources]);
        }
    }

    public static function getResourceById($url_lms, $context_id, $session_id, $resourceId)
    {
        $request = SakaiController::createClient($url_lms . '/api/sites/' . $context_id . '/entities/resources', $session_id);

        $modules = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            foreach ($modules as $resource) {
                if ($resource->id == $resourceId) {
                    return $resource;
                }
            }
        }
    }

    public static function getUserMembers($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/direct/site/' . $context_id . '/memberships.json', $session_id);

        $dataUsers = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $users = [];
        foreach ($dataUsers->membership_collection as $user) {
            $users[] = array(
                'id' => $user->userId,
                'name' => $user->userDisplayName
            );
        }
        return $users;
    }
    public static function getGroups($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/direct/site/' . $context_id . '/groups.json', $session_id);

        $dataGroups = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $groups = [];
        foreach ($dataGroups as $group) {
            $groups[] = array(
                'id' => $group->reference,
                'name' => $group->title
            );
        }
        return $groups;
    }

    public static function getModules($url_lms, $lesson_id, $session_id, $context_id)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($url_lms.'/direct/lessons/lesson/'.$context_id.'.json');
        $lessonGetRequest = SakaiController::createClient($url_lms . '/direct/lessons/lesson/' . $lesson_id . '.json', $session_id);

        $modulesData = json_decode($lessonGetRequest['requestBody']);
        $modulesRequestStatus = $lessonGetRequest['statusCode'];

        $modules = [];
        $section = 0;
        $column = 0;
        $order = 1;

        if ($modulesRequestStatus == 200) {
            if ($modulesData->contentsList != null && count($modulesData->contentsList) >= 1) {
                foreach ($modulesData->contentsList as $index => $module) {
                    $modulesData->contentsList[$index]->type = SakaiController::changeIdNameType($module->type);

                    if ($modulesData->contentsList[$index]->type == 'break') {
                        $format = isset($modulesData->contentsList[$index]->format);
                        if ($format) {
                            switch ($modulesData->contentsList[$index]->format) {
                                case 'section':
                                    $section++;
                                    break;

                                case 'column':
                                    $column++;
                                    break;
                            }
                        } else {
                            $section++;
                        }
                        $order = 1;
                    } else if ($modulesData->contentsList[$index]->type != 'break' /*&& $modulesData->contentsList[$index]->type != 'generic'*/&& $modulesData->contentsList[$index]->type != 'page' && $modulesData->contentsList[$index]->type != 'text') {
                        // $modulesData->contentsList[$index]->section = $section;
                        $module = [
                            "id" => $modulesData->contentsList[$index]->id,
                            "name" => $modulesData->contentsList[$index]->name,
                            "modname" => $modulesData->contentsList[$index]->type,
                            "pageId" => $modulesData->contentsList[$index]->pageId,
                            "section" => $section,
                            "indent" => $column,
                            "order" => $order++,
                        ];

                        switch ($modulesData->contentsList[$index]->type) {
                            case 'exam':
                                $sakaiId = SakaiController::parseSakaiId($modulesData->contentsList[$index]);
                                $module['sakaiId'] = $sakaiId;

                                $examFounded = SakaiController::getAssesmentById($url_lms, $context_id, $session_id, $sakaiId);

                                if (isset($examFounded) && isset($examFounded->openDate)) {
                                    $openDate = date('Y-m-d\TH:i', $examFounded->openDate);
                                    $module['openDate'] = $openDate;
                                }

                                if (isset($examFounded) && isset($examFounded->dueDate)) {
                                    $dueDate = date('Y-m-d\TH:i', $examFounded->dueDate);
                                    $module['dueDate'] = $dueDate;
                                }

                                if (isset($examFounded) && isset($examFounded->closeDate)) {
                                    $closeDate = date('Y-m-d\TH:i', $examFounded->closeDate);
                                    $module['closeDate'] = $closeDate;
                                }

                                $updatedModule = SakaiController::parseItemDates($module);

                                break;
                            case 'assign':
                                $sakaiId = SakaiController::parseSakaiId($modulesData->contentsList[$index]);
                                $module['sakaiId'] = $sakaiId;

                                $assignmentFounded = SakaiController::getAssignmentById($url_lms, $context_id, $session_id, $sakaiId);

                                if (isset($assignmentFounded) && isset($assignmentFounded->openTime) && isset($assignmentFounded->openTime->epochSecond)) {
                                    $openDate = date('Y-m-d\TH:i', $assignmentFounded->openTime->epochSecond);
                                    $module['openDate'] = $openDate;
                                }

                                if (isset($assignmentFounded) && isset($assignmentFounded->dueTime) && isset($assignmentFounded->dueTime->epochSecond)) {
                                    $dueDate = date('Y-m-d\TH:i', $assignmentFounded->dueTime->epochSecond);
                                    $module['dueDate'] = $dueDate;
                                }

                                if (isset($assignmentFounded) && isset($assignmentFounded->closeTime) && isset($assignmentFounded->closeTime->epochSecond)) {
                                    $closeDate = date('Y-m-d\TH:i', $assignmentFounded->closeTime->epochSecond);
                                    $module['closeDate'] = $closeDate;
                                }

                                $updatedModule = SakaiController::parseItemDates($module);

                                break;
                            case 'forum':
                                $sakaiId = SakaiController::parseSakaiId($modulesData->contentsList[$index]);
                                $module['sakaiId'] = $sakaiId;

                                $forumFounded = SakaiController::getForumById($url_lms, $context_id, $session_id, $sakaiId);

                                if (isset($forumFounded) && isset($forumFounded->openDate)) {
                                    $openDate = date('Y-m-d\TH:i', $forumFounded->openDate);
                                    $module['openDate'] = $openDate;
                                }

                                if (isset($forumFounded) && isset($forumFounded->closeDate)) {
                                    $closeDate = date('Y-m-d\TH:i', $forumFounded->closeDate);
                                    $module['dueDate'] = $closeDate;
                                }

                                $updatedModule = SakaiController::parseItemDates($module);

                                break;
                            case 'break':
                                break 2;
                            default:
                                $sakaiId = $modulesData->contentsList[$index]->sakaiId;
                                $module['sakaiId'] = $sakaiId;

                                $resourceFounded = SakaiController::getResourceById($url_lms, $context_id, $session_id, $sakaiId);

                                if (isset($resourceFounded) && isset($resourceFounded->openDate)) {
                                    $openDate = date('Y-m-d\TH:i', $resourceFounded->openDate);
                                    $module['openDate'] = $openDate;
                                }

                                if (isset($resourceFounded) && isset($resourceFounded->closeDate)) {
                                    $closeDate = date('Y-m-d\TH:i', $resourceFounded->closeDate);
                                    $module['dueDate'] = $closeDate;
                                }

                                $updatedModule = SakaiController::parseItemDates($module);

                                break;
                        }

                        array_push(
                            $modules,
                            $updatedModule
                        );
                    }
                }

                $conditionGetRequest = SakaiController::createClient($url_lms . '/api/sites/' . $context_id . '/conditions', $session_id);

                $conditionsData = json_decode($conditionGetRequest['requestBody']);
                $conditionsRequestStatus = $conditionGetRequest['statusCode'];

                if ($conditionsRequestStatus == 200 || ($conditionsData != null && count($conditionsData) >= 1)) {
                    $parsedModules = SakaiController::linkConditionToLessonItem($modules, $conditionsData);
                    return response()->json(['ok' => true, 'data' => $parsedModules]);
                } else {
                    return response()->json(['ok' => true, 'data' => $modules, 'extraInfo' => 'conditions_not']);
                }
            } else {
                return response()->json(['ok' => true, 'data' => []]);
            }
        } else {
            return response()->json(['ok' => false, 'errorType' => 'MODULES_ERROR']);
        }
    }

    public static function parseItemDates($module)
    {
        if (!isset($module['openDate'])) {
            $newOpenDate = date('Y-m-d\TH:i', time());
            $module['openDate'] = $newOpenDate;
        }

        if (isset($module['openDate']) && !isset($module['dueDate'])) {
            $timestamp = strtotime($module['openDate']);
            $oneWeekLater = strtotime('+1 week', $timestamp);

            $newDueDate = date('Y-m-d\TH:i', $oneWeekLater);
            $module['dueDate'] = $newDueDate;
        }

        if ($module['modname'] == "assign" || $module['modname'] == "exam") {
            if (isset($module['openDate']) && isset($module['dueDate']) && !isset($module['closeDate'])) {
                $timestamp = strtotime($module['dueDate']);
                $oneWeekLater = strtotime('+1 week', $timestamp);

                $newCloseDate = date('Y-m-d\TH:i', $oneWeekLater);
                $module['closeDate'] = $newCloseDate;
            }
        }

        return $module;
    }

    public static function parseSakaiId($contentListIndex)
    {
        $a = substr($contentListIndex->sakaiId, 1);
        $dataSakaiId = explode('/', $a);
        $sakaiId = end($dataSakaiId);
        return $sakaiId;
    }

    public static function linkConditionToLessonItem($modules, $conditions, $assign = true)
    {
        if ($assign == true) {
            foreach ($modules as &$module) {
                if ($conditions != null && count($conditions) >= 1) {
                    foreach ($conditions as $condition) {
                        if ($condition->type == "ROOT" && $condition->toolId == "sakai.lessonbuildertool") {
                            if ($condition->itemId == $module['id']) {
                                $module['gradeRequisites'] = json_decode(json_encode($condition));
                                break;
                            }
                        }
                    }
                }
            }
            unset($module);
            return $modules;
        } else {
            $conditionLessonList = [];
            if ($conditions != null && count($conditions) >= 1) {
                foreach ($conditions as &$condition) {
                    if ($condition->type == "ROOT" && $condition->toolId == "sakai.lessonbuildertool") {
                        foreach ($modules as &$module) {
                            if ($condition->itemId === $module->id) {
                                array_push($conditionLessonList, $condition);
                                break;
                            }
                        }
                    }
                }
            }
            return $conditionLessonList;
        }
    }

    public static function changeIdNameType($type)
    {
        switch ($type) {
            case 1:
                return 'resource';
                break;
            case 2:
                return 'page';
                break;
            case 3:
                return 'assign';
                break;
            case 4:
                return 'exam';
                break;
            case 5:
                return 'text';
                break;
            case 6:
                return 'url';
                break;
            case 8:
                return 'forum';
                break;
            case 14:
                return 'break';
                break;
            case 20:
                return 'folder';
                break;
            default:
                return 'generic';
                break;
        }
    }
    public static function getAssesments($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

        $modules = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $assesments = [];
        if ($statusCode == 200) {
            foreach ($modules as $assesment) {
                $assesments[] = array(
                    'id' => $assesment->id,
                    'name' => $assesment->title
                );
            }
        }
        return response()->json(['ok' => true, 'data' => $assesments]);
    }

    public static function getAssesmentById($url_lms, $context_id, $session_id, $assesmentId)
    {
        $request = SakaiController::createClient($url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

        $modules = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            foreach ($modules as $assesment) {
                if ($assesment->id == $assesmentId) {
                    return $assesment;
                }
            }
        }
    }

    public static function createClient($url, $session_id, $type = 'GET', $bodyData = [])
    {
        $client = new Client();
        $options = [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $session_id
            ],
        ];
        switch ($type) {
            case "GET":
            case "DELETE":
                // Both GET and DELETE share the same request options
                $response = $client->request($type, $url, $options);
                if ($type === "DELETE") {
                    $statusCode = $response->getStatusCode();
                    return $statusCode;
                }
                break;

            case "BATCH":
            case "POST":
                // Convert the $bodyData array to JSON
                $options['json'] = $bodyData;
                $options['headers']['Content-Type'] = 'application/json';
                if ($type === "POST") {
                    $response = $client->post($url, $options);
                } else {
                    $response = $client->patch($url, $options);
                }
                break;
            default:
                // Handle unsupported request types here
                return ['error' => 'Unsupported request type'];
        }
        $content = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        $responseData = [
            'requestBody' => $content,
            'statusCode' => $statusCode,
        ];
        return $responseData;
    }
    public static function getId($user_id)
    {
        $url = explode('/', $user_id);
        return $url[count($url) - 1];
    }

    public static function getUrl($url_lms, $context_id, $user_id)
    {
        return ($url_lms . '/direct/profile/' . $user_id . '/image/thumb?siteId=' . $context_id);
    }

    public static function find($array, $callback)
    {
        return current(array_filter($array, $callback));
    }

    public static function exportVersion(Request $request, $sessionData)
    {
        //header('Access-Control-Allow-Origin: *');
        $nodes = $request->nodes;

        $nodes = array_map(function ($item) {
            if ($item['title'] === null) {
                $item['title'] = "";
            }
            return $item;
        }, $nodes);

        $nodesToUpdate = $request->nodesToUpdate;
        $conditionList = $request->conditionList;

        $firstNode = reset($nodes);
        $firstPageId = $firstNode['pageId'];

        $allHaveSamePageId = true;

        foreach ($nodes as $node) {
            if (!isset($node['pageId']) || $node['pageId'] !== $firstPageId) {
                $allHaveSamePageId = false;
                break;
            }
        }

        if ($allHaveSamePageId) {
            $lessonCopyRequest = SakaiController::createClient($sessionData->platform_id . '/direct/lessons/lesson/' . $request->lessonId . '.json', $sessionData->session_id);
            $lessonCopy = json_decode($lessonCopyRequest['requestBody']);
            $lessonStatusCode = $lessonCopyRequest['statusCode'];

            $conditionsCopyRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions', $sessionData->session_id);
            $conditionsCopy = $conditionsCopyRequest['requestBody'];
            $conditionsStatusCode = $conditionsCopyRequest['statusCode'];

            if ($lessonStatusCode == 200 && $conditionsStatusCode == 200) {
                if (count($nodesToUpdate) >= 1) {
                    $nodesUpdateRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/entities', $sessionData->session_id, 'BATCH', $nodesToUpdate);
                    $nodesUpdateStatusCode = $nodesUpdateRequest['statusCode'];

                    if ($nodesUpdateStatusCode !== 200) {
                        return response()->json(['ok' => false, 'errorType' => 'NODE_UPDATE_ERROR', 'data' => '']);
                    }
                }

                $conditionsDelete = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/conditions', $sessionData->session_id, 'DELETE');
                $conditionsDeleteStatusCode = $conditionsDelete;

                $lessonItemsDelete = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items', $sessionData->session_id, 'DELETE');
                $lessonItemsDeleteStatusCode = $lessonItemsDelete;

                if ($conditionsDeleteStatusCode === 200 && $lessonItemsDeleteStatusCode === 200) {
                    $nodesCreationRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $nodes);
                    $nodesCreated = $nodesCreationRequest['requestBody'];
                    $nodesCreationStatusCode = $nodesCreationRequest['statusCode'];

                    if ($nodesCreationStatusCode == 200) {
                        if (($conditionList != null && count($conditionList) >= 1)) {
                            $filteredArray = SakaiController::conditionIdParse(json_decode($nodesCreated), $conditionList);

                            $conditionsParsedList = (array_values($filteredArray));

                            $conditionsCreationRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsParsedList);
                            $conditionsCreationStatusCode = $conditionsCreationRequest['statusCode'];

                            if ($conditionsCreationStatusCode == 200) {
                                return response()->json(['ok' => true, 'errorType' => 'EXPORTACION_CON_EXITO', 'data' => '']);
                            } else {
                                return response()->json(['ok' => true, 'errorType' => 'EXPORTACION_CON_EXITO', 'data' => '', 'extraInfo' => 'conditions_not']);
                            }
                        } else {
                            return response()->json(['ok' => true, 'errorType' => 'EXPORTACION_CON_EXITO', 'data' => '', 'extraInfo' => 'conditions_not']);
                        }
                    } else {
                        $parsedNodes = SakaiController::parseSakaiLessonCopy($lessonCopy->contentsList);

                        $nodesCopyCreationRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $parsedNodes);
                        $nodesCopyCreation = json_decode($nodesCopyCreationRequest['requestBody']);
                        $nodesCopyCreationStatusCode = $nodesCopyCreationRequest['statusCode'];

                        $parsedConditions = SakaiController::linkConditionToLessonItem(($nodesCopyCreation), json_decode($conditionsCopy), false);

                        $filteredArray = SakaiController::conditionIdParse($nodesCopyCreation, $parsedConditions);

                        $conditionsCopyCreationRequest = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $filteredArray);
                        $conditionsCopyCreationStatusCode = $conditionsCopyCreationRequest['statusCode'];

                        if ($nodesCopyCreationStatusCode == 200 && $conditionsCopyCreationStatusCode == 200) {
                            return response()->json(['ok' => false, 'errorType' => 'LESSON_ITEMS_CREATION_ERROR', 'data' => '']);
                        } else {
                            return response()->json(['ok' => false, 'errorType' => 'FATAL_ERROR', 'data' => '']);
                        }
                    }
                } else {
                    return response()->json(['ok' => false, 'errorType' => 'LESSON_DELETE_ERROR', 'data' => '']);
                }
            } else {
                return response()->json(['ok' => false, 'errorType' => 'LESSON_COPY_ERROR', 'data' => '']);
            }
        } else {
            return response()->json(['ok' => false, 'errorType' => 'PAGE_EXPORT_ERROR', 'data' => '']);
        }
    }

    public static function conditionIdParse($nodesCreated, $conditionList)
    {
        $nodesIdList = [];
        foreach ($nodesCreated as $node) {
            if (isset($node->type) && $node->type !== 14) {
                $nodeJson = json_encode(['id' => $node->id, 'contentRef' => $node->contentRef]);
                array_push($nodesIdList, $nodeJson);
            }
        }

        $filteredArray = array_filter($conditionList, function ($condition) use ($nodesIdList) {
            foreach ($nodesIdList as $idObject) {
                $idObject = json_decode($idObject, true);
                if (isset($condition['itemId']) && $condition['itemId'] === $idObject['contentRef']) {
                    return true;
                }
            }
            return false;
        });

        foreach ($filteredArray as &$root) {
            foreach ($nodesIdList as $idObject) {
                $idObject = json_decode($idObject, true);
                if ($root['itemId'] == $idObject['contentRef']) {
                    $root['itemId'] = ($idObject['id']);
                    unset($root['id']);
                    break;
                }
            }

            if (isset($root['subConditions'])) {
                foreach ($root['subConditions'] as &$parent) {
                    unset($parent['id']);
                    if (isset($parent['subConditions']) && count($parent['subConditions']) >= 1) {
                        foreach ($parent['subConditions'] as &$childCondition) {
                            foreach ($nodesIdList as $idObject) {
                                $idObject = json_decode($idObject, true);
                                if ($childCondition['itemId'] == $idObject['contentRef']) {
                                    $childCondition['itemId'] = ($idObject['id']);
                                    unset($childCondition['id']);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($root, $subCondition, $childCondition);

        return $filteredArray;
    }

    public static function parseSakaiLessonCopy($contentsList)
    {
        foreach ($contentsList as $content) {
            unset($content->description);
            unset($content->html);
            unset($content->id);

            $content->title = $content->name;
            unset($content->name);

            unset($content->url);
            unset($content->prerequisite);
            unset($content->required);

            if ($content->type == 14) {
                unset($content->sakaiId);
            } else {
                unset($content->format);
                $content->contentRef = $content->sakaiId;
                unset($content->sakaiId);
            }
        }

        return $contentsList;
    }
}