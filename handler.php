<?php 

writeLog('log.txt', '-> request', $_GET);
// echo "<pre>";

if (empty($_GET['token']) || $_GET['token'] != "d41d8cd98f00b204e9800998ecf8427e" || empty($_GET['action'])) {exit;} // проверяем токен

require_once 'amocrm_class.php';
$AmoCRM = new AmoCRM("demidovv", "8c092b51-1ac7-4eb3-9eea-0bae980c1b37", "LGfNkkUX5SRb9hYSmuXAKFNtcW6C0RlVZkQMgMGqtW4eOEMhbxKgrDK21ZqlOkF0", "http://demidovv.online.swtest.ru"); // Создаем объект AmoCRM
$db = $AmoCRM->setDB('localhost', 'demidovvro', 'kIS4B82svyj312', 'demidovvro'); // Подключаемся к БД



// Ссылка для теста. Параметры: token action token_auth_20
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=refresh_integration&token_auth_20=
if ($_GET['action'] == 'refresh_integration' && !empty($_GET['token_auth_20'])) { // полное обновление двух токенов за счет токена авторизации (20 минут)
	$token_auth_20 = htmlspecialchars($_GET['token_auth_20']);
	$arr = $AmoCRM->accessTokenAuthorizationCode($token_auth_20);
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);;
}


// Ссылка для теста. Параметры: token action user
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=import_scheme&user=
if ($_GET['action'] == 'import_scheme') {
	//----
	$pipelines = $AmoCRM->getPipelines();
	$all_statuses = [];
	foreach ($pipelines['_embedded']['pipelines'] as $key => $value) {
		$name_pipe = $value['name'];
		foreach ($value['_embedded']['statuses'] as $key2 => $value2) {
			$all_statuses[] = [
				"id" => $value2['id'],
				"name" => $value2['name']." ($name_pipe)",
			];
		}
	}
	//----
	$customFiels = $AmoCRM->getCustomFields();
	$all_custom_fields = [];
	foreach ($customFiels['_embedded']['custom_fields'] as $key => $value) {
		$all_custom_fields[] = [
			"id" => $value['id'],
			"name" => $value['name'],
		];
	}
	//----
	$users = $AmoCRM->getUsers();
	$all_users = [];
	foreach ($users['_embedded']['users'] as $key => $value) {
		$all_users[] = [
			"id" => $value['id'],
			"name" => $value['name'],
			"phone" => '',
			"email" => $value['email'],
		];
	}
	//----
	$arr = [
		"statuses" => $all_statuses,
		"fields" => $all_custom_fields,
		"managers" => $all_users,
	];

	writeLog('log.txt', 'response ->', $arr);

	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}


