<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoOrderNotesLine
{
    public int $lineNumber;
    public string $reference;
    public string $description;
    public string $formatForPrice;
    public float $unitsPerBox;
    public float $units;
    public float $boxes;
    public float $kilos;
    public string $observation;
    public int $formatIdForQuantity;
    public float $quantity;
    public float $formatMultiplierForUnits;
    public float $formatMultiplierForKilos;
    public float $price;
    public float $total;
    public float $discount;
    public float $percentageDiscount2;
    public float $percentageDiscount3;
    public float $percentageDiscount4;
    public float $percentageCurrencyPercentagePVP;
    public float $percentageCurrencyPercentagePVPPro;

    /**
     * @param array $lines
     * @return DtoOrderNotesLine
     */
    public static function create(array $lines) : DtoOrderNotesLine
    {
        $toRet = new DtoOrderNotesLine();

        $toRet->lineNumber = $lines['lineNumber'];
        $toRet->reference = $lines['reference'];
        $toRet->description = $lines['description'];
        $toRet->formatForPrice = $lines['formatForPrice'];
        $toRet->unitsPerBox = $lines['unitsPerBox'];
        $toRet->units = $lines['units'];
        $toRet->boxes = $lines['boxes'];
        $toRet->kilos = $lines['kilos'];
        $toRet->observation = $lines['observation'];
        $toRet->formatIdForQuantity = $lines['formatIdForQuantity'];
        $toRet->quantity = $lines['quantity'];
        $toRet->formatMultiplierForUnits = $lines['formatMultiplierForUnits'];
        $toRet->formatMultiplierForKilos = $lines['formatMultiplierForKilos'];
        $toRet->price = $lines['price'];
        $toRet->total = $lines['total'];
        $toRet->discount = $lines['discount'];
        $toRet->percentageDiscount2 = $lines['percentageDiscount2'];
        $toRet->percentageDiscount3 = $lines['percentageDiscount3'];
        $toRet->percentageDiscount4 = $lines['percentageDiscount4'];
        $toRet->percentageCurrencyPercentagePVP = $lines['percentageCurrencyPercentagePVP'];
        $toRet->percentageCurrencyPercentagePVPPro = $lines['percentageCurrencyPercentagePVPPro'];

        return $toRet;
    }

    public function toApiArray($lineNumber) : array
    {
        return [
            "lineNumber" => $lineNumber ?? $this->lineNumber ?? 1,
            "reference" => $this->reference ?? "1",
            "description" => $this->description ?? "",
            "formatForPrice" => $this->formatForPrice ?? "U",
            "unitsPerBox" => $this->unitsPerBox ?? 1.00,
            "units" => $this->units ?? 1.00,
            "boxes" => $this->boxes ?? 1.00,
            "kilos" => $this->kilos ?? 1.00,
            "observation" => $this->observation ?? "",
            "formatIdForQuantity" => $this->formatIdForQuantity ?? 1,
            "quantity" => $this->quantity ?? 1,
            "formatMultiplierForUnits" => $this->formatMultiplierForUnits ?? 1.000,
            "formatMultiplierForKilos" => $this->formatMultiplierForKilos ?? 0.333,
            "price" => $this->price ?? 1.000,
            "total" => $this->total ?? 1.000,
            "discount" => $this->discount,
            "percentageDiscount2" => $this->percentageDiscount2,
            "percentageDiscount3" => $this->percentageDiscount3,
            "percentageDiscount4" => $this->percentageDiscount4,
            "percentageCurrencyPercentagePVP" => $this->percentageCurrencyPercentagePVP,
            "percentageCurrencyPercentagePVPPro" => $this->percentageCurrencyPercentagePVPPro,

        ];
    }


    public static function fromPrestashop(array $product, int $line): DtoOrderNotesLine
    {

        $toRet = new DtoOrderNotesLine();
        $toRet->lineNumber = $line;
        $toRet->reference = $product['product_reference'];
        $toRet->description = $product['product_name'];
        $toRet->formatForPrice = "U";
        $toRet->unitsPerBox = 1.0;
        $toRet->units = (float)$product['product_quantity'];
        $toRet->boxes = 0.0;
        $toRet->kilos = (float)$product['weight'];
        $toRet->observation = "";
        $toRet->formatIdForQuantity = 1;
        $toRet->quantity = (float)$product['product_quantity'];
        $toRet->formatMultiplierForUnits = 1.0;
        $toRet->formatMultiplierForKilos = 1.0;
        $toRet->price = (float)$product['unit_price_tax_incl'];
        $toRet->total = (float)$product['total_price_tax_incl'];
        $toRet->discount = (float)$product['quantity_discount'];
        $toRet->percentageDiscount2 = 0.0;
        $toRet->percentageDiscount3 = 0.0;
        $toRet->percentageDiscount4 = 0.0;
        $toRet->percentageCurrencyPercentagePVP = 0.0;
        $toRet->percentageCurrencyPercentagePVPPro = 0.0;

        return $toRet;
    }

    public static function addShippingLine(string $shipping, int $index_line): DtoOrderNotesLine {

        $toRet = new DtoOrderNotesLine();
        $toRet->lineNumber = $index_line;
        $toRet->reference = "PW0001";
        $toRet->description = "Portes";
        $toRet->formatForPrice = "U";
        $toRet->unitsPerBox = 1.0;
        $toRet->units = 1.0;
        $toRet->boxes = 0.0;
        $toRet->kilos = 0.0;
        $toRet->observation = "";
        $toRet->formatIdForQuantity = 1;
        $toRet->quantity = 1.0;
        $toRet->formatMultiplierForUnits = 1.0;
        $toRet->formatMultiplierForKilos = 1.0;
        $toRet->price = (float)$shipping;
        $toRet->total = (float)$shipping;
        $toRet->discount = 0.0;
        $toRet->percentageDiscount2 = 0.0;
        $toRet->percentageDiscount3 = 0.0;
        $toRet->percentageDiscount4 = 0.0;
        $toRet->percentageCurrencyPercentagePVP = 0.0;
        $toRet->percentageCurrencyPercentagePVPPro = 0.0;

        return $toRet;

    }

    public static function addDiscountLine(string $discount, int $index_line): DtoOrderNotesLine {
        
        $toRet = new DtoOrderNotesLine();
        $toRet->lineNumber = $index_line;
        $toRet->reference = "PW0003";
        $toRet->description = "Cupón Web";
        $toRet->formatForPrice = "U";
        $toRet->unitsPerBox = 1.0;
        $toRet->units = -1.0;
        $toRet->boxes = 0.0;
        $toRet->kilos = 0.0;
        $toRet->observation = "";
        $toRet->formatIdForQuantity = 1;
        $toRet->quantity = -1.0;
        $toRet->formatMultiplierForUnits = 1.0;
        $toRet->formatMultiplierForKilos = 1.0;
        $toRet->price = (float)$discount;
        $toRet->total = (float)$discount;
        $toRet->discount = 0.0;
        $toRet->percentageDiscount2 = 0.0;
        $toRet->percentageDiscount3 = 0.0;
        $toRet->percentageDiscount4 = 0.0;
        $toRet->percentageCurrencyPercentagePVP = 0.0;
        $toRet->percentageCurrencyPercentagePVPPro = 0.0;

        return $toRet;
    }
}
