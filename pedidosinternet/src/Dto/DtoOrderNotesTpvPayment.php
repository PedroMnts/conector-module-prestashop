<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

class DtoOrderNotesTpvPayment
{
    public int $debtCollectorId;
    public string $paymentProvider;
    public string $paymentReference;
    public float $total;

    public function create(array $tpvPayment) : DtoOrderNotesTpvPayment
    {
        $toRet = new DtoOrderNotesTpvPayment();
        $toRet->debtCollectorId = $tpvPayment['debtCollectorId'];
        $toRet->paymentProvider = $tpvPayment['paymentProvider'];
        $toRet->paymentReference = $tpvPayment['paymentReference'];
        $toRet->total = $tpvPayment['total'];

        return $toRet;
    }

    public function toApiArray() : array
    {
        return [
            "debtCollectorId" => $this->debtCollectorId ?? 1,
            "paymentProvider" => $this->paymentProvider ?? "",
            "paymentReference" => $this->paymentReference ?? "",
            "total" => $this->total ?? 1.0,
        ];
    }

    public static function fromPrestashop(\Order $order): DtoOrderNotesTpvPayment
    {

        $toRet = new DtoOrderNotesTpvPayment();
        $toRet->debtCollectorId = 1;
        $toRet->paymentProvider = $order->payment;
        $toRet->paymentReference = $order->module;
        $toRet->total = (float)$order->total_paid_tax_incl;

        return $toRet;
    }

}