// Ссылка для теста. Параметры: title text name phone email data created_date visit manager_id user token action
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=lead&title=Тестовый заголовок&text=Тестовый комментарий&name=Тестировщик&phone=79980000000&email=test@test.ru&data={"640163":"test"}&created_date=2022-04-08 12:00:00&visit=seo_yandex&manager_id=8083396&user=
if ($_GET['action'] == 'lead') {
	$title = htmlspecialchars($_GET['title']);
	$text = htmlspecialchars($_GET['text']);
	$name = htmlspecialchars($_GET['name']);
	$phone = htmlspecialchars($_GET['phone']);
	$email = htmlspecialchars($_GET['email']);
	$price = 0;
	$data = $_GET['data'];
	$created_date = htmlspecialchars($_GET['created_date']);
	$visit = htmlspecialchars($_GET['visit']);
	$manager_id = htmlspecialchars($_GET['manager_id']);

	$cfv = []; // массив с доп.полями
	$cfv[] = [
							"field_id"=>(int)624167, // roistat поле в UTM метках
							"values"=>[[
								"value"=>$visit
							]]
						];
	foreach (json_decode($data) as $key => $value) { // по идеи сюда надо добавить какой-то интерфейс для того чтобы связывать поля из формы сайта с полями в CRM, а пока сделаю просто отсев по id полей
		if (is_numeric($key)) {
			$cfv[] = [
								"field_id"=>(int)$key,
								"values"=>[[
									"value"=>htmlspecialchars($value)
								]]
							];
		}
		if ($key == "cost") {
			$price = htmlspecialchars($value);
		}
	}

	$data = [[
		"name"=>$title,
		"price"=>(int)$price,
		"created_at"=>strtotime($created_date),
		"responsible_user_id"=>(int)$manager_id,
		"_embedded"=>[
			"contacts"=>[[
				"name"=>$name,
				"responsible_user_id"=>(int)$manager_id,
				"custom_fields_values"=>[
					[
						"field_code"=>"EMAIL",
						"values"=>[[
							"enum_code"=>"WORK",
							"value"=>$email
						]]
					],
					[
						"field_code"=>"PHONE",
						"values"=>[[
							"enum_code"=>"WORK",
							"value"=>$phone
						]]
					]
				]
			]]
		],
		"custom_fields_values"=>$cfv
	]];
	$new_deal = $AmoCRM->addDealAndContact($data);
	// writeLog('log.txt', 'response ->', $new_deal); // если не создается сделка, то можно включить чтобы узнать в чем проблема
	if (!empty($new_deal[0]['id'])) {
		$settings_arr = [
			[
				"note_type" => "common",
				"params" => [
					"text" => "$text",
				]
			]
		];
		$AmoCRM->addCommentToDeal($new_deal[0]['id'], $settings_arr);
		$arr = [
			"status"=>"ok",
			"order_id"=>$new_deal[0]['id']
		];
	} else {
		$arr = [
			"status"=>"error",
			"order_id"=>''
		];
	}
	writeLog('log.txt', 'response ->', $arr);
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}



// Ссылка для теста. Параметры: token action date offset limit user
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=export&date=1646747823&offset=0&limit=1000&user=
if ($_GET['action'] == 'export') {
	$form_date = htmlspecialchars($_GET['date']);
	$form_offset = htmlspecialchars($_GET['offset']);

	$amocrm_limit = 250; // Лимит по которому будут выгружаться сделки (макс 250)
	$total_count = $AmoCRM->getTotalCountListDeal("?limit=$amocrm_limit&filter[updated_at][from]=$form_date");
	$deals = $AmoCRM->getListDeal("?limit=$amocrm_limit&filter[updated_at][from]=$form_date&with=contacts&page=".(($form_offset/$amocrm_limit)+1));
	$res_deals = [];
	foreach ($deals['_embedded']['leads'] as $key => $value) {
		$found_key_roistat = array_search('roistat', array_column($value['custom_fields_values'], 'field_name'));
		$temp_custom_fields = [];
		foreach ($value['custom_fields_values'] as $key2 => $value2) {
			$temp_custom_fields[$value2['field_id']] = $value2['values'][0]['value'];
		}
		// $is_error = [30014105, 30014273, 30014415, 30015103, 30015651, 30023207, 30023353, 30023517, 30023691, 30023767, 30023805, 30024227, 30024305, 30024361, 30026525, 30028941, 30029011, 30029117, 30030225, 30030329, 30029063, 30030447, 30031117, 30033601, 30037037, 30037249]; // УДАЛИТЬ!!!!!!!!!!!!!!!!!!!!!!
		// if (in_array($value['id'], $is_error)) {continue;}
		$res_deals[] = [
			"id"          => $value['id'],
			"name"        => $value['name'],
			"date_create" => $value['created_at'],
			"status"      => $value['status_id'],
			"price"       => $value['price'],
			// "cost"        => 0, // себестоимости же нету в amocrm?
			"roistat"     => $value['custom_fields_values'][$found_key_roistat]['values'][0]['value'],
			"client_id"   => $value['_embedded']['contacts'][0]['id'],
			"manager_id"  => $value['responsible_user_id'],
			"fields"      => $temp_custom_fields,
			"products"    => [],
		];
	}

	$arr = [
		"pagination" => [
			"total_count" => $total_count,
			"limit" => $amocrm_limit,
		],
		"orders" => $res_deals
	];

	writeLog('log.txt', 'response ->', $arr);

	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}


