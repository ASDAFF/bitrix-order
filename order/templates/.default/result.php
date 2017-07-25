<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use \Bitrix\Main\Localization\Loc as Loc;
$APPLICATION->SetTitle('Заказ сформирован');
?>
<? if (!empty($arResult["ORDER"])): ?>
    <div class="news_cat news_page news_checkout">
        <h3>Номер заказа #<?=intval($_REQUEST['ORDER_ID'])?></h3>
        <p>Информация о заказе отправлена на вашу электронную почту. В ближайшее время с Вами свяжется наш оператор для уточнения деталей заказа.</p>

        <?
        if (!empty($arResult["PAYMENT"]))
        {
            foreach ($arResult["PAYMENT"] as $payment)
            {
                if ($payment["PAID"] != 'Y')
                {
                    if (!empty($arResult['PAY_SYSTEM_LIST'])
                        && array_key_exists($payment["PAY_SYSTEM_ID"], $arResult['PAY_SYSTEM_LIST'])
                    )
                    {
                        $arPaySystem = $arResult['PAY_SYSTEM_LIST'][$payment["PAY_SYSTEM_ID"]];

                        if (empty($arPaySystem["ERROR"]))
                        {
                            ?>
                            <br /><br />
                            <table class="sale_order_full_table">
                                <tr>
                                    <td class="ps_logo">
                                        <div class="pay_name">Платежная система</div>
                                        <?= CFile::ShowImage($arPaySystem["LOGOTIP"], 100, 100, "border=0\" style=\"width:100px\"", "", false) ?>
                                        <div class="paysystem_name"><?= $arPaySystem["NAME"] ?></div>
                                        <br/>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <? if (strlen($arPaySystem["ACTION_FILE"]) > 0 && $arPaySystem["NEW_WINDOW"] == "Y" && $arPaySystem["IS_CASH"] != "Y"): ?>
                                            <?
                                            $orderAccountNumber = urlencode(urlencode($arResult["ORDER"]["ACCOUNT_NUMBER"]));
                                            $paymentAccountNumber = $payment["ACCOUNT_NUMBER"];
                                            ?>
                                            <script>
                                                window.open('<?=$arParams["PATH_TO_PAYMENT"]?>?ORDER_ID=<?=$orderAccountNumber?>&PAYMENT_ID=<?=$paymentAccountNumber?>');
                                            </script>
                                        <?=Loc::getMessage("SOA_PAY_LINK", array("#LINK#" => $arParams["PATH_TO_PAYMENT"]."?ORDER_ID=".$orderAccountNumber."&PAYMENT_ID=".$paymentAccountNumber))?>
                                        <? if (CSalePdf::isPdfAvailable() && $arPaySystem['IS_AFFORD_PDF']): ?>
                                        <br/>
                                            <?=Loc::getMessage("SOA_PAY_PDF", array("#LINK#" => $arParams["PATH_TO_PAYMENT"]."?ORDER_ID=".$orderAccountNumber."&pdf=1&DOWNLOAD=Y"))?>
                                        <? endif ?>
                                        <? else: ?>
                                            <?=$arPaySystem["BUFFERED_OUTPUT"]?>
                                        <? endif ?>
                                    </td>
                                </tr>
                            </table>

                            <?
                        }
                        else
                        {
                            ?>
                            <span style="color:red;">Ошибка выбранного способа оплаты. Обратитесь к Администрации сайта, либо выберите другой способ оплаты.</span>
                            <?
                        }
                    }
                    else
                    {
                        ?>
                        <span style="color:red;">Ошибка выбранного способа оплаты. Обратитесь к Администрации сайта, либо выберите другой способ оплаты.</span>
                        <?
                    }
                }
            }
        }
        ?>
    </div>
<?else:?>

    <b>Ошибка формирования заказа</b>
    <br /><br />

    <table class="sale_order_full_table">
        <tr>
            <td>
                Заказ №<?=intval($_REQUEST['ORDER_ID'])?> не найден.
                Пожалуйста обратитесь к администрации интернет-магазина или попробуйте оформить ваш заказ еще раз.
            </td>
        </tr>
    </table>
<?endif;?>