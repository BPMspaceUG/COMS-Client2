<?php
require_once(__DIR__ . '/inc/COMS_Client_header.inc.php');
define('__ROOT__', dirname(__FILE__));
require_once(__DIR__.'/inc/COMS_Client_api.secret.inc.php');
require_once(__DIR__.'/inc/COMS_Client_api.inc.php');

require_once(__DIR__ . '/inc/php-jwt-master/src/BeforeValidException.inc.php');
require_once(__DIR__ . '/inc/php-jwt-master/src/ExpiredException.inc.php');
require_once(__DIR__ . '/inc/php-jwt-master/src/SignatureInvalidException.inc.php');
require_once(__DIR__ . '/inc/php-jwt-master/src/JWT.inc.php');
use \Firebase\JWT\JWT;

$url_array = explode('/', $_SERVER['REQUEST_URI']);
if (count($url_array) < 2) {
    $error_404 = true;
}

//$url_array = array_reverse($url_array);
$USER_TYPE = $url_array[1];
$PARTID_MD5 = $url_array[2];
$PARTID = $url_array[3];
if (strpos($PARTID, '?')) {
    $PARTID = strstr($PARTID, '?', true);
}
//check validity of URL
if (ctype_xdigit($PARTID_MD5) && strlen($PARTID_MD5) == 32 && strlen($PARTID) == 6 && $PARTID_MD5 == (md5($PARTID))) {
    $PARTID_MD5_first5 = substr($PARTID_MD5, 0, 5);
    $matNr_postfix = substr(base_convert($PARTID_MD5_first5, 16, 10), 0, 3);
    $matNr = $PARTID.substr(base_convert($PARTID_MD5_first5, 16, 10), 0, 3);
    $matNr = str_pad(strtoupper(base_convert($matNr, 10, 32)), 8, "0", STR_PAD_LEFT);
}
else {
    $error_404 = true;
}

