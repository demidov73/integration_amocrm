<?php 
/**
 * Работа с REST Api AmoCRM
 */
class AmoCRM
{
	public static $link;
	public static $client_id;
	public static $client_secret;
	public static $redirect_uri;
	public static $is_connect_db;
	public $db;
	function __construct($subdomain = '', $c_i = '', $c_s = '', $r_u = '') // subdomain, client_id, client_secret, redirect_uri (Установка значений для работы с API)
	{
		self::$link = 'https://'.$subdomain.'.amocrm.ru';
		self::$client_id = $c_i;
		self::$client_secret = $c_s;
		self::$redirect_uri = $r_u;
		self::$is_connect_db = false;
	}

// ==================================================
// ==================================================
// ==================================================
// ==================================================
// ==================================================

	function setDB($db_ip='', $db_login='', $db_password='', $db_table='') { // Создает соединение с БД
		$this->$db = mysqli_connect($db_ip, $db_login, $db_password, $db_table); // подключаемся к БД
		if (mysqli_connect_error() == NULL) {$result_connect = false;} else {$result_connect = mysqli_connect_error();}
		if ($result_connect !== false) {
			self::$is_connect_db = false;
			return ["error_code"=>401, "error_message"=>$result_connect];
		} else {
			self::$is_connect_db = true;
			return $this->$db;
		}
	}

	function saveTokens($access = '', $refresh = '') { // Сохраняет оба токена в БД
		if (!self::$is_connect_db) {return ["error_code"=>401, "error_message"=>'Ошибка: Ошибка авторизации! База данных не подключена!'];}
		if ($access == '' || $refresh == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];}
		$access = mysqli_real_escape_string($this->$db, $access);
		$refresh = mysqli_real_escape_string($this->$db, $refresh);
		$count = (mysqli_fetch_row(mysqli_query($this->$db, "SELECT COUNT(*) FROM `tokens`")))[0];
		if ($count == 0) {
			$insert_str = "INSERT INTO `tokens` (access_token,	refresh_token) VALUES ('$access', '$refresh')";
			mysqli_query($this->$db, $insert_str);
		} else {
			$tokens = mysqli_fetch_row(mysqli_query($this->$db, "SELECT id FROM `tokens`"));
			$update_str = "UPDATE `tokens` SET `access_token`='$access', `refresh_token`='$refresh' WHERE `id`='".$tokens[0]."'";
			mysqli_query($this->$db, $update_str);
		}
	}

	function getToken($tokenName = '') { // access или refresh. Получает токен из БД
		if (!self::$is_connect_db) {return ["error_code"=>401, "error_message"=>'Ошибка: Ошибка авторизации! База данных не подключена!'];}
		if ($tokenName == 'access' || $tokenName == 'refresh') {
			$token_full_name = $tokenName.'_token';
			$token = mysqli_fetch_row(mysqli_query($this->$db, "SELECT ".$token_full_name." FROM `tokens`"));
			if (!empty($token[0])) {
				return $token[0];
			} else {
				return ["error_code"=>401, "error_message"=>'Ошибка: Токены отсутствуют в бд! Выполните авторизацию с помощью кода авторизации.'];
			}
		} else {
			return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];
		}
	}

	function accessTokenRefreshToken() { // Обновляет access и refresh токен по старому refresh токену
		$refreshToken = $this->getToken('refresh');
		if (is_array($refreshToken)) {return $refreshToken;} 
		$data = [
		    'client_id' => self::$client_id,
		    'client_secret' => self::$client_secret,
		    'grant_type' => 'refresh_token',
		    'refresh_token' => $refreshToken,
		    'redirect_uri' => self::$redirect_uri,
		];
		$response = $this->request(self::$link.'/oauth2/access_token', ['Content-Type:application/json'], 'POST', $data);
		$this->saveTokens($response['access_token'], $response['refresh_token']);
		return $response;
	}

	function accessTokenAuthorizationCode($authCode = '') { // Возвращает access токен по коду авторизации
		$data = [
		    'client_id' => self::$client_id,
		    'client_secret' => self::$client_secret,
		    'grant_type' => 'authorization_code',
		    'code' => $authCode,
		    'redirect_uri' => self::$redirect_uri,
		];
		$response = $this->request(self::$link.'/oauth2/access_token', ['Content-Type:application/json'], 'POST', $data);
		$this->saveTokens($response['access_token'], $response['refresh_token']);
		return $response;
	}

