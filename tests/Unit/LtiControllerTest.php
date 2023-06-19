<?php

namespace Tests\Unit\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\LtiController;
use Illuminate\Http\Request;

class LtiControllerTest extends TestCase
{


    private $idUserMoodle = 3;
    private $idCourseMoodle = 8;
    private $idCourseSakai = 'dc37c313-079c-4073-9ed6-7a6d2cb79732';
    private $idInstanceMoodle = 1;
    private $platformMoodle = 'moodle';
    private $platformSakai = 'sakai';
    private $urlLmsMoodle = 'http://localhost/moodle-3.11.13';
    private $urlLmsSakai = 'http://localhost:8080';
    private $resourceType = 'quiz';
    private $sessionSakai = '4cd55d5d-7474-4cc4-be4e-6bd45428b406.DESKTOP-643I850';

    //Moodle

    public function testGetJwks()
    {
        $controller = new LtiController();
        $response = $controller->getJWKS();

        $this->assertIsArray($response);
    }

    public function testGetSession()
    {
        $controller = new LtiController();
        $response = $controller->getsession();

        $this->assertIsArray($response);
    }

    public function testGetImgUser()
    {
        $controller = new LtiController();
        $response = $controller->getImgUser($this->urlLmsMoodle, $this->idUserMoodle);

        $this->assertIsString($response);
    }

    public function testGetGroups()
    {
        $controller = new LtiController();
        $response = $controller->getGroups($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);
    }

    public function testGetGrupings()
    {
        $controller = new LtiController();
        $response = $controller->getGrupings($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);
    }

    public function testGetModules()
    {
        $controller = new LtiController();
        $request = new Request([
            // 'platform' => $this->platformMoodle,
            'instance' => $this->idInstanceMoodle,
            'course' => $this->idCourseMoodle,
            'moodlewsrestformat' => 'json'
        ]);
        $response = $controller->getModules($request);

        $this->assertIsArray($response);
    }

    public function testgetModulesByType()
    {
        $controller = new LtiController();
        $request = new Request([
            'platform' => $this->platformMoodle,
            'course' => $this->idCourseMoodle,
            'type' => $this->resourceType
        ]);

        $response = $controller->getModulesByType($request);
        $this->assertIsArray($response);
    }

    public function testGetSections()
    {
        $controller = new LtiController();
        $response = $controller->getSections($this->urlLmsMoodle, $this->idCourseMoodle);

        $this->assertIsArray($response);
    }

    public function testGetBadges()
    {
        $controller = new LtiController();
        $response = $controller->getBadges($this->idCourseMoodle);

        $this->assertIsArray($response);
    }

    public function testGetCourse()
    {
        $controller = new LtiController();
        $response = $controller->getCourse($this->idCourseMoodle, $this->platformMoodle, $this->urlLmsMoodle);
        $this->assertIsArray($response);
    }

    //Sakai

    // public function testGetLessons()
    // {
    //     $controller = new LtiController();
    //     $response = $controller->getLessons();

    //     $this->assertIsArray($response);
    // }
    public function testGetAssignments()
    {
        $controller = new LtiController();
        $response = $controller->getAssignments($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai);

        $this->assertIsArray($response);
    }
    public function testGetForums()
    {
        $controller = new LtiController();
        $response = $controller->getForums($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai);

        $this->assertIsArray($response);
    }
    public function testGetResourcesTextSimple()
    {
        $controller = new LtiController();
        $response = $controller->getResources($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai, 'text/plain');

        $this->assertIsArray($response);
    }
    public function testGetResourcesHtml()
    {
        $controller = new LtiController();
        $response = $controller->getResources($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai, 'text/html');

        $this->assertIsArray($response);
    }
    public function testGetResourcesUrl()
    {
        $controller = new LtiController();
        $response = $controller->getResources($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai, 'text/url');

        $this->assertIsArray($response);
    }
    public function testGetResourcesFolder()
    {
        $controller = new LtiController();
        $response = $controller->getResources($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai, null);

        $this->assertIsArray($response);
    }
    public function testGetResourcesResource()
    {
        $controller = new LtiController();
        $response = $controller->getResources($this->urlLmsSakai, $this->idCourseSakai, $this->sessionSakai, 'resource');

        $this->assertIsArray($response);
    }




    // public function testLtiMessage()
    // {
    //     $this->withoutMiddleware();

    //     $response = $this->post('/lti-launch', [
    //         "oauth_consumer_key" => "E1XGZGk2Y7n05Y6",
    //         "oauth_signature_method" => "RS256",
    //         "context_id" => "8",
    //         "context_label" => "flamenco",
    //         "context_title" => "Introducción al flamenco",
    //         "context_type" => "CourseSection",
    //         "lis_course_section_sourcedid" => "",
    //         "launch_presentation_document_target" => "window",
    //         "launch_presentation_locale" => "es",
    //         "launch_presentation_return_url" => "http://localhost/moodle-3.11.13/mod/lti/return.php?course=8&launch_container=4&instanceid=3&sesskey=FuTqztpDg5",
    //         "lis_person_contact_email_primary" => "djimenez@entornosdeformacion.com",
    //         "lis_person_name_family" => "Usuario",
    //         "lis_person_name_full" => "Administrador Usuario",
    //         "lis_person_name_given" => "Administrador",
    //         "lis_person_sourcedid" => "",
    //         "user_id" => "2",
    //         "roles" => "http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator,http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor,http://purl.imsglobal.org/v",
    //         "platformMoodle_id" => "http://localhost/moodle-3.11.13",
    //         "deployment_id" => "3",
    //         "lti_message_type" => "basic-lti-launch-request",
    //         "lti_version" => "1.3.0",
    //         "resource_link_description" => "",
    //         "resource_link_id" => "3",
    //         "resource_link_title" => "Uniadaptive",
    //         "target_link_uri" => "http://127.0.0.1:8000",
    //         "tool_consumer_info_product_family_code" => "moodle",
    //         "tool_consumer_info_version" => "2021051713",
    //         "tool_consumer_instance_description" => "primera prueba",
    //         "tool_consumer_instance_guid" => "26e47fe3a567a319e6ac9b2ba869b67a",
    //         "tool_consumer_instance_name" => "prueba",
    //         "custom_context_memberships_v2_url" => "http://localhost/moodle-3.11.13/mod/lti/services.php/CourseSection/8/bindings/3/memberships",
    //         "custom_nrps_versions" => "1.0,2.0",
    //         "custom_context_memberships_url" => "http://localhost/moodle-3.11.13/mod/lti/services.php/CourseSection/8/bindings/3/memberships",
    //         "ext_user_username" => "admin",
    //         "ext_lms" => "moodle-2",
    //     ]);

    //     $response->assertStatus(302);
    //     $response->assertRedirect('http://localhost:3000');
    // }
}
