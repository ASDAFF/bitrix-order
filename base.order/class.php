<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main,
    Bitrix\Main\Localization\Loc as Loc,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Mail\Event,
    Bitrix\Main\Loader,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Sale,
    Bitrix\Sale\Order,
    Bitrix\Main\Context,
    Bitrix\Sale\Location\LocationTable;
use Bitrix\Sale\Location\GeoIp;


class BaseOrderComponent extends \CBitrixComponent
{
    const URL_ORDER_CODE = 'ORDER_ID';
    const BASKET_URL = '/cart/';

    protected $siteId;
    protected $data;
    protected $isOrderFinal;
    protected $personTypeId;
    protected $userId;
    protected $profileId;
    protected $order;
    protected $currencyCode;
    protected $deliveryList = [];
    protected $paySystemList;
    protected $userType = [
        'phisic' => 1,
        'organization' => 2,
    ];

    public function __construct($component)
    {
        parent::__construct($component);

        $this->siteId = Context::getCurrent()->getSite();
        $this->currencyCode = Option::get('sale', 'default_currency', 'RUB');
        $this->userId = $this->initUser();
        $this->profileId = $this->initProfile();
        $this->order = $this->initOrder();

        $this->isOrderFinal = isset($_REQUEST[self::URL_ORDER_CODE]);
        $this->personTypeId = $this->getPersonTypeId();

        $this->data = $this->getData();
    }

