<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Dto\DtoFamily;
use PrestaShop\PrestaShop\Adapter\Validate;
use PrestaShop\PrestaShop\Adapter\Entity\Db;

class DtoWebProduct
{
    public string $reference;
    public string $description;
    public float $unitsPerBox;
    public float $kilosPerUnity;
    public float $subUnits;
    public bool $isNovelty;
    public array $formats;
    public string $shippingFormat;
    public string $webFamilyId;
    public ?string $webSubFamilyId = "";
    public ?string $webSubSubFamilyId = "";
    public int $taxTypeId;
    public float $taxPercentage;
    public int $groupId;
    public int $providerId;
    public int $brandId;
    public array $imagesInformation;
    public string $iva;
    public int $quantity;
    public float $price;

    public function __construct()
    {
        //$this->formats = new DtoWebProductsFormats();
        //$this->imagesInformation = new DtoWebProductsImagesInformation();
    }

    /**
     * @param array $webProduct
     * @return void
     */
    public static function create(array $webProduct) : ?DtoWebProduct
    {
        $toRet = new DtoWebProduct();
        $toRet->reference = $webProduct['reference'];
        $toRet->description = $webProduct['description'];
        $toRet->unitsPerBox = $webProduct['unitsPerBox'];
        $toRet->kilosPerUnity = $webProduct['kilosPerUnity'];
        $toRet->subUnits = $webProduct['subUnits'];
        $toRet->isNovelty = $webProduct['isNovelty'];

        if (!empty($webProduct['webSubSubFamilyId'])) {
            $toRet->webFamilyId = $webProduct['webSubSubFamilyId'];
        } else {
            if (!empty($webProduct['webSubFamilyId'])) {
                $toRet->webFamilyId = $webProduct['webSubFamilyId'];
            } else {
                $toRet->webFamilyId = $webProduct['webFamilyId'];
            }
        }

        if (empty($toRet->webFamilyId)) {
            return null;
        }

        $toRet->taxTypeId = (int)$webProduct['taxTypeId'];
        $toRet->taxPercentage = $webProduct['taxPercentage'];
        $toRet->groupId = $webProduct['groupId'];
        $toRet->providerId = $webProduct['providerId'];
        $toRet->brandId = $webProduct['brandId'];
        $toRet->imagesInformation = [];
        if (count($webProduct['imagesInformation']) > 0) {
            foreach ($webProduct['imagesInformation'] as $imageInformation) {
                $imageData['id'] = $imageInformation['id'];
                $imageData['sendToWeb'] = $imageInformation['sendToWeb'];
                $toRet->imagesInformation[] = $imageData;
            }
        }
        
        $toRet->iva = $webProduct['iva'];

        $toRet->formats = [];
        foreach ($webProduct['formats'] as $format) {
            $toRet->formats[$format['formatId']] = [
                'description' => $format['description'],
                'quantity' => $format['quantityMultiplier'],
                'kilos' => $format['kilosMultiplier'],
                'default' => $format['isDefaultFormatForProduct'],
            ];
        }

        return $toRet;
    }

    public function toApiArray(bool $encodeAsJson = true): array
    {
        return [
            "reference" => $this->reference,
            "description" => $this->description,
            "unitsPerBox" => $this->unitsPerBox,
            "kilosPerUnity" => $this->kilosPerUnity,
            "subUnits" => $this->subUnits,
            "isNovelty" => $this->isNovelty,
            "formats" => $encodeAsJson ? json_encode($this->formats->toApiArray()) : $this->formats,
            "shippingFormat" => $this->shippingFormat,
            "webFamilyId" => $this->webFamilyId,
            "webSubFamilyId" => $this->webSubFamilyId,
            "webSubSubFamilyId" => $this->webSubSubFamilyId,
            "taxTypeId" => $this->taxTypeId,
            "taxPercentage" => $this->taxPercentage,
            "groupId" => $this->groupId,
            "providerId" => $this->providerId,
            "brandId" => $this->brandId,
            "imagesInformation" => $encodeAsJson ? json_encode($this->imagesInformation->toApiArray()) : $this->imagesInformation,
            "iva" => $this->iva
        ];
    }

