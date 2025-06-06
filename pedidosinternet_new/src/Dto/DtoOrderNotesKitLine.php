<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;

/**
 * DTO para representar líneas de kit de pedido
 * 
 * Facilita la conversión entre productos agrupados (kits) de PrestaShop
 * y el formato esperado por Distrib
 */
class DtoOrderNotesKitLine
{
    public string $productReference = '';
    public int $quantity = 0;
    public int $kilos = 0;
    public string $mode = '';
    public float $price = 0.0;
    public float $percentageDiscount = 0.0;
    public float $percentageDiscount2 = 0.0;
    public float $percentageDiscount3 = 0.0;
    public float $percentageDiscount4 = 0.0;
    public float $percentageCurrencyPercentagePVP = 0.0;
    public float $percentageCurrencyPercentagePVPPro = 0.0;
    public bool $hasToPrint = true;
    public string $kitReference = '';
    public float $buyingPrice = 0.0;
    public bool $hasToApplyCommercialTerms = true;
    public int $binary = 0;
    public float $percentage = 0.0;
    public float $b2BPrice = 0.0;
    public float $b2BUnitPrice = 0.0;

    /**
     * Crea un objeto DtoOrderNotesKitLine a partir de datos recibidos
     * 
     * @param array $line Datos de la línea de kit
     * @return DtoOrderNotesKitLine|null El objeto creado o null si fallan las validaciones
     */
    public static function create(array $line): ?DtoOrderNotesKitLine
    {
        try {
            // Validación básica
            if (empty($line['productReference'])) {
                Log::warning("KitLine sin referencia de producto", [
                    'kitReference' => $line['kitReference'] ?? 'no presente'
                ]);
                return null;
            }

            $toRet = new DtoOrderNotesKitLine();
            $toRet->productReference = $line['productReference'];
            $toRet->quantity = (int)($line['quantity'] ?? 0);
            $toRet->kilos = (int)($line['kilos'] ?? 0);
            $toRet->mode = $line['mode'] ?? '';
            $toRet->price = (float)($line['price'] ?? 0.0);
            $toRet->percentageDiscount = (float)($line['percentageDiscount'] ?? 0.0);
            $toRet->percentageDiscount2 = (float)($line['percentageDiscount2'] ?? 0.0);
            $toRet->percentageDiscount3 = (float)($line['percentageDiscount3'] ?? 0.0);
            $toRet->percentageDiscount4 = (float)($line['percentageDiscount4'] ?? 0.0);
            $toRet->percentageCurrencyPercentagePVP = (float)($line['percentageCurrencyPercentagePVP'] ?? 0.0);
            $toRet->percentageCurrencyPercentagePVPPro = (float)($line['percentageCurrencyPercentagePVPPro'] ?? 0.0);
            $toRet->hasToPrint = $line['hasToPrint'] ?? true;
            $toRet->kitReference = $line['kitReference'] ?? '';
            $toRet->buyingPrice = (float)($line['buyingPrice'] ?? 0.0);
            $toRet->hasToApplyCommercialTerms = $line['hasToApplyCommercialTerms'] ?? true;
            $toRet->binary = (int)($line['binary'] ?? 0);
            $toRet->percentage = (float)($line['percentage'] ?? 0.0);
            $toRet->b2BPrice = (float)($line['b2BPrice'] ?? 0.0);
            $toRet->b2BUnitPrice = (float)($line['b2BUnitPrice'] ?? 0.0);

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear KitLine", [
                'productReference' => $line['productReference'] ?? 'desconocida',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convierte la entidad a un array para enviar al API
     * 
     * @return array Datos formateados para el API
     */
    public function toApiArray(): array
    {
        try {
            return [
                "productReference" => $this->productReference,
                "quantity" => $this->quantity,
                "kilos" => $this->kilos,
                "mode" => $this->mode,
                "price" => $this->price,
                "percentageDiscount" => $this->percentageDiscount,
                "percentageDiscount2" => $this->percentageDiscount2,
                "percentageDiscount3" => $this->percentageDiscount3,
                "percentageDiscount4" => $this->percentageDiscount4,
                "percentageCurrencyPercentagePVP" => $this->percentageCurrencyPercentagePVP,
                "percentageCurrencyPercentagePVPPro" => $this->percentageCurrencyPercentagePVPPro,
                "hasToPrint" => $this->hasToPrint,
                "kitReference" => $this->kitReference,
                "buyingPrice" => $this->buyingPrice,
                "hasToApplyCommercialTerms" => $this->hasToApplyCommercialTerms,
                "binary" => $this->binary,
                "percentage" => $this->percentage,
                "b2BPrice" => $this->b2BPrice,
                "b2BUnitPrice" => $this->b2BUnitPrice
            ];
        } catch (\Exception $e) {
            Log::error("Error al convertir KitLine a array para API", [
                'productReference' => $this->productReference ?? 'desconocida',
                'error' => $e->getMessage()
            ]);
            
            // Objeto mínimo en caso de error
            return [
                "productReference" => "0",
                "quantity" => 0,
                "kilos" => 0,
                "mode" => "0",
                "price" => 0.0,
                "hasToPrint" => true,
                "hasToApplyCommercialTerms" => true
            ];
        }
    }

    /**
     * Crea un objeto KitLine por defecto para PrestaShop
     * 
     * @return DtoOrderNotesKitLine El objeto con valores por defecto
     */
    public static function fromPrestashop(): DtoOrderNotesKitLine
    {
        try {
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
        } catch (\Exception $e) {
            Log::error("Error al crear KitLine por defecto", [
                'error' => $e->getMessage()
            ]);
            
            // Crear un objeto mínimo en caso de error
            return new DtoOrderNotesKitLine();
        }
    }
}