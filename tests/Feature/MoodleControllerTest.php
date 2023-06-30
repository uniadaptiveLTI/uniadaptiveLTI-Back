<?php

namespace Tests\Feature;

use App\Http\Controllers\MoodleController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use stdClass;
use Tests\TestCase;

class MoodleControllerTest extends TestCase
{

    private $idUserMoodle = 4;
    private $idCourseMoodle = 8;
    private $idInstanceMoodle = 1;
    private $platformMoodle = 'moodle';
    private $urlLmsMoodle = 'http://localhost/moodle-3.11.13';
    private $resourceType1 = 'quiz';
    private $resourceType2 = 'resource';
    private $itemMap = '{"instance_id":1,"course_id":"8","platform":"moodle","user_id":"2","map":{"id":"834589328291","name":"Nuevo Mapa 1","versions":{"id":21,"map_id":25,"name":"Última versión","updated_at":"2023-06-29T09:35:28.000000Z","default":1,"blocksData":[{"id":"1464800653154","data":{"label":"Entrada","children":["900404137072"]},"type":"start","position":{"x":-128.5,"y":-11.5},"deletable":false},{"id":"1630639804058","data":{"label":"Salida","conditions":{"id":"162033670147","op":"&","type":"conditionsGroup","conditions":[{"id":"1284856013808","op":"811637142173","type":"completion","query":"completed"}]}},"type":"end","position":{"x":296.5,"y":1},"deletable":false},{"id":"811637142173","data":{"label":"Carpeta 2","order":1,"section":0,"children":["1630639804058"],"conditions":{"id":"1561552545390","op":"&","type":"conditionsGroup","conditions":[{"id":"1158475704642","op":"900404137072","type":"completion","query":"completed"}]},"identation":0,"lmsResource":80,"lmsVisibility":"hidden_until_access"},"type":"folder","position":{"x":166.5,"y":-70.5}},{"id":"900404137072","data":{"label":"Carpeta 3","order":0,"section":0,"children":["811637142173"],"conditions":{"id":"413700410136","op":"&","type":"conditionsGroup","conditions":[{"id":"1303388121936","op":"1464800653154","type":"completion","query":"completed"}]},"identation":0,"lmsResource":-1,"lmsVisibility":"hidden_until_access"},"type":"folder","position":{"x":32,"y":51.5}}],"lastUpdate":"30/6/2023, 11:19:52"}}}';
    private $itemSession = '{"id": 179,"tool_consumer_info_product_family_code": "moodle","context_id": "8"
    ,"context_title": "Introducción al flamenco"
    ,"launch_presentation_locale": "es"
    ,"platform_id": "http://localhost/moodle-3.11.13"
    ,"ext_sakai_serverid": null
    ,"session_id": null
    ,"launch_presentation_return_url": "http://localhost/moodle-3.11.13/mod/lti/return.php?course=8&launch_container=4&instanceid=3&sesskey=eInS9NLmBm"
    ,"user_id": "2"
    ,"lis_person_name_full": "Administrador Usuario"
    ,"profile_url": "http://localhost/moodle-3.11.13/pluginfile.php/5/user/icon/boost/f1?rev=211"
    ,"roles": "http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator,http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor,http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator"
    ,"created_at": "2023-06-30 10:40:56"
    ,"updated_at": "2023-06-30 10:40:56"}';


    public function testGetImgUser()
    {
        $controller = new MoodleController();
        $response = $controller->getImgUser($this->urlLmsMoodle, $this->idUserMoodle);

        $this->assertIsString($response);
    }

    public function testGetGroups()
    {
        $controller = new MoodleController();
        $response = $controller->getGroups($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);

        foreach ($response as $group) {
            $this->assertIsArray($group);
            $this->assertArrayHasKey('id', $group);
            $this->assertArrayHasKey('name', $group);
        }
    }

    public function testGetGrupings()
    {
        $controller = new MoodleController();
        $response = $controller->getGrupings($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);

        foreach ($response as $gruping) {
            $this->assertIsArray($gruping);
            $this->assertArrayHasKey('id', $gruping);
            $this->assertArrayHasKey('name', $gruping);
        }
    }

    public function testGetModules()
    {
        $controller = new MoodleController();
        $request = new Request([
            'url_lms' => $this->urlLmsMoodle,
            'course' => $this->idCourseMoodle,
            'moodlewsrestformat' => 'json'
        ]);
        $response = $controller->getModules($request);

        $this->assertIsArray($response);

        foreach ($response as $module) {
            $this->assertIsArray($module);
            $this->assertArrayHasKey('name', $module);
            $this->assertArrayHasKey('modname', $module);
            $this->assertArrayHasKey('id', $module);
            $this->assertArrayHasKey('has_califications', $module);
            $this->assertArrayHasKey('order', $module);
            $this->assertArrayHasKey('section', $module);
            $this->assertArrayHasKey('indent', $module);
            $this->assertArrayHasKey('visible', $module);
        }
    }

    public function testgetModulesByType()
    {
        $controller = new MoodleController();
        $request = new Request([
            'url_lms' => $this->urlLmsMoodle,
            'platform' => $this->platformMoodle,
            'course' => $this->idCourseMoodle,
            'type' => $this->resourceType1
        ]);

        $response = $controller->getModulesByType($request);
        $this->assertIsArray($response);

        foreach ($response as $module) {
            $this->assertIsArray($module);
            $this->assertArrayHasKey('id', $module);
            $this->assertArrayHasKey('name', $module);
            $this->assertArrayHasKey('section', $module);
            $this->assertArrayHasKey('has_grades', $module);
        }

        $request = new Request([
            'url_lms' => $this->urlLmsMoodle,
            'platform' => $this->platformMoodle,
            'course' => $this->idCourseMoodle,
            'type' => $this->resourceType2
        ]);

        $response = $controller->getModulesByType($request);
        $this->assertIsArray($response);

        foreach ($response as $module) {
            $this->assertIsArray($module);
            $this->assertArrayHasKey('id', $module);
            $this->assertArrayHasKey('name', $module);
            $this->assertArrayHasKey('section', $module);
            $this->assertArrayHasKey('has_grades', $module);
        }
    }

    public function testGetSections()
    {
        $controller = new MoodleController();
        $response = $controller->getSections($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);

        foreach ($response as $section) {
            $this->assertIsArray($section);
            $this->assertArrayHasKey('id', $section);
            $this->assertArrayHasKey('name', $section);
            $this->assertArrayHasKey('position', $section);
        }
    }

    public function testGetBadges()
    {
        $controller = new MoodleController();
        $response = $controller->getBadges($this->idCourseMoodle);

        $this->assertIsArray($response);

        foreach ($response as $badge) {
            $this->assertIsArray($badge);
            $this->assertArrayHasKey('id', $badge);
            $this->assertArrayHasKey('name', $badge);
            $this->assertArrayHasKey('conditions', $badge);
        }
    }

    public function testGetCourse()
    {
        $controller = new MoodleController();
        $response = $controller->getCourse($this->idCourseMoodle, $this->platformMoodle, $this->urlLmsMoodle);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('maps', $response);

        foreach ($response['maps'] as $map) {
            $this->assertIsArray($map);
            $this->assertArrayHasKey('id', $map);
            $this->assertArrayHasKey('course_id', $map);
            $this->assertArrayHasKey('name', $map);
            $this->assertArrayHasKey('versions', $map);
            foreach ($map['versions'] as $version) {
                $this->assertIsArray($version);
                $this->assertArrayHasKey('id', $version);
                $this->assertArrayHasKey('map_id', $version);
                $this->assertArrayHasKey('name', $version);
                $this->assertArrayHasKey('updated_at', $version);
                $this->assertArrayHasKey('default', $version);
                $this->assertArrayHasKey('blocksData', $version);
            }
        }
    }

    public function testStoreVersion()
    {

        $request = new Request([
            'saveData' => json_decode($this->itemMap, true)
        ]);

        // Llamar a la función
        $result = MoodleController::storeVersion($request);

        // Verificar que el resultado es 0
        $this->assertEquals('0', $result);
    }

    public function testGetSession()
    {
        $result = MoodleController::getSession(json_decode($this->itemSession));

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        $this->assertIsArray($result[0]);
    }

    public function testGetCoursegrades()
    {
        $result = MoodleController::getCoursegrades($this->idCourseMoodle);

        $this->assertIsArray($result);
    }

    public function testGetIdCoursegrades()
    {
        $result = MoodleController::getIdCoursegrades($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($result);
    }
}
