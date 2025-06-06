<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoFeature
{
    public function __construct()
    {
        
    }
    
    /**
     * Crea nuevas caracterśtica en la web
     * No se actualizan las características existentes.
     *
     * @param int $langDefault
     * @param int $currentShopId
     * @param array $featureValue
     * @param array $template
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function createFeature(int $langDefault, int $currentShopId, array $featureValue): void
    {
        $feature = New \Feature();

        if( \Feature::getFeature($langDefault, $featureValue['id'])) {
            echo '<pre>';
            dump('Feature con id ' . $featureValue['id'] . ', existe');
            echo '</pre>';
        } else {

            echo '<pre>';
            dump('Feature con id ' . $featureValue['id'] . ',NO existe');
            echo '</pre>';

            $data_feature = [
                "id_feature" => $featureValue["id"],
                "position" => $feature::getHigherPosition() + 1,
            ];

            \Db::getInstance()->insert('feature',$data_feature);

            $data_feature_shop = [
                "id_feature" => $featureValue["id"],
                "id_shop" => $currentShopId,
            ];

            \Db::getInstance()->insert('feature_shop',$data_feature_shop);

            $data_feature_lang = [
                "id_feature" => $featureValue["id"],
                "id_lang" => $langDefault,
                "name" => $featureValue["name"],
            ];

            \Db::getInstance()->insert('feature_lang',$data_feature_lang);

        }

    }

}
