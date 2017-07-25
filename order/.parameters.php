<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Localization\Loc as Loc;
Loc::loadMessages(__FILE__);

$arComponentParameters = [
	"GROUPS" => [],
	"PARAMETERS" => [
		"CACHE_TIME"  =>  ["DEFAULT"=>36000000],
		"CACHE_GROUPS" => [
			"PARENT" => "CACHE_SETTINGS",
			"NAME" => Loc::GetMessage("BASE_CP_BCE_CACHE_GROUPS"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		],
        "PERSONAL" => [
            "PARENT" => "BASE",
            "NAME" => '',
            "TYPE" => "CHECKBOX",
            "DEFAULT" => '',
        ],
	],
];