    protected function getResult()
    {
        if ($this->isOrderFinal && $this->order){
            $this->initPaymentForm();
            return;
        }

        $this->order->setBasket(Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), $this->siteId)->getOrderableItems());

        if (!count($this->order->getBasket()))
            LocalRedirect(self::BASKET_URL);

        $this->order->setPersonTypeId($this->personTypeId);

        if ($this->data['LOCATION'])
            $this->setLocationId($this->data['LOCATION']);
        else
            $this->setLocationId($this->getAutoLocation());

        $this->initShipment($this->data['DELIVERY_ID']);
        $this->initPayment($this->data['PAYMENT_ID']);

        $this->arResult['DELIVERY_LIST'] = $this->getDeliveryList();
        $this->arResult['PAY_SYSTEMS'] = $this->getPaySystemList();

        if ($_SERVER['REQUEST_METHOD'] == 'POST'){
            $propertyCollection = $this->order->getPropertyCollection();
            foreach ($this->data as $key => $item){
                $property = $this->getPropertyByCode($propertyCollection, $key);
                if ($property){
                    $property->setValue($item);
                }
            }

            if ($_REQUEST['final'] == 'yes'){
                $this->order->save();
                $this->saveProfile();
                $this->sendEmail();
                LocalRedirect('/order/?ORDER_ID=' . $this->order->getId());
                die();
            }
        }
    }

    protected function getPropertyByCode($propertyCollection, $code)
    {
        $tmpAr = $propertyCollection->getArray();
        $codes = [];
        foreach($tmpAr['properties'] as $property){
            $codes[] = $property['CODE'];
        }

        if (!in_array($code, $codes)) return;

        foreach ($propertyCollection as $property)
        {
            if($property->getField('CODE') == $code)
                return $property;
        }
    }

    public function onIncludeComponentLang()
    {
        $this->includeComponentLang(basename(__FILE__));
        Loc::loadMessages(__FILE__);
    }

    public function onPrepareComponentParams($params)
    {
        return $params;
    }

    protected function checkModules()
    {
        if (!Main\Loader::includeModule('sale'))
            throw new Main\LoaderException('Модуль интернет магазина не установлен');
    }

    private function getPersonTypeId()
    {
        return $this->userType['phisic'];
    }

    protected function initUser(){
        global $USER;

        if ($USER->getId())
            return $USER->getId();
        else
            return \CSaleUser::GetAnonymousUserID();
    }

    private function initShipment($deliveryId = null)
    {
        $shipmentCollection = $this->order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem();
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        $shipment->setField('CURRENCY', $this->order->getCurrency());

        foreach ($this->order->getBasket() as $item)
        {
            $shipmentItem = $shipmentItemCollection->createItem($item);
            $shipmentItem->setQuantity($item->getQuantity());
        }

        $arDeliveryServiceAll = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
        $shipmentCollection = $shipment->getCollection();

        $clonedOrder = $this->order->createClone();
        foreach ($clonedOrder->getShipmentCollection() as $item)
        {
            if (!$shipment->isSystem())
                $clonedShipment =  $item;
        }

        if (!empty($arDeliveryServiceAll)) {
            reset($arDeliveryServiceAll);
            foreach ($arDeliveryServiceAll as $key => $delivery){
                $arDelivery = [];
                if ($delivery->isProfile()) {
                    $name = $delivery->getNameWithParent();
                } else {
                    $name = $delivery->getName();
                }

                $arDelivery['NAME'] = $name;
                $arDelivery['DESCRIPTION'] = $delivery->getDescription();

                $clonedShipment->setField('CUSTOM_PRICE_DELIVERY', 'N');
                $clonedOrder->getShipmentCollection()->calculateDelivery();
                $arDelivery['ID'] = $delivery->getId();

                $calcResult = $delivery->calculate($clonedShipment);

                if ($calcResult->isSuccess())
                {
                    $arDelivery['PRICE'] = Sale\PriceMaths::roundByFormatCurrency($calcResult->getPrice(), $this->order->getCurrency());
                    $arDelivery['PRICE_FORMATED'] = SaleFormatCurrency($arDelivery['PRICE'], $this->order->getCurrency());

                    $currentCalcDeliveryPrice = Sale\PriceMaths::roundByFormatCurrency($this->order->getDeliveryPrice(), $this->order->getCurrency());
                    if ($currentCalcDeliveryPrice >= 0 && $arDelivery['PRICE'] != $currentCalcDeliveryPrice)
                    {
                        $arDelivery['DELIVERY_DISCOUNT_PRICE'] = $currentCalcDeliveryPrice;
                        $arDelivery['DELIVERY_DISCOUNT_PRICE_FORMATED'] = SaleFormatCurrency($arDelivery['DELIVERY_DISCOUNT_PRICE'], $this->order->getCurrency());
                    }

                    if (strlen($calcResult->getPeriodDescription()) > 0)
                    {
                        $arDelivery['PERIOD_TEXT'] = $calcResult->getPeriodDescription();
                    }
                }
                else
                {
                    if (count($calcResult->getErrorMessages()) > 0)
                    {
                        foreach ($calcResult->getErrorMessages() as $message)
                        {
                            $arDelivery['CALCULATE_ERRORS'] .= $message.'<br>';
                        }
                    }
                    else
                    {
                        $arDelivery['CALCULATE_ERRORS'] = '';
                    }
                }

                $arDelivery['CALCULATE_DESCRIPTION'] = $calcResult->getDescription();
                $this->deliveryList[] =  $arDelivery;
                if ($deliveryId == $delivery->getId()){
                    $deliveryObj = $delivery;
                    $arDelivery['SELECTED'] = 'Y';
                }
            }

            if (!$deliveryObj){
                reset($arDeliveryServiceAll);
                $deliveryObj = current($arDeliveryServiceAll);
            }

            if ($deliveryObj->isProfile()) {
                $name = $deliveryObj->getNameWithParent();
            } else {
                $name = $deliveryObj->getName();
            }

            $shipment->setFields(array(
                'DELIVERY_ID' => $deliveryObj->getId(),
                'DELIVERY_NAME' => $name,
                'CURRENCY' => $this->order->getCurrency(),
            ));

            $shipmentCollection->calculateDelivery();

            $this->data['DELIVERY_ID'] = $deliveryObj->getId();
            $this->data['DELIVERY_NAME'] = $name;
        }
    }

    private function initPayment($paySystemId = null)
    {
        $arPaySystemServiceAll = [];
        $paymentCollection = $this->order->getPaymentCollection();

        $remainingSum = $this->order->getPrice() - $paymentCollection->getSum();
        if ($remainingSum > 0 || $this->order->getPrice() == 0)
        {
            $extPayment = $paymentCollection->createItem();
            $extPayment->setField('SUM', $remainingSum);
            $arPaySystemServices = PaySystem\Manager::getListWithRestrictions($extPayment);
            $arPaySystemServiceAll += $arPaySystemServices;

            if (array_key_exists($paySystemId, $arPaySystemServiceAll))
            {
                $arPaySystem = $arPaySystemServiceAll[$paySystemId];
            }
            else
            {
                reset($arPaySystemServiceAll);
                $arPaySystem = current($arPaySystemServiceAll);
            }

            if (!empty($arPaySystem))
            {
                $extPayment->setFields(array(
                    'PAY_SYSTEM_ID' => $arPaySystem["ID"],
                    'PAY_SYSTEM_NAME' => $arPaySystem["NAME"]
                ));
                $this->data['PAYMENT_NAME'] = $arPaySystem["NAME"];
                $this->data['PAYMENT_ID'] = $arPaySystem["ID"];
            }
            else
                $extPayment->delete();
        }

        $this->paySystemList = $arPaySystemServiceAll;
    }

    private function getData()
    {
        global $USER;
        $userId = $USER->getId();

        $return = [];

        if ($userId){
            $filter = ["ID" => $userId];
            $rsUsers = \CUser::GetList(($by="personal_country"), ($order="desc"), $filter, ["SELECT" => ["UF_*"]]);
            if($user = $rsUsers->fetch()){
                $return = [
                    'EMAIL' => $user['EMAIL'],
                    'PHONE' => $user['PERSONAL_PHONE'],
                    'NAME' => $user['NAME'],
                    'LAST_NAME' => $user['LAST_NAME'],
                    'SECOND_NAME' => $user['SECOND_NAME'],
                ];
            }

            $profileData = ($this->getProfile()) ?: [];
            if ($profileData)
                $return = $profileData + $return;
        }

        return $return;
    }

    public function executeComponent()
    {
        try
        {
            $this->checkModules();
            $this->getResult();
        }
        catch (Exception $e)
        {
            ShowError($e->getMessage());
        }
    }

    private function initOrder()
    {
        $orderIdFromRequest = intval($_REQUEST[self::URL_ORDER_CODE]);
        if ($orderIdFromRequest){
            $order = Sale\Order::loadByAccountNumber($orderIdFromRequest);
            if(!$order)
                $order = Sale\Order::load($orderIdFromRequest);
        }

        if (!$order)
            $order = Order::create($this->siteId, $this->userId);

        return $order;
    }

    private function initPaymentForm()
    {
        $paymentCollection = $this->order->getPaymentCollection();
        foreach ($paymentCollection as $payment)
        {
            $this->arResult["PAYMENT"][$payment->getId()] = $payment->getFieldValues();

            if (intval($payment->getPaymentSystemId()) > 0 && !$payment->isPaid())
            {
                $paySystemService = PaySystem\Manager::getObjectById($payment->getPaymentSystemId());
                if (!empty($paySystemService))
                {
                    $arPaySysAction = $paySystemService->getFieldsValues();

                    $initResult = $paySystemService->initiatePay($payment, null, PaySystem\BaseServiceHandler::STRING);

                    if ($initResult->isSuccess())
                        $arPaySysAction['BUFFERED_OUTPUT'] = $initResult->getTemplate();
                    else
                        $arPaySysAction["ERROR"] = $initResult->getErrorMessages();

                    $this->arResult["PAYMENT"][$payment->getId()]['PAID'] = $payment->getField('PAID');

                    $arPaySysAction["NAME"] = htmlspecialcharsEx($arPaySysAction["NAME"]);
                    $arPaySysAction["IS_AFFORD_PDF"] = $paySystemService->isAffordPdf();

                    if ($arPaySysAction > 0)
                        $arPaySysAction["LOGOTIP"] = CFile::GetFileArray($arPaySysAction["LOGOTIP"]);

                    $this->arResult["PAY_SYSTEM_LIST"][$payment->getPaymentSystemId()] = $arPaySysAction;
                }
                else
                    $this->arResult["PAY_SYSTEM_LIST"][$payment->getPaymentSystemId()] = array('ERROR' => true);
            }
        }
    }

    protected function getProfile()
    {
        if (!$this->profileId)
            return;

        $profileData = [];

        $res = \CSaleOrderUserPropsValue::GetList(
            [],
            [
                "PERSON_TYPE_ID" => $this->getPersonTypeId(),
                "USER_PROPS_ID" => $this->profileId,
            ],
            false,
            false,
            []
        );

        while ($profileItem = $res->fetch())
        {
            $profileData[$profileItem['PROP_CODE']] = $profileItem['VALUE'];
        }

        return $profileData;
    }

    private function saveProfile()
    {
        \CSaleOrderUserProps::DoSaveUserProfile(
            $this->order->getUserId(),
            $this->profileId,
            'Профиль пользователя [ID: ' . $this->order->getUserId() . ']',
            $this->order->getPersonTypeId(),
            $this->order->getId(),
            $errors
        );
    }

    protected function setPaymentId($id)
    {
        $this->initPayment($id);
    }

    protected function getPaymentId()
    {
        return $this->order->getField('PAY_SYSTEM_ID');
    }

    protected function getDeliveryId()
    {
        return $this->order->getField('DELIVERY_ID');
    }

    protected function setLocationId($id)
    {
        $propertyCollection = $this->order->getPropertyCollection();
        $locationProperty = $this->getPropertyByCode($propertyCollection, 'LOCATION');
        if ($locationProperty){
            $res = LocationTable::getList([
                'filter' => array('=NAME.LANGUAGE_ID' => LANGUAGE_ID, 'ID' => $id),
                'select' => array('*', 'NAME_RU' => 'NAME.NAME', 'TYPE_CODE' => 'TYPE.CODE')
            ]);
            if($loc = $res->fetch()){
                $locationProperty->setValue($loc['CODE']);
                $this->data['LOCATION'] = $loc['ID'];
            }
        }
    }

    protected function getLocationId()
    {
        $propertyCollection = $this->order->getPropertyCollection();
        $locationProperty = $this->getPropertyByCode($propertyCollection, 'LOCATION');
        if ($locationProperty){
            $code = $locationProperty->getValue();
            if ($code){
                $loc = LocationTable::getByCode($code)->fetch();
                return $loc['ID'];
            }
        }
    }

    protected function getDeliveryList()
    {
        return $this->deliveryList;
    }

    protected function getPaySystemList()
    {
        return $this->paySystemList;
    }

    protected function getAutoLocation()
    {
        $ip = \Bitrix\Main\Service\GeoIp\Manager::getRealIp();
        return GeoIp::getLocationId($ip, "ru");
    }

    protected function sendEmail()
    {
        $saleEmail = Option::get("sale", "order_email");
        $propertyCollection = $this->order->getPropertyCollection();

        $emailFields = [
            'SALE_EMAIL' => $saleEmail,
            'EMAIL' => $propertyCollection->getUserEmail(),
            'ORDER_ID' => $this->order->getId(),
            'ORDER_FINAL_PRICE' => SaleFormatCurrency($this->order->getPrice(), 'RUB'),
        ];

        foreach ($this->data as $key => $item){
            $property = $this->getPropertyByCode($propertyCollection, $key);
            if ($property){
                $emailFields[$key] = $item;
            }
        }

        Event::send(array(
            "EVENT_NAME" => "SH_SALE_NEW_ORDER",
            "LID" => \Bitrix\Main\Context::getCurrent()->getSite(),
            "C_FIELDS" => $emailFields,
        ));
    }

    protected function initProfile()
    {
        global $USER;

        if (!$USER->isAuthorized() || !$this->userId)
            return;

        $dbUserProfiles = \CSaleOrderUserProps::GetList(
            ["ID" => "ASC"],
            [
                "PERSON_TYPE_ID" => $this->getPersonTypeId(),
                "USER_ID" => $this->userId
            ]
        );

        if ($arUserProfiles = $dbUserProfiles->fetch())
            $this->profileId = intval($arUserProfiles["ID"]);
    }

    protected function getCartInfo(){
        $koef = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_koef', 1, $this->siteId));
        $unit = htmlspecialcharsbx(COption::GetOptionString('sale', 'weight_unit', "", $this->siteId));

        foreach($this->order->getBasket() as $basketItem){
            $productID = $basketItem->getProductId();
            $picture = '';

            $select = ["ID", "NAME", "IBLOCK_ID", 'PREVIEW_PICTURE', 'DETAIL_PICTURE'];
            $filter = ["ACTIVE_DATE" => "Y", "ACTIVE" => "Y", 'ID' => $productID];
            $res = \CIBlockElement::GetList([], $filter, false, false, $select);
            while($row = $res->fetch())
            {
                if ($row['PREVIEW_PICTURE']){
                    $picture = \CFile::GetFileArray($row['PREVIEW_PICTURE']);
                }elseif ($row['DETAIL_PICTURE']){
                    $picture = \CFile::GetFileArray($row['DETAIL_PICTURE']);
                }
            }

            $cartItems[] = [
                'NAME' => $basketItem->getField('NAME'),
                'QUANTITY' => $basketItem->getQuantity(),
                'WEIGHT' => $basketItem->getWeight(),
                'DETAIL_PAGE_URL' => $basketItem->getField('DETAIL_PAGE_URL'),
                'PRICE_FORMATTED' => SaleFormatCurrency($basketItem->getFinalPrice(), $this->currencyCode),
                'PRICE' => $basketItem->getFinalPrice(),
                'PICTURE' => $picture,
                'WEIGHT_FORMATED' => ($basketItem->getWeight() > 0) ?
                    number_format(
                        roundEx(
                            doubleval(
                                $basketItem->getWeight() / $koef
                            ),
                            SALE_WEIGHT_PRECISION
                        ),
                        1,
                        ',',
                        ' '
                    )
                    . ' '
                    . $unit
                    : '',
            ];
        }

        return $cartItems;
    }
}