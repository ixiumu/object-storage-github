<?php
/**
 * Github Api
 * @author niqingyang,xiumu
 */

use GuzzleHttp\Exception\ClientException;

class GithubSDK {
	// 成功
	const API_SUCCESS = 0;
	
	// 参数错误
	const API_PARAMS_ERROR = - 1;
	
	// 网络错误
	const API_NETWORK_ERROR = - 2;

	private static $client = null;
	private static $options = null;

	public function __construct($options = []) {
		if ( !self::$options ) {
			self::$options = $options;
		}

		if ( !self::$client ) {
			self::$client = new \GuzzleHttp\Client([
				'verify' => false,
				'base_uri' => 'https://api.github.com/',
				'headers' => [
					'Content-Type' => 'application/json'
				]
			]);
		}
	}

	/**
	 * 测试 README.md
	 */
	public function test() {
		return self::getSha('README.md');
	}
	
	/**
	 * 获取文件的 sha 值
	 */
	public static function getSha($path) {
		if(!$path) {
			return array(
				'code' => static::API_PARAMS_ERROR,
				'message' => 'path is empty'
			);
		}
		
		try {
			// 通过 head 请求获取 sha 哈希值
			$response = self::$client->head( self::getTokenUrl($path) );
			$sha = trim(current($response->getHeader("etag")), "\"");
			return $sha;
		} catch (ClientException $e) {
			return array(
				'code' => static::API_NETWORK_ERROR,
				'message' => $e->getMessage()
			);
		}
	}
	
	/**
	 * 上传文件
	 *
	 * @param string $srcPath 本地文件路径
	 * @param string $dstPath 上传的文件路径
	 * @param string $insertOnly 同名文件是否覆盖
	 * @return array
	 */
	public static function upload($srcPath, $dstPath, $message = ':art:', $insertOnly = false, $sha = null) {
		if( !file_exists($srcPath) ) {
			return array(
				'code' => static::API_PARAMS_ERROR,
				'message' => 'file ' . $srcPath . ' not exists',
				'data' => []
			);
		}
		
		try {
			$content = file_get_contents($srcPath);
			$body = [
				'message' => $message,
				'branch' => self::$options['branch'],
				'content' => base64_encode($content)
			];
			
			if($insertOnly == false && ! empty($sha)) {
				$body['sha'] = $sha;
			}
			
			$response = self::$client->put( self::getTokenUrl($dstPath), [
				'body' => json_encode($body)
			]);
			
			// 资源已被创建过，而且此次没有任何改动
			if($response->getStatusCode() == 201) {
				
			}
			
			$data = json_decode($response->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);
			
			return array(
				'code' => 0,
				'data' => $data,
				'message' => 'ok'
			);
		} catch(ClientException $e) {
			if($e->getCode() == 409) {
				return array(
					'code' => $e->getCode(),
					'message' => '资源冲突',
					'data' => []
				);
			}
			// 更新资源却没有提供 sha 签名值
			else if($e->getCode() == 422 && $insertOnly == false) {
				$sha = self::getSha($path);
				
				if(empty($sha)) {
					return array(
						'code' => $e->getCode(),
						'data' => [],
						'message' => '更新时获取 sha 失败'
					);
				}
				
				return static::upload($srcPath, $dstPath, $message, $insertOnly, $sha);
			} else {
				return array(
					'code' => $e->getCode(),
					'data' => [],
					'message' => $e->getMessage()
				);
			}
		}
	}
	
	/**
	 * 删除文件
	 *
	 * @param string $path 文件路径
	 */
	public static function delFile($path, $message=':fire:') {
		if(!$path) {
			return array(
				'code' => static::API_PARAMS_ERROR,
				'message' => 'path is empty'
			);
		}
		
		try {
			$sha = self::getSha($path);
			
			if(!$sha) {
				return false;
			}
			
			$response = self::$client->delete( self::getTokenUrl($path), [
				'body' => json_encode([
					'message' => $message,
					'branch' => self::$options['branch'],
					'sha' => $sha
				])
			]);
			
			if($response->getStatusCode() == 200) {
				return true;
			}
			
			return false;
		} catch(ClientException $e) {
			return array(
				'code' => static::API_NETWORK_ERROR,
				'message' => $e->getMessage()
			);
		}
	}

	private static function getTokenUrl($path) {
		static $url = null;

		if (!$url) {
			// 过滤不支持的路径
			$path = preg_replace('#/+#', '/', $path);

			// 移动 wp-content/uploads 到根路径
			$path = str_replace('wp-content/uploads/', '', $path);

			$options = self::$options;
			$url = strtr('/repos/{owner}/{repo}/contents/{path}?access_token={token}&ref={branch}', [
				'{owner}' => $options['owner'],
				'{repo}' => $options['repo'],
				'{path}' => ltrim($path, '/'),
				'{token}' => $options['token'],
				'{branch}' => $options['branch']
			]);
		}

		return $url;
	}
}