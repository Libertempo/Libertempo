<?php declare(strict_types = 1);
defined('ROOT_PATH') or define('ROOT_PATH', '');
defined('INCLUDE_PATH') or define('INCLUDE_PATH',     ROOT_PATH . 'includes/');
require_once INCLUDE_PATH . 'define.php';
require CONFIG_PATH . 'dbconnect.php';

// L'installation a-t-elle été faite ?
if (!\includes\SQL::existsDatabase($mysql_database)) {
    echo "L'application n'est pas installée. Veuillez passer par l'installateur.";
    exit();
}

function redirectAuth() : void
{
    $request= "SELECT u_nom, u_passwd, u_prenom, u_is_resp, u_is_hr, u_is_admin, u_is_active  FROM conges_users where u_login = '". \includes\SQL::quote($_SESSION['userlogin'])."' " ;
    $rs = \includes\SQL::query($request );
    if ($rs->num_rows != 1) {
        redirect(ROOT_PATH . 'authentification');
    } else {
        $row = $rs->fetch_array();
        $is_hr = $row["u_is_hr"];
        $is_resp = $row["u_is_resp"];
        $is_active = $row["u_is_active"];
        if($is_active == "N") {
            errorInactif();
            return;
        }
        if ($is_hr == "Y") {
            redirect( ROOT_PATH .'hr/page_principale.php');
        } elseif ($is_resp=="Y") {
            redirect( ROOT_PATH .'responsable/resp_index.php');
        } else {
            redirect( ROOT_PATH . 'utilisateur/user_index.php');
        }
    }
}

function errorInactif()
{
    header_error();
    echo  _('session_compte_inactif') ."<br>\n";
    echo  _('session_contactez_admin') ."\n";
    bottom();
}

function errorAuthentification() {
    header_error();
    echo  _('session_pas_de_compte_dans_dbconges') ."<br>\n";
    echo  _('session_contactez_admin') ."\n";
    bottom();
}

function authCas(\App\Libraries\ApiClient $api, \App\Libraries\Configuration $config)
{
    try {
        $usernameCAS = authentification_passwd_conges_CAS();
        if ($usernameCAS == "") {
            throw new \Exception("Nom d'utilisateur vide");
        }
        session_create($usernameCAS);
        storeTokenApi($api, $usernameCAS, '');
    } catch (\Exception $e) {
        errorAuthentification();
        deconnexion_CAS($config->getUrlAccueil());
    }
}

function authSso(\App\Libraries\ApiClient $api)
{
    if (session_id() != "") {
        session_destroy();
    }
    try {
        $usernameSSO = authentification_AD_SSO();
        if ($usernameSSO == "") {
            throw new \Exception("Nom d'utilisateur vide");
        }
        session_create($usernameSSO);
        storeTokenApi($api, $usernameSSO, '');
    } catch (\Exception $e) {
        errorAuthentification();
    }
}

function authDefault(string $authMethod, \App\Libraries\ApiClient $api)
{
    $session_username = $_POST['session_username'] ?? '';
    $session_password = $_POST['session_password'] ?? '';
    if (session_id() != "") {
        session_destroy();
    }

    if (($session_username == "") || ($session_password == "")) {
        session_saisie_user_password("", "");
    } else {
        if ('ldap' == $authMethod) {
            $usernameAuth = authentification_ldap_conges($session_username, $session_password);
        } else {
            $usernameAuth = authentification_passwd_conges($session_username, $session_password);
        }
        try {
            if ($usernameAuth != $session_username) {
                throw new \Exception("Noms d'utilisateurs différents");
            }
            session_create($session_username);
            storeTokenApi($api, $session_username, $session_password);
        } catch (\Exception $e) {
            ddd($e->getMessage());
            $session_username="";
            $session_password="";
            $erreur="login_passwd_incorrect";
            session_saisie_user_password($erreur, $session_username);
        }
    }
}

$sql = \includes\SQL::singleton();
$config = new \App\Libraries\Configuration($sql);
$injectableCreator = new \App\Libraries\InjectableCreator($sql, $config);
$api = $injectableCreator->get(\App\Libraries\ApiClient::class);

if (!session_is_valid()) {
    $authMethod = $config->getHowToConnectUser();
    switch ($authMethod) {
        case 'cas':
            authCas($api, $config);
            break;
        case 'SSO':
            authSso($api);
            break;
        default:
            authDefault($authMethod, $api);
            break;
    }
}

if (isset($_SESSION['userlogin'])) {
    redirectAuth();
}
