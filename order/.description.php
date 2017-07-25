<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
	"NAME" => GetMessage("FEEDBACK_FORM_NAME"), //component name lang
	"DESCRIPTION" => GetMessage("FEEDBACK_FORM_DESCRIPTION"), //component description lang
	"ICON" => "", // component image path like "/images/cat_detail.gif"
	"CACHE_PATH" => "Y", // button for clear cache
	"SORT" => 10,
	"PATH" => array(
		"ID" => "shantilab", //main group name
		"NAME" => GetMessage("FEEDBACK_FORM_MAIN_GROUP_NAME"), //main group name
		"CHILD" => array(
			"ID" => "bxtools", //subgroup ID
			"NAME" => GetMessage("FEEDBACK_FORM_SUBGROUP_NAME"), //subgroup name
			"SORT" => 10
		),
	),
);

?>