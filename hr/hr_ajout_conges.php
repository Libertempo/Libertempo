<?php declare(strict_types = 1);

/*
 * $tab_type_cong
 * $tab_type_conges_exceptionnels
 */

defined('_PHP_CONGES') or die('Restricted access');

function insert_ajout_dans_periode($login, $nombreJours, $idTypeAbsence, $commentaire)
{
    $today = date("Y-m-d");

    insert_dans_periode($login, $today, "am", $today, "am", $nombreJours, $commentaire, $idTypeAbsence, "ajout", 0);
}

function ajout_conges($tab_champ_saisie)
{
    $db = \includes\SQL::singleton();

    foreach ($tab_champ_saisie as $user_name => $tab_conges) {
        // tab_champ_saisie[$current_login][$id_conges]=valeur du nb de jours ajouté saisi
        foreach ($tab_conges as $id_conges => $user_nb_jours_ajout) {

            $valid=verif_saisie_decimal($user_nb_jours_ajout);
            if (!$valid || $user_nb_jours_ajout == 0) {
                continue;
            }
            /* Modification de la table conges_users */
            $sql1 = 'UPDATE conges_solde_user SET su_solde = su_solde+' . $user_nb_jours_ajout . ' WHERE su_login="' . $db->quote($user_name) . '" AND su_abs_id = "' . $db->quote($id_conges) . '";';
            /* On valide l'UPDATE dans la table ! */
            $db->query($sql1);

            // on insert l'ajout de conges dans la table periode
            $commentaire =  _('resp_ajout_conges_comment_periode_user');
            insert_ajout_dans_periode($user_name, $user_nb_jours_ajout, $id_conges, $commentaire);
        }
    }
}

function ajout_global(array $tab_new_nb_conges, array $calculProportionnel, array $tab_new_comment_all, string $loginSession)
{
    $db = \includes\SQL::singleton();

    // recup de la liste de TOUS les users dont $resp_login est responsable
    // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
    // renvoit une liste de login entre quotes et séparés par des virgules
    $list_users_du_resp = \App\ProtoControllers\Utilisateur::getListId(true);

    foreach ($tab_new_nb_conges as $id_conges => $nb_jours) {
        if ($nb_jours == 0) {
            continue;
        }
        $comment = $tab_new_comment_all[$id_conges];

        $sql1= 'SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ("' . implode('","', $list_users_du_resp) . '") ORDER BY u_login';
        $ReqLog1 = $db->query($sql1);

        while ($resultat1 = $ReqLog1->fetch_array()) {
            $current_login = $resultat1["u_login"];
            $current_quotite = $resultat1["u_quotite"];

            if ((!isset($calculProportionnel[$id_conges])) || ($calculProportionnel[$id_conges] != true) ) {
                $nb_conges=$nb_jours;
            } else {
                // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2
                $nb_conges = (round(($nb_jours*($current_quotite/100))*2))/2  ;
            }
            $valid = verif_saisie_decimal($nb_conges);
            if (!$valid) {
                continue;
            }
            // 1 : update de la table conges_solde_user
            $req_update = 'UPDATE conges_solde_user SET su_solde = su_solde + '.$nb_conges.'
                    WHERE  su_login = "'. $db->quote($current_login).'"  AND su_abs_id = "'. $db->quote($id_conges).'";';
            $db->query($req_update);

            // 2 : on insert l'ajout de conges GLOBAL (pour tous les users) dans la table periode
            $commentaire =  _('resp_ajout_conges_comment_periode_all');
            // ajout conges
            insert_ajout_dans_periode($current_login, $nb_conges, $id_conges, $commentaire);
        }

        if ((!isset($calculProportionnel[$id_conges])) || ($calculProportionnel[$id_conges]!= true) ) {
            $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
        } else {
            $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
        }
        log_action(0, "ajout", "tous", $comment_log);
    }
}

