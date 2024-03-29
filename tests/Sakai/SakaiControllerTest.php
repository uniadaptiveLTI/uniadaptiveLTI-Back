<?php

namespace Tests\Feature;

use App\Http\Controllers\SakaiController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use stdClass;
use Tests\TestCase;

class SakaiControllerTest extends TestCase
{
	public function testCreateSession()
	{
		parent::setUp();

		$unique_lms_config = config('lms_config_test');
		$result = SakaiController::createSession($unique_lms_config['url'], null, $unique_lms_config);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);

			$session_id = $result['data']['user_id'];
			$this->session_id = $session_id;
		}
	}

	public function testGetLessons()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getLessons($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetUserMembers()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getUserMembers($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetGroups()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getGroups($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetForums()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getForums($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetAssignments()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getAssignments($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetAssesments()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getAssesments($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetResourcesByTypePlain()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getResourcesByType($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id, "plain");

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetResourcesByTypeUrl()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getResourcesByType($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id, "url");

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetResourcesByTypeHtml()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getResourcesByType($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id, "html");

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetResourcesByTypeFolder()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getResourcesByType($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id, "folder");

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetResourcesByTypeResource()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getResourcesByType($unique_lms_config['url'], $unique_lms_config['site_id'], $session_id, "resource");

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}

	public function testGetModules()
	{
		$session_id = $this->session_id;

		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::getModules($unique_lms_config['url'], $unique_lms_config['site_id'], $unique_lms_config['lesson_id'], $session_id, $unique_lms_config['site_id']);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(true, $result['ok']);
		}
	}
}