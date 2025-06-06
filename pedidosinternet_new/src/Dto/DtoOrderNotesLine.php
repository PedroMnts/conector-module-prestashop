<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;

/**
 * DTO para representar líneas de pedido
 * 
 * Facilita la conversión entre líneas de pedido de PrestaShop y el formato esperado por Distrib
 */
class DtoOrderNotesLine
{
    // Propiedades actuales conservadas
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
     * Crea un objeto DtoOrderNotesLine a partir de datos recibidos
     * 
     * @param array $lines Datos de la línea de pedido
     * @return DtoOrderNotesLine|null El objeto creado o null si fallan las validaciones
     */
    public static function create(array $lines): ?DtoOrderNotesLine
    {
        try {
            // Validaciones básicas
            if (empty($lines['reference']) || !isset($lines['lineNumber'])) {
                Log::warning("Datos insuficientes para crear línea de pedido", [
                    'reference' => $lines['reference'] ?? 'no presente',
                    'lineNumber' => $lines['lineNumber'] ?? 'no presente'
                ]);
                return null;
            }

            $toRet = new DtoOrderNotesLine();
            $toRet->lineNumber = (int)($lines['lineNumber'] ?? 0);
            $toRet->reference = $lines['reference'];
            $toRet->description = $lines['description'] ?? '';
            $toRet->formatForPrice = $lines['formatForPrice'] ?? 'U';
            $toRet->unitsPerBox = (float)($lines['unitsPerBox'] ?? 1.0);
            $toRet->units = (float)($lines['units'] ?? 0.0);
            $toRet->boxes = (float)($lines['boxes'] ?? 0.0);
            $toRet->kilos = (float)($lines['kilos'] ?? 0.0);
            $toRet->observation = $lines['observation'] ?? '';
            $toRet->formatIdForQuantity = (int)($lines['formatIdForQuantity'] ?? 1);
            $toRet->quantity = (float)($lines['quantity'] ?? 1.0);
            $toRet->formatMultiplierForUnits = (float)($lines['formatMultiplierForUnits'] ?? 1.0);
            $toRet->formatMultiplierForKilos = (float)($lines['formatMultiplierForKilos'] ?? 0.0);
            $toRet->price = (float)($lines['price'] ?? 0.0);
            $toRet->total = (float)($lines['total'] ?? 0.0);
            $toRet->discount = (float)($lines['discount'] ?? 0.0);
            $toRet->percentageDiscount2 = (float)($lines['percentageDiscount2'] ?? 0.0);
            $toRet->percentageDiscount3 = (float)($lines['percentageDiscount3'] ?? 0.0);
            $toRet->percentageDiscount4 = (float)($lines['percentageDiscount4'] ?? 0.0);
            $toRet->percentageCurrencyPercentagePVP = (float)($lines['percentageCurrencyPercentagePVP'] ?? 0.0);
            $toRet->percentageCurrencyPercentagePVPPro = (float)($lines['percentageCurrencyPercentagePVPPro'] ?? 0.0);

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear línea de pedido", [
                'reference' => $lines['reference'] ?? 'desconocida',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Convierte la entidad a un array para enviar al API
     * 
     * @param int|null $lineNumber Número de línea opcional (sobrescribe el actual)
     * @return array Datos formateados para el API
     */
    public function toApiArray($lineNumber = null): array
    {
        try {
            $effectiveLineNumber = $lineNumber ?? $this->lineNumber ?? 1;
            
            return [
                "lineNumber" => $effectiveLineNumber,
                "reference" => $this->reference ?? "1",
                "description" => $this->description ?? "",
                "formatForPrice" => $this->formatForPrice ?? "U",
                "unitsPerBox" => $this->unitsPerBox ?? 1.00,
                "units" => $this->units ?? 1.00,
                "boxes" => $this->boxes ?? 0.00,
                "kilos" => $this->kilos ?? 0.00,
                "observation" => $this->observation ?? "",
                "formatIdForQuantity" => $this->formatIdForQuantity ?? 1,
                "quantity" => $this->quantity ?? 1,
                "formatMultiplierForUnits" => $this->formatMultiplierForUnits ?? 1.000,
                "formatMultiplierForKilos" => $this->formatMultiplierForKilos ?? 0.333,
                "price" => $this->price ?? 0.000,
                "total" => $this->total ?? 0.000,
                "discount" => $this->discount ?? 0.0,
                "percentageDiscount2" => $this->percentageDiscount2 ?? 0.0,
                "percentageDiscount3" => $this->percentageDiscount3 ?? 0.0,
                "percentageDiscount4" => $this->percentageDiscount4 ?? 0.0,
                "percentageCurrencyPercentagePVP" => $this->percentageCurrencyPercentagePVP ?? 0.0,
                "percentageCurrencyPercentagePVPPro" => $this->percentageCurrencyPercentagePVPPro ?? 0.0,
            ];
        } catch (\Exception $e) {
            Log::error("Error al convertir línea de pedido a array para API", [
                'reference' => $this->reference ?? 'desconocida',
                'error' => $e->getMessage()
            ]);
            
            // En caso de error, devolvemos un objeto básico
            return [
                "lineNumber" => $lineNumber ?? 1,
                "reference" => $this->reference ?? "ERROR",
                "description" => "Error en conversión",
                "formatForPrice" => "U",
                "unitsPerBox" => 1.00,
                "units" => 1.00,
                "quantity" => 1,
                "formatIdForQuantity" => 1,
                "formatMultiplierForUnits" => 1.000,
                "formatMultiplierForKilos" => 0.333,
                "price" => 0.00,
                "total" => 0.00
            ];
        }
    }

    /**
     * Crea un DtoOrderNotesLine a partir de datos de producto de PrestaShop
     * 
     * @param array $product Datos del producto de un pedido PrestaShop
     * @param int $line Número de línea
     * @return DtoOrderNotesLine|null El objeto creado o null si hay error
     */
    public static function fromPrestashop(array $product, int $line): ?DtoOrderNotesLine
    {
        try {
            // Validaciones básicas
            if (empty($product['product_reference'])) {
                Log::warning("Producto sin referencia al crear línea de pedido", [
                    'product_id' => $product['product_id'] ?? 'desconocido',
                ]);
                return null;
            }

            $toRet = new DtoOrderNotesLine();
            $toRet->lineNumber = $line;
            $toRet->reference = $product['product_reference'];
            $toRet->description = $product['product_name'] ?? '';
            $toRet->formatForPrice = "U"; // Formato por defecto: unidades
            $toRet->unitsPerBox = 1.0;
            $toRet->units = (float)($product['product_quantity'] ?? 0);
            $toRet->boxes = 0.0;
            $toRet->kilos = (float)($product['weight'] ?? 0);
            $toRet->observation = "";
            $toRet->formatIdForQuantity = 1;
            $toRet->quantity = (float)($product['product_quantity'] ?? 0);
            $toRet->formatMultiplierForUnits = 1.0;
            $toRet->formatMultiplierForKilos = 1.0;
            $toRet->price = (float)($product['unit_price_tax_incl'] ?? 0);
            $toRet->total = (float)($product['total_price_tax_incl'] ?? 0);
            $toRet->discount = (float)($product['quantity_discount'] ?? 0);
            $toRet->percentageDiscount2 = 0.0;
            $toRet->percentageDiscount3 = 0.0;
            $toRet->percentageDiscount4 = 0.0;
            $toRet->percentageCurrencyPercentagePVP = 0.0;
            $toRet->percentageCurrencyPercentagePVPPro = 0.0;

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear línea de pedido desde producto PrestaShop", [
                'product_id' => $product['product_id'] ?? 'desconocido',
                'reference' => $product['product_reference'] ?? 'desconocida',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Crea una línea de pedido específica para gastos de envío
     * 
     * @param string $shipping Importe de envío
     * @param int $index_line Número de línea
     * @return DtoOrderNotesLine La línea de envío creada
     */
    public static function addShippingLine(string $shipping, int $index_line): DtoOrderNotesLine
    {
        try {
            $shippingAmount = (float)$shipping;
            
            if ($shippingAmount <= 0) {
                Log::warning("Creando línea de envío con importe cero o negativo", [
                    'shipping_amount' => $shippingAmount,
                    'index_line' => $index_line
                ]);
            }
            
            $toRet = new DtoOrderNotesLine();
            $toRet->lineNumber = $index_line;
            $toRet->reference = "PW0001"; // Referencia fija para envíos
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
            $toRet->price = $shippingAmount;
            $toRet->total = $shippingAmount;
            $toRet->discount = 0.0;
            $toRet->percentageDiscount2 = 0.0;
            $toRet->percentageDiscount3 = 0.0;
            $toRet->percentageDiscount4 = 0.0;
            $toRet->percentageCurrencyPercentagePVP = 0.0;
            $toRet->percentageCurrencyPercentagePVPPro = 0.0;

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear línea de envío", [
                'shipping_amount' => $shipping,
                'index_line' => $index_line,
                'error' => $e->getMessage()
            ]);
            
            // Devolver una línea básica en caso de error
            $fallbackLine = new DtoOrderNotesLine();
            $fallbackLine->lineNumber = $index_line;
            $fallbackLine->reference = "PW0001";
            $fallbackLine->description = "Portes (Error)";
            $fallbackLine->formatForPrice = "U";
            $fallbackLine->quantity = 1.0;
            $fallbackLine->price = 0.0;
            $fallbackLine->total = 0.0;
            
            return $fallbackLine;
        }
    }

    /**
     * Crea una línea de pedido específica para descuentos
     * 
     * @param string $discount Importe de descuento
     * @param int $index_line Número de línea
     * @return DtoOrderNotesLine La línea de descuento creada
     */
    public static function addDiscountLine(string $discount, int $index_line): DtoOrderNotesLine
    {
        try {
            $discountAmount = (float)$discount;
            
            if ($discountAmount <= 0) {
                Log::warning("Creando línea de descuento con importe cero o negativo", [
                    'discount_amount' => $discountAmount,
                    'index_line' => $index_line
                ]);
            }
            
            $toRet = new DtoOrderNotesLine();
            $toRet->lineNumber = $index_line;
            $toRet->reference = "PW0003"; // Referencia fija para descuentos
            $toRet->description = "Cupón Web";
            $toRet->formatForPrice = "U";
            $toRet->unitsPerBox = 1.0;
            $toRet->units = -1.0; // Cantidad negativa para reflejar descuento
            $toRet->boxes = 0.0;
            $toRet->kilos = 0.0;
            $toRet->observation = "";
            $toRet->formatIdForQuantity = 1;
            $toRet->quantity = -1.0; // Cantidad negativa para reflejar descuento
            $toRet->formatMultiplierForUnits = 1.0;
            $toRet->formatMultiplierForKilos = 1.0;
            $toRet->price = $discountAmount;
            $toRet->total = $discountAmount;
            $toRet->discount = 0.0;
            $toRet->percentageDiscount2 = 0.0;
            $toRet->percentageDiscount3 = 0.0;
            $toRet->percentageDiscount4 = 0.0;
            $toRet->percentageCurrencyPercentagePVP = 0.0;
            $toRet->percentageCurrencyPercentagePVPPro = 0.0;

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear línea de descuento", [
                'discount_amount' => $discount,
                'index_line' => $index_line,
                'error' => $e->getMessage()
            ]);
            
            // Devolver una línea básica en caso de error
            $fallbackLine = new DtoOrderNotesLine();
            $fallbackLine->lineNumber = $index_line;
            $fallbackLine->reference = "PW0003";
            $fallbackLine->description = "Cupón Web (Error)";
            $fallbackLine->formatForPrice = "U";
            $fallbackLine->quantity = -1.0;
            $fallbackLine->price = 0.0;
            $fallbackLine->total = 0.0;
            
            return $fallbackLine;
        }
    }
}