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

    // Función que devuelve los datos del usuario y del curso
    public static function getSession(Object $lastInserted)
    {
        $data = [
            [
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => $lastInserted->profile_url,
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                // 'instance_id' => $this->getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
            ],
            SakaiController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        return $data;
    }

    public static function createSession($url_lms, $sakaiServerId)
    {
        $client = new Client();

        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . env('SAKAI_USER') . '&pw=' . env('SAKAI_PASSWORD'));
        $content = $response->getBody()->getContents();
        $userId = $content . '.' . $sakaiServerId;
        return $userId;
    }

    public static function getLessons($url_lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $url_lms . '/direct/lessons/site/' . $contextId . '.json', [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        // dd($data);
        foreach ($data->lessons_collection as $Lesson) {
            $response2 = $client->request('GET', $url_lms . '/direct/lessons/lesson/' . $Lesson->id . '.json', [
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

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request)
    {
        // dd($request);
        switch ($request->type) {
            case 'forum':
                return SakaiController::getForums($request->lms, $request->course, $request->session);
                break;
            case 'assign':
                return SakaiController::getAssignments($request->lms, $request->course, $request->session);
                break;
            case 'text':
                return SakaiController::getResources($request->lms, $request->course, $request->session, 'text/plain');
                break;
            case 'url':
                return SakaiController::getResources($request->lms, $request->course, $request->session, 'text/url');
                break;
            case 'html':
                return SakaiController::getResources($request->lms, $request->course, $request->session, 'text/html');
                break;
            case 'folder':
                return SakaiController::getResources($request->lms, $request->course, $request->session, null);
                break;
            case 'resource':
                return SakaiController::getResources($request->lms, $request->course, $request->session, 'resource');
                break;
            default:
                # code...
                break;
        }
    }

    // Función que devuelve los foros de un curso de Sakai
    public static function getForums($url_lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $url_lms . '/direct/forums/site/' . $contextId . '.json', [
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

    // Función que devuelve las tareas de un curso de Sakai
    public static function getAssignments($url_lms, $contextId, $sessionId)
    {
        $client = new Client();

        $response = $client->request('GET', $url_lms . '/direct/assignment/site/' . $contextId . '.json', [
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

    // Función que devuelve los recursos de un curso de Sakai dependiendo de su tipo
    public static function getResources($url_lms, $contextId, $sessionId, $type)
    {
        // dd($sessionId);
        $client = new Client();

        $response = $client->request('GET', $url_lms . '/direct/content/resources/group/' . $contextId . '.json?depth=3', [
            'headers' => [
                'Cookie' => 'SAKAI2SESSIONID=' . $sessionId,
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
}
