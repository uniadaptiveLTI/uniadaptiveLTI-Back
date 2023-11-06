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
        $lessonGetRequest = SakaiController::getLessons($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        $successfulLessonRequest = SakaiController::requestChecker($lessonGetRequest);
        if ($successfulLessonRequest == true) {
            $lessons = $lessonGetRequest['data']['lessons'];
        } else {
            return response()->json($lessonGetRequest);
        }

        $userMembersGetRequest = SakaiController::getUserMembers($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        $successfulUserRequest = SakaiController::requestChecker($userMembersGetRequest);

        if ($successfulUserRequest == true) {
            $userMembers = $userMembersGetRequest['data']['users'];
        } else {
            return response()->json($userMembersGetRequest);
        }

        $groupsGetRequest = SakaiController::getGroups($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        $successfulGroupRequest = SakaiController::requestChecker($groupsGetRequest);

        if ($successfulGroupRequest == true) {
            $groups = $groupsGetRequest['data']['groups'];
        } else {
            return response()->json($groupsGetRequest);
        }

        $data = [
            [
                'user_id' => SakaiController::getId($lastInserted->user_id),
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => SakaiController::getUrl($lastInserted->platform_id, $lastInserted->context_id, SakaiController::getId($lastInserted->user_id)),
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                'instance_id' => SakaiController::getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'lessons' => $lessons,
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'user_members' => $userMembers,
                'sakai_groups' => $groups
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

    

    public static function createSession($url_lms, $sakaiServerId, $data)
    {
        $client = new Client();
        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . $data['user'] . '&pw=' . $data['password']);
        $content = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();

        if (isset($statusCode) && $statusCode == 200) {
            $user_id = $content . '.' . $sakaiServerId;
            return ['ok' => true, 'data' => ['user_id' => $user_id, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => []];
        }
    }

    public static function getLessons($url_lms, $context_id, $session_id)
    {
        // header('Access-Control-Allow-Origin: *');
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/lessons/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        $lessons = [];
        if ($statusCode == 200) {
            $data = json_decode(($request['requestBody']));
            foreach ($data->lessons_collection as $Lesson) {
                $pageIdGetRequest = SakaiController::getPageIdLesson($url_lms, $Lesson->id, $session_id);
                $successfulRequest = SakaiController::requestChecker($pageIdGetRequest);

                if ($successfulRequest == true) {
                    array_push($lessons, [
                        'id' => $Lesson->id,
                        'name' => $Lesson->lessonTitle,
                        'page_id' => $pageIdGetRequest['data']['page_founded']
                    ]);
                } else {
                    return ['ok' => false, 'data' => ['lessons' => $lessons, 'status_code' => $statusCode]];
                }
            }
            return ['ok' => true, 'data' => ['lessons' => $lessons, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['lessons' => $lessons, 'status_code' => $statusCode]];
        }
    }

    public static function getPageIdLesson($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/lessons/lesson/' . $context_id . '.json', $session_id);
        $data = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];
        if ($statusCode == 200) {
            $data = json_decode($request['requestBody']);
            return ['ok' => true, 'data' => ['page_founded' => $data->sakaiId, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request, $sessionData)
    {
        switch ($request->type) {
            case 'forum':
                $forumsGetRequest = SakaiController::getForums($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulForumsRequest = SakaiController::requestChecker($forumsGetRequest);

                $forumsStatusCode = $forumsGetRequest['data']['status_code'];

                if ($successfulForumsRequest == true) {
                    return ['ok' => true, 'data' => ['items' => $forumsGetRequest['data']['forums'], 'status_code' => $forumsStatusCode]];
                } else {
                    return ['ok' => false, 'data' => ['items' => [], 'status_code' => $forumsStatusCode]];
                }
            case 'exam':
                $examsGetRequest = SakaiController::getAssesments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulExamsRequest = SakaiController::requestChecker($examsGetRequest);

                $examsStatusCode = $examsGetRequest['data']['status_code'];

                if ($successfulExamsRequest == true) {
                    return ['ok' => true, 'data' => ['items' => $examsGetRequest['data']['assesments'], 'status_code' => $examsStatusCode]];
                } else {
                    return ['ok' => false, 'data' => ['items' => [], 'status_code' => $examsStatusCode]];
                }
            case 'assign':
                $assignmentsGetRequest = SakaiController::getAssignments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulAssignmentsRequest = SakaiController::requestChecker($assignmentsGetRequest);

                $assignmentsStatusCode = $assignmentsGetRequest['data']['status_code'];

                if ($successfulAssignmentsRequest == true) {
                    return ['ok' => true, 'data' => ['items' => $assignmentsGetRequest['data']['assignments'], 'status_code' => $assignmentsStatusCode]];
                } else {
                    return ['ok' => false, 'data' => ['items' => [], 'status_code' => $assignmentsStatusCode]];
                }
            case 'text':
                return SakaiController::getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'plain');
            case 'url':
                return SakaiController::getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'url');
            case 'html':
                return SakaiController::getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'html');
            case 'folder':
                return SakaiController::getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'folder');
            case 'resource':
                return SakaiController::getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'resource');
            default:
                return response()->json(['ok' => false, 'errorType' => 'TYPE_NOT_SUPPORTED', 'data' => '']);
        }
    }

    public static function getResourcesByType($platform_id, $context_id, $session_id, $type)
    {
        $resourcesGetRequest = SakaiController::getResources($platform_id, $context_id, $session_id, $type);
        $successfulRequest = SakaiController::requestChecker($resourcesGetRequest);

        $resourcesStatusCode = $resourcesGetRequest['data']['status_code'];

        if ($successfulRequest == true) {
            return ['ok' => true, 'data' => ['items' => $resourcesGetRequest['data']['resources'], 'status_code' => $resourcesStatusCode]];
        } else {
            return ['ok' => false, 'data' => ['items' => [], 'status_code' => $resourcesStatusCode]];
        }
    }

    // Función que devuelve los foros de un curso de Sakai
    public static function getForums($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        $forums = [];
        if ($statusCode == 200) {
            $dataForums = json_decode($request['requestBody']);
            foreach ($dataForums->forums_collection as $forum) {
                $forums[] = array(
                    'id' => $forum->entityId,
                    'name' => $forum->title
                );
            }
            return ['ok' => true, 'data' => ['forums' => $forums, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function getForumById($url_lms, $context_id, $session_id, $forumId)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $dataForums = json_decode($request['requestBody']);
            foreach ($dataForums->forums_collection as $forum) {
                if ($forum->id == $forumId) {
                    return ['ok' => true, 'data' => ['forum_founded' => $forum, 'status_code' => $statusCode]];
                }
            }
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    // Función que devuelve las tareas de un curso de Sakai
    public static function getAssignments($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];
        $assignments = [];
        if ($statusCode == 200) {
            $dataAssignments = json_decode($request['requestBody']);
            foreach ($dataAssignments->assignment_collection as $assignment) {
                $assignments[] = array(
                    'id' => $assignment->entityId,
                    'name' => $assignment->entityTitle
                );
            }
            return ['ok' => true, 'data' => ['assignments' => $assignments, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function getAssignmentById($url_lms, $context_id, $session_id, $assignmentId)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $dataAssignments = json_decode($request['requestBody']);
            foreach ($dataAssignments->assignment_collection as $assignment) {
                if ($assignment->id == $assignmentId) {
                    return ['ok' => true, 'data' => ['assignment_founded' => $assignment, 'status_code' => $statusCode]];
                }
            }
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    // Función que devuelve los recursos de un curso de Sakai dependiendo de su tipo
    public static function getResources($url_lms, $context_id, $session_id, $type)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/content/resources/group/' . $context_id . '.json?depth=3', $session_id);
        $statusCode = $request['statusCode'];

        $resources = [];

        if ($statusCode == 200) {
            $dataContents = json_decode($request['requestBody']);
            function decode_unicode($str)
            {
                $str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                }, $str);
                return $str;
            }


            function process_resource($resource, &$resources, $type)
            {
                $id = decode_unicode(str_replace('\/', '/', $resource->resourceId));
                if ($resource->mimeType == null && $type == 'folder') {
                    array_push($resources, [
                        'id' => htmlspecialchars($id),
                        'name' => htmlspecialchars($resource->name)
                    ]);
                } elseif (is_string($type) && $type !== '' && strpos($resource->mimeType, $type) == true) {
                    array_push($resources, [
                        'id' => htmlspecialchars($id),
                        'name' => htmlspecialchars($resource->name)
                    ]);
                } elseif (
                    is_string($type) &&
                    $type == "resource" &&
                    $resource->mimeType !== null &&
                    (strpos($resource->mimeType, 'url') == false) &&
                    (strpos($resource->mimeType, 'html') == false) &&
                    (strpos($resource->mimeType, 'plain') === false)
                ) {
                    array_push($resources, [
                        'id' => htmlspecialchars($id),
                        'name' => htmlspecialchars($resource->name)
                    ]);
                }

                if (isset($resource->resourceChildren) && count($resource->resourceChildren) >= 1) {
                    foreach ($resource->resourceChildren as $child) {
                        process_resource($child, $resources, $type);
                    }
                }
            }

            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                process_resource($resource, $resources, $type);
            }

            return ['ok' => true, 'data' => ['resources' => $resources, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['resources' => $resources, 'status_code' => $statusCode]];
        }
    }

    public static function getResourceById($url_lms, $context_id, $session_id, $resourceId)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/resources', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $modules = json_decode($request['requestBody']);
            foreach ($modules as $resource) {
                if ($resource->id == $resourceId) {
                    return ['ok' => true, 'data' => ['resource_founded' => $resource, 'status_code' => $statusCode]];
                }
            }
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function getUserMembers($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/site/' . $context_id . '/memberships.json', $session_id);

        $statusCode = $request['statusCode'];

        $users = [];
        if ($statusCode == 200) {
            $dataUsers = json_decode($request['requestBody']);
            foreach ($dataUsers->membership_collection as $user) {
                $users[] = array(
                    'id' => $user->userId,
                    'name' => $user->userDisplayName
                );
            }

            return ['ok' => true, 'data' => ['users' => $users, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }
    public static function getGroups($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/direct/site/' . $context_id . '/groups.json', $session_id);

        $dataGroups = json_decode($request['requestBody']);
        $statusCode = $request['statusCode'];

        $groups = [];
        if ($statusCode == 200) {
            foreach ($dataGroups as $group) {
                $groups[] = array(
                    'id' => $group->reference,
                    'name' => $group->title
                );
            }
            return ['ok' => true, 'data' => ['groups' => $groups, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function getModules($url_lms, $site_id, $lesson_id, $session_id, $context_id)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($url_lms.'/direct/lessons/lesson/'.$context_id.'.json');
        $lessonGetRequest = SakaiController::createClient($url_lms, $url_lms . '/direct/lessons/lesson/' . $lesson_id . '.json', $session_id);
        $modulesRequestStatus = $lessonGetRequest['statusCode'];

        $modules = [];
        $section = 0;
        $column = 0;
        $order = 1;

        if ($modulesRequestStatus == 200) {
            $modulesData = json_decode($lessonGetRequest['requestBody']);
            if ($modulesData->contentsList != null && count($modulesData->contentsList) >= 1) {
                foreach ($modulesData->contentsList as $index => $module) {
                    $modulesData->contentsList[$index]->type = SakaiController::changeIdNameType($module);
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
                    } else if ($modulesData->contentsList[$index]->type != 'break') {
                        if($modulesData->contentsList[$index]->type == "folder"){
                            $modulesData->contentsList[$index]->dataDirectory = str_replace("//", "/", $modulesData->contentsList[$index]->dataDirectory);
                        }
                        $sakaiId = SakaiController::nodeSakaiIdUpdater($modulesData->contentsList[$index]);
                        // $modulesData->contentsList[$index]->section = $section;
                        $itemFounded = SakaiController::getLessonItemById($modulesData->contentsList[$index], $url_lms, $context_id, $session_id, $sakaiId);
                        
                        if (isset($itemFounded)) {
                            $moduleAlreadyExists = false;
                            foreach ($modules as $existingModule) {
                                if ($existingModule["sakaiId"] === $sakaiId) {
                                    $moduleAlreadyExists = true;
                                    break;
                                }
                            }

                            if (!$moduleAlreadyExists) {
                                $module = [
                                    "id" => $modulesData->contentsList[$index]->id,
                                    "sakaiId" => $sakaiId,
                                    "name" => $modulesData->contentsList[$index]->name,
                                    "modname" => $modulesData->contentsList[$index]->type,
                                    "pageId" => $modulesData->contentsList[$index]->pageId,
                                    "section" => $section,
                                    "indent" => $column,
                                    "order" => $order++,
                                ];

                                switch ($modulesData->contentsList[$index]->type) {
                                    case 'exam':
                                        if (isset($itemFounded)) {
                                            if (isset($itemFounded->openDate)) {
                                                $openDate = date('Y-m-d\TH:i', $itemFounded->openDate);
                                                $module['openDate'] = $openDate;
                                            }

                                            if (isset($itemFounded->dueDate)) {
                                                $dueDate = date('Y-m-d\TH:i', $itemFounded->dueDate);
                                                $module['dueDate'] = $dueDate;
                                            }

                                            if (isset($itemFounded->closeDate)) {
                                                $closeDate = date('Y-m-d\TH:i', $itemFounded->closeDate);
                                                $module['closeDate'] = $closeDate;
                                            }

                                            if (
                                                isset($itemFounded->timeExceptions) &&
                                                is_array($itemFounded->timeExceptions) && count($itemFounded->timeExceptions) > 0
                                            ) {
                                                $module['timeExceptions'] = [];
                                                foreach ($itemFounded->timeExceptions as $exception) {
                                                    if (isset($exception->forEntityRef)) {
                                                        $exceptionData = [];

                                                        $exceptionData['forEntityRef'] = $exception->forEntityRef;

                                                        if (isset($exception->openDate)) {
                                                            $exceptionData['openDate'] = date('Y-m-d\TH:i', $exception->openDate);
                                                        }

                                                        if (isset($exception->dueDate)) {
                                                            $exceptionData['dueDate'] = date('Y-m-d\TH:i', $exception->dueDate);
                                                        }

                                                        if (isset($exception->closeDate)) {
                                                            $exceptionData['closeDate'] = date('Y-m-d\TH:i', $exception->closeDate);
                                                        }

                                                        $module['timeExceptions'][] = $exceptionData;
                                                    }
                                                }
                                            }

                                            if (
                                                isset($itemFounded->groupRefs) &&
                                                is_array($itemFounded->groupRefs) && count($itemFounded->groupRefs) > 0
                                            ) {
                                                $module['groups'] = $itemFounded->groupRefs;
                                            }
                                        }

                                        break;
                                    case 'assign':
                                        if (isset($itemFounded) && isset($itemFounded->openTime) && isset($itemFounded->openTime->epochSecond)) {
                                            $openDate = date('Y-m-d\TH:i', $itemFounded->openTime->epochSecond);
                                            $module['openDate'] = $openDate;
                                        }

                                        if (isset($itemFounded) && isset($itemFounded->dueTime) && isset($itemFounded->dueTime->epochSecond)) {
                                            $dueDate = date('Y-m-d\TH:i', $itemFounded->dueTime->epochSecond);
                                            $module['dueDate'] = $dueDate;
                                        }

                                        if (isset($itemFounded) && isset($itemFounded->closeTime) && isset($itemFounded->closeTime->epochSecond)) {
                                            $closeDate = date('Y-m-d\TH:i', $itemFounded->closeTime->epochSecond);
                                            $module['closeDate'] = $closeDate;
                                        }

                                        if (
                                            isset($itemFounded->groups) &&
                                            is_array($itemFounded->groups) && count($itemFounded->groups) > 0
                                        ) {
                                            $module['groups'] = $itemFounded->groups;
                                        }

                                        break;
                                    case 'forum':
                                        if (isset($itemFounded) && isset($itemFounded->openDate)) {
                                            $openDate = date('Y-m-d\TH:i', $itemFounded->openDate);
                                            $module['openDate'] = $openDate;
                                        }

                                        if (isset($itemFounded) && isset($itemFounded->closeDate)) {
                                            $closeDate = date('Y-m-d\TH:i', $itemFounded->closeDate);
                                            $module['dueDate'] = $closeDate;
                                        }

                                        break;
                                    case 'folder':
                                        $module['name'] = $itemFounded->title;

                                        if (isset($itemFounded) && isset($itemFounded->openDate)) {
                                            $openDate = date('Y-m-d\TH:i', $itemFounded->openDate);
                                            $module['openDate'] = $openDate;
                                        }

                                        if (isset($itemFounded) && isset($itemFounded->closeDate)) {
                                            $closeDate = date('Y-m-d\TH:i', $itemFounded->closeDate);
                                            $module['dueDate'] = $closeDate;
                                        }
                                        break;
                                    case 'text':
                                    case 'url':
                                    case 'html':
                                    case 'resource':
                                        if (isset($itemFounded) && isset($itemFounded->openDate)) {
                                            $openDate = date('Y-m-d\TH:i', $itemFounded->openDate);
                                            $module['openDate'] = $openDate;
                                        }

                                        if (isset($itemFounded) && isset($itemFounded->closeDate)) {
                                            $closeDate = date('Y-m-d\TH:i', $itemFounded->closeDate);
                                            $module['dueDate'] = $closeDate;
                                        }

                                        if (
                                            isset($itemFounded->groupRefs) &&
                                            is_array($itemFounded->groupRefs) && count($itemFounded->groupRefs) > 0
                                        ) {
                                            $module['groups'] = $itemFounded->groupRefs;
                                        }
                                        break;
                                    default:
                                        array_push(
                                            $modules,
                                            $module
                                        );
                                        break;
                                }

                                $updatedModuleWithDates = SakaiController::parseItemDates($module);
                                $updatedModuleWithExceptionDates = SakaiController::parseItemExceptionDates($updatedModuleWithDates);

                                array_push(
                                    $modules,
                                    $updatedModuleWithExceptionDates
                                );
                            }
                        }
                    }
                }

                $conditionGetRequest = SakaiController::createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/conditions', $session_id);

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

    public static function nodeSakaiIdUpdater($item)
    {
        if (isset($item) && isset($item->type)) {
            switch ($item->type) {
                case "exam":
                case "assign":
                case "forum":
                    return SakaiController::parseSakaiId($item);
                case "folder":
                    return $item->dataDirectory;
                case "html":
                case "text":
                case "url":
                case "resource":
                    return $item->sakaiId;
                default:
                    return null;
            }
        }
    }

    public static function getLessonItemById($item, $url_lms, $context_id, $session_id, $sakaiId)
    {
        if (isset($item) && isset($item->type)) {
            switch ($item->type) {
                case "exam":
                    $examFounded = SakaiController::getAssesmentById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulExamRequest = SakaiController::requestChecker($examFounded);

                    if ($successfulExamRequest == true) {
                        return $examFounded['data']['assesment_founded'];
                    } else {
                        return null;
                    }
                case "assign":
                    $assignmentFounded = SakaiController::getAssignmentById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulAssignmentRequest = SakaiController::requestChecker($assignmentFounded);

                    if ($successfulAssignmentRequest == true) {
                        return $assignmentFounded['data']['assignment_founded'];
                    } else {
                        return null;
                    }
                case "forum":
                    $forumFounded = SakaiController::getForumById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulForumRequest = SakaiController::requestChecker($forumFounded);

                    if ($successfulForumRequest == true) {
                        return $forumFounded['data']['forum_founded'];
                    } else {
                        return null;
                    }
                case 'folder':
                case "resource":
                    $resourceFounded = SakaiController::getResourceById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulResourceRequest = SakaiController::requestChecker($resourceFounded);

                    if ($successfulResourceRequest == true) {
                        return $resourceFounded['data']['resource_founded'];
                    } else {
                        return null;
                    }
                default:
                    return ['type' => 'generic'];
            }
        } else {
            return null;
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

    public static function parseItemExceptionDates($module)
    {
        if ($module['modname'] == "exam") {
            if (
                isset($module['timeExceptions']) &&
                is_array($module['timeExceptions']) && count($module['timeExceptions']) > 0
            ) {
                foreach ($module['timeExceptions'] as &$exception) {
                    if (!isset($exception['openDate'])) {
                        $newOpenDate = date('Y-m-d\TH:i', time());
                        $exception['openDate'] = $newOpenDate;
                    }

                    if (isset($exception['openDate']) && !isset($exception['dueDate'])) {
                        $timestamp = strtotime($exception['openDate']);
                        $oneWeekLater = strtotime('+1 week', $timestamp);

                        $newDueDate = date('Y-m-d\TH:i', $oneWeekLater);
                        $exception['dueDate'] = $newDueDate;
                    }

                    if (isset($exception['dueDate']) && !isset($exception['closeDate'])) {
                        $timestamp = strtotime($exception['dueDate']);
                        $oneWeekLater = strtotime('+1 week', $timestamp);

                        $newCloseDate = date('Y-m-d\TH:i', $oneWeekLater);
                        $exception['closeDate'] = $newCloseDate;
                    }
                }
            }
        }

        unset($exception);

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

    public static function changeIdNameType($module)
    {
        switch ($module->type) {
            case 1:
                if (isset($module->contentType)) {
                    if (str_contains($module->contentType, "plain")) {
                        return "text";
                    } elseif (str_contains($module->contentType, "url")) {
                        return "url";
                    } elseif (str_contains($module->contentType, "html")) {
                        return "html";
                    } else {
                        return "resource";
                    }
                } else {
                    return "resource";
                }
            case 3:
                return 'assign';
            case 4:
                return 'exam';
            case 5:
                return 'text';
            case 6:
                return 'url';
            case 8:
                return 'forum';
            case 14:
                return 'break';
            case 2:
            case 20:
                return 'folder';
            default:
                return 'generic';
        }
    }
    public static function getAssesments($url_lms, $context_id, $session_id)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

        $statusCode = $request['statusCode'];

        $assesments = [];
        if ($statusCode == 200) {
            $modules = json_decode($request['requestBody']);
            foreach ($modules as $assesment) {
                $assesments[] = array(
                    'id' => $assesment->id,
                    'name' => $assesment->title
                );
            }
            return ['ok' => true, 'data' => ['assesments' => $assesments, 'status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function getAssesmentById($url_lms, $context_id, $session_id, $assesmentId)
    {
        $request = SakaiController::createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $modules = json_decode($request['requestBody']);
            foreach ($modules as $assesment) {
                if ($assesment->id == $assesmentId) {
                    return ['ok' => true, 'data' => ['assesment_founded' => $assesment, 'status_code' => $statusCode]];
                }
            }
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        } else {
            return ['ok' => false, 'data' => ['status_code' => $statusCode]];
        }
    }

    public static function createClient($lms_url, $url, $session_id, $type = 'GET', $bodyData = [])
    {
        $lmsInstance = LtiController::getLmsToken($lms_url, "sakai", true);
        if (
            isset($lmsInstance) && isset($lmsInstance['ok']) && $lmsInstance['ok'] == true
        ) {
            if (isset($lmsInstance['data']) && isset($lmsInstance['data']['cookieName'])) {
                $cookieName = $lmsInstance['data']['cookieName'];
            } else {
                $cookieName = "JSESSIONID";
            }
        } else {
            return ['requestBody' => [], 'statusCode' => 400];
        }

        $client = new Client();
        $options = [
            'headers' => [
                'Cookie' => $cookieName . '=' . $session_id
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

            case "PATCH":
            case "POST":
                // Convert the $bodyData array to JSON
                $options['json'] = $bodyData;
                $options['headers']['Content-Type'] = 'application/json';
                if ($type == "POST") {
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

    // Saves the user's session in the database and redirects to the front end.
    public static function storeVersion($saveData)
    {
        try {
            $course = Course::where('instance_id', $saveData['instance_id'])
                ->where('course_id', $saveData['course_id'])
                ->select('id')
                ->first();
            $mapData = $saveData['map'];

            $map = Map::updateOrCreate(
                ['created_id' => $mapData['id'], 'course_id' => $course->id, 'user_id' => $saveData['user_id']],
                ['name' => $mapData['name']]
            );

            $versionData = $mapData['versions'];
            Version::updateOrCreate(
                ['map_id' => $map->id, 'name' => $versionData['name']],
                ['default' => boolval($versionData['default']), 'blocks_data' => json_encode($versionData['blocksData'])]
            );
            return response()->json(['ok' => true, 'errorType' => '', 'data' => []]);
        } catch (\Exception $e) {
            error_log($e);
            abort(500, $e->getMessage());
            return response()->json(['ok' => false, 'errorType' => 'ERROR_SAVING_VERSION']);
        }
    }

    public static function exportVersion(Request $request, $sessionData)
    {
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
            $lessonCopyRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/direct/lessons/lesson/' . $request->lessonId . '.json', $sessionData->session_id);
            $lessonCopy = json_decode($lessonCopyRequest['requestBody']);
            $lessonStatusCode = $lessonCopyRequest['statusCode'];

            $conditionsCopyRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions', $sessionData->session_id);
            $conditionsCopy = $conditionsCopyRequest['requestBody'];
            $conditionsStatusCode = $conditionsCopyRequest['statusCode'];

            if ($lessonStatusCode == 200 && $conditionsStatusCode == 200) {
                if (count($nodesToUpdate) >= 1) {
                    $nodesUpdateRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/entities', $sessionData->session_id, 'PATCH', $nodesToUpdate);
                    $nodesUpdateStatusCode = $nodesUpdateRequest['statusCode'];

                    if ($nodesUpdateStatusCode !== 200) {
                        return response()->json(['ok' => false, 'errorType' => 'NODE_UPDATE_ERROR', 'data' => '']);
                    }
                }

                $conditionsDelete = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/conditions', $sessionData->session_id, 'DELETE');
                $conditionsDeleteStatusCode = $conditionsDelete;

                $lessonItemsDelete = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items', $sessionData->session_id, 'DELETE');
                $lessonItemsDeleteStatusCode = $lessonItemsDelete;

                if ($conditionsDeleteStatusCode === 200 && $lessonItemsDeleteStatusCode === 200) {
                    $nodesCreationRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $nodes);
                    $nodesCreated = $nodesCreationRequest['requestBody'];
                    $nodesCreationStatusCode = $nodesCreationRequest['statusCode'];

                    if ($nodesCreationStatusCode == 200) {
                        if (($conditionList != null && count($conditionList) >= 1)) {
                            $filteredArray = SakaiController::conditionIdParse(json_decode($nodesCreated), $conditionList);

                            $conditionsParsedList = (array_values($filteredArray));
                            $conditionsCreationRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsParsedList);
                            $conditionsCreationStatusCode = $conditionsCreationRequest['statusCode'];

                            if ($conditionsCreationStatusCode == 200) {
                                return response()->json(['ok' => true, 'successType' => 'SUCCESSFUL_EXPORT']);
                            } else {
                                return response()->json(['ok' => true, 'successType' => 'SUCCESSFUL_EXPORT_WITHOUT_CONDITIONS']);
                            }
                        } else {
                            return response()->json(['ok' => true, 'successType' => 'SUCCESSFUL_EXPORT_WITHOUT_CONDITIONS']);
                        }
                    } else {
                        $parsedNodes = SakaiController::parseSakaiLessonCopy($lessonCopy->contentsList, false);
                        $parsedNodesWithId = SakaiController::parseSakaiLessonCopy($lessonCopy->contentsList, true);
                        $nodesCopyCreationRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $parsedNodes);
                        $nodesCopyCreationStatusCode = $nodesCopyCreationRequest['statusCode'];
                        if ($nodesCopyCreationStatusCode == 200) {
                            $nodesCopyCreation = ($nodesCopyCreationRequest['requestBody']);
                            $parsedConditions = SakaiController::conditionItemIdAdder(($parsedNodesWithId), json_decode($conditionsCopy));
                            $parsedConditionsJson = json_decode(json_encode($parsedConditions), true);
                            $filteredArray = SakaiController::conditionIdParse(json_decode($nodesCopyCreation), $parsedConditionsJson);
                            $conditionsCopyParsedList = (array_values($filteredArray));

                            $conditionsCopyCreationRequest = SakaiController::createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsCopyParsedList);
                            $conditionsCopyCreationStatusCode = $conditionsCopyCreationRequest['statusCode'];

                            if ($conditionsCopyCreationStatusCode == 200) {
                                return response()->json(['ok' => false, 'errorType' => 'LESSON_ITEMS_CREATION_ERROR']);
                            } else {
                                return response()->json(['ok' => false, 'errorType' => 'LESSON_ITEMS_WITHOUT_CONDITIONS_CREATION_ERROR']);
                            }
                        } else {
                            return response()->json(['ok' => false, 'errorType' => 'FATAL_ERROR']);
                        }
                    }
                } else {
                    return response()->json(['ok' => false, 'errorType' => 'LESSON_DELETE_ERROR']);
                }
            } else {
                return response()->json(['ok' => false, 'errorType' => 'LESSON_COPY_ERROR']);
            }
        } else {
            return response()->json(['ok' => false, 'errorType' => 'PAGE_EXPORT_ERROR']);
        }
    }

    public static function conditionItemIdAdder($nodes, $conditionList)
    {
        $nodesIdList = [];
        foreach ($nodes as $node) {
            $node = json_decode(json_encode($node));
            if (isset($node->type) && $node->type != 14) {
                $nodeJson = json_encode(['id' => $node->id, 'contentRef' => $node->contentRef]);
                array_push($nodesIdList, $nodeJson);
            }
        }


        $filteredConditions = array_filter($conditionList, function ($condition) use ($nodesIdList) {
            foreach ($nodesIdList as $idObject) {
                $idObject = json_decode($idObject, true);
                if (isset($condition->itemId) && $condition->itemId == $idObject['id']) {
                    return true;
                }
            }
            return false;
        });

        foreach ($filteredConditions as &$root) {
            foreach ($nodes as $node) {
                if (isset($root->itemId) && $root->itemId == $node['id']) {
                    $root->itemId = $node['contentRef'];
                }
            }

            if (isset($root->subConditions)) {
                foreach ($root->subConditions as &$parent) {
                    unset($parent->id);
                    if (isset($parent->subConditions) && count($parent->subConditions) >= 1) {
                        foreach ($parent->subConditions as &$childCondition) {
                            foreach ($nodes as $node) {
                                if ($childCondition->itemId == $node['id']) {
                                    $childCondition->itemId = ($node['contentRef']);
                                    unset($childCondition->id);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        unset($condition, $parent, $childCondition);

        return $filteredConditions;
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
                if (isset($condition['itemId']) && $condition['itemId'] == $idObject['contentRef'] && isset($condition['type']) && $condition['type'] == 'ROOT') {
                    return true;
                }
            }
            return false;
        });

        if (isset($filteredArray) && $filteredArray != null) {
            foreach ($filteredArray as &$root) {
                foreach ($nodesIdList as $idObject) {
                    $idObject = json_decode($idObject, true);
                    if ($root['itemId'] == $idObject['contentRef']) {
                        $root['itemId'] = ($idObject['id']);
                        unset($root['id']);
                        if(array_key_exists('argument', $root)){
                            unset($root['argument']);
                        }
                        if(isset($root['hasParent'])){
                            unset($root['hasParent']);
                        }
                        break;
                    }
                }

                if (isset($root['subConditions'])) {
                    foreach ($root['subConditions'] as &$parent) {
                        unset($parent['id']);
                        if(isset($parent['hasParent'])){
                            unset($parent['hasParent']);
                        }
                        if(array_key_exists('argument', $parent)){
                            unset($parent['argument']);
                        }
                        if(array_key_exists('itemId', $parent)){
                            unset($parent['itemId']);
                        }
                        if (isset($parent['subConditions']) && count($parent['subConditions']) >= 1) {
                            foreach ($parent['subConditions'] as &$childCondition) {
                                foreach ($nodesIdList as $idObject) {
                                    $idObject = json_decode($idObject, true);
                                    if ($childCondition['itemId'] == $idObject['contentRef']) {
                                        $childCondition['itemId'] = ($idObject['id']);
                                        unset($childCondition['id']);
                                        unset($childCondition['subConditions']);
                                        if(isset($childCondition['hasParent'])){
                                            unset($childCondition['hasParent']);
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            unset($root, $parent, $childCondition);
        }

        return $filteredArray;
    }

    public static function parseSakaiLessonCopy($contentsList, $idAdder)
    {
        $parsedSakaiLessonCopy = [];
        if (isset($contentsList) && $contentsList != null) {
            foreach ($contentsList as $content) {
                $parsedContent = [];
                if ($idAdder == true) {
                    $parsedContent['id'] = $content->id;
                }
                $parsedContent['title'] = $content->name;
                $parsedContent['pageId'] = $content->pageId;
                $parsedContent['type'] = $content->type;

                if ($content->type == 14) {
                    $parsedContent['format'] = $content->format;
                } else {
                    $parsedContent['contentRef'] = $content->sakaiId;
                }

                array_push($parsedSakaiLessonCopy, $parsedContent);
            }
        }
        return $parsedSakaiLessonCopy;
    }

    public static function requestChecker($getRequest)
    {
        if (
            isset($getRequest) && isset($getRequest['ok']) && $getRequest['ok'] == true && isset($getRequest['data']) &&
            isset($getRequest['data']['status_code']) && $getRequest['data']['status_code'] == 200
        ) {
            return true;
        } else {
            return false;
        }
    }
}