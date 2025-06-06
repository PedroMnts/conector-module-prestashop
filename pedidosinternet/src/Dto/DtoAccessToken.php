<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoAccessToken
{
    public string $accessToken;
    public string $refreshToken;
    public \DateTimeInterface $expirationTime;

    public static function create(string $curl_answer)
    {
        $temporal = json_decode($curl_answer, true);

        $toRet = new DtoAccessToken();
        $toRet->accessToken = $temporal['access_token'];
        $toRet->refreshToken = $temporal['refresh_token'];

        $dateTime = new \DateTime();
        $dateTime->add(new \DateInterval("PT{$temporal['expires_in']}S"));
        
        $toRet->expirationTime = $dateTime;

        // Crear un datetime y le sumas un dateinterval con el tiempo del campo expires_in

        return $toRet;
    }

    public static function createFromDatabase(array $row): DtoAccessToken
    {
        $dtoAccessToken = new DtoAccessToken();
        $dtoAccessToken->accessToken = $row['access_token'];
        $dtoAccessToken->refreshToken = $row['refresh_token'];
        try {
            if (!empty($row['expiration_token'])) {
                $dtoAccessToken->expirationTime = new \DateTimeImmutable($row['expiration_token']);
            } else {
                $dtoAccessToken->expirationTime = new \DateTimeImmutable();
            }
        } catch (\Exception $e) {
            $dtoAccessToken->expirationTime = new \DateTimeImmutable();
        }

        return $dtoAccessToken;
    }
}