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


	public function testCreateSession()
	{
		$unique_lms_config = config('lms_config_test');

		$result = SakaiController::createSession($unique_lms_config['url'], null, $unique_lms_config);

		$this->assertTrue(array_key_exists('ok', $result));

		if (array_key_exists('ok', $result) && $result['ok']) {
			$this->assertEquals(200, $result['data']['status_code']);
		}
	}
}