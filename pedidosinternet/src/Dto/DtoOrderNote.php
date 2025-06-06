<?php

namespace PedidosInternet\Dto;

use PedidosInternet\Dto\DtoOrderNotesLine;

class DtoOrderNote
{
    public string $serial;
    public int $code;
    public int $loadId;
    public int $customerId;
    public string $utcCreationDateTime;
    public string $utcDeliveryDate;
    public string $observation;
    /** @var array<DtoOrderNotesLine> $lines */
    public array $lines = [];
    public string $shippingCompanyId;
    public DtoOrderNotesTpvPayment $tpvPayment;
    public string $state;
    public int $shippingAddressId;
    /** @var array<DtoOrderNotesKitLine> $line */
    public array $kitLines = [];

    public static function create(array $orderNote) : DtoOrderNote
    {
        $toRet = new DtoOrderNote();
        $toRet->serial = $orderNote['serial'];
        $toRet->code = $orderNote['code'];
        $toRet->customerId = $orderNote['customerId'];
        $toRet->utcCreationDateTime = $orderNote['utcCreationDateTime'];
        $toRet->utcDeliveryDate = $orderNote['utcCreationDateTime'];
        $toRet->observation = $orderNote['observation'];
        foreach ($orderNote['lines'] as $key => $value) {
            $toRet->lines[] = DtoOrderNotesLine::create($value);
        }
        $toRet->state = $orderNote['state'];
        $toRet->shippingCompanyId = $orderNote['shippingCompanyId'];
        $toRet->tpvPayment = DtoOrderNotesTpvPayment::create($orderNote['tpvPayment']);
        $toRet->shippingAddressId = $orderNote['shippingAddressId'];

        return $toRet;

    }

    public static function createFromPrestashopOrder(\Order $order): DtoOrderNote
    {
        $creationDate = null;
        if ($order->invoice_date && $order->invoice_date !== "0000-00-00 00:00:00") {
            $creationDate = new \DateTimeImmutable($order->invoice_date);
        } else {
            $creationDate = new \DateTimeImmutable();
        }
        
        $creationDate = $creationDate->setTimezone(new \DateTimeZone('UTC'));

        $id_customer = $order->id_customer;
        $id_customer = (int)$id_customer;
		
        $toRet = new DtoOrderNote();
        $toRet->serial = $order->reference; 
        $toRet->code = $order->id;
        $toRet->customerId = $id_customer;
        $toRet->utcCreationDateTime = $creationDate->format("Y-m-d\TH:i:s.u\Z");
        $toRet->utcDeliveryDate =  $creationDate->format("Y-m-d\TH:i:s.u\Z");
        $toRet->observation = $order->note ?? "";
        $toRet->shippingCompanyId = "33";
        $toRet->shippingAddressId = (int)$order->id_address_delivery;

        $index_line = 0;

        /**
         * @var int $index
         * @var \Product $product
         */
        foreach ($order->getProducts() as $index => $product)
        {
            $toRet->lines[] = DtoOrderNotesLine::fromPrestashop($product, $index);
            $index_line = $index + 1;
        }

        $shipping = $order->total_shipping;

        if(!is_null($shipping)) {
            array_push($toRet->lines, DtoOrderNotesLine::addShippingLine($shipping, $index_line));
            $index_line++;
        }

        $discount = $order->total_discounts;

        if(!is_null($discount) && $discount > 0) {
            array_push($toRet->lines, DtoOrderNotesLine::addDiscountLine($discount, $index_line));
        }

        $toRet->tpvPayment = DtoOrderNotesTpvPayment::fromPrestashop($order);

        $toRet->kitLines[] = DtoOrderNotesKitLine::fromPrestashop(); 

        return $toRet;
    }

    public function toApiArray(bool $encodeAsJson = true)
    {
        $id_customer = DtoCliente::idByUser($this->customerId);
        $id_customer = (int)$id_customer;
        $values = [
            "serial" => "VINE",
            "code" => $this->code ?? "1",
            "loadId" => 0,
            "customerId" => $id_customer,
            "utcCreationDateTime" => $this->utcCreationDateTime,
            "utcDeliveryDate" => $this->utcDeliveryDate,
            "observation" => $this->observation,
            "lines" => array_map(function ($lines){
                static $i = 0;
                $result = $lines->toApiArray($i);
                $i++;
                return $result;
            } , $this->lines),
            "shippingCompanyId" => $this->shippingCompanyId,
            "tpvPayment" => $this->tpvPayment,
            "shippingAddressId" => $this->shippingAddressId,
            "kitLines" => $this->kitLines
        ];

        if (!$encodeAsJson) {
            return $values;
        } else {
            return json_encode($values);
        }
    }
}
