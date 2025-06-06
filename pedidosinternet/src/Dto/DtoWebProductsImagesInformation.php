<?php
declare(strict_types=1);

namespace VorticeSoft\PedidosInternet\Dto;

class DtoWebProductsImagesInformation
{
    private int $id;
    private string $seoName;
    private bool $isDefaultImage;
    private string $lastUpdateDate;
    private bool $sendToWeb;

    public static function create($imagesInformation): DtoWebProductsImagesInformation
    {
        $toRet = new DtoWebProductsImagesInformation();

        $toRet->id = $imagesInformation['id'];
        $toRet->seoName = $imagesInformation['seoName'];
        $toRet->isDefaultImage = $imagesInformation['isDefaultImage'];
        $toRet->lastUpdateDate = $imagesInformation['lastUpdateDate'];
        $toRet->sendToWeb = $imagesInformation['sendToWeb'];
        return $toRet;
    }

    /**
     * @return array
     */
    public function toApiArray(): array
    {
        return [
            "id" => $this->id,
            "seoName" => $this->seoName,
            "isDefaultImage" => $this->isDefaultImage,
            "lastUpdateDate" => $this->lastUpdateDate,
            "sendToWeb" => $this->sendToWeb,
        ];
    }
}
