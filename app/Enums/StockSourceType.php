<?php

namespace App\Enums;

enum StockSourceType: string
{
    case WooCommerce = 'woocommerce';
    case BolCom = 'bol_com';
    case Onlinefact = 'onlinefact';

    public function label(): string
    {
        return match ($this) {
            self::WooCommerce => 'WooCommerce',
            self::BolCom => 'Bol.com',
            self::Onlinefact => 'Onlinefact',
        };
    }
}
