<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;

/**
 * DTO para representar notas de pedido
 * 
 * Facilita la conversión entre el modelo de pedido de PrestaShop
 * y el modelo de nota de pedido de Distrib
 */
class DtoOrderNote
{
    public string $serial = '';
    public int $code = 0;
    public int $loadId = 0;
    public int $customerId = 0;
    public string $utcCreationDateTime = '';
    public string $utcDeliveryDate = '';
    public string $observation = '';
    /** @var DtoOrderNotesLine[] */
    public array $lines = [];
    public string $shippingCompanyId = '';
    public DtoOrderNotesTpvPayment $tpvPayment;
    public string $state = '';
    public int $shippingAddressId = 0;
    /** @var DtoOrderNotesKitLine[] */
    public array $kitLines = [];

    public function __construct()
    {
        $this->tpvPayment = new DtoOrderNotesTpvPayment();
    }

    /**
     * Crea un objeto DtoOrderNote a partir de datos del API
     * 
     * @param array $orderNote Datos del pedido desde el API
     * @return DtoOrderNote
     */
    public static function create(array $orderNote) : DtoOrderNote
    {
        try {
            // Validar datos mínimos necesarios
            if (empty($orderNote['serial']) || empty($orderNote['code']) || empty($orderNote['customerId'])) {
                throw new \InvalidArgumentException('Faltan datos obligatorios para crear el pedido');
            }
            
            $toRet = new DtoOrderNote();
            $toRet->serial = $orderNote['serial'];
            $toRet->code = (int)$orderNote['code'];
            $toRet->customerId = (int)$orderNote['customerId'];
            $toRet->utcCreationDateTime = $orderNote['utcCreationDateTime'];
            $toRet->utcDeliveryDate = $orderNote['utcDeliveryDate'] ?? $orderNote['utcCreationDateTime'];
            $toRet->observation = $orderNote['observation'] ?? '';
            
            if (!empty($orderNote['lines']) && is_array($orderNote['lines'])) {
                foreach ($orderNote['lines'] as $line) {
                    $lineDto = DtoOrderNotesLine::create($line);
                    if ($lineDto) {
                        $toRet->lines[] = $lineDto;
                    }
                }
            }
            
            $toRet->state = $orderNote['state'] ?? '';
            $toRet->shippingCompanyId = $orderNote['shippingCompanyId'] ?? '';
            
            if (!empty($orderNote['tpvPayment'])) {
                $toRet->tpvPayment = DtoOrderNotesTpvPayment::create($orderNote['tpvPayment']);
            }
            
            $toRet->shippingAddressId = isset($orderNote['shippingAddressId']) ? (int)$orderNote['shippingAddressId'] : 0;
            
            if (!empty($orderNote['kitLines']) && is_array($orderNote['kitLines'])) {
                foreach ($orderNote['kitLines'] as $kitLine) {
                    $kitLineDto = DtoOrderNotesKitLine::create($kitLine);
                    if ($kitLineDto) {
                        $toRet->kitLines[] = $kitLineDto;
                    }
                }
            }

            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoOrderNote', [
                'order_data' => $orderNote ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Creamos un objeto mínimo para evitar errores en el flujo
            $toRet = new DtoOrderNote();
            if (!empty($orderNote['serial'])) $toRet->serial = $orderNote['serial'];
            if (!empty($orderNote['code'])) $toRet->code = (int)$orderNote['code'];
            
            return $toRet;
        }
    }

