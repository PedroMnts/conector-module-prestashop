<?php
declare(strict_types=1);

namespace PedidosInternet\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class Configuration
{
    public static function update(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'username' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'password' => new Assert\Required([new Assert\Type('string')]),
                'url' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'url_append' => new Assert\Required([new Assert\Type('string')]),
                'client_id' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'client_secret' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'scope' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'lastSynchronization' => new Assert\Optional(),
            ]
        ]);
    }
}