<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoOrderNotesKitLine
{
    public string $productReference;
    public int $quantity;
    public int $kilos;
    public string $mode;
    public float $price;
    public float $percentageDiscount;
    public float $percentageDiscount2;
    public float $percentageDiscount3;
    public float $percentageDiscount4;
    public float $percentageCurrencyPercentagePVP;
    public float $percentageCurrencyPercentagePVPPro;
    public bool $hasToPrint;
    public string $kitReference;
    public float $buyingPrice;
    public bool $hasToApplyCommercialTerms;
    public int $binary;
    public float $percentage;
    public float $b2BPrice;
    public float $b2BUnitPrice;


    /**
     * @param array $line
     * @return DtoOrderNotesKitLine
     */
    public static function create(array $line) : DtoOrderNotesKitLine
    {
        $toRet = new DtoOrderNotesKitLine();

        $toRet->productReference = $line['productReference'];
        $toRet->quantity = $line['reference'];
        $toRet->kilos = $line['description'];
        $toRet->mode = $line['formatForPrice'];
        $toRet->price = $line['price'];
        $toRet->percentageDiscount = $line['percentageDiscount'];
        $toRet->percentageDiscount2 = $line['percentageDiscount2'];
        $toRet->percentageDiscount3 = $line['percentageDiscount3'];
        $toRet->percentageDiscount4 = $line['percentageDiscount4'];
        $toRet->percentageCurrencyPercentagePVP = $line['percentageCurrencyPercentagePVP'];
        $toRet->percentageCurrencyPercentagePVPPro = $line['percentageCurrencyPercentagePVPPro'];
        $toRet->hasToPrint = $line['hasToPrint'];
        $toRet->kitReference = $line['kitReference'];
        $toRet->buyingPrice = $line['buyingPrice'];
        $toRet->hasToApplyCommercialTerms = $line['hasToApplyCommercialTerms'];
        $toRet->binary = $line['binary'];
        $toRet->percentage = $line['percentage'];
        $toRet->b2BPrice = $line['b2BPrice'];
        $toRet->b2BUnitPrice = $line['b2BUnitPrice'];

        return $toRet;
    }

    public function toApiArray() : array
    {
        return [
            "productReference" => $this->productReference ?? "0",
            "quantity" => $this->quantity ?? 0,
            "kilos" => $this->kilos ?? 0,
            "mode" => $this->mode ?? "0",
            "price" => $this->price ?? 0.0,
            "percentageDiscount" => $this->percentageDiscount ?? 0.0,
            "percentageDiscount2" => $this->percentageDiscount2 ?? 0.0,
            "percentageDiscount3" => $this->percentageDiscount3 ?? 0.0,
            "percentageDiscount4" => $this->percentageDiscount4 ?? 0.0,
            "percentageCurrencyPercentagePVP" => $this->percentageCurrencyPercentagePVP ?? 0.0,
            "percentageCurrencyPercentagePVPPro" => $this->percentageCurrencyPercentagePVPPro ?? 0.0,
            "hasToPrint" => $this->hasToPrint ?? true,
            "kitReference" => $this->kitReference ?? "0",
            "buyingPrice" => $this->buyingPrice ?? 0.0,
            "hasToApplyCommercialTerms" => $this->hasToApplyCommercialTerms ?? true,
            "binary" => $this->binary ?? 0,
            "percentage" => $this->percentage ?? 0.0,
            "b2BPrice" => $this->b2BPrice ?? 0.0,
            "b2BUnitPrice" => $this->b2BUnitPrice ?? 0.0
        ];
    }


    public static function fromPrestashop(): DtoOrderNotesKitLine
    {

        $toRet = new DtoOrderNotesKitLine();
        $toRet->productReference = "0";
        $toRet->quantity = 0;
        $toRet->kilos = 0;
        $toRet->mode = "0";
        $toRet->price = 0.0;
        $toRet->percentageDiscount = 0.0;
        $toRet->percentageDiscount2 = 0.0;
        $toRet->percentageDiscount3 = 0.0;
        $toRet->percentageDiscount4 = 0.0;
        $toRet->percentageCurrencyPercentagePVP = 0.0;
        $toRet->percentageCurrencyPercentagePVPPro = 0.0;
        $toRet->hasToPrint = true;
        $toRet->kitReference = "0";
        $toRet->buyingPrice = 0.0;
        $toRet->hasToApplyCommercialTerms = true;
        $toRet->binary = 0;
        $toRet->percentage = 0.0;
        $toRet->b2BPrice = 0.0;
        $toRet->b2BUnitPrice = 0.0;

        return $toRet;
    }
}