    /**
     * Crea un DtoOrderNote a partir de un pedido de PrestaShop
     * 
     * @param \Order $order Pedido de PrestaShop
     * @return DtoOrderNote
     */
    public static function createFromPrestashopOrder(\Order $order): DtoOrderNote
    {
        try {
            // Validar el pedido
            if (empty($order->id) || empty($order->id_customer)) {
                throw new \InvalidArgumentException('El pedido no tiene los datos mínimos necesarios');
            }
            
            // Determinar fecha de creación
            $creationDate = null;
            if (!empty($order->invoice_date) && $order->invoice_date !== "0000-00-00 00:00:00") {
                $creationDate = new \DateTimeImmutable($order->invoice_date);
            } else {
                $creationDate = new \DateTimeImmutable();
            }
            
            $creationDate = $creationDate->setTimezone(new \DateTimeZone('UTC'));
            $formattedDate = $creationDate->format("Y-m-d\TH:i:s.000\Z");
            
            $toRet = new DtoOrderNote();
            $toRet->serial = $order->reference ?: ('ORD' . $order->id); 
            $toRet->code = (int)$order->id;
            $toRet->customerId = (int)$order->id_customer;
            $toRet->utcCreationDateTime = $formattedDate;
            $toRet->utcDeliveryDate = $formattedDate;
            $toRet->observation = $order->note ?? "";
            $toRet->shippingCompanyId = "33"; // ID por defecto
            $toRet->shippingAddressId = (int)$order->id_address_delivery;

            $index_line = 0;

            // Añadir líneas de producto
            $products = $order->getProducts();
            if (!empty($products)) {
                foreach ($products as $index => $product) {
                    $line = DtoOrderNotesLine::fromPrestashop($product, $index);
                    if ($line) {
                        $toRet->lines[] = $line;
                        $index_line = $index + 1;
                    }
                }
            }

            // Añadir línea de envío si corresponde
            $shipping = $order->total_shipping;
            if (!empty($shipping) && (float)$shipping > 0) {
                $shippingLine = DtoOrderNotesLine::addShippingLine($shipping, $index_line);
                if ($shippingLine) {
                    $toRet->lines[] = $shippingLine;
                    $index_line++;
                }
            }

            // Añadir línea de descuento si corresponde
            $discount = $order->total_discounts;
            if (!empty($discount) && (float)$discount > 0) {
                $discountLine = DtoOrderNotesLine::addDiscountLine($discount, $index_line);
                if ($discountLine) {
                    $toRet->lines[] = $discountLine;
                }
            }

            // Configurar información de pago
            $toRet->tpvPayment = DtoOrderNotesTpvPayment::fromPrestashop($order);

            // Añadir un KitLine por defecto
            $kitLine = DtoOrderNotesKitLine::fromPrestashop();
            if ($kitLine) {
                $toRet->kitLines[] = $kitLine;
            }

            return $toRet;
        } catch (\Exception $e) {
            Log::error('Error al crear DtoOrderNote desde pedido PrestaShop', [
                'order_id' => $order->id ?? 'unknown',
                'reference' => $order->reference ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En caso de error, devolvemos un objeto básico para que la aplicación pueda continuar
            $toRet = new DtoOrderNote();
            $toRet->serial = $order->reference ?? ('ERR' . ($order->id ?? '0'));
            $toRet->code = (int)($order->id ?? 0);
            $toRet->customerId = (int)($order->id_customer ?? 0);
            $toRet->utcCreationDateTime = (new \DateTimeImmutable())->format("Y-m-d\TH:i:s.000\Z");
            $toRet->utcDeliveryDate = (new \DateTimeImmutable())->format("Y-m-d\TH:i:s.000\Z");
            
            return $toRet;
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @param bool $encodeAsJson Si la respuesta debe ser JSON o array
     * @return string|array Datos formateados para el API
     */
    public function toApiArray(bool $encodeAsJson = true)
    {
        try {
            $customerId = DtoCliente::idByUser($this->customerId);
            if (!$customerId) {
                Log::warning('Cliente no encontrado en el ERP al crear pedido', [
                    'customer_id' => $this->customerId,
                    'order_code' => $this->code
                ]);
            }
            
            $values = [
                "serial" => "VINE",
                "code" => $this->code ?: 1,
                "loadId" => 0,
                "customerId" => (int)$customerId ?: $this->customerId,
                "utcCreationDateTime" => $this->utcCreationDateTime,
                "utcDeliveryDate" => $this->utcDeliveryDate,
                "observation" => $this->observation,
                "lines" => [],
                "shippingCompanyId" => $this->shippingCompanyId,
                "tpvPayment" => $this->tpvPayment->toApiArray(),
                "shippingAddressId" => $this->shippingAddressId,
                "kitLines" => []
            ];
            
            // Procesar líneas de pedido
            foreach ($this->lines as $i => $line) {
                $values["lines"][] = $line->toApiArray($i);
            }
            
            // Procesar líneas de kit
            foreach ($this->kitLines as $kitLine) {
                $values["kitLines"][] = $kitLine->toApiArray();
            }

            if (!$encodeAsJson) {
                return $values;
            } else {
                return json_encode($values);
            }
        } catch (\Exception $e) {
            Log::error('Error al convertir DtoOrderNote a array para API', [
                'order_code' => $this->code,
                'customer_id' => $this->customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Datos mínimos en caso de error
            $values = [
                "serial" => "VINE",
                "code" => $this->code ?: 1,
                "customerId" => $this->customerId,
                "utcCreationDateTime" => (new \DateTimeImmutable())->format("Y-m-d\TH:i:s.000\Z"),
                "utcDeliveryDate" => (new \DateTimeImmutable())->format("Y-m-d\TH:i:s.000\Z"),
                "observation" => "Error al procesar pedido",
                "lines" => [],
                "shippingCompanyId" => "33",
                "tpvPayment" => $this->tpvPayment->toApiArray(),
                "shippingAddressId" => 0,
                "kitLines" => []
            ];
            
            return $encodeAsJson ? json_encode($values) : $values;
        }
    }
}