// ==================================================
// ==================================================
// ==================================================
// ==================================================
// ==================================================

	function getUserByID($id = '')  { // Возвращает всех пользователей
		if ($id == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];}
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/users/'.$id, ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getUsers()  { // Возвращает всех пользователей
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/users', ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getCustomFields()  { // Возвращает все статусы в сделках
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/leads/custom_fields', ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getPipelines()  { // Возвращает воронки со всеми их статусами
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/leads/pipelines', ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getTotalCountListContact($params = '') { // Возвращает количество сделок по определенному фильтру
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;}
		$final_count = 0;
		$page = 1;
		while (true) {
			$response = $this->request(self::$link.'/api/v4/contacts'.$params."&page=$page", ['Authorization: Bearer '.$access_token], 'GET', []);
			if (!empty($response)) {
				$final_count += count($response['_embedded']['contacts']);
			} else {
				break;
			}
			$page++;
		}
		$this->accessTokenRefreshToken();
		return $final_count;
	}

	function getListContact($params = '') { // 
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/contacts'.$params, ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function addContact($arr = []) { // 
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/contacts', ['Authorization: Bearer '.$access_token], 'POST', $arr);
		$this->accessTokenRefreshToken();
		return $response;
	}


	function getListCompanyByID($id_comp = '', $params = '') { // Возвращает все сделки в AmoCRM
		if ($id_comp == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];}
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link."/api/v4/companies/$id_comp".$params, ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function addCommentToDeal($id = '', $arr = []) {
		if ($id == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request!'];}
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link."/api/v4/leads/$id/notes", ['Authorization: Bearer '.$access_token], 'POST', $arr);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getEventsByID($id = '') {
		if ($id == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];}
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link."/api/v4/events/$id", ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getEvents($params = '') {
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link."/api/v4/events?$params", ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function getTotalCountListDeal($params = '') { // Возвращает количество сделок по определенному фильтру
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;}
		$final_count = 0;
		$page = 1;
		while (true) {
			$response = $this->request(self::$link.'/api/v4/leads'.$params."&page=$page", ['Authorization: Bearer '.$access_token], 'GET', []);
			if (!empty($response)) {
				$final_count += count($response['_embedded']['leads']);
			} else {
				break;
			}
			$page++;
		}
		$this->accessTokenRefreshToken();
		return $final_count;
	}

	function getListDeal($params = '') { // Возвращает все сделки в AmoCRM
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/leads'.$params, ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}
	function getDealByID($id = '') {
		if ($id == '') {return ["error_code"=>400, "error_message"=>'Ошибка: Bad request'];}
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link."/api/v4/leads/$id", ['Authorization: Bearer '.$access_token], 'GET', []);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function addCallsToDeal($arr = []) { // 
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/calls', ['Authorization: Bearer '.$access_token], 'POST', $arr);
		$this->accessTokenRefreshToken();
		return $response;
	}

	function addTasksToDeal($arr = []) { // 
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/tasks', ['Authorization: Bearer '.$access_token], 'POST', $arr);
		$this->accessTokenRefreshToken();
		return $response;
	}



	function addDealAndContact($arr = []) { // Добавляет в AmoCRM сделку с контактом
		$access_token = $this->getToken('access');
		if (is_array($access_token)) {return $access_token;} 
		$response = $this->request(self::$link.'/api/v4/leads/complex', ['Authorization: Bearer '.$access_token, 'Content-Type:application/json'], 'POST', $arr);
		$this->accessTokenRefreshToken();
		return $response;
	}



// ==================================================
// ==================================================
// ==================================================
// ==================================================
// ==================================================



	function request($final_url = '', $headers = [], $method = 'GET', $data_send = []) { // Функция по отправке CURL запросов к API AMOCRM
		$curl = curl_init(); //Сохраняем дескриптор сеанса cURL
		/** Устанавливаем необходимые опции для сеанса cURL  */
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl,CURLOPT_URL, $final_url);
		curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
		// curl_setopt($curl,CURLOPT_HEADER, false);
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data_send));
		$out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$code = (int)$code;
		$errors = [
		    400 => 'Bad request',
		    401 => 'Unauthorized',
		    403 => 'Forbidden',
		    404 => 'Not found',
		    500 => 'Internal server error',
		    502 => 'Bad gateway',
		    503 => 'Service unavailable',
		];

		try {
			/** Если код ответа не успешный - возвращаем сообщение об ошибке  */
			if ($code < 200 || $code > 204) {
				throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
			}
		} catch(Exception $e) {
			return ["error_code"=>$e->getCode(), "error_message"=>'Ошибка: '.$e->getMessage(), "error_request"=>$out];
		}
		$response = json_decode($out, true);
		return $response;
	}

	function __destruct()
	{
	}

}

 ?>