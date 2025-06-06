<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use Product;
use Category;
use Feature;

class DtoFamiliesWithTemplates
{
    public function __construct()
    {
        
    }
    
    /**
     * Asigna una categoría para una característica para el mismo nombre
     *
     * @return \void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function asignValues(): void
    {

        $coincidences = 0;

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'product`';
        $allProducts = \Db::getInstance()->executeS($sql);

        //Obtener todas las categorías
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'category`';
        $allCategories = \Db::getInstance()->executeS($sql);

        foreach($allProducts as $product) //recorrer todos los productos
        {
            //Obtener todas los valores de características de un producto
            $sql = 'SELECT `id_feature_value` FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` ='. $product['id_product'];
            $ProductFeatureValues = \Db::getInstance()->executeS($sql);
            
            foreach($ProductFeatureValues as $feature) {
                //Obtener el nombre o valor de la característica
                $sql = 'SELECT `value` FROM `' . _DB_PREFIX_ . 'feature_value_lang` WHERE `id_feature_value` ='. $feature['id_feature_value'];
                $featuresSelected = \Db::getInstance()->executeS($sql);
                                
                foreach($allCategories as $category) 
                {
                    //Comparar el nombre de cada categoría con las categorías seleccionadas
                    //Si coinciden, añadir la categoría al producto.
                    foreach($featuresSelected as $featureValue) {
                        
                        $sql = 'SELECT `id_category`, `name` FROM `' . _DB_PREFIX_ . 'category_lang` WHERE `id_category` ='. $category['id_category'];
                        $categorySelected = \Db::getInstance()->executeS($sql);

                        if(strtolower($categorySelected[0]["name"]) == strtolower($featureValue["value"])) {
                            echo '<pre>';
                            print_r($categorySelected[0]["name"] . " coincide con " . $featureValue["value"]);
                            echo '</pre>';
                            
                            $coincidences++;

                            $idCategorySelected = $categorySelected[0]['id_category'];

                            $productToAsignment = new Product($product['id_product']);

                            $actualProductCategories = $productToAsignment->getCategories();

                            if(!in_array($idCategorySelected, $actualProductCategories)) {

                                echo '<pre>';
                                print_r('Categoría ' . $categorySelected[0]['name'] . ' asignada al producto con refrencia '. $product['reference']);
                                echo '</pre>';
                                
                                $productToAsignment->addToCategories($idCategorySelected);


                            } else {
                                echo '<pre>';
                                print_r('La categoría ' . $categorySelected[0]['name'] .' ya estaba asignada');
                                echo '</pre>';
                            }
                        }
                    
                    }

                }

            }
        }

        echo '<pre>';
        dump($coincidences . ' coincidencias');
        echo '</pre>';
    
    }
    public static function assignToFlashOffersCategory($product_id): void
    {
        $FlashOffersIdCategory = 3;
        $productToAsignment = new Product($product_id);
        $productToAsignment->addToCategories($FlashOffersIdCategory);
    }
}