// Ссылка для теста. Параметры: token action date offset limit user
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=export_clients&date=1646747823&offset=0&limit=1000&user=
if ($_GET['action'] == 'export_clients') {
	$form_date = htmlspecialchars($_GET['date']);
	$form_offset = htmlspecialchars($_GET['offset']);

	$amocrm_limit = 250; // Лимит по которому будут выгружаться клиенты (макс 250)
	$total_count = $AmoCRM->getTotalCountListContact("?limit=$amocrm_limit&filter[updated_at][from]=$form_date");
	$contacts = $AmoCRM->getListContact("?limit=$amocrm_limit&filter[updated_at][from]=$form_date&page=".(($form_offset/$amocrm_limit)+1));
	$res_contacts = [];
	foreach ($contacts['_embedded']['contacts'] as $key => $value) {
		$found_key_phone = array_search('Телефон', array_column($value['custom_fields_values'], 'field_name'));
		$found_key_email = array_search('Email', array_column($value['custom_fields_values'], 'field_name'));
		$phones_str = '';
		if ($found_key_phone !== false) {
			foreach ($value['custom_fields_values'][$found_key_phone]['values'] as $key2 => $value2) {
				$phones_str .= $value2['value'].", ";
			}
			$phones_str = mb_substr($phones_str,0,-2);
		}
		$emails_str = '';
		if ($found_key_email !== false) {
			foreach ($value['custom_fields_values'][$found_key_email]['values'] as $key2 => $value2) {
				$emails_str .= $value2['value'].", ";
			}
			$emails_str = mb_substr($emails_str,0,-2);
		}
		$res_contacts[] = [
			"id"          => $value['id'],
			"name"        => $value['name'],
			"phone"       => $phones_str,
			"email"       => $emails_str,
			"company"     => ($AmoCRM->getListCompanyByID($value['_embedded']['companies'][0]['id']))['name'],
			"birth_date"  => '', // день рождения нет в amocrm
		];
	}

	$arr = [
		"pagination" => [
			"total_count" => $total_count,
			"limit" => $amocrm_limit,
		],
		"clients" => $res_contacts
	];
	writeLog('log.txt', 'response ->', $arr);
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}


// Ссылка для теста. Параметры: token action leadId title message user
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=message&leadId=30219053&title=Заголовок сообщения&message=Текст сообщения&user=

if ($_GET['action'] == 'message') {
	$leadId = htmlspecialchars($_GET['leadId']);
	$title = htmlspecialchars($_GET['title']);
	$message = htmlspecialchars($_GET['message']);
	$settings_arr = [
		[
			"note_type" => "common",
			"params" => [
				"text" => "$title\n$message",
			]
		]
	];
	$arr = $AmoCRM->addCommentToDeal($leadId, $settings_arr);
	writeLog('log.txt', 'response ->', $arr);
	// print_r($arr);
}


// Ссылка для теста. Параметры: token action element_id deadline text user
// http://demidovv.online.swtest.ru/handler.php?token=d41d8cd98f00b204e9800998ecf8427e&action=task&element_id=30219053&deadline=2022-05-01T11:11:11&text=Текст задачи&user=
if ($_GET['action'] == 'task') {
	$element_id = (int)htmlspecialchars($_GET['element_id']);
	$deadline = htmlspecialchars($_GET['deadline']);
	$text = htmlspecialchars($_GET['text']);
	$deal = $AmoCRM->getDealByID($element_id);
	$responsible_user_id = $deal['responsible_user_id'];
	if (!empty($responsible_user_id)) {
		$settings_arr = [
			[
				"text" => $text,
				"complete_till" => strtotime($deadline), // 2022-04-10T10:10:10
				"entity_id" => $element_id,
				"entity_type" => "leads",
				"task_type_id" => 1,
				"responsible_user_id" => $deal['responsible_user_id'],
			]
		];
		$task = $AmoCRM->addTasksToDeal($settings_arr);
		$arr = [
			"status"=>"ok",
			"task_id"=>$task['_embedded']['tasks'][0]['id']
		];
	} else {
		$arr = [
			"status"=>"error",
			"task_id"=>''
		];
	}
	writeLog('log.txt', 'response ->', $arr);
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}


