<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoFeatureValue
{
    public function __construct()
    {
        
    }
    
    /**
     * Crea nuevos valores de caracterśtica en la web
     * No se actualizan los valores de características existentes.
     *
     * @param int $langDefault
     * @param array $featureValue
     * @param array $taxonomies
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function createFeatureValue(int $langDefault, array $featureValue, array $taxonomies): void
    {
        
        if($featureValue["taxonomyId"] > 0) {

            foreach($taxonomies as $taxonomy) {
                
                if($featureValue["taxonomyId"] == $taxonomy["id"]) {

                    $idFeatureValueAlreadyExist = \Db::getInstance()->getValue('
                        SELECT `id_feature`
                        FROM ' . _DB_PREFIX_ . 'feature_value fp
                        WHERE `id_feature_value` = ' . (int) $taxonomy["id"]);

                    if ($idFeatureValueAlreadyExist) {
                        echo '<pre>';
                            dump('FeatureValue ' . $taxonomy["id"] . ', existe');
                        echo '</pre>';
                    } else {
                        echo '<pre>';
                            dump('FeatureValue ' . $taxonomy["id"] . ', NO existe');
                        echo '</pre>';
                        self::addFeatureValueToDataBase($langDefault, $featureValue, $taxonomy);
                    }

                    if($taxonomy["terms"]) {

                        foreach($taxonomy["terms"] as $term) {
                            
                            self::addFeatureValueToDataBase($langDefault, $featureValue, $taxonomy, $term);
                            
                            if($term["childrenTerms"]) {

                                foreach($term['childrenTerms'] as $childTerm) {
                                
                                    self::addFeatureValueToDataBase($langDefault, $featureValue, $taxonomy, $term, $childTerm);

                                    if($childTerm["childrenTerms"]) {

                                        foreach($childTerm["childrenTerms"] as $subChildTerm) {
                                            self::addFeatureValueToDataBase($langDefault, $featureValue, $taxonomy, $term, $subChildTerm);
                                        }
                                    }

                                }
                            }
                        }
                    }

                }
            }
        }

    }

    public function addFeatureValueToDataBase(int $langDefault, array $featureValue, array $taxonomy, array $term = null, array $childTerm = null)
    {
        $id_feature_value = $taxonomy["id"];
        $value = $taxonomy["description"];
        dump($id_feature_value . " - Taxononmy: " . $value);

        if($term) {
            if($childTerm) {
                $id_feature_value = $taxonomy["id"] . 0 . $childTerm["id"];
                $value = $childTerm["description"];
                dump($id_feature_value . " - ChildTerm: " . $value);
            } else {
                $id_feature_value = $taxonomy["id"] . 0 . $term["id"];
                $value = $term["description"];
                dump($id_feature_value . " - Term: " . $value);
            }
        }

        self::insertFeatureValue($id_feature_value, $featureValue);

        self::insertFeatureValueLang($id_feature_value, $langDefault, $value);
    }

    public static function setNumbericFeatureValues(int $langDefault, array $featureValue): void 
    {
        for ($i = 1; $i <= 5; $i++) {

            $idFeatureValue = $featureValue["id"] . $i;

            $existOnDb = \Db::getInstance()->getValue('
                            SELECT `id_feature`
                            FROM ' . _DB_PREFIX_ . 'feature_value
                            WHERE `id_feature_value` = ' . (int) $idFeatureValue . ' AND `id_feature` = ' . (int) $featureValue["id"] . ' AND `custom` = 0');

            if ($existOnDb) {
                return;
            }         

            self::insertFeatureValue($idFeatureValue, $featureValue);

            self::insertFeatureValueLang($idFeatureValue, $langDefault, $i);

        }

    }
    
    public function insertFeatureValue($id_feature_value, $featureValue, $custom = 0): void
    {
        $existOnDb = \Db::getInstance()->getValue('
                            SELECT `id_feature`
                            FROM ' . _DB_PREFIX_ . 'feature_value
                            WHERE `id_feature_value` = ' . (int) $id_feature_value . ' AND `id_feature` = ' . (int) $featureValue["id"] . ' AND `custom` = 0');

        if ($existOnDb) {
            return;
        }  

        $data_feature_value = [
            "id_feature_value" => $id_feature_value,
            "id_feature" => $featureValue["id"],
            "custom" => $custom,
        ];

        \Db::getInstance()->insert('feature_value',$data_feature_value);
    }

    public function insertFeatureValueLang($id_feature_value, $langDefault, $value): void
    {
        $existOnDb = \Db::getInstance()->getValue('
                            SELECT `id_feature_value`
                            FROM ' . _DB_PREFIX_ . 'feature_value_lang
                            WHERE `id_feature_value` = ' . (int) $id_feature_value . ' AND `value` = ' . (int) $value . ' AND `id_lang` = 1');

        if ($existOnDb) {
            return;
        } 
        
        $data_feature_value_lang = [
            "id_feature_value" => $id_feature_value,
            "id_lang" => $langDefault,
            "value" => $value,
        ];

        \Db::getInstance()->insert('feature_value_lang',$data_feature_value_lang);

    }
}
