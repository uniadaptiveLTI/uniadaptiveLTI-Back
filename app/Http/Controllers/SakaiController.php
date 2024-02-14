<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SakaiController extends Controller
{
    /**
     * @param string $platform
     * @param string $url_lms
     * 
     * @return mixed
     */
    public function getinstance(string $platform, string $url_lms)
    {

        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' => $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        };
        return $dataInstance->id;
    }

    /**
     * @param string $course_id
     * @param string $platform
     * @param string $url_lms
     * 
     * @return array
     */
    public function getCourse(string $course_id, string $platform, string $url_lms)
    {
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' => $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        };
        $dataCourse = Course::firstOrCreate(
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id],
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id, 'timestamps' => now()]
        );
        while (is_null($dataCourse->id)) {
            sleep(1);
        };
        $dataMaps = Map::select('id', 'created_id', 'course_id', 'name', 'updated_at')
            ->where('course_id', $dataCourse->id)
            ->get();
        $maps = [];
        foreach ($dataMaps as $map) {
            array_push($maps, [
                'id' => $map->created_id,
                'course_id' => $map->course_id,
                'name' => $map->name,
            ]);
        }
        $course = [
            'maps' => $maps,
        ];
        return $course;
    }
    /**
     * Function that returns user and course data
     * 
     * @param object $lastInserted
     * 
     * @return mixed
     */
    //FIXME: this function can be more efficient
    public function getSession(object $lastInserted)
    {

        $lessonGetRequest = app(SakaiController::class)->getLessons($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        if (!$lessonGetRequest['ok']) {
            return response()->json($lessonGetRequest, $lessonGetRequest['data']['error_code']);
        }
        $lessons = $lessonGetRequest['data']['lessons'];
        $userMembersGetRequest = app(SakaiController::class)->getUserMembers($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        if (!$userMembersGetRequest['ok']) {
            return response()->json($userMembersGetRequest, $userMembersGetRequest['data']['error_code']);
        }
        $userMembers = $userMembersGetRequest['data']['users'];

        $groupsGetRequest = app(SakaiController::class)->getGroups($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        if (!$groupsGetRequest['ok']) {
            return response()->json($groupsGetRequest, $groupsGetRequest['data']['error_code']);
        }
        $groups = $groupsGetRequest['data']['groups'];

        $data = [
            [
                'user_id' => app(SakaiController::class)->getId($lastInserted->user_id),
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => app(SakaiController::class)->getUrl($lastInserted->platform_id, $lastInserted->context_id, app(SakaiController::class)->getId($lastInserted->user_id)),
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                'instance_id' => app(SakaiController::class)->getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'lessons' => $lessons,
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'user_members' => $userMembers,
                'sakai_groups' => $groups
            ],
            app(SakaiController::class)->getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        return response()->json(app(LtiController::class)->response($data));
    }



    /**
     * @param string $url_lms
     * @param string $sakaiServerId
     * @param array $data
     * 
     * @return array
     */
    public function createSession(string $url_lms, string $sakaiServerId, array $data)
    {
        $client = new Client();
        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . $data['user'] . '&pw=' . $data['password']);
        $content = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();

        if (isset($statusCode) && $statusCode == 200) {
            $user_id = $content . '.' . $sakaiServerId;
            $data = ['user_id' => $user_id, 'status_code' => $statusCode];
            return app(LtiController::class)->response($data);
        } else {
            return app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR');
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getLessons(string $url_lms, string $context_id, string $session_id)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/lessons/site/' . $context_id . '.json', $session_id);

        $lessons = [];
        // dd($request);
        if ($request['ok']) {
            $dates = $request['data']['requestBody'];
            foreach ($dates->lessons_collection as $lesson) {

                $pageIdGetRequest = app(SakaiController::class)->getPageIdLesson($url_lms, $lesson->id, $session_id);
                if (!$pageIdGetRequest['ok']) {

                    return app(LtiController::class)->errorResponse(null, $pageIdGetRequest['data']['error'], $pageIdGetRequest['data']['error_code']);
                } else {
                    $lessons[] = [
                        'id' => $lesson->id,
                        'name' => $lesson->lessonTitle,
                        'page_id' => $pageIdGetRequest['data']['page_founded']
                    ];
                }
            }
            $data = ['lessons' => $lessons, 'status_code' => $request['data']['statusCode']];

            return app(LtiController::class)->response($data);
        } else {
            return app(LtiController::class)->errorResponse(null, $request['data']['error'], $request['data']['error_code']);
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getPageIdLesson(string $url_lms, string $context_id, string $session_id)
    {

        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/lessons/lesson/' . $context_id . '.json', $session_id);
        $dates = $request['data']['requestBody'];

        if ($request['ok']) {
            $data = ['page_founded' => $dates->sakaiId, 'status_code' => $request['data']['statusCode']];
            return app(LtiController::class)->response($data);
        } else {
            return app(LtiController::class)->errorResponse(null, $request['data']['error'], $request['data']['error_code']);
        }
    }

    /**
     * Function that returns the modules of a specific type of a course
     * 
     * @param Request $request
     * @param object $sessionData
     * 
     * @return mixed
     */
    public function getModulesByType(Request $request, object $sessionData)
    {

        switch ($request->type) {
            case 'forum':
                $forumsGetRequest = app(SakaiController::class)->getForums($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulForumsRequest = app(SakaiController::class)->requestChecker($forumsGetRequest);

                $forumsStatusCode = $forumsGetRequest['data']['status_code'];

                if ($successfulForumsRequest == true) {
                    $data = ['items' => $forumsGetRequest['data']['forums'], 'status_code' => $forumsStatusCode];
                    return app(LtiController::class)->response($data);
                } else {
                    return app(LtiController::class)->errorResponse(null, 'FORUM_ERROR');
                }
            case 'exam':
                $examsGetRequest = app(SakaiController::class)->getAssesments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulExamsRequest = app(SakaiController::class)->requestChecker($examsGetRequest);

                $examsStatusCode = $examsGetRequest['data']['status_code'];

                if ($successfulExamsRequest == true) {
                    $data = ['items' => $examsGetRequest['data']['assesments'], 'status_code' => $examsStatusCode];
                    return response()->json(app(LtiController::class)->response($data));
                } else {
                    return app(LtiController::class)->errorResponse(null, 'EXAM_ERROR', $examsStatusCode);
                }
            case 'assign':
                $assignmentsGetRequest = app(SakaiController::class)->getAssignments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                $successfulAssignmentsRequest = app(SakaiController::class)->requestChecker($assignmentsGetRequest);

                $assignmentsStatusCode = $assignmentsGetRequest['data']['status_code'];

                if ($successfulAssignmentsRequest == true) {
                    $data = ['items' => $assignmentsGetRequest['data']['assignments'], 'status_code' => $assignmentsStatusCode];
                    return response()->json(app(LtiController::class)->response($data));
                } else {
                    return response()->json(app(LtiController::class)->errorResponse(null, 'ASSIGN_ERROR'), $assignmentsStatusCode);
                }
            case 'text':
                return app(SakaiController::class)->getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'plain');
            case 'url':
                return app(SakaiController::class)->getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'url');
            case 'html':
                return app(SakaiController::class)->getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'html');
            case 'folder':
                return app(SakaiController::class)->getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'folder');
            case 'resource':
                return app(SakaiController::class)->getResourcesByType($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'resource');
            default:
                return response()->json(app(LtiController::class)->errorResponse(null, 'TYPE_NOT_SUPPORTED'), 500);
        }
    }

    /**
     * @param string $platform_id
     * @param string $context_id
     * @param string $session_id
     * @param string $type
     * 
     * @return array
     */
    public function getResourcesByType(string $platform_id, string $context_id, string $session_id, string $type)
    {

        $resourcesGetRequest = app(SakaiController::class)->getResources($platform_id, $context_id, $session_id, $type);
        $successfulRequest = app(SakaiController::class)->requestChecker($resourcesGetRequest);

        $resourcesStatusCode = $resourcesGetRequest['data']['status_code'];

        if ($successfulRequest == true) {
            $data = ['items' => $resourcesGetRequest['data']['resources'], 'status_code' => $resourcesStatusCode];
            return response()->json(app(LtiController::class)->response($data));
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $resourcesStatusCode);
        }
    }

    /**
     * Function that returns the forums of a Sakai course
     * 
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getForums(string $url_lms, string $context_id, string $session_id)
    {

        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

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
            $data = ['forums' => $forums, 'status_code' => $statusCode];
            return response()->json(app(LtiController::class)->response($data));
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $forumId
     * 
     * @return array
     */
    public function getForumById(string $url_lms, string $context_id, string $session_id, string $forumId)
    {

        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $dataForums = json_decode($request['requestBody']);
            foreach ($dataForums->forums_collection as $forum) {
                if ($forum->id == $forumId) {
                    $data = ['forum_founded' => $forum, 'status_code' => $statusCode];
                    return response()->json(app(LtiController::class)->response($data));
                }
            }
        }
        return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
    }
    /**
     * Function that returns the assignments of a Sakai course
     * 
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getAssignments(string $url_lms, string $context_id, string $session_id)
    {

        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

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
            $data = ['assignments' => $assignments, 'status_code' => $statusCode];
            return response()->json(app(LtiController::class)->response($data));
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $assignmentId
     * 
     * @return array
     */
    public function getAssignmentById(string $url_lms, string $context_id, string $session_id, string $assignmentId)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $dataAssignments = json_decode($request['requestBody']);
            foreach ($dataAssignments->assignment_collection as $assignment) {
                if ($assignment->id == $assignmentId) {
                    $data = ['assignment_founded' => $assignment, 'status_code' => $statusCode];
                    return response()->json(app(LtiController::class)->response($data));
                }
            }
        }
        return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
    }

    /**
     * Function that returns the resources of a Sakai course depending on its type
     * 
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $type
     * 
     * @return mixed
     */
    public function getResources(string $url_lms, string $context_id, string $session_id, string $type)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/content/resources/group/' . $context_id . '.json?depth=3', $session_id);
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
            $data = ['resources' => $resources, 'status_code' => $statusCode];
            return response()->json(app(LtiController::class)->response($data));
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $resourceId
     * 
     * @return array
     */
    public function getResourceById(string $url_lms, string $context_id, string $session_id, string $resourceId)
    {

        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/resources', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $modules = json_decode($request['requestBody']);
            foreach ($modules as $resource) {
                if ($resource->id == $resourceId) {
                    $data = ['resource_founded' => $resource, 'status_code' => $statusCode];
                    return response()->json(app(LtiController::class)->response($data));
                }
            }
        }
        return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getUserMembers(string $url_lms, string $context_id, string $session_id)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/site/' . $context_id . '/memberships.json', $session_id);

        $users = [];

        if ($request['ok']) {
            $dates = $request['data']['requestBody'];
            foreach ($dates->membership_collection as $user) {
                $users[] = [
                    'id' => $user->userId,
                    'name' => $user->userDisplayName
                ];
            }
            $data = ['users' => $users, 'status_code' => $request['data']['statusCode']];

            return app(LtiController::class)->response($data);
        } else {
            return app(LtiController::class)->errorResponse(null, $request['data']['error'], $request['data']['error_code']);
        }
    }
    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getGroups(string $url_lms, string $context_id, string $session_id)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/site/' . $context_id . '/groups.json', $session_id);
        $groups = [];

        if ($request['ok']) {
            $dates = $request['data']['requestBody'];
            foreach ($dates as $group) {
                $groups[] = [
                    'id' => $group->reference,
                    'name' => $group->title
                ];
            }
            $data = ['groups' => $groups, 'status_code' => $request['data']['statusCode']];
            return app(LtiController::class)->response($data);
        } else {
            return app(LtiController::class)->errorResponse(null, $request['data']['error'], $request['data']['error_code']);
        }
    }

    /**
     * @param string $url_lms
     * @param string $lesson_id
     * @param string $session_id
     * @param string $context_id
     * 
     * @return mixed
     */
    public function getModules(string $url_lms, int $lesson_id, string $session_id, string $context_id)
    {
        $lessonGetRequest = app(SakaiController::class)->createClient($url_lms, $url_lms . '/direct/lessons/lesson/' . $lesson_id . '.json', $session_id);

        $modulesRequestStatus = $lessonGetRequest['statusCode'];

        $modules = [];
        $section = 0;
        $column = 0;
        $order = 1;

        if ($modulesRequestStatus == 200) {
            $modulesData = json_decode($lessonGetRequest['requestBody']);
            if ($modulesData->contentsList != null && count($modulesData->contentsList) >= 1) {
                error_log(print_r($modulesData->contentsList, true));
                foreach ($modulesData->contentsList as $index => $module) {
                    $modulesData->contentsList[$index]->type = app(SakaiController::class)->changeIdNameType($module);
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
                        if ($modulesData->contentsList[$index]->type == "folder") {
                            $modulesData->contentsList[$index]->dataDirectory = str_replace("//", "/", $modulesData->contentsList[$index]->dataDirectory);
                        }
                        $sakaiId = app(SakaiController::class)->nodeSakaiIdUpdater($modulesData->contentsList[$index]);
                        $itemFounded = app(SakaiController::class)->getLessonItemById($modulesData->contentsList[$index], $url_lms, $context_id, $session_id, $sakaiId);

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

                                $updatedModuleWithDates = app(SakaiController::class)->parseItemDates($module);
                                $updatedModuleWithExceptionDates = app(SakaiController::class)->parseItemExceptionDates($updatedModuleWithDates);

                                array_push(
                                    $modules,
                                    $updatedModuleWithExceptionDates
                                );
                            }
                        }
                    }
                }

                $conditionGetRequest = app(SakaiController::class)->createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/conditions', $session_id);

                $conditionsData = json_decode($conditionGetRequest['requestBody']);
                $conditionsRequestStatus = $conditionGetRequest['statusCode'];

                if ($conditionsRequestStatus == 200 || ($conditionsData != null && count($conditionsData) >= 1)) {
                    $parsedModules = app(SakaiController::class)->linkConditionToLessonItem($modules, $conditionsData);
                    return response()->json(app(LtiController::class)->response($parsedModules));
                } else {
                    return response()->json(app(LtiController::class)->response($modules));
                }
            } else {
                return response()->json(app(LtiController::class)->response());
            }
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'MODULES_ERROR'), 500);
        }
    }

    /**
     * @param object $item
     * 
     * @return mixed
     */
    public function nodeSakaiIdUpdater(object $item)
    {

        if (isset($item) && isset($item->type)) {
            switch ($item->type) {
                case "exam":
                case "assign":
                case "forum":
                    return app(SakaiController::class)->parseSakaiId($item);
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

    /**
     * @param object $item
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $sakaiId
     * 
     * @return mixed
     */
    public function getLessonItemById(object $item, string $url_lms, string $context_id, string $session_id, string $sakaiId)
    {

        if (isset($item) && isset($item->type)) {
            switch ($item->type) {
                case "exam":
                    $examFounded = app(SakaiController::class)->getAssesmentById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulExamRequest = app(SakaiController::class)->requestChecker($examFounded);

                    if ($successfulExamRequest == true) {
                        return $examFounded['data']['assesment_founded'];
                    } else {
                        return null;
                    }
                case "assign":
                    $assignmentFounded = app(SakaiController::class)->getAssignmentById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulAssignmentRequest = app(SakaiController::class)->requestChecker($assignmentFounded);

                    if ($successfulAssignmentRequest == true) {
                        return $assignmentFounded['data']['assignment_founded'];
                    } else {
                        return null;
                    }
                case "forum":
                    $forumFounded = app(SakaiController::class)->getForumById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulForumRequest = app(SakaiController::class)->requestChecker($forumFounded);

                    if ($successfulForumRequest == true) {
                        return $forumFounded['data']['forum_founded'];
                    } else {
                        return null;
                    }
                case 'folder':
                case "resource":
                    $resourceFounded = app(SakaiController::class)->getResourceById($url_lms, $context_id, $session_id, $sakaiId);
                    $successfulResourceRequest = app(SakaiController::class)->requestChecker($resourceFounded);

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

    /**
     * @param array $module
     * 
     * @return array
     */
    public function parseItemDates(array $module)
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

    /**
     * @param array $module
     * 
     * @return array
     */
    public function parseItemExceptionDates(array $module)
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

    /**
     * @param object $contentListIndex
     * 
     * @return object
     */
    public function parseSakaiId(object $contentListIndex)
    {
        $a = substr($contentListIndex->sakaiId, 1);
        $dataSakaiId = explode('/', $a);
        $sakaiId = end($dataSakaiId);
        return $sakaiId;
    }

    /**
     * @param object $modules
     * @param array $conditions
     * @param bool $assign
     * 
     * @return mixed
     */
    public function linkConditionToLessonItem(array $modules, array $conditions, bool $assign = true)
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

    /**
     * @param object $module
     * 
     * @return string
     */
    public function changeIdNameType(object $module)
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

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * 
     * @return array
     */
    public function getAssesments(string $url_lms, string $context_id, string $session_id)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

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
            $data = ['assesments' => $assesments, 'status_code' => $statusCode];
            return response()->json(app(LtiController::class)->response($data));
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
        }
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $session_id
     * @param string $assesmentId
     * 
     * @return mixed
     */
    public function getAssesmentById(string $url_lms, string $context_id, string $session_id, string $assesmentId)
    {
        $request = app(SakaiController::class)->createClient($url_lms, $url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);

        $statusCode = $request['statusCode'];

        if ($statusCode == 200) {
            $modules = json_decode($request['requestBody']);
            foreach ($modules as $assesment) {
                if ($assesment->id == $assesmentId) {
                    $data = ['assesment_founded' => $assesment, 'status_code' => $statusCode];
                    return response()->json(app(LtiController::class)->response($data));
                }
            }
        }
        return response()->json(app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR'), $statusCode);
    }

    /**
     * @param string $lms_url
     * @param string $url
     * @param string $session_id
     * @param string $type
     * @param array $bodyData
     * 
     * @return mixed
     */
    public function createClient(string $lms_url, string $url, string $session_id, $type = 'GET', $bodyData = [])
    {
        $lmsInstance = app(LtiController::class)->getLmsToken($lms_url, "sakai");
        $cookieName = "JSESSIONID";
        if ($lmsInstance != '') {
            if (isset($lmsInstance['cookieName'])) {
                $cookieName = $lmsInstance['cookieName'];
            }
        } else {
            return app(LtiController::class)->errorResponse(null, 'INSTANCE_ERROR', 500);
        }

        $client = new Client();
        $options = [
            'headers' => [
                'Cookie' => $cookieName . '=' . $session_id
            ],
        ];
        $response = '';
        try {
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
                    return app(LtiController::class)->errorResponse(null, 'UNSUPORTED_REQUEST_TYPE', 500);
            }
        } catch (\Exception $e) {
            return app(LtiController::class)->errorResponse(null, 'REQUEST_ERROR', $e->getCode());
        }
        $content = $response->getBody();
        $statusCode = $response->getStatusCode();
        $responseData = [
            'requestBody' => json_decode($content),
            'statusCode' => $statusCode,
        ];
        return app(LtiController::class)->response($responseData);
    }
    /**
     * @param string $user_id
     * 
     * @return string
     */
    public function getId(string $user_id)
    {
        $url = explode('/', $user_id);
        return $url[count($url) - 1];
    }

    /**
     * @param string $url_lms
     * @param string $context_id
     * @param string $user_id
     * 
     * @return string
     */
    public function getUrl(string $url_lms, string $context_id, string $user_id)
    {
        return ($url_lms . '/direct/profile/' . $user_id . '/image/thumb?siteId=' . $context_id);
    }

    /**
     * @param array $array
     * @param string $callback
     * 
     * @return string
     */
    public function find(array $array, string $callback)
    {
        return current(array_filter($array, $callback));
    }


    /**
     * @param Request $request
     * @param object $sessionData
     * 
     * @return array
     */
    public function exportVersion(Request $request, object $sessionData)
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
            $lessonCopyRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/direct/lessons/lesson/' . $request->lessonId . '.json', $sessionData->session_id);
            $lessonStatusCode = $lessonCopyRequest['statusCode'];

            $conditionsCopyRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions', $sessionData->session_id);
            $conditionsStatusCode = $conditionsCopyRequest['statusCode'];

            if ($lessonStatusCode == 200 && $conditionsStatusCode == 200) {
                $lessonCopy = json_decode($lessonCopyRequest['requestBody']);
                $conditionsCopy = $conditionsCopyRequest['requestBody'];

                if (count($nodesToUpdate) >= 1) {
                    $nodesUpdateRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/entities', $sessionData->session_id, 'PATCH', $nodesToUpdate);
                    $nodesUpdateStatusCode = $nodesUpdateRequest['statusCode'];

                    if ($nodesUpdateStatusCode !== 200) {
                        return response()->json(app(LtiController::class)->errorResponse(null, 'NODE_UPDATE_ERROR'), 500);
                    }
                }

                $conditionsDelete = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/conditions', $sessionData->session_id, 'DELETE');
                $conditionsDeleteStatusCode = $conditionsDelete;

                $lessonItemsDelete = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items', $sessionData->session_id, 'DELETE');
                $lessonItemsDeleteStatusCode = $lessonItemsDelete;

                if ($conditionsDeleteStatusCode === 200 && $lessonItemsDeleteStatusCode === 200) {
                    $nodesCreationRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $nodes);
                    $nodesCreationStatusCode = $nodesCreationRequest['statusCode'];

                    if ($nodesCreationStatusCode == 200) {
                        if (($conditionList != null && count($conditionList) >= 1)) {
                            $nodesCreated = $nodesCreationRequest['requestBody'];
                            $filteredArray = app(SakaiController::class)->conditionIdParse(json_decode($nodesCreated), $conditionList);

                            $conditionsParsedList = (array_values($filteredArray));
                            $conditionsCreationRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsParsedList);
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
                        $parsedNodes = app(SakaiController::class)->parseSakaiLessonCopy($lessonCopy->contentsList, false);
                        $parsedNodesWithId = app(SakaiController::class)->parseSakaiLessonCopy($lessonCopy->contentsList, true);
                        $nodesCopyCreationRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $parsedNodes);
                        $nodesCopyCreationStatusCode = $nodesCopyCreationRequest['statusCode'];
                        if ($nodesCopyCreationStatusCode == 200) {
                            $nodesCopyCreation = ($nodesCopyCreationRequest['requestBody']);
                            $parsedConditions = app(SakaiController::class)->conditionItemIdAdder(($parsedNodesWithId), json_decode($conditionsCopy));
                            $parsedConditionsJson = json_decode(json_encode($parsedConditions), true);
                            $filteredArray = app(SakaiController::class)->conditionIdParse(json_decode($nodesCopyCreation), $parsedConditionsJson);
                            $conditionsCopyParsedList = (array_values($filteredArray));

                            $conditionsCopyCreationRequest = app(SakaiController::class)->createClient($sessionData->platform_id, $sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsCopyParsedList);
                            $conditionsCopyCreationStatusCode = $conditionsCopyCreationRequest['statusCode'];

                            if ($conditionsCopyCreationStatusCode == 200) {
                                return response()->json(app(LtiController::class)->errorResponse(null, 'LESSON_ITEMS_CREATION_ERROR'), 500);
                            } else {
                                return response()->json(app(LtiController::class)->errorResponse(null, 'LESSON_ITEMS_WITHOUT_CONDITIONS_CREATION_ERROR'), 500);
                            }
                        } else {
                            return response()->json(app(LtiController::class)->errorResponse(null, 'FATAL_ERROR'), 500);
                        }
                    }
                } else {
                    return response()->json(app(LtiController::class)->errorResponse(null, 'LESSON_DELETE_ERROR'), 500);
                }
            } else {
                return response()->json(app(LtiController::class)->errorResponse(null, 'LESSON_COPY_ERROR'), 500);
            }
        } else {
            return response()->json(app(LtiController::class)->errorResponse(null, 'PAGE_EXPORT_ERROR'), 500);
        }
    }

    /**
     * @param array $nodes
     * @param array $conditionList
     * 
     * @return array
     */
    public function conditionItemIdAdder(array $nodes, array $conditionList)
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

    /**
     * @param array $nodesCreated
     * @param array $conditionList
     * 
     * @return array
     */
    public function conditionIdParse(array $nodesCreated, array $conditionList)
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
                        if (array_key_exists('argument', $root)) {
                            unset($root['argument']);
                        }
                        if (isset($root['hasParent'])) {
                            unset($root['hasParent']);
                        }
                        break;
                    }
                }

                if (isset($root['subConditions'])) {
                    foreach ($root['subConditions'] as &$parent) {
                        unset($parent['id']);
                        if (isset($parent['hasParent'])) {
                            unset($parent['hasParent']);
                        }
                        if (array_key_exists('argument', $parent)) {
                            unset($parent['argument']);
                        }
                        if (array_key_exists('itemId', $parent)) {
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
                                        if (isset($childCondition['hasParent'])) {
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

    /**
     * @param array $contentsList
     * @param bool $idAdder
     * 
     * @return array
     */
    public function parseSakaiLessonCopy(array $contentsList, bool $idAdder)
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

    /**
     * @param mixed $getRequest
     * 
     * @return bool
     */
    public function requestChecker($getRequest)
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
