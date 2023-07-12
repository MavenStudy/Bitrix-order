<?php

namespace Legacy\API;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Context;
use Bitrix\Sale;
use Legacy\General\Constants;

if (!\Bitrix\Main\Loader::includeModule("iblock"))
    return;
if(!\Bitrix\Main\Loader::includeModule("sale"))
    return;

class Order
{
    public static function checkout($arRequest)
    {
        $userId = Sale\Fuser::getId();
        if (is_null($userId)) {
            throw new \Exception('Ошибка при оформлении заказа.');
        }

        $siteId = \Bitrix\Main\Context::getCurrent()->getSite();
        $basket = \Bitrix\Sale\Basket::loadItemsForFUser($userId, $siteId);
        $order = Sale\Order::create($siteId, $userId);


        $order->setPersonTypeId(Constants::PERSON_TYPE_INDIVIDUAL);
        $order->setField('USER_DESCRIPTION', $arRequest['comment']);
        $order->setBasket($basket);

        $propertyCollection = $order->getPropertyCollection();
        $phone = $propertyCollection->getPhone();
        $phone->setValue($arRequest['phone']);
        $email = $propertyCollection->getUserEmail();
        $email->setValue($arRequest['email']);

        $propertyMap = [
            'COMPANY' => 'company',
            'INN' => 'inn',
            'KPP' => 'kpp',
            'OGRN' => 'ogrn',
            'CONTACT_PERSON' => 'person',
            'CITY' => 'city',
            'ADDRESS' => 'address',
        ];

        foreach ($propertyCollection->getGroups() as $group) {
            foreach ($propertyCollection->getGroupProperties($group['ID']) as $property) {
                $p = $property->getProperty();
                $propertyCode = $p['CODE'];
                if (isset($propertyMap[$propertyCode]) && isset($arRequest[$propertyMap[$propertyCode]])) {
                    $property->setValue($arRequest[$propertyMap[$propertyCode]]);
                }
            }
        }

        $shipmentCollection = $order->getShipmentCollection();
        if($arRequest['delivery'] == 'pickup') {
            $shipment = $shipmentCollection->createItem(
                \Bitrix\Sale\Delivery\Services\Manager::getObjectById(Constants::DELIVERY_SAMOVYVOZ)
            );
            $shipment->setStoreId($arRequest['storeId']);

        } elseif($arRequest['delivery'] == 'courier')  {
        $shipment = $shipmentCollection->createItem(
            \Bitrix\Sale\Delivery\Services\Manager::getObjectById(Constants::DELIVERY_DOSTAVKA_KUREROM)
        );
        }

        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($basket->getBasket() as $basketItem)
        {
            $item = $shipmentItemCollection->createItem($basketItem);
            $item->setQuantity($basketItem->getQuantity());
        }

        $paymentCollection = $order->getPaymentCollection();
        $paymentCode = 'PAY_SYSTEM_'.mb_strtoupper($arRequest['payment']);
        $reflector = new \ReflectionClass(Constants::class);
        $constants = $reflector->getConstants();
        if (!$constants[$paymentCode]) {
            throw new \Exception('Ошибка платежной системы.');
        }
        $payment = $paymentCollection->createItem(
            \Bitrix\Sale\PaySystem\Manager::getObjectById($constants[$paymentCode])
        );
        $payment->setField("SUM", $order->getPrice());
        $payment->setField("CURRENCY", $order->getCurrency());

        $order->save();
        $orderId = $order->getId();

        return ['orderNum' => $orderId];
    }
}