// поиск для прикрепления звонка в amocrm идет только по номеру телефона
// Ссылка для теста. Параметры: id(нужно менять, т.к. одинаковые значения не загружаются) action callee caller visit marker status duration file_url order_id date user token
// http://demidovv.online.swtest.ru/handler.php?action=call&id=14&callee=79012223344&caller=80000000103&visit=12345&marker=vk_new_post&status=ANSWER&duration=50&file_url=http://demidovv.online.swtest.ru/play.mp3&order_id=30216141&date=2022-04-06T12:12:11&user=test_user&token=d41d8cd98f00b204e9800998ecf8427e
if ($_GET['action'] == 'call') {
	$id = htmlspecialchars($_GET['id']);
	$callee = htmlspecialchars($_GET['callee']);
	$caller = htmlspecialchars($_GET['caller']);
	$visit = htmlspecialchars($_GET['visit']); // не совсем понятно куда их крепить
	$marker = htmlspecialchars($_GET['marker']); // не совсем понятно куда их крепить
	$status = htmlspecialchars($_GET['status']);
	$duration = htmlspecialchars($_GET['duration']);
	$file_url = htmlspecialchars($_GET['file_url']); // файл крепится с записью, но не воспроизводится, т.к. соединение идет через http а не https
	$order_id = htmlspecialchars($_GET['order_id']); // не совсем понятно куда их крепить
	$date = htmlspecialchars($_GET['date']);
	// $status_call = [ // памятка о том какие бывают call_status
	// 	"" => 1, // оставил сообщение
	// 	"" => 2, // перезвонить позже
	// 	"" => 3, // нет на месте
	// 	"" => 4, // разговор состоялся
	// 	"" => 5, // неверный номер
	// 	"" => 6, // Не дозвонился
	// 	"" => 7, // номер занят
	// ];
	$status_call_arr = [
		"ANSWER"      => "Звонок был принят и обработан сотрудником",
		"BUSY"        => "Входящий звонок был, но линия была занята",
		"NOANSWER"    => "Входящий вызов состоялся, но в течение времени ожидания ответа не был принят сотрудником",
		"CANCEL"      => "Входящий вызов состоялся, но был завершен до того, как сотрудник ответил",
		"CONGESTION"  => "Вызов не состоялся из-за технических проблем",
		"CHANUNAVAIL" => "Вызываемый номер был недоступен",
		"DONTCALL"    => "Входящий вызов был отменен",
		"TORTURE"     => "Входящий вызов был перенаправлен на автоответчик",
	];
	if (!empty($status_call_arr[$status])) {$call_result = $status_call_arr[$status];} else {$call_result = 'Неизвестный статус звонка';}
	$deal = $AmoCRM->getDealByID($order_id);
	$settings_arr = [
		[
			"direction"   => "inbound", // inbound / outbound
			"uniq"        => $id,
			"duration"    => (int)$duration,
			"source"      => 'Roistat',
			"link"        => $file_url,
			"phone"       => $caller,
			"created_at"  => strtotime($date),
			"call_result" => $call_result,
			// "call_status" => 4, // в данном случае ненужна, т.к. непонятно какие будут статусы и как их совмещать
		]
	];
	$call = $AmoCRM->addCallsToDeal($settings_arr);
	if (!empty($call['_embedded']['calls'][0]['id'])) {
		$arr = [
			"status"  =>"ok",
			"call_id" =>$call['_embedded']['calls'][0]['id']
		];
	} else {
		$arr = [
			"status"  =>"error",
			"call_id" =>''
		];
	}
	writeLog('log.txt', 'response ->', $arr);
	echo json_encode($arr, JSON_UNESCAPED_UNICODE);
	// print_r($arr);
}



//==========================================================
//                       HELP функции
//==========================================================

function writeLog($file = '', $method = '', $arr = []) { // запись в лог
	file_put_contents($file, "\n\n", FILE_APPEND);
	file_put_contents($file, "===============\n", FILE_APPEND);
	file_put_contents($file, date('Y-m-d H:i:s')." | $method | ".json_encode($arr, JSON_UNESCAPED_UNICODE), FILE_APPEND);
}





 ?>