<?php

namespace Legacy\API;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Legacy\General\Constants;

if (!\Bitrix\Main\Loader::includeModule("iblock"))
    return;
if(!\Bitrix\Main\Loader::includeModule("sale"))
    return;
class Basket
{
    private static function getBasketItemProps($arRequest)
    {
        $id = (int) $arRequest['id'];
        if ($id <= 0) {
            throw new \Exception('Некорректный ID товара');
        }

        $q = \Legacy\Sale\BasketElementTable::query()->withID($id)->withSelect();
        $db = $q->exec();
        $properties = [];
        while ($res = $db->fetch()) {
            $properties []= [
                'NAME' => $res['PROPERTY_NAME'],
                'CODE' => $res['PROPERTY_CODE'],
                'VALUE' => $res['PROPERTY_VALUE'],
            ];
        }
        return $properties;
    }

    public static function getLength($arRequest)
    {
        $basket = \Legacy\Sale\Basket::loadItems();

        return ['length' => $basket->getLength()];
    }

    public static function getPrice($arRequest)
    {
        $basket = \Legacy\Sale\Basket::loadItems();

        return ['price' => $basket->getPrice()];
    }


    public static function add($arRequest)
    {
        $fields = [
            'PRODUCT_ID' => $arRequest['id'],
            'QUANTITY' => $arRequest['qunatity'] ?? 1,
            'PROPS' => self::getBasketItemProps($arRequest),
        ];
        $r = \Bitrix\Catalog\Product\Basket::addProduct($fields);
        if ($r->isSuccess()) {
            return array_merge($r->getData(), self::getLength($arRequest), self::getPrice($arRequest)); 
        } else {
            throw new \Exception(implode('. ', $r->getErrorMessages()));
        }
    }

}