if (isset($_GET['token'])) {
    $jwt = $_GET['token'];
    $jwt_key = AUTH_KEY;

    /**
     * You can add a leeway to account for when there is a clock skew times between
     * the signing and verifying servers. It is recommended that this leeway should
     * not be bigger than a few minutes.
     *
     * Source: http://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#nbfDef
     */
    JWT::$leeway = 60; // $leeway in seconds
    try {
        $decoded = JWT::decode($jwt, $jwt_key, array('HS256'));
    } catch (Exception $e) {
        $jwt_error = $e->getMessage();
    }
    $user_id = $decoded->uid;

    if (!isset($jwt_error)) {
        if ($USER_TYPE == 'ato' && !isset($error_404)) {
            require_once(__DIR__ . '/inc/COMS_Client_load_ato_data.inc.php');
        } elseif ($USER_TYPE == 'participant' && !isset($error_404)) {
            require_once(__DIR__ . '/inc/COMS_Client_load_participant_data.inc.php');
        } else {
            $error_404 = true;
        }
    }
}
if (isset($jwt_error)) {
    $error = $jwt_error;
    require_once(__DIR__ . '/inc/templates/COMS_Client_error_page.inc.php');
}
if (isset($error_404)) {
    header('Location: /404.php');
    exit();
}
if (!isset($_GET['token']) && !isset($_SESSION['coms_user_id']) && !isset($jwt_error)) {
    $db_content = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__id__email",
        "where" => "a.coms_participant_md5 = '$PARTID_MD5' && a.coms_participant_id = $PARTID && coms_participant_email_state_id in (113,114)"))), true);
    // If email exists in coms db
    if ($db_content) {
        $liam_id = $db_content[0]['coms_participant_LIAM_id'];
        // If liam id is set in coms
        if ($liam_id) {
            $liam_user = json_decode(liam_client_api(json_encode(array("cmd" => "read", "paramJS" => array("table" => "liam2_User",
                "where" => "liam2_User_id = $liam_id")))), true);
            // If firstname and lastname are the same in coms and liam
            if ($liam_user[0]['liam2_User_firstname'] == $db_content[0]['coms_participant_firstname'] && $liam_user[0]['liam2_User_lastname'] == $db_content[0]['coms_participant_lastname']) {
                $coms_emails = array();
                foreach ($db_content as $value) {
                    $coms_email = $value['coms_participant_emailadresss'];
                    $coms_emails[] = $value['coms_participant_emailadresss'];
                    $liam_email = json_decode(liam_client_api(json_encode(array("cmd" => "read", "paramJS" => array("table" => "liam2_User_email",
                        "where" => "liam2_User_id = '$liam_id' && liam2_email_text = '$coms_email'")))), true);
                    if ($liam_email) {
                        $liam_email_exists = true;
                        $liam_email_id = $liam_email[0]['liam2_email_id_fk_396224']['liam2_email_id'];
                        $liam_email_verified = json_decode(liam_client_api(json_encode(array("cmd" => "read", "paramJS" => array("table" => "liam2_email",
                            "where" => "liam2_email_id = '$liam_email_id'")))), true);
                        if ($liam_email_verified[0]['state_id']['state_id'] == 14 && $value['coms_participant_email_state_id'] == 113) {
                            $set_coms_email_to_active = json_decode(coms_client_api(array("cmd" => "makeTransition", "paramJS" => array("table" => "coms_participant_email", "row" => array(
                                "coms_participant_email_id" => $value['coms_participant_email_id'],
                                "state_id" => 114
                            )))), true);
                        }
                    } else {
                        $coms_email_not_in_liam = $value['coms_participant_emailadresss'];
                    }
                }
                // If coms email exists in liam
                if (isset($liam_email_exists)) {
                    // If additional coms email is not found in liam
                    if (isset($coms_email_not_in_liam)) {
                        header('Location: ' . LIAM_URL . '/LIAM2_Client_manage_emails.php?origin=//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '&liam2_add_another_email=' . $coms_email_not_in_liam . '&user_id=' . $liam_id);
                        exit();
                    } else {
                        $liam_emails = json_decode(liam_client_api(json_encode(array("cmd" => "read", "paramJS" => array("table" => "liam2_User_email",
                            "where" => "liam2_User_id = '$liam_id'")))), true);
                        foreach ($liam_emails as $liam_email) {
                            // If liam email is not found in coms
                            if (!in_array($liam_email['liam2_email_id_fk_396224']['liam2_email_text'], $coms_emails)) {
                                $create_coms_email = json_decode(coms_client_api(array("cmd" => "create", "paramJS" => array("table" => "coms_participant_email", "row" => array(
                                    "coms_participant_id" => $PARTID,
                                    "coms_participant_emailadresss" => $liam_email['liam2_email_id_fk_396224']['liam2_email_text'],
                                )))), true);
                                if (count($create_coms_email) == 1) $error = $create_coms_email[0]['errormsg'];
                            }
                        }
                    }
                } else {
                    $error = 'Emails in coms and liam are different';
                }
                // Login
                if (!$error) {
                    header('Location: ' . LIAM_URL . '/LIAM2_Client_login.php?origin=//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    exit();
                }
            } else {
                $error = 'Firstname or lastname different in coms and liam';
            }
            // If liam id is not set in coms
        } else {
            foreach ($db_content as $value) {
                $coms_email = $value['coms_participant_emailadresss'];
                $liam_email = json_decode(liam_client_api(json_encode(array("cmd" => "read", "paramJS" => array("table" => "liam2_User_email",
                    "where" => "liam2_email_text = '$coms_email'")))), true);
                // If coms email is found in liam
                if ($liam_email) {
                    $liam_email_exists = true;
                    if (!$liam_id) $liam_id = $liam_email[0]['liam2_User_id_fk_164887']['liam2_User_id'];
                }
                if (isset($liam_id) && $liam_id != $liam_email[0]['liam2_User_id_fk_164887']['liam2_User_id']) {
                    $error = 'E-Mails (if there are more than 2) belong to 2 or more different LIAMS ID';
                }
            }
            // If coms email is not found in liam
            if (!isset($liam_email_exists)) {

                /*if ($db_content[0]['coms_participant_firstname'] && $db_content[0]['coms_participant_lastname']) {

                } else {*/
                    header('Location: ' . LIAM_URL . '/LIAM2_Client_self_register.php?origin=//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '&email=' . $db_content[0]['coms_participant_emailadresss'] . '&firstname=' . $db_content[0]['coms_participant_firstname'] . '&lastname=' . $db_content[0]['coms_participant_lastname']);
                    exit();
                //}
                // If coms email is found in liam
            } else {
                $coms_firstname = $db_content[0]['coms_participant_firstname'];
                $coms_lastname = $db_content[0]['coms_participant_lastname'];
                $liam_firstname = $liam_email[0]['liam2_User_id_fk_164887']['liam2_User_firstname'];
                $liam_lastname = $liam_email[0]['liam2_User_id_fk_164887']['liam2_User_lastname'];
                // Check firstname and lastname
                if ((!$coms_firstname || $coms_firstname == $liam_firstname) && (!$coms_lastname || $coms_lastname == $liam_lastname)) {
                    $create_liam_id_in_coms = json_decode(coms_client_api(array("cmd" => "makeTransition", "paramJS" => array("table" => "coms_participant", "row" => array(
                        "coms_participant_id" => $db_content[0]['coms_participant_id'],
                        "coms_participant_firstname" => $liam_firstname,
                        "coms_participant_lastname" => $liam_lastname,
                        "coms_participant_LIAM_id" => $liam_email[0]['liam2_User_id_fk_164887']['liam2_User_id'],
                        "state_id" => 111
                    )))), true);
                } else {
                    $error = 'Different names error';
                }
            }
        }
    } else {
        $error = 'No e-Mail active (or new)';
    }
    // Refresh after succesful actions so the user can login
    if (!$error) {
        header('Location: //'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
        exit();
    }
    require_once(__DIR__ . '/inc/templates/COMS_Client_error_page.inc.php');
}