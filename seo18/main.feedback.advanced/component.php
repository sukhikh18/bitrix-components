<?php
if(!defined("B_PROLOG_INCLUDED")||B_PROLOG_INCLUDED!==true)die();

/**
 * Bitrix vars
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
$arResult["PARAMS_HASH"] = md5(serialize($arParams).$this->GetTemplateName());

$arParams["USE_CAPTCHA"] = (($arParams["USE_CAPTCHA"] != "N" && !$USER->IsAuthorized()) ? "Y" : "N");
$arParams["EVENT_NAME"] = trim($arParams["EVENT_NAME"]);
$arParams["EMAIL_TO"] = trim($arParams["EMAIL_TO"]);
$arParams["OK_TEXT"] = trim($arParams["OK_TEXT"]);

if(strlen($arParams["EVENT_NAME"]) <= 0)
	$arParams["EVENT_NAME"] = "FEEDBACK_FORM";

if(strlen($arParams["EMAIL_TO"]) <= 0)
	$arParams["EMAIL_TO"] = COption::GetOptionString("main", "email_from");

if(strlen($arParams["OK_TEXT"]) <= 0)
	$arParams["OK_TEXT"] = GetMessage("MF_OK_MESSAGE");

$arParams['NEW_EXT_FIELDS'] = Array();
if( is_array($arParams['CFIELD_NAME']) ) {
    for ($i=0; $i < count($arParams['CFIELD_NAME']); $i++) {
        if( $arParams['CFIELD_NAME'][$i] ) {
            $arParams['NEW_EXT_FIELDS'][] = (object) array(
                'num'  => $i,
                'req'  => $arParams['CFIELD_REQ'][$i],
                'type' => $arParams['CFIELD_TYPE'][$i],
                'name' => $arParams['CFIELD_NAME'][$i],
            );
        }
    }
}

if($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["submit"] <> '' && (!isset($_POST["PARAMS_HASH"]) || $arResult["PARAMS_HASH"] === $_POST["PARAMS_HASH"])) {
	$arResult["ERROR_MESSAGE"] = array();
	if(check_bitrix_sessid())
	{
		// Check Required
		if(empty($arParams["REQUIRED_FIELDS"]) || !in_array("NONE", $arParams["REQUIRED_FIELDS"]))
		{
			if((in_array("NAME", $arParams["REQUIRED_FIELDS"])) && strlen($_POST["user_name"]) <= 1)
				$arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_NAME");

			if((in_array("EMAIL", $arParams["REQUIRED_FIELDS"])) && strlen($_POST["user_email"]) <= 1)
				$arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_EMAIL");

			if((in_array("MESSAGE", $arParams["REQUIRED_FIELDS"])) && strlen($_POST["MESSAGE"]) <= 3)
				$arResult["ERROR_MESSAGE"][] = GetMessage("MF_REQ_MESSAGE");
		}

		// Email validation
		if(strlen($_POST["user_email"]) > 1 && !check_email($_POST["user_email"]))
			$arResult["ERROR_MESSAGE"][] = GetMessage("MF_EMAIL_NOT_VALID");

        // Check security code
		if($arParams["USE_CAPTCHA"] == "Y") {
			include_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/captcha.php");
			$captcha_code = $_POST["captcha_sid"];
			$captcha_word = $_POST["captcha_word"];
			$cpt = new CCaptcha();
			$captchaPass = COption::GetOptionString("main", "captcha_password", "");
			if (strlen($captcha_word) > 0 && strlen($captcha_code) > 0)
			{
				if (!$cpt->CheckCodeCrypt($captcha_word, $captcha_code, $captchaPass))
					$arResult["ERROR_MESSAGE"][] = GetMessage("MF_CAPTCHA_WRONG");
			}
			else
				$arResult["ERROR_MESSAGE"][] = GetMessage("MF_CAPTHCA_EMPTY");
		}

        // Check custom fields required
        foreach ($arParams['NEW_EXT_FIELDS'] as $obField) {
            if( "Y" === $obField->req && strlen($_POST["CUSTOM"][ $obField->num ]) <= 1)
                $arResult["ERROR_MESSAGE"][] = "Поле {$obField->name} обязательно к заполнению.";
        }

		if(empty($arResult["ERROR_MESSAGE"]))
		{
			$arFields = Array(
				"AUTHOR" => $_POST["user_name"],
				"AUTHOR_EMAIL" => $_POST["user_email"],
				"EMAIL_TO" => $arParams["EMAIL_TO"],
				"TEXT" => $_POST["MESSAGE"],
			);

			foreach($_POST["CUSTOM"] as $i => $custom_field)
				$arFields["TEXT"] .= "\n\n".$arParams['NEW_EXT_FIELDS'][$i]->name.": ".$custom_field;

			if(!empty($arParams["EVENT_MESSAGE_ID"]))
			{
				foreach($arParams["EVENT_MESSAGE_ID"] as $v)
					if(IntVal($v) > 0)
						CEvent::Send($arParams["EVENT_NAME"], SITE_ID, $arFields, "N", IntVal($v));
			}
			else
				CEvent::Send($arParams["EVENT_NAME"], SITE_ID, $arFields);

			$_SESSION["MF_NAME"] = htmlspecialcharsEx($_POST["user_name"]);
			$_SESSION["MF_EMAIL"] = htmlspecialcharsEx($_POST["user_email"]);
			LocalRedirect($APPLICATION->GetCurPageParam("success=".$arResult["PARAMS_HASH"], Array("success")));
		}

		$arResult["MESSAGE"] = htmlspecialcharsEx($_POST["MESSAGE"]);
		$arResult["AUTHOR_NAME"] = htmlspecialcharsEx($_POST["user_name"]);
		$arResult["AUTHOR_EMAIL"] = htmlspecialcharsEx($_POST["user_email"]);
		foreach($_POST["CUSTOM"] as $i => $custom_field)
		{
			$arResult["CUSTOM_$i"] = htmlspecialcharsEx($custom_field);
		}
	}
	else
		$arResult["ERROR_MESSAGE"][] = GetMessage("MF_SESS_EXP");
}
elseif($_REQUEST["success"] == $arResult["PARAMS_HASH"])
{
	$arResult["OK_MESSAGE"] = $arParams["OK_TEXT"];
}

if(empty($arResult["ERROR_MESSAGE"]))
{
	if($USER->IsAuthorized())
	{
		$arResult["AUTHOR_NAME"] = htmlspecialcharsEx($USER->GetFullName());
		$arResult["AUTHOR_EMAIL"] = htmlspecialcharsEx($USER->GetEmail());
	}
	else
	{
		if(strlen($_SESSION["MF_NAME"]) > 0)
			$arResult["AUTHOR_NAME"] = htmlspecialcharsEx($_SESSION["MF_NAME"]);
		if(strlen($_SESSION["MF_EMAIL"]) > 0)
			$arResult["AUTHOR_EMAIL"] = htmlspecialcharsEx($_SESSION["MF_EMAIL"]);
	}
}

if($arParams["USE_CAPTCHA"] == "Y")
	$arResult["capCode"] =  htmlspecialchars($APPLICATION->CaptchaGetCode());

$this->IncludeComponentTemplate();
?>