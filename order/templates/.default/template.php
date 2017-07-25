<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use \Bitrix\Main\Localization\Loc as Loc;
Loc::loadMessages(__FILE__);
?>

<?if (!isset($_REQUEST['ORDER_ID'])){
    require_once (__DIR__ . '/order.php');
}else{
    require_once (__DIR__ . '/result.php');
}?>