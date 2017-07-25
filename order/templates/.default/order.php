<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use \Bitrix\Main\Localization\Loc as Loc;
Loc::loadMessages(__FILE__);
?>
<form action="" class="order-form" method="POST">
    <?$APPLICATION->IncludeComponent(
        "bitrix:sale.location.selector.search",
        "",
       [
            "ID" => $arResult['DATA']['LOCATION'],
            "CODE" => "",
            "INPUT_NAME" => 'LOCATION',
            "PROVIDE_LINK_BY" => "id",
            "JSCONTROL_GLOBAL_ID" => "",
            "JS_CALLBACK" => "getLocation();",
            "FILTER_BY_SITE" => "Y",
            "SHOW_DEFAULT_LOCATIONS" => "Y",
            "CACHE_TYPE" => "A",
            "CACHE_TIME" => "36000000",
            "FILTER_SITE_ID" => "s1",
            "PRECACHE_LAST_LEVEL" => "N",
            "PRESELECT_TREE_TRUNK" => "N",
            "DISABLE_KEYBOARD_INPUT" => "N",
            "INITIALIZE_BY_GLOBAL_EVENT" => "",
            "SUPPRESS_ERRORS" => "N"
        ]
    );?>

    <?if ($arResult['DATA']['LOCATION']):?>
        <?foreach ($arResult['DELIVERY_LIST'] as $key => $item):?>
            <input id="d_<?=$item['ID']?>" type="radio" name="DELIVERY_ID" <?=($arResult['DATA']['DELIVERY_ID'] ==$item['ID']) ? 'checked="checked"': ''?> value="<?=$item['ID']?>">
            <label for="d_<?=$item['ID']?>"><?=$item['NAME']?></label>
            <?if ($item['CALCULATE_ERRORS']):?>
                <div class="delivery_error">
                    <b>Не удалось рассчитать стоимость доставки.</b>
                    <div>Вы можете продолжить оформление заказа, а чуть позже менеджер магазина свяжется с вами и уточнит информацию по доставке.</div>
                </div>
            <?endif?>
            <?=$item['PERIOD_TEXT']?>
            <?if ($item['PRICE']):?>
                <?/*Price*/?>
            <?endif;?>
        <?endforeach?>
    <?endif;?>

    <?foreach ($arResult['PAY_SYSTEMS'] as $key => $paySystem):?>
        <input id="p_<?=$paySystem['ID']?>" type="radio" name="PAYMENT_ID" <?=($arResult['DATA']['PAYMENT_ID'] == $paySystem['ID']) ? 'checked="checked"': ''?> value="<?=$paySystem['ID']?>">
        <label for="p_<?=$paySystem['ID']?>"><?=$paySystem['NAME']?>
            <?=$paySystem['DESCRIPTION']?>
        </label>
    <?endforeach?>

    <div class="ui-label">Электронная почта</div>
    <input type="email" placeholder="john@gmail.com" name="EMAIL" class="ui-form-control" value="<?=$arResult['DATA']['EMAIL']?>">
    <br>
    <input type="submit" name="submit" value="Создать">
</form>

<div class="order-confirm__block-info">
    <div class="title">Способ доставки</div>
    <div class="description"><?=$arResult['DATA']['DELIVERY_NAME']?></div>
</div>
<div class="order-confirm__block-info">
    <div class="title">Способ оплаты</div>
    <div class="description"><?=$arResult['DATA']['PAYMENT_NAME']?></div>
</div>

<?foreach ($arResult['BASKET_ITEMS']as $item):?>
    <tr>
        <td class="item-img">
            <?if ($item['PICTURE']['SRC']):?>
                <a href="<?=$item['DETAIL_PAGE_URL']?>">
                    <img src="<?=$item['PICTURE']['SRC']?>">
                </a>
            <?endif;?>
        </td>
        <td class="item-description">
            <a href="<?=$item['DETAIL_PAGE_URL']?>"><?=$item['NAME']?></a>
            <?if ($item['WEIGHT']):?>
                <p>Вес: <?=$item['WEIGHT']?> кг</p>
            <?endif;?>
        </td>
        <td class="count"><?=$item['QUANTITY']?></td>
        <td class="price"><?=$item['PRICE_FORMATTED']?></td>
    </tr>
<?endforeach;?>

<?if ($arResult['BASKET_PRICE']):?>
    <tr class="order-confirm-summ first">
        <td colspan="2"></td>
        <td class="count summ-name">Товары</td>
        <td class="price"><?=$arResult['BASKET_PRICE_FORMATTED']?></td>
    </tr>
<?endif;?>

<?=$arResult['ORDER_DELIVERY_PRICE_FORMATTED']?>

<?if ($arResult['ORDER']):?>
   <?=$arResult['ORDER_PRICE_FORMATTED']?>
<?endif;?>

