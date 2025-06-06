<?php
declare(strict_types=1);

namespace PedidosInternet\Dto;

use PedidosInternet\Log;

/**
 * DTO para representar información de pago TPV en pedidos
 * 
 * Facilita la conversión entre los datos de pago de PrestaShop
 * y el formato esperado por Distrib
 */
class DtoOrderNotesTpvPayment
{
    public int $debtCollectorId = 1;
    public string $paymentProvider = '';
    public string $paymentReference = '';
    public float $total = 0.0;

    /**
     * Crea un objeto DtoOrderNotesTpvPayment a partir de datos recibidos
     * 
     * @param array $tpvPayment Datos del pago TPV
     * @return DtoOrderNotesTpvPayment El objeto creado
     */
    public static function create(array $tpvPayment): DtoOrderNotesTpvPayment
    {
        try {
            $toRet = new DtoOrderNotesTpvPayment();
            $toRet->debtCollectorId = $tpvPayment['debtCollectorId'] ?? 1;
            $toRet->paymentProvider = $tpvPayment['paymentProvider'] ?? '';
            $toRet->paymentReference = $tpvPayment['paymentReference'] ?? '';
            $toRet->total = isset($tpvPayment['total']) ? (float)$tpvPayment['total'] : 0.0;

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear objeto de pago TPV", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_data' => $tpvPayment ?? []
            ]);
            
            // Devolver un objeto con valores por defecto en caso de error
            return new DtoOrderNotesTpvPayment();
        }
    }

    /**
     * Convierte el DTO a un array para enviar al API
     * 
     * @return array Datos formateados para el API
     */
    public function toApiArray(): array
    {
        try {
            return [
                "debtCollectorId" => $this->debtCollectorId,
                "paymentProvider" => $this->paymentProvider,
                "paymentReference" => $this->paymentReference,
                "total" => $this->total,
            ];
        } catch (\Exception $e) {
            Log::error("Error al convertir pago TPV a array para API", [
                'error' => $e->getMessage(),
                'payment_provider' => $this->paymentProvider
            ]);
            
            // Valores por defecto en caso de error
            return [
                "debtCollectorId" => 1,
                "paymentProvider" => "Error",
                "paymentReference" => "Error",
                "total" => 0.0,
            ];
        }
    }

    /**
     * Crea un DtoOrderNotesTpvPayment a partir de un pedido de PrestaShop
     * 
     * @param \Order $order Pedido de PrestaShop
     * @return DtoOrderNotesTpvPayment El objeto creado
     */
    public static function fromPrestashop(\Order $order): DtoOrderNotesTpvPayment
    {
        try {
            $toRet = new DtoOrderNotesTpvPayment();
            $toRet->debtCollectorId = 1;
            $toRet->paymentProvider = $order->payment ?? '';
            $toRet->paymentReference = $order->module ?? '';
            $toRet->total = (float)($order->total_paid_tax_incl ?? 0.0);

            if (empty($toRet->paymentProvider)) {
                Log::warning("Pedido sin método de pago definido", [
                    'order_id' => $order->id,
                    'reference' => $order->reference
                ]);
                $toRet->paymentProvider = "Desconocido";
            }

            return $toRet;
        } catch (\Exception $e) {
            Log::error("Error al crear objeto de pago TPV desde pedido", [
                'order_id' => $order->id ?? 'desconocido',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Objeto por defecto en caso de error
            $toRet = new DtoOrderNotesTpvPayment();
            $toRet->debtCollectorId = 1;
            $toRet->paymentProvider = "Error";
            $toRet->paymentReference = "Error";
            $toRet->total = (float)($order->total_paid_tax_incl ?? 0.0);
            
            return $toRet;
        }
    }
}