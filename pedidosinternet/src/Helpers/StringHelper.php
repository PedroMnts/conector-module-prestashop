<?php
declare(strict_types=1);

namespace PedidosInternet\Helpers;

class StringHelper
{
    /**
     * Intenta dividir un string por el último espacio.
     * En caso de que no tenga espacios devuelve la cadena por duplicado.
     * Y en caso de que la cadena esté vacia devolverá en ambos "No definido"
     *
     * @see se usa para rellenar el nombre y el apellido del customer o de la dirección que son obligatorios
     * @param string $stringWithSpaces
     * @return array{string, string}
     */
    public static function separateNames(string $stringWithSpaces): ?array
    {
        if (empty(trim($stringWithSpaces))) {
            return ['No definido', 'No definido'];
        }

        // Hay que eliminar los números y los espacios alrededor
        $stringWithSpaces = trim(preg_replace('/\d+/', '', $stringWithSpaces));

        $spacePos = strrpos($stringWithSpaces, " ");
        $firstname = $lastname = "";
        if ($spacePos === false) {
            $firstname = $stringWithSpaces;
            $lastname = $stringWithSpaces;
        } else {
            $firstname = str_replace([',','.', '(', ')', '@'], "", substr($stringWithSpaces, 0, $spacePos));
            $lastname = str_replace([',','.', '(', ')', '@'], "", substr($stringWithSpaces, $spacePos + 1));
        }

        if (empty($lastname) && empty($firstname)) {
            return null;
        }
        if (empty($lastname)) {
            $lastname = ' ';
        }
        if (empty($firstname)) {
            $firstname = ' ';
        }

        return [$firstname, $lastname];
    }
}
