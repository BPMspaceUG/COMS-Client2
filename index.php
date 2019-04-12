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
    echo $jwt_error;exit();
}
if (isset($error_404)) {
    header('Location: /404.php');
    exit();
}
if (!isset($_GET['token']) && !isset($_SESSION['coms_user_id'])) {
    $db_content = json_decode(coms_client_api(array("cmd" => "read", "paramJS" => array("table" => "v_coms_participant__id__email",
        "where" => "a.coms_participant_md5 = '$PARTID_MD5' && a.coms_participant_id = $PARTID"))), true);
    if ($db_content) {
        if ($db_content[0]['coms_participant_LIAM_id']) {
            header('Location: http://liam2-client.local/LIAM2_Client_login.php?origin=//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        } else {
            header('Location: http://liam2-client.local/LIAM2_Client_self_register.php');
        }
    } else {
        $error = "Couldn't login";
    }
}