function ajout_global_groupe($choix_groupe, array $tab_new_nb_conges, array $calculProportionnel, array $tab_new_comment_all)
{
    $db = \includes\SQL::singleton();

    // recup de la liste des users d'un groupe donné
    $list_users = get_list_users_du_groupe($choix_groupe);
    if (empty($list_users)) {
        return;
    }
    foreach ($tab_new_nb_conges as $id_conges => $nb_jours) {
        if ($nb_jours!=0) {
            $comment = $tab_new_comment_all[$id_conges];

            $sql1="SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ($list_users) AND u_is_active='Y' ORDER BY u_login ";
            $ReqLog1 = $db->query($sql1);

            while ($resultat1 = $ReqLog1->fetch_array()) {
                $current_login  =$resultat1["u_login"];
                $current_quotite=$resultat1["u_quotite"];

                if (!isset($calculProportionnel[$id_conges]) || $calculProportionnel[$id_conges] != true) {
                    $nb_conges=$nb_jours;
                } else {
                    // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2
                    $nb_conges = (round(($nb_jours*($current_quotite/100))*2))/2  ;
                }

                $valid=verif_saisie_decimal($nb_conges);
                if ($valid) {
                    // 1 : on update conges_solde_user
                    $req_update = 'UPDATE conges_solde_user SET su_solde = su_solde+ '.$nb_conges.'
                            WHERE  su_login = "'. $db->quote($current_login).'" AND su_abs_id = '.intval($id_conges).';';
                    $db->query($req_update);

                    // 2 : on insert l'ajout de conges dans la table periode
                    // recup du nom du groupe
                    $groupename= get_group_name_from_id($choix_groupe);
                    $commentaire =  _('resp_ajout_conges_comment_periode_groupe') ." $groupename";

                    // ajout conges
                    insert_ajout_dans_periode($current_login, $nb_conges, $id_conges, $commentaire);
                }
            }

            $group_name = get_group_name_from_id($choix_groupe);
            if (!isset($calculProportionnel[$id_conges]) || $calculProportionnel[$id_conges] != true) {
                $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
            } else {
                $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
            }
            log_action(0, "ajout", "groupe", $comment_log);
        }
    }
}


// recup de la liste de tous les groupes pour le mode RH
function get_list_groupes_pour_rh() : string
{
    $list_group="";

    $sql1="SELECT DISTINCT gu_gid FROM conges_groupe_users ORDER BY gu_gid"; // Le but est de sélectionner tous les groupes ayant des utilisateurs
    $ReqLog1 = \includes\SQL::singleton()->query($sql1);

    if ($ReqLog1->num_rows != 0) {
        while ($resultat1 = $ReqLog1->fetch_array()) {
            $current_group=$resultat1["gu_gid"];
            if ($list_group=="") {
                $list_group="$current_group";
            } else {
                $list_group=$list_group.", $current_group";
            }
        }
    }
    return $list_group;
}


//var pour resp_ajout_conges_all.php
$ajout_conges = getpost_variable('ajout_conges');
$ajout_global = getpost_variable('ajout_global');
$ajout_groupe = getpost_variable('ajout_groupe');
$choix_groupe = getpost_variable('choix_groupe');

// titre
$titre = _('resp_ajout_conges_titre');

if ('true' === $ajout_conges) {
    $tab_champ_saisie = getpost_variable('tab_champ_saisie');
    $tab_commentaire_saisie = getpost_variable('tab_commentaire_saisie');

    ajout_conges($tab_champ_saisie);
    redirect(ROOT_PATH . 'hr/page_principale?notice=credit-added');
}

if ('true' === $ajout_global) {
    $tab_new_nb_conges_all = getpost_variable('tab_new_nb_conges_all');
    $tab_calcul_proportionnel = getpost_variable('tab_calcul_proportionnel', array());
    $tab_new_comment_all = getpost_variable('tab_new_comment_all');

    ajout_global($tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all, $_SESSION['userlogin']);
    redirect(ROOT_PATH . 'hr/page_principale?notice=credit-added');
}

if ('true' === $ajout_groupe) {
    $tab_new_nb_conges_all = getpost_variable('tab_new_nb_conges_all');
    $tab_calcul_proportionnel = getpost_variable('tab_calcul_proportionnel', array());
    $tab_new_comment_all = getpost_variable('tab_new_comment_all');
    $choix_groupe = getpost_variable('choix_groupe');

    ajout_global_groupe($choix_groupe, $tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all);
    redirect(ROOT_PATH . 'hr/page_principale?notice=credit-added');
}

$config = new \App\Libraries\Configuration(\includes\SQL::singleton());
$PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);

// recup du tableau des types de conges (seulement les congesexceptionnels )
$tab_type_conges_exceptionnels = recup_tableau_types_conges_exceptionnels();

// recup de la liste de TOUS les users pour le RH
// (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
// renvoit une liste de login entre quotes et séparés par des virgules
$tab_all_users_du_hr = \hr\Fonctions::recup_infos_all_users_du_hr($_SESSION['userlogin']);
asort($tab_all_users_du_hr);
$tab_all_users_du_grand_resp = recup_infos_all_users_du_grand_resp($_SESSION['userlogin']);
$list_group = get_list_groupes_pour_rh($_SESSION['userlogin']);
if (!empty($list_group)) {
    $sql_group = "SELECT g_gid, g_groupename FROM conges_groupe WHERE g_gid IN ($list_group) ORDER BY g_groupename "  ;
    $resultatGroupe = \includes\SQL::singleton()->query($sql_group);

    $groupes = $resultatGroupe->fetch_all();
} else {
    $groupes = [];
}

require_once VIEW_PATH . 'HautResponsable/CreditConges.php';
