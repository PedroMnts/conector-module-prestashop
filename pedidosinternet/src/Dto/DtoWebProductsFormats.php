<?php

namespace VorticeSoft\PedidosInternet\Dto;

class DtoWebProductsFormats
{

    public int $formatId;
    public string $description;
    public int $quantityMultiplier;
    public int $kilosMultiplier;
    public bool $isDefaultFormatForProduct;


    /**
     * @param array $webProducts
     * @return DtoWebProductsFormats
     */
    public static function create(array $webProducts) : DtoWebProductsFormats
    {
        $toRet = new DtoWebProductsFormats();

        $toRet->formatId = $webProducts['formatId'];
        $toRet->description = $webProducts['description'];
        $toRet->quantityMultiplier = $webProducts['quantityMultiplier'];
        $toRet->kilosMultiplier = $webProducts['kilosMultiplier'];
        $toRet->isDefaultFormatForProduct = $webProducts['isDefaultFormatForProduct'];
        return $toRet;
    }

    /**
     * @return array
     */
    public function toApiArray() : array
    {
        return [
            'formatId' => $this->formatId,
            'description' => $this->description,
            'quantityMultiplier' => $this->quantityMultiplier,
            'kilosMultiplier' => $this->kilosMultiplier,
            'isDefaultFormatForProduct' => $this->isDefaultFormatForProduct,
        ];
    }
}