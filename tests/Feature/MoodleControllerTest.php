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
    private $test_config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->test_config = config('lms_config_test.test_config');
    }

    public function testGetImgUser()
    {
        $controller = new MoodleController();
        $response = $controller->getImgUser($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['user_id']);

        $this->assertIsString($response);
    }

    public function testGetGroups()
    {
        $controller = new MoodleController();
        $response = $controller->getGroups($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

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
        $response = $controller->getGrupings($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

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
        $response = $controller->getModules($this->test_config['lms_url'], $this->test_config['course_id']);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('ok', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        foreach ($responseData["data"] as $module) {
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
            'url_lms' => $this->test_config['lms_url'],
            'platform' => $this->test_config['platform'],
            'course' => $this->test_config['course_id'],
            'type' => $this->test_config['resource_type']
        ]);

        $session_data = new stdClass();
        $session_data->platform_id = $this->test_config['lms_url'];
        $session_data->context_id = $this->test_config['context_id'];

        $response = $controller->getModulesByType($request, $session_data);

        $this->assertArrayHasKey('ok', $response);
        $this->assertArrayHasKey('data', $response);
    }

    public function testGetSections()
    {
        $controller = new MoodleController();
        $response = $controller->getSections($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

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
        $response = $controller->getBadges($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

        $this->assertIsArray($response);
        foreach ($response as $badge) {
            $this->assertNotNull($badge->id);
            $this->assertNotNull($badge->name);
            $this->assertNotNull($badge->params);
        }
    }

    public function testGetCourse()
    {
        $controller = new MoodleController();
        $response = $controller->getCourse($this->test_config['course_id'], $this->test_config['platform'], $this->test_config['lms_url'], $this->test_config['user_id']);
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

    public function testGetInstance()
    {
        $controller = new MoodleController();
        $response = $controller->getinstance($this->test_config['platform'], $this->test_config['lms_url']);
        $this->assertNotNull($response);
    }

    public function testGetVersion()
    {
        $controller = new MoodleController();
        $response = $controller->getVersion($this->test_config['id_map_version']);
        $this->assertNotNull($response);
    }

    public function testGetCoursegrades()
    {
        $controller = new MoodleController();
        $result = $controller->getCoursegrades($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testGetIdCoursegrades()
    {
        $controller = new MoodleController();
        $result = $controller->getIdCoursegrades($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

        $this->assertIsArray($result);
    }

    public function testGetUrlLms()
    {
        $controller = new MoodleController();
        $result = $controller->getUrlLms($this->test_config['instance_id']);

        if ($result) {
            $this->assertIsString($result);
        }
        if (!$result) {
            $this->assertNull($result);
        }
    }

    public function testGetModuleById()
    {
        $controller = new MoodleController();
        $result = $controller->getModuleById($this->test_config['instance_id'], $this->test_config['item_id']);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testGetIdGrade()
    {
        $controller = new MoodleController();
        $module = new stdClass();
        $module->cm = new stdClass();
        $module->cm->course = $this->test_config['cm_course_id'];
        $module->cm->name = $this->test_config['cm_name'];
        $module->cm->modname = $this->test_config['cm_modname'];
        $module->cm->instance = $this->test_config['cm_instance'];


        $result = $controller->getIdGrade($this->test_config['instance_id'], $module);

        if ($result) {
            $this->assertIsInt($result);
        }

        if (!$result) {
            $this->assertNull($result);
        }
    }

    public function testGetIdCoursegrade()
    {
        $controller = new MoodleController();
        $result = $controller->getIdCoursegrade($this->test_config['instance_id'], $this->test_config['course_id']);

        $this->assertNotNull($result);
    }

    public function testGetModulesListBySectionsCourse()
    {
        $controller = new MoodleController();
        $result = $controller->getModulesListBySectionsCourse($this->test_config['instance_id'], $this->test_config['course_id']);

        $this->assertInstanceOf(stdClass::class, $result);
    }


    public function testGetGradeModule()
    {
        $controller = new MoodleController();
        $result = $controller->getGradeModule($this->test_config['lms_url'], $this->test_config['grade_id']);

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testGetRoles()
    {
        $controller = new MoodleController();
        $result = $controller->getRoles($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

        $this->assertIsArray($result);
    }

    public function testGetCompetencies()
    {
        $controller = new MoodleController();
        $result = $controller->getCompetencies($this->test_config['token_request'], $this->test_config['lms_url'], $this->test_config['course_id']);

        $this->assertIsArray($result);
    }

    public function testGetCalifications()
    {
        $controller = new MoodleController();
        $result = $controller->getCalifications($this->test_config['lms_url'], $this->test_config['module_id'], $this->test_config['cm_modname']);

        if ($result) {
            $this->assertInstanceOf(stdClass::class, $result);
        }

        if (!$result) {
            $this->assertNull($result);
        }
    }
}
