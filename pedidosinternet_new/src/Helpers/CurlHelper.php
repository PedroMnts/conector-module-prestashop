<?php
declare(strict_types=1);

namespace PedidosInternet\Helpers;
use PrestaShopLogger;

class CurlHelper
{
	private static $logFile = __DIR__ . '/curl_errors.log';

	private static function logError(string $message): void
	{
		// Formato de log: [Fecha y hora] Mensaje de error
		$logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

		// Escribe el mensaje en el archivo de log
		file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
	}
	
    private static function initPetition(string $url, string $accessToken)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return $ch;
    }

    private static function initPetitionFormData(string $url, string $accessToken)
    {
        $boundary = uniqid(); 

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return $ch;
    }

    public static function executeAndCheckCode($ch, int $expectedCode, bool $mustReturn = true): ?array
    {

        $serverOutput = curl_exec($ch); 
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);

        if ($serverOutput === false || $responseCode !== $expectedCode) {
            $errorMessage = "Error en cURL:\n" .
                "URL: " . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . "\n" .
                "Código de respuesta HTTP: $responseCode\n" .
                "Error cURL: $curlError\n" .
                "Output: " . ($serverOutput ?? 'Ninguno');
            self::logError($errorMessage);

            curl_close($ch);
            throw new \Exception('Error al ejecutar la solicitud cURL. Ver el log para más detalles.');
        }
		
        curl_close($ch);
				
        if ($mustReturn) {
            $data = json_decode($serverOutput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
                self::logError("Error al decodificar JSON: $jsonError\nRespuesta: $serverOutput");
                throw new \Exception('Error al decodificar JSON. Ver el log para más detalles.');
            }
            return $data;
        }
            
		return null;
    }

    public static function executeAndCheckCodeClient($ch, int $code, bool $mustReturn = true): ?array
    {

        $serverOutput = curl_exec($ch); 
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        list( $header, $contents ) = preg_split( '/([\r\n][\r\n])\\1/', $serverOutput, 2 );

        if($serverOutput === false) {
            throw new \Exception('Error en la conexión con la API: ' . curl_error($ch));
        }
        
        if (!curl_errno($ch)) {
            if ($responseCode !== $code) {

  		        PrestaShopLogger::addLog('Content: '. $contents, 1);
                throw new \Exception('Error en la conexión con la API. Revise el Log de PrestaShop');
				
            }
        }
        curl_close($ch);

        if ($mustReturn) {
            return json_decode($contents, true);
        } else {
            return null;
        }
    }

    public static function getPetition(string $url, string $accessToken, ?array $parameters = null)
    {
        if ($parameters != null) {
            $url .= '?' . http_build_query($parameters);
        }
        return self::initPetition($url, $accessToken);
    }

    public static function postPetition(
        string $url,
        string $accessToken,
        $parameters = null,
        bool $process = true
    ) {
        $ch = self::initPetition($url, $accessToken);
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($parameters != null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $process ? http_build_query($parameters) : $parameters);
        }
        return $ch;
    }

    public static function postPetitionFormData(
        string $url,
        string $accessToken,
        $parameters = null,
        bool $process = true
    ) {
        $ch = self::initPetitionFormData($url, $accessToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        $parameters = self::normalizeArray($parameters);
        if ($parameters != null) {
            if ($process) { 
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
            }
        }
        return $ch;
    }

    public static function normalizeArray($array) {
        foreach ($array as &$el) {
            if (is_bool($el)) {
            $el = ($el) ? "true" : "false";
            } elseif (is_array($el)) {
            $el = self::normalizeArray($el);
            }
        }
        return $array;
    }

    public static function putPetition(string $url, string $accessToken, ?array $parameters = null)
    {
        $ch = self::initPetition($url, $accessToken);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        return $ch;
    }
}
