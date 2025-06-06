<?php
declare(strict_types=1);

namespace PedidosInternet\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class SendToDistrib
{
    public static function ValidateOrder(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'client_id' => new Assert\Optional([new Assert\Type('string')]),
            ]
        ]);
    }

    public static function ValidateClient(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'order_id' => new Assert\Optional([new Assert\Type('string')]),
            ]
        ]);
    }

}