<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

\CBitrixComponent::includeComponentClass("shantilab:base.order");

class OrderComponent extends \BaseOrderComponent
{
    public function executeComponent()
    {
        try
        {
            parent::executeComponent();

            $this->arResult['ORDER'] = $this->order;
            $this->arResult['DATA'] = $this->data;

            $this->arResult['CART_ITEMS'] = $this->getCartInfo();

            //$this->arResult['BASKET_PRICE_FORMATTED']  = SaleFormatCurrency($this->arResult['BASKET_PRICE'], $this->currencyCode);
            $this->arResult['ORDER_DELIVERY_PRICE']  = $this->order->getDeliveryPrice();
            $this->arResult['ORDER_DELIVERY_PRICE_FORMATTED']  = SaleFormatCurrency($this->order->getDeliveryPrice(), $this->currencyCode);
            $this->arResult['ORDER_PRICE_FORMATTED']  = SaleFormatCurrency($this->order->getPrice(), $this->currencyCode);

            $this->includeComponentTemplate();
        }
        catch (Exception $e)
        {
            ShowError($e->getMessage());
        }
    }
}