<?php
namespace Bookstack;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class Bookstack {
	// private $url = 'http://public-panda.ddns.net';
	private $url = 'http://public-panda.duckdns.org';
	// private $token = null;//'Token Rp9aM5zTGltOpI8Kk92mk7NwNsWCsxaC:4mh7tbH8EVYER7vHtqxV1pS9YrJJsHqv';

	public $bookClient;

	public function __construct($token, $debug = false) {
		// $this->token = $token;
		$this->bookClient = new Client([
			'base_uri' => $this->url,
			'headers' => ['Authorization' => $token],
			'debug' => $debug,
			'http_errors' => false,
		]);
	}

	public function getRequest($requestParam)
	{
		$body = '';
		$request_str = '/api/' . $requestParam;
		try {
			$response = $this->bookClient->request('GET', $request_str);
			$code = $response->getStatusCode(); // 200
			$body = $response->getBody();
		} catch (RequestException $e) {
			error_log(Psr7\Message::toString($e->getResponse()));
		}
		return $body;
	}
	public function postRequest($name, $requestObj, $action = 'create', $id = 0)
	{
		$body = '';
		$request_str = '/api/' . $name . ($id ? '/' . $id : '');
		$method = 'POST';
		if ($id) {
			if ($action == 'update') {
				$method = 'PUT';
			} else if ($action == 'delete') {
				$method = 'DELETE';
			}
		}
		try {
			$response = $this->bookClient->request($method, $request_str, ['json' => $requestObj]);
			$code = $response->getStatusCode(); // 200
			$body = $response->getBody();
		} catch (RequestException $e) {
			error_log(Psr7\Message::toString($e->getResponse()));
		}
		return $body;
	}
	public function postFile($uploaded_file_name, $page_id, $uploaded_file) 
	{
		try {
			$response = $this->bookClient->request('POST', '/api/attachments', [
				'multipart' => [
					[
						'name' => 'name',
						'contents' => $uploaded_file_name,
					],
					[
						'name' => 'uploaded_to',
						'contents' => $page_id,
					],
					[
						'name' => 'file',
						'contents' => Psr7\Utils::tryFopen($uploaded_file, 'r'),
						'filename' => $uploaded_file_name,
					]
				]
			]);
			$code = $response->getStatusCode(); // 200
			$body = $response->getBody();
		} catch (RequestException $e) {
			error_log(Psr7\Message::toString($e->getResponse()));
		}
		return $body;
	}
}

