<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoFamily
{
    public function __construct()
    {
        
    }
    
    /**
     * Crea nuevas categorías desde la web
     * No se actualizan las categorías existentes porque no coincidirían las rutas existentes.
     *
     * @param int $langDefault
     * @param array $family
     * @param int $parent
     * @return \Category
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function createCategory(int $langDefault, array $family, int $parent): \Category
    {
        $categoryId = self::getIdByApiId($family['id']);

        if (!empty($categoryId)) {
            return new \Category($categoryId);
        }

        $objects = \Category::searchByName($langDefault, $family['description']);
        
        /** @var \CategoryCore $object */
        if (!empty($objects)) {
            self::addId($objects[0]["id_category"], $family['id']);

            return new \Category($objects[0]["id_category"]);
        }
        
        $object = new \Category();
        $object->name = [$langDefault => $family['description']];
        $object->id_parent = $parent;

        $object->description = [$langDefault => htmlspecialchars($family['description'])];
        $transliterator = \Transliterator::createFromRules(
            ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
            \Transliterator::FORWARD
        );
        $normalized = $transliterator->transliterate($family['description']);
        $url = str_replace([" ", ".", "&"], ["_", "", ""], strtolower($normalized));
        $object->link_rewrite = [$langDefault => $url];

        $object->save();

        self::addId($object->id, $family['id']);

        return $object;
    }

    public static function createCategoryWithTaxonomy(int $langDefault, array $family, int $parent): \Category
    {
        $array = array("B2C- ", "B2C-", "B2C - ");
        $categoryName = str_replace($array,"",$family['description']);

        $objects = \Category::searchByName($langDefault, $categoryName);
        
        /** @var \CategoryCore $object */
        if (!empty($objects)) {
            self::addId($objects[0]["id_category"], (string)$family['id']);

            return new \Category($objects[0]["id_category"]);
        }
        
        $object = new \Category();
        $object->name = [$langDefault => $categoryName];
        $object->id_parent = $parent;

        $object->description = [$langDefault => htmlspecialchars($categoryName)];
        $transliterator = \Transliterator::createFromRules(
            ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
            \Transliterator::FORWARD
        );
        $normalized = $transliterator->transliterate($categoryName);
        $url = str_replace([" ", ".", "&"], ["_", "", ""], strtolower($normalized));
        $object->link_rewrite = [$langDefault => $url];

        $object->save();

        self::addId($object->id, (string)$family['id']);

        return $object;
    }

    public static function getIdByApiId(string $apiId)
    {
        return \Db::getInstance()->getValue("SELECT id_category FROM " . pSQL(_DB_PREFIX_) . 'category WHERE api_id="' . $apiId . '"');
    }

    public static function addId(string $categoryId, string $apiId)
    {
        \Db::getInstance()->execute("UPDATE " . pSQL(_DB_PREFIX_) . 'category SET api_id="' . $apiId . '" WHERE id_category=' . $categoryId);
    }
}