    public function createOrSaveProduct(): ?\Product
    {
        
        $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');

        $productId = intval($this->reference);
        $addProduct = false;

        $productFounded = self::getProductByReference($this->reference);

        if (empty($productFounded)) {
            $product = new \Product(null, false, $langDefault);
            $addProduct = true;
            $product->id = $productId;
            $product->reference = $this->reference;
            $product->name = [$langDefault, htmlspecialchars($this->description)];
        } else {
            $product = new \Product($productFounded[0]['id_product'], false, $langDefault);
        }

        $categoryId = DtoFamily::getIdByApiId($this->webFamilyId);

        if (empty($categoryId)) {
            $categoryId = 6;
        } 

        $product->id_category_default = 6 ?? $categoryId;
        $product->redirect_type = '301';
        if(empty($product->price)) {
            $product->price = 0;
        }
        $product->minimal_quantity = 1;
        $product->show_price = 1;
        $product->on_sale = 0;
        $product->online_only = 0;
        $product->meta_description = '';
        $product->id_tax_rules_group = 1;
        $product->weight = $this->kilosPerUnity;

        if (empty($productFounded)) {
            $transliterator = \Transliterator::createFromRules(
                ':: Any-Latin; :: Latin-ASCII; :: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
                \Transliterator::FORWARD
            );
            $normalized = $transliterator->transliterate($this->description);
            $url = str_replace(
                [" ", ".", ",", "&", "/", "+","(",")","´","'","_"],
                ["_", "", "", "", "", "","","","","",""],
                strtolower($normalized)
            );

            $normalizedName = $transliterator->transliterate($product->name[1]);
            $replacedName = str_replace(
                [",", "/", "+","(",")","´","'","_","&amp", ";"],
                ["", "", "","","","","","","&", ""],
                ucwords(strtolower($normalizedName), " ")
            );

            $product->link_rewrite = [$langDefault => $url];
            $product->name[1] = $replacedName;
        }
        
        if ($addProduct) {
            \PrestaShopLogger::addLog('Product ' . $this->reference . ' added', 1);
            $saved = $product->add();
        } else {
            $saved = $product->save();
        }

        $categories = [];

        $single_category = new \Category($categoryId);
				
		$categories[] = $single_category->id;

        $subCategoryId = DtoFamily::getIdByApiId($this->webSubFamilyId);

        if (!empty($subCategoryId)) {
            $single_category = new \Category($subCategoryId);
            $categories[] = $single_category->id;
        }

        $subSubCategoryId = DtoFamily::getIdByApiId($this->webSubSubFamilyId);

        if (!empty($subSubCategoryId)) {
            $single_category = new \Category($subSubCategoryId);
            $categories[] = $single_category->id;
        }
		
        $product->addToCategories($categories);

        if ($saved) {
            return $product;
        } else {
            return null;
        }
    }

    public static function updatePrice(string $reference, float $price, bool $isPvp)
    {
        if ($isPvp) {
            $price /= 1.21;
        }
        $db = Db::getInstance();
        $db->Execute(
            'UPDATE `'. _DB_PREFIX_ . "product` SET `price` = {$price},id_tax_rules_group=1 WHERE `reference` = '{$reference}'");
        $db->Execute(
             "update " . _DB_PREFIX_ ."product_shop
                  left join ps_product ON ps_product_shop.id_product=ps_product.id_product
                  SET ps_product_shop.price={$price}
                  where ps_product.reference='{$reference}'"
        );
    }

    public static function asignProductToPriceCategory(string $reference, float $price, bool $isPvp)
    {
        
        $price = $price * 1.21;

        $product = self::getProductByReference($reference);

        if($product) {
            $productToAsignment = new \Product($product[0]['id_product']);

            self::cleanPriceCategories($productToAsignment);

            if($productToAsignment) {
                switch($price) {
                    case $price > 0 && $price < 10:
                        $productToAsignment->addToCategories(145);
                        $productToAsignment->addToCategories(146);
                        $productToAsignment->addToCategories(147);
                        $productToAsignment->addToCategories(148);
                        break;
                    case $price > 0 && $price < 15:
                        $productToAsignment->addToCategories(146);
                        $productToAsignment->addToCategories(147);
                        $productToAsignment->addToCategories(148);
                        break;
                    case $price > 0 && $price < 20:
                        $productToAsignment->addToCategories(147);
                        $productToAsignment->addToCategories(148);
                        break;
                    case $price > 0 && $price < 30:
                        $productToAsignment->addToCategories(148);
                        break;
                    case $price >= 30 && $price <= 50:
                        $productToAsignment->addToCategories(149);
                        break;
                    default:
                        $productToAsignment->addToCategories(150);
                }
            }
        }
        
    }

    public static function cleanPriceCategories($product)
    {
        $categories = $product->getCategories();
        foreach($categories as $category) {
            if($category == 145 || $category == 146 || $category == 147 || $category == 148 || $category == 149 || $category == 150) {
                $product->deleteCategory($category);
            }
        }
    }

    public static function getProductByReference(string $productReference)
    {
        $db = Db::getInstance();
        $productReferenceConverted = intval($productReference);
        $sql = 'SELECT * FROM `'. _DB_PREFIX_ . "product` WHERE `reference` = {$productReferenceConverted}";
        $result = $db->ExecuteS($sql);
        return $result;
    }

    public static function updateProductName($product_id, $name)
    {
        $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
        $product = new \Product($product_id, false, $langDefault);
        $product->name = $name;
        $product->save();
    }

    public static function updateProductShortDescription($product_id, $shortDescription)
    {
        $langDefault = (int)\Configuration::get('PS_LANG_DEFAULT');
        $product = new \Product($product_id, false, $langDefault);
        $product->description_short = $shortDescription;
        $product->save();
    }

}
