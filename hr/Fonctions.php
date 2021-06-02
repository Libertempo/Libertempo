<?php

namespace hr;

/**
 * Regroupement des fonctions liées au haut responsable
 */
class Fonctions
{
    private static function traite_all_demande_en_cours(array $tab_bt_radio, array $tab_text_refus) : string
    {

        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $return = '';

        while($elem_tableau = each($tab_bt_radio)) {
            $champs             = explode("--", $elem_tableau['value']);
            $user_login         = $champs[0];
            $user_nb_jours_pris = $champs[1];
            $type_abs           = $champs[2];   // id du type de conges demandé
            $date_deb           = $champs[3];
            $demi_jour_deb      = $champs[4];
            $date_fin           = $champs[5];
            $demi_jour_fin      = $champs[6];
            $reponse            = $champs[7];

            $numero             = $elem_tableau['key'];
            $numero_int         = (int) $numero;
            $return .= $numero . '---' . $user_login . '---' . $user_nb_jours_pris . '---' . $reponse . '<br>';

            /* Modification de la table conges_periode */
            if (strcmp($reponse, "OK")==0) {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat=\'ok\', p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );' ;
                /* On valide l'UPDATE dans la table "conges_periode" ! */
                $ReqLog1 = $db->query($sql1);
                if ($ReqLog1 && \includes\SQL::getVar('affected_rows') ) {
                    // Log de l'action
                    log_action($numero_int,"ok", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $reponse");

                    /* UPDATE table "conges_solde_user" (jours restants) */
                    soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris, $type_abs, $date_deb, $demi_jour_deb, $date_fin, $demi_jour_fin);

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailValidationUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "accept_conges");
                    }
                }
            } elseif (strcmp($reponse, "not_OK")==0) {
                // recup du motif de refus
                $motif_refus=addslashes($tab_text_refus[$numero_int]);
                $sql1 = 'UPDATE conges_periode SET p_etat=\'refus\', p_motif_refus=\''.$motif_refus.'\', p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';

                /* On valide l'UPDATE dans la table ! */
                $ReqLog1 = $db->query($sql1) ;
                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int,"refus", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : refus");


                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailRefusUtilisateur())
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "refus_conges");
                }
            }
        }

        $return .= _('form_modif_ok') . '<br><br>';
        /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
        $return .= '<META HTTP-EQUIV=REFRESH CONTENT="2; URL=' . $PHP_SELF . '">';
        return $return;
    }

    private static function affiche_all_demandes_en_cours($tab_type_conges) : string
    {
        $return = '';
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $count1=0;
        $count2=0;

        $typeAbsence = \App\ProtoControllers\Conge::getTypesAbsences($db);

        /*********************************/
        // Récupération des informations
        /*********************************/

        // Récup dans un tableau de tableau des informations de tous les users
        $tab_all_users=recup_infos_all_users();

        // si tableau des users du resp n'est pas vide
        if ( count($tab_all_users)!=0 ) {
            // constitution de la liste (séparé par des virgules) des logins ...
            $list_users="";
            foreach ($tab_all_users as $current_login => $tab_current_user) {
                if ($list_users=="") {
                    $list_users= "'$current_login'" ;
                } else {
                    $list_users=$list_users.", '$current_login'" ;
                }
            }
        }

        /*********************************/

        $return .= '<form action="' . $PHP_SELF . '" method="POST">';

        /*********************************/
        /* TABLEAU DES DEMANDES DES USERS*/
        /*********************************/

        // si tableau des users n'est pas vide :)
        if ( count($tab_all_users)!=0 ) {

            // Récup des demandes en cours pour les users :
            $sql1 = "SELECT p_num, p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement FROM conges_periode ";
            $sql1=$sql1." WHERE p_etat =\"demande\" OR p_etat =\"valid\"";
            $sql1=$sql1." AND p_login IN ($list_users) ";
            $sql1=$sql1." ORDER BY p_num";

            $ReqLog1 = $db->query($sql1) ;

            $count1 = $ReqLog1->num_rows;
            if ($count1!=0) {
                // AFFICHAGE TABLEAU DES DEMANDES EN COURS
                //$return .= '<h3>' . _('resp_traite_demandes_titre') . '</h3>';
                $return .= '<table cellpadding="2" class="table table-hover table-responsive table-condensed table-striped">';
                $return .= '<thead>';
                $return .= '<tr>';
                $return .= '<th>' . _('divers_nom_maj_1') . '<br>' . _('divers_prenom_maj_1') . '</th>';
                $return .= '<th>' . _('divers_quotite_maj_1') . '</th>';
                $return .= '<th>' . _('divers_type_maj_1') . '</th>';
                $return .= '<th>' . _('divers_debut_maj_1') .'</th>';
                $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
                $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
                $return .= '<th>' . _('resp_traite_demandes_nb_jours') . '</th>';
                $return .= '<th>' . _('divers_solde') . '</th>';
                $return .= '<th>'. _('divers_accepter_maj_1') .'</th>' ;
                $return .= '<th>'. _('divers_refuser_maj_1') .'</th>' ;
                $return .= '<th>' . _('resp_traite_demandes_attente') . '</th>';
                $return .= '<th>'. _('resp_traite_demandes_motif_refus') . '</th>';
                if ($config->canAfficheDateTraitement()) {
                    $return .= '<th>' . _('divers_date_traitement') . '</th>';
                }
                $return .= '</tr>';
                $return .= '</thead>' ;
                $return .= '<tbody>' ;
                $i = true;
                $tab_bt_radio=array();
                while ($resultat1 = $ReqLog1->fetch_array()) {
                    /** sur la ligne ,   **/
                    /** le 1er bouton radio est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--OK"> */
                    /**  et le 2ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--not_OK"> */
                    /**  et le 3ieme est <input type="radio" name="tab_bt_radio[valeur de p_num]" value="[valeur de p_login]--[valeur p_nb_jours]--$type--RIEN"> */

                    $sql_p_date_deb         = $resultat1["p_date_deb"];
                    $sql_p_date_fin         = $resultat1["p_date_fin"];
                    $sql_p_date_deb_fr      = eng_date_to_fr($resultat1["p_date_deb"]);
                    $sql_p_date_fin_fr      = eng_date_to_fr($resultat1["p_date_fin"]);
                    $sql_p_demi_jour_deb    = $resultat1["p_demi_jour_deb"] ;
                    $sql_p_demi_jour_fin    = $resultat1["p_demi_jour_fin"] ;
                    $sql_p_commentaire      = $resultat1["p_commentaire"];
                    $sql_p_num              = $resultat1["p_num"];
                    $sql_p_login            = $resultat1["p_login"];
                    $sql_p_nb_jours         = affiche_decimal($resultat1["p_nb_jours"]);
                    $sql_p_type             = $resultat1["p_type"];
                    $sql_p_date_demande     = $resultat1["p_date_demande"];
                    $sql_p_date_traitement  = $resultat1["p_date_traitement"];

                    if ($sql_p_demi_jour_deb=="am") {
                        $demi_j_deb="mat";
                    } else {
                        $demi_j_deb="aprm";
                    }

                    if ($sql_p_demi_jour_fin=="am") {
                        $demi_j_fin="mat";
                    } else {
                        $demi_j_fin="aprm";
                    }

                    // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                    $chaine_bouton_radio = "$sql_p_login--$sql_p_nb_jours--$sql_p_type--$sql_p_date_deb--$sql_p_demi_jour_deb--$sql_p_date_fin--$sql_p_demi_jour_fin";
                    $boutonradio1="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--OK\">";
                    $boutonradio2="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--not_OK\">";
                    $boutonradio3="<input type=\"radio\" name=\"tab_bt_radio[$sql_p_num]\" value=\"$chaine_bouton_radio--RIEN\" checked>";
                    $text_refus="<input class=\"form-control\" type=\"text\" name=\"tab_text_refus[$sql_p_num]\" size=\"20\" max=\"100\">";

                    $return .= '<tr class="' . ($i ? 'i' : 'p') . '">';
                    $return .= '<td><b>' . $tab_all_users[$sql_p_login]['nom'] . '</b><br>' . $tab_all_users[$sql_p_login]['prenom'] . '</td><td>' . $tab_all_users[$sql_p_login]['quotite'] . '%</td>';
                    $return .= '<td>' . $typeAbsence[$sql_p_type]['libelle'] . '</td>';
                    $return .= '<td>' . $sql_p_date_deb_fr . '<span class="demi">' . $demi_j_deb . '</span></td><td>' . $sql_p_date_fin_fr . '<span class="demi">' . $demi_j_fin . '</span></td><td>' . $sql_p_commentaire . '</td><td><b>' . $sql_p_nb_jours . '</b></td>';
                    $tab_conges=$tab_all_users[$sql_p_login]['conges'];
                    $return .= '<td>' . $tab_conges[$typeAbsence[$sql_p_type]['libelle']]['solde'] . '</td>';
                    $return .= '<td>' . $boutonradio1 . '</td><td>' . $boutonradio2 . '</td><td>' . $boutonradio3 . '</td><td>' . $text_refus . '</td>';
                    if ($config->canAfficheDateTraitement()) {
                        if ($sql_p_date_demande == NULL) {
                            $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_p_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_p_date_traitement . '</td>';
                        } else {
                            $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_p_date_demande . '<br>' . _('divers_traitement') . ' : pas traité</td>';
                        }
                    }
                    $return .= '</tr>' ;
                    $i = !$i;
                } // while
                $return .= '</tbody>' ;
                $return .= '</table>' ;
            } //if ($count1!=0)
        } //if ( count($tab_all_users)!=0 )

        $return .= '<br>';

        if (($count1==0) && ($count2==0)) {
            $return .= '<strong>' . _('aucune_demande') . '</strong>';
        } else {
            $return .= '<hr/>';
            $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
        }
        $return .= '</form>';
        return $return;
    }

    /**
     * Encapsule le comportement du module de traitement des demandes
     *
     * @param array  $tab_type_cong
     * @param string $onglet
     *
     * @access public
     * @static
     */
    public static function pageTraitementDemandeModule(array $tab_type_cong, $onglet) : string
    {
        $return = '';

        //var pour resp_traite_demande_all.php
        $tab_bt_radio   = getpost_variable('tab_bt_radio');
        $tab_text_refus = getpost_variable('tab_text_refus');

        // titre
        $return .= '<h1>'. _('resp_traite_demandes_titre') .'</h1>';

        // si le tableau des bouton radio des demandes est vide , on affiche les demandes en cours
        if ( $tab_bt_radio == '' ) {
            $return .= \hr\Fonctions::affiche_all_demandes_en_cours($tab_type_cong);
        } else {
            $return .= \hr\Fonctions::traite_all_demande_en_cours($tab_bt_radio, $tab_text_refus);
            echo $return;
            return '';
        }
        return $return;
    }

    public static function new_conges($user_login, $numero_int, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type_id) : string
    {
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        $new_debut = convert_date($new_debut);
        $new_fin = convert_date($new_fin);

        // verif validité des valeurs saisies
        $valid = verif_saisie_new_demande($new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $user_login);

        if ($valid) {
            $return .= $user_login . '---' . $new_debut . '_' . $new_demi_jour_deb . '---' . $new_fin . '_' . $new_demi_jour_fin . '---' . $new_nb_jours . '---' . $new_comment . '---' . $new_type_id . '<br>';

            // recup dans un tableau de tableau les infos des types de conges et absences
            $tab_tout_type_abs = recup_tableau_tout_types_abs();

            /**********************************/
            /* insert dans conges_periode     */
            /**********************************/
            $new_etat="ok";
            $result=insert_dans_periode($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type_id, $new_etat, 0);

            /************************************************/
            /* UPDATE table "conges_solde_user" (jours restants) */
            // on retranche les jours seulement pour des conges pris (pas pour les absences)
            // donc seulement si le type de l'absence qu'on annule est un "conges"
            if ($tab_tout_type_abs[$new_type_id]['type']=="conges" || $tab_tout_type_abs[$new_type_id]['type']=="conges_exceptionnels") {
                $user_nb_jours_pris_float=(float) $new_nb_jours ;
                soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris_float, $new_type_id, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin);
            }

            $comment_log = "saisie conges par le responsable pour $user_login ($new_nb_jours jour(s)) type_conges = $new_type_id ( de $new_debut $new_demi_jour_deb a $new_fin $new_demi_jour_fin) ($new_comment)";
            log_action(0, "", $user_login, $comment_log);

            if ($result) {
                $return .= _('form_modif_ok') . '<br><br>';
            } else {
                $return .= _('form_modif_not_ok') . '<br><br>';
            }
        } else {
            $return .= _('resp_traite_user_valeurs_not_ok') . '<br><br>';
        }

        /* APPEL D'UNE AUTRE PAGE */
        $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '" method="POST">';
        $return .= '<input type="submit" value="' . _('form_retour') . '">';
        $return .= '</form>';
        return $return;
    }

    private static function traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus) : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // recup dans un tableau de tableau les infos des types de conges et absences
        $tab_tout_type_abs = recup_tableau_tout_types_abs();

        while($elem_tableau = each($tab_radio_traite_demande)) {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris=$champs[1];
            $user_nb_jours_pris_float=(float) $user_nb_jours_pris ;
            $value_type_abs_id=$champs[2];
            $date_deb=$champs[3];
            $demi_jour_deb=$champs[4];
            $date_fin=$champs[5];
            $demi_jour_fin=$champs[6];
            $reponse=$champs[7];

            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;

            if ($reponse == "ACCEPTE") { // acceptation definitive d'un conges
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat=\'ok\', p_date_traitement=NOW() WHERE p_num='. $db->quote($numero_int).' AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';
                $ReqLog1 = $db->query($sql1);

                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int,"ok", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

                    /* UPDATE table "conges_solde_user" (jours restants) */
                    // on retranche les jours seulement pour des conges pris (pas pour les absences)
                    // donc seulement si le type de l'absence qu'on annule est un "conges"
                    if (($tab_tout_type_abs[$value_type_abs_id]['type']=="conges")||($tab_tout_type_abs[$value_type_abs_id]['type']=="conges_exceptionnels")) {
                        soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris_float, $value_type_abs_id, $date_deb, $demi_jour_deb, $date_fin, $demi_jour_fin);
                    }

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailValidationUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "accept_conges");
                    }
                }
            } elseif ($reponse == "VALID") // première validation dans le cas d'une double validation
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat=\'valid\', p_date_traitement=NOW() WHERE p_num='. $db->quote($numero_int).' AND p_etat=\'demande\';' ;
                $ReqLog1 = $db->query($sql1);

                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int,"valid", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailValidationUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "valid_conges");
                    }
                }
            } elseif ($reponse == "REFUSE") // refus d'un conges
            {
                // recup di motif de refus
                $motif_refus=addslashes($tab_text_refus[$numero_int]);
                $sql3 = 'UPDATE conges_periode SET p_etat=\'refus\', p_motif_refus="'. $db->quote($motif_refus).'", p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';

                $ReqLog3 = $db->query($sql3);

                if ($ReqLog3 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int,"refus", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailRefusUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "refus_conges");
                    }
                }
            }
        }
        $return .= _('form_modif_ok') . '<br><br>';
        /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
        $return .= '<META HTTP-EQUIV=REFRESH CONTENT="2; URL=' . $PHP_SELF . '?user_login=' . $user_login . '">';
        return $return;
    }

    private static function annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul) : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // recup dans un tableau de tableau les infos des types de conges et absences
        $tab_tout_type_abs = recup_tableau_tout_types_abs();

        while ($elem_tableau = each($tab_checkbox_annule)) {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris=$champs[1];
            $VerifDec=verif_saisie_decimal($user_nb_jours_pris) ;
            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;
            $user_type_abs_id=$champs[2];

            $motif_annul=addslashes($tab_text_annul[$numero_int]);

            /* UPDATE table "conges_periode" */
            $sql1 = 'UPDATE conges_periode SET p_etat="annul", p_motif_refus="'. $db->quote($motif_annul).'", p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND p_etat="ok";';
            $ReqLog1 = $db->query($sql1);

            if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                // Log de l'action
                log_action($numero_int,"annul", $user_login, "annulation conges $numero ($user_login) ($user_nb_jours_pris jours)");

                /* UPDATE table "conges_solde_user" (jours restants) */
                // on re-crédite les jours seulement pour des conges pris (pas pour les absences)
                // donc seulement si le type de l'absence qu'on annule est un "conges"
                if (in_array($tab_tout_type_abs[$user_type_abs_id]['type'],["conges","conges_exceptionnels"])) {
                    $sql2 = 'UPDATE conges_solde_user SET su_solde = su_solde+"'. $db->quote($user_nb_jours_pris).'" WHERE su_login="'. $db->quote($user_login).'" AND su_abs_id="'. $db->quote($user_type_abs_id).'";';
                    $db->query($sql2);
                }

                //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                if ($config->isSendMailAnnulationCongesUtilisateur()) {
                    alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "annul_conges");
                }
            }
        }

        $return .= _('form_modif_ok') . '<br><br>';
        /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
        $return .= '<META HTTP-EQUIV=REFRESH CONTENT="2; URL=' . $PHP_SELF . '?user_login=' . $user_login . '">';
        return $return;
    }

    //affiche l'état des conges du user (avec le formulaire pour le responsable)
    private static function affiche_etat_conges_user_for_resp($user_login, $year_affichage, $tri_date) : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // affichage de l'année et des boutons de défilement
        $year_affichage_prec = $year_affichage-1 ;
        $year_affichage_suiv = $year_affichage+1 ;

        $return .= '<b>';
        $return .= '<a href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&year_affichage=' . $year_affichage_prec . '"><<</a>';
        $return .= '&nbsp&nbsp&nbsp ' . $year_affichage . '&nbsp&nbsp&nbsp';
        $return .= '<a href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&year_affichage=' . $year_affichage_suiv . '">>></a>';
        $return .= '</b><br><br>';


        // Récupération des informations de speriodes de conges/absences
        $sql3 = "SELECT p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_etat, p_motif_refus, p_date_demande, p_date_traitement, p_num FROM conges_periode " .
                "WHERE p_login = '$user_login' " .
                "AND p_etat !='demande' " .
                "AND p_etat !='valid' " .
                "AND (p_date_deb LIKE '$year_affichage%' OR p_date_fin LIKE '$year_affichage%') ";
        if ($tri_date=="descendant")
            $sql3=$sql3." ORDER BY p_date_deb DESC ";
        else
            $sql3=$sql3." ORDER BY p_date_deb ASC ";

        $ReqLog3 = $db->query($sql3);

        $count3=$ReqLog3->num_rows;
        if ($count3==0) {
            $return .= '<b>' . _('resp_traite_user_aucun_conges') . '</b><br><br>';
        } else {
            // recup dans un tableau de tableau les infos des types de conges et absences
            $tab_types_abs = recup_tableau_tout_types_abs() ;

            // AFFICHAGE TABLEAU
            $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user" method="POST">';
            $return .= '<table cellpadding="2" class="table table-hover table-responsive table-condensed table-striped">';
            $return .= '<thead>';
            $return .= '<tr>';
            $return .= '<th>';
            $return .= '<a href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&tri_date=descendant"><img src="' . IMG_PATH . '1downarrow-16x16.png" width="16" height="16" border="0" title="trier"></a>';
            $return .= _('divers_debut_maj_1');
            $return .= '<a href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&tri_date=ascendant"><img src="' . IMG_PATH . '1uparrow-16x16.png" width="16" height="16" border="0" title="trier"></a>';
            $return .= '</th>';
            $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
            $return .= '<th>' . _('divers_nb_jours_pris_maj_1') . '</th>';
            $return .= '<th>' . _('divers_comment_maj_1') . '<br><i>' . _('resp_traite_user_motif_possible') . '</i></th>';
            $return .= '<th>' . _('divers_type_maj_1') . '</th>';
            $return .= ' <th>'. _('divers_etat_maj_1') .'</th>';
            $return .= ' <th>'. _('resp_traite_user_annul') .'</th>';
            $return .= ' <th>'. _('resp_traite_user_motif_annul') .'</th>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<th>'. _('divers_date_traitement') .'</th>' ;
            }
            $return .= '</tr>';
            $return .= '</thead>';
            $return .= '<tbody>';

            $i = true;
            $tab_checkbox=array();
            while ($resultat3 = $ReqLog3->fetch_array() ) {
                $sql_date_deb           = $resultat3["p_date_deb"];
                $sql_date_fin           = $resultat3["p_date_fin"];
                $sql_demi_jour_deb      = $resultat3["p_demi_jour_deb"] ;
                $sql_demi_jour_fin      = $resultat3["p_demi_jour_fin"] ;

                $sql_login              = $resultat3["p_login"] ;
                $sql_nb_jours           = affiche_decimal($resultat3["p_nb_jours"]) ;
                $sql_commentaire        = $resultat3["p_commentaire"] ;
                $sql_type               = $resultat3["p_type"] ;
                $sql_etat               = $resultat3["p_etat"] ;
                $sql_motif_refus        = $resultat3["p_motif_refus"] ;
                $sql_p_date_demande     = $resultat3["p_date_demande"];
                $sql_p_date_traitement  = $resultat3["p_date_traitement"];
                $sql_num                = $resultat3["p_num"] ;

                
                $demi_j_deb = ($sql_demi_jour_deb == "am") ? 'matin' : 'après-midi';
                $demi_j_fin = ($sql_demi_jour_fin == "am") ? 'matin' : 'après-midi';

                if (($sql_etat=="annul") || ($sql_etat=="refus") || ($sql_etat=="ajout")) {
                    $casecocher1="";
                    if ($sql_etat=="refus") {
                        if ($sql_motif_refus=="")
                            $sql_motif_refus =  _('divers_inconnu')  ;
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    } elseif ($sql_etat=="annul") {
                        if ($sql_motif_refus=="")
                            $sql_motif_refus =  _('divers_inconnu')  ;
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    } elseif ($sql_etat=="ajout") {
                        $text_annul="&nbsp;";
                    }
                } else {
                    $casecocher1=sprintf("<input type=\"checkbox\" name=\"tab_checkbox_annule[$sql_num]\" value=\"$sql_login--$sql_nb_jours--$sql_type--ANNULE\">");
                    $text_annul="<input type=\"text\" name=\"tab_text_annul[$sql_num]\" size=\"20\" max=\"100\">";
                }

                $return .= '<tr class="' . ($i ? 'i' : 'p') . '">';
                $return .= '<td>' . \App\Helpers\Formatter::dateIso2Fr($sql_date_deb) . ' <span class="demi">' . schars($demi_j_deb) . '</span></td>';
                $return .= '<td>' . \App\Helpers\Formatter::dateIso2Fr($sql_date_fin) . ' <span class="demi">' . schars($demi_j_fin) . '</span></td>';
                $return .= '<td>' . $sql_nb_jours . '</td>';
                $return .= '<td>' . $sql_commentaire . '</td>';
                $return .= '<td>' . $tab_types_abs[$sql_type]['libelle'] . '</td>';
                $return .= '<td>';
                if ($sql_etat=="refus") {
                    $return .= _('divers_refuse') ;
                } elseif ($sql_etat=="annul") {
                    $return .= _('divers_annule') ;
                } else {
                    $return .= $sql_etat;
                }
                $return .= '</td>';
                $return .= '<td>' . $casecocher1 . '</td>';
                $return .= '<td>' . $text_annul . '</td>';

                if ($config->canAfficheDateTraitement()) {
                    if (empty($sql_p_date_demande)) {
                        $return .= '<td class="histo-left">' . _('divers_traitement') . ' : ' . $sql_p_date_traitement . '</td>';
                    } else {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_p_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_p_date_traitement . '</td>';
                    }
                }
                $return .= '</tr>';
                $i = !$i;
            }
            $return .= '</tbody>';
            $return .= '</table>';

            $return .= '<input type="hidden" name="user_login" value="' . $user_login . '">';
            $return .= '<br><input type="submit" value="' . _('form_submit') . '">';
            $return .= '</form>';
        }
        return $return;
    }

    //affiche l'état des demande en attente de 2ieme validation du user (avec le formulaire pour le responsable)
    private static function affiche_etat_demande_2_valid_user_for_resp($user_login) : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // Récupération des informations
        $sql2 = "SELECT p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement, p_num " .
                "FROM conges_periode " .
                "WHERE p_login = '$user_login' AND p_etat ='valid' ORDER BY p_date_deb";
        $ReqLog2 = $db->query($sql2);

        $count2=$ReqLog2->num_rows;
        if ($count2==0) {
            $return .= '<b>' . _('resp_traite_user_aucune_demande') . '</b><br><br>';
        } else {
            // recup dans un tableau des types de conges
            $tab_type_all_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user" method="POST">';
            $return .= '<table cellpadding="2" class="tablo">';
            $return .= '<thead>';
            $return .= '<tr>';
            $return .= '<th>'. _('divers_debut_maj_1') .'</th>';
            $return .= '<th>'. _('divers_fin_maj_1') .'</th>';
            $return .= '<th>'. _('divers_nb_jours_pris_maj_1') .'</th>';
            $return .= '<th>'. _('divers_comment_maj_1') .'</th>';
            $return .= '<th>'. _('divers_type_maj_1') .'</th>';
            $return .= '<th>'. _('divers_accepter_maj_1') .'</th>';
            $return .= '<th>'. _('divers_refuser_maj_1') .'</th>';
            $return .= '<th>'. _('resp_traite_user_motif_refus') .'</th>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<th>'. _('divers_date_traitement') .'</th>' ;
            }
            $return .= '</tr>';
            $return .= '</thead>';
            $return .= '<tbody>';

            $i = true;
            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array() ) {
                $sql_date_deb = $resultat2["p_date_deb"];
                $sql_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]) ;
                $sql_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                if ($sql_demi_jour_deb=="am") {
                    $demi_j_deb =  _('divers_am_short') ;
                } else {
                    $demi_j_deb =  _('divers_pm_short') ;
                }
                $sql_date_fin = $resultat2["p_date_fin"];
                $sql_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]) ;
                $sql_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                if ($sql_demi_jour_fin=="am") {
                    $demi_j_fin =  _('divers_am_short') ;
                } else {
                    $demi_j_fin =  _('divers_pm_short') ;
                }
                $sql_nb_jours=affiche_decimal($resultat2["p_nb_jours"]) ;
                $sql_commentaire=$resultat2["p_commentaire"] ;
                $sql_type=$resultat2["p_type"] ;
                $sql_date_demande = $resultat2["p_date_demande"];
                $sql_date_traitement = $resultat2["p_date_traitement"];
                $sql_num=$resultat2["p_num"] ;

                // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                $chaine_bouton_radio = "$user_login--$sql_nb_jours--$sql_type--$sql_date_deb--$sql_demi_jour_deb--$sql_date_fin--$sql_demi_jour_fin";


                $casecocher1 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                $casecocher2 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--REFUSE\">";
                $text_refus  = "<input type=\"text\" name=\"tab_text_refus[$sql_num]\" size=\"20\" max=\"100\">";

                $return .= '<tr class="' . ($i ? 'i' : 'p') . '">';
                $return .= '<td>' . $sql_date_deb_fr . '_' . $demi_j_deb . '</td>';
                $return .= '<td>' . $sql_date_fin_fr . '_' . $demi_j_fin . '</td>';
                $return .= '<td>' . $sql_nb_jours . '</td>';
                $return .= '<td>' . $sql_commentaire . '</td>';
                $return .= '<td>' . $tab_type_all_abs[$sql_type]['libelle'] . '</td>';
                $return .= '<td>' . $casecocher1 . '</td>';
                $return .= '<td>' . $casecocher2 . '</td>';
                $return .= '<td>' . $text_refus . '</td>';
                if ($config->canAfficheDateTraitement()) {
                    $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_date_traitement . '</td>';
                }

                $return .= '</tr>';
                $i = !$i;
            }
            $return .= '</tbody>';
            $return .= '</table>';

            $return .= '<input type="hidden" name="user_login" value="' . $user_login . '">';
            $return .= '<br><input class="btn btn-success" type="submit" value="' . _('form_submit') . '">  &nbsp;&nbsp;&nbsp;&nbsp; <input type="reset" value="' . _('form_cancel') . '">';
            $return .= '<a class="btn" href="' . $PHP_SELF . '">' . _('form_cancel') . '</a>';
            $return .= '</form>';
        }
        return $return;
    }

    //affiche l'état des demande du user (avec le formulaire pour le responsable)
    private static function affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp) : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // Récupération des informations
        $sql2 = "SELECT p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_date_demande, p_date_traitement, p_num " .
                "FROM conges_periode " .
                "WHERE p_login = '$user_login' AND p_etat ='demande' ".
                "ORDER BY p_date_deb";
        $ReqLog2 = $db->query($sql2);

        $count2=$ReqLog2->num_rows;
        if ($count2==0) {
            $return .= '<b>' . _('resp_traite_user_aucune_demande') . '</b><br><br>';
        } else {
            // recup dans un tableau des types de conges
            $tab_type_all_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user" method="POST">';
            $return .= '<table cellpadding="2" class="tablo">';
            $return .= '<thead>';
            $return .= '<tr>';
            $return .= '<th>'. _('divers_debut_maj_1') .'</th>';
            $return .= '<th>'. _('divers_fin_maj_1') .'</th>';
            $return .= '<th>'. _('divers_nb_jours_pris_maj_1') .'</th>';
            $return .= '<th>'. _('divers_comment_maj_1') .'</th>';
            $return .= '<th>'. _('divers_type_maj_1') .'</th>';
            $return .= '<th>'. _('divers_accepter_maj_1') .'</th>';
            $return .= '<th>'. _('divers_refuser_maj_1') .'</th>';
            $return .= '<th>'. _('resp_traite_user_motif_refus') .'</th>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<th>'. _('divers_date_traitement') .'</th>' ;
            }
            $return .= '</tr>';
            $return .= '</thead>';
            $return .= '<tbody>';

            $i = true;
            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array()) {
                $sql_date_deb       = $resultat2["p_date_deb"];
                $sql_date_fin       = $resultat2["p_date_fin"];
                $sql_date_deb_fr    = eng_date_to_fr($resultat2["p_date_deb"]) ;
                $sql_date_fin_fr    = eng_date_to_fr($resultat2["p_date_fin"]) ;
                $sql_demi_jour_deb  = $resultat2["p_demi_jour_deb"] ;
                $sql_demi_jour_fin  = $resultat2["p_demi_jour_fin"] ;

                $sql_nb_jours       = affiche_decimal($resultat2["p_nb_jours"]) ;
                $sql_commentaire    = $resultat2["p_commentaire"] ;
                $sql_type           = $resultat2["p_type"] ;
                $sql_date_demande   = $resultat2["p_date_demande"];
                $sql_date_traitement= $resultat2["p_date_traitement"];
                $sql_num            = $resultat2["p_num"] ;


                if ($sql_demi_jour_deb=="am") {
                    $demi_j_deb =  _('divers_am_short') ;
                } else {
                    $demi_j_deb =  _('divers_pm_short') ;
                }
                if ($sql_demi_jour_fin=="am") {
                    $demi_j_fin =  _('divers_am_short') ;
                } else {
                    $demi_j_fin =  _('divers_pm_short') ;
                }

                // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                $chaine_bouton_radio = "$user_login--$sql_nb_jours--$sql_type--$sql_date_deb--$sql_demi_jour_deb--$sql_date_fin--$sql_demi_jour_fin";

                // si le user fait l'objet d'une double validation on a pas le meme resultat sur le bouton !
                if ($tab_user['double_valid'] == "Y") {
                    /*******************************/
                    /* verif si le resp est grand_responsable pour ce user*/
                    if (in_array($_SESSION['userlogin'], $tab_grd_resp)) { // si resp_login est dans le tableau
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--VALID\">";
                    } else {
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                    }
                } else {
                    $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                }

                $boutonradio2 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--REFUSE\">";

                $text_refus  = "<input type=\"text\" name=\"tab_text_refus[$sql_num]\" size=\"20\" max=\"100\">";

                $return .= '<tr class="' . ($i ? 'i' : 'p') . '">';
                $return .= '<td>' . $sql_date_deb_fr . '_' . $demi_j_deb . '</td>';
                $return .= '<td>' . $sql_date_fin_fr . '_'  . $demi_j_fin . '</td>';
                $return .= '<td>' . $sql_nb_jours . '</td>';
                $return .= '<td>' . $sql_commentaire . '</td>';
                $return .= '<td>' . $tab_type_all_abs[$sql_type]['libelle'] . '</td>';
                $return .= '<td>' . $boutonradio1 . '</td>';
                $return .= '<td>' . $boutonradio2 . '</td>';
                $return .= '<td>' . $text_refus . '</td>';
                if ($config->canAfficheDateTraitement()) {
                    if ($sql_date_traitement==NULL) {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : pas traité</td>';
                    } else {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_date_traitement . '</td>';
                    }
                }

                $return .= '</tr>';
                $i = !$i;
            }
            $return .= '</tbody>';
            $return .= '</table>';

            $return .= '<input type="hidden" name="user_login" value="' . $user_login . '">';
            $return .= '<br><input class="btn btn-success" type="submit" value="' . _('form_submit') . '">  &nbsp;&nbsp;&nbsp;&nbsp;  <input type="reset" value="' . _('form_cancel') . '">';
            $return .= '<a class="btn" href="' . $PHP_SELF . '">' . _('form_cancel') . '</a>';
            $return .= '</form>';
        }
        return $return;
    }

    private static function affichage($user_login, $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date, $onglet) : string
    {
        $config = new \App\Libraries\Configuration(\includes\SQL::singleton());

        $return = '';

        // on initialise le tableau global des jours fériés s'il ne l'est pas déjà :
        if (!isset($_SESSION["tab_j_feries"])) {
            init_tab_jours_feries();
        }

        /********************/
        /* Récupération des informations sur le user : */
        /********************/
        $list_group_dbl_valid_du_resp = get_list_groupes_double_valid_du_resp($_SESSION['userlogin']);
        $tab_user=array();
        $tab_user = recup_infos_du_user($user_login, $list_group_dbl_valid_du_resp);
        $list_all_users_du_hr=\hr\Fonctions::get_list_all_users_du_hr($_SESSION['userlogin']);
        // recup des grd resp du user
        $tab_grd_resp=array();
        get_tab_grd_resp_du_user($user_login, $tab_grd_resp);

        /********************/
        /* Titre */
        /********************/
        $return .= '<h3>'. _('resp_traite_user_titre') . ' ' . $tab_user['prenom'] . ' ' . $tab_user['nom'] . '.</h3>';


        /********************/
        /* Bilan des Conges */
        /********************/
        // AFFICHAGE TABLEAU
        // affichage du tableau récapitulatif des solde de congés d'un user
        $return .= affiche_tableau_bilan_conges_user($user_login);
        $return .= '<br><br>';

        /*************************/
        /* SAISIE NOUVEAU CONGES */
        /*************************/
        /* Génération du datePicker et de ses options */
        $daysOfWeekDisabled = [];
        $datesDisabled      = [];
        if ((!$config->isDimancheOuvrable())
            && (!$config->isSamediOuvrable())
        ) {
            $daysOfWeekDisabled = [0,6];
        } else {
            if (!$config->isDimancheOuvrable()) {
                $daysOfWeekDisabled = [0];
            }
            if (!$config->isSamediOuvrable()) {
                $daysOfWeekDisabled = [6];
            }
        }

        if (is_array($_SESSION["tab_j_feries"])) {
            foreach ($_SESSION["tab_j_feries"] as $date) {
                $datesDisabled[] = \App\Helpers\Formatter::dateIso2Fr($date);
            }
        }

        if (isset($_SESSION["tab_j_fermeture"]) && is_array($_SESSION["tab_j_fermeture"])) {
            foreach ($_SESSION["tab_j_fermeture"] as $date) {
                $datesDisabled[] = \App\Helpers\Formatter::dateIso2Fr($date);
            }
        }
        $startDate =  '';

        $datePickerOpts = [
            'daysOfWeekDisabled' => $daysOfWeekDisabled,
            'datesDisabled'      => $datesDisabled,
            'startDate'          => $startDate,
        ];
        $return .= '<script>generateDatePicker(' . json_encode($datePickerOpts) . ');</script>';

        // si les mois et année ne sont pas renseignés, on prend ceux du jour
        if ($year_calendrier_saisie_debut==0) {
            $year_calendrier_saisie_debut=date("Y");
        }
        if ($mois_calendrier_saisie_debut==0) {
            $mois_calendrier_saisie_debut=date("m");
        }
        if ($year_calendrier_saisie_fin==0) {
            $year_calendrier_saisie_fin=date("Y");
        }
        if ($mois_calendrier_saisie_fin==0) {
            $mois_calendrier_saisie_fin=date("m");
        }
        $return .= '<h1>' . _('resp_traite_user_new_conges') . '</h1>';

        $return .= saisie_nouveau_conges2($user_login, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $onglet);

        $return .= '<hr align="center" size="2" width="90%">';

        /*********************/
        /* Etat des Demandes */
        /*********************/
        if ($config->canUserSaisieDemande()) {
            //verif si le user est bien un user du resp (et pas seulement du grad resp)
            if (strstr($list_all_users_du_hr, "'$user_login'")!=FALSE) {
                $return .= '<h3>' . _('resp_traite_user_etat_demandes') . '</h3>';

                //affiche l'état des demande du user (avec le formulaire pour le responsable)
                $return .= \hr\Fonctions::affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp);

                $return .= '<hr align="center" size="2" width="90%">';
            }
        }

        /*********************/
        /* Etat des Demandes en attente de 2ieme validation */
        /*********************/
        /*******************************/
        /* verif si le resp est grand_responsable pour ce user*/

        if (in_array($_SESSION['userlogin'], $tab_grd_resp)) // si resp_login est dans le tableau
        {
            $return .= '<h3>' . _('resp_traite_user_etat_demandes_2_valid') . '</h3>';

            //affiche l'état des demande en attente de 2ieme valid du user (avec le formulaire pour le responsable)
            $return .= self::affiche_etat_demande_2_valid_user_for_resp($user_login);

            $return .= '<hr align="center" size="2" width="90%">';
        }

        /*******************/
        /* Etat des Conges */
        /*******************/
        $return .= '<h3>' . _('resp_traite_user_etat_conges') . '</h3>';

        //affiche l'état des conges du user (avec le formulaire pour le responsable)
        $return .= \hr\Fonctions::affiche_etat_conges_user_for_resp($user_login,  $year_affichage, $tri_date);

        //echo "<hr align=\"center\" size=\"2\" width=\"90%\"> \n";

        $return .= '<td valign="middle">';
        $return .= '</td></tr></table>';
        $return .= '<center>';
        return $return;
    }

    /**
     * Encapsule le comportement du module de traitement des utilisateurs
     *
     * @param string $onglet
     *
     * @access public
     * @static
     */
    public static function pageTraiteUserModule($onglet) : string
    {
        //var pour hr_traite_user.php
        $user_login                 = htmlentities(getpost_variable('user_login'), ENT_QUOTES | ENT_HTML401);
        $tab_checkbox_annule        = getpost_variable('tab_checkbox_annule') ;
        $tab_radio_traite_demande   = getpost_variable('tab_radio_traite_demande') ;
        $new_demande_conges         = getpost_variable('new_demande_conges', 0) ;
        $return = '';

        // si une annulation de conges a été selectionée :
        if ( $tab_checkbox_annule != '' ) {
            $tab_text_annul         = getpost_variable('tab_text_annul') ;
            $return .= \hr\Fonctions::annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul);
        }
        // si le traitement des demandes a été selectionée :
        elseif ( $tab_radio_traite_demande != '' ) {
            $tab_text_refus         = getpost_variable('tab_text_refus') ;
            $return .= \hr\Fonctions::traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus);
        }
        // si un nouveau conges ou absence a été saisi pour un user :
        elseif ( $new_demande_conges == 1 ) {
            $new_debut = htmlentities(getpost_variable('new_debut'), ENT_QUOTES | ENT_HTML401);
            $new_demi_jour_deb  = htmlentities(getpost_variable('new_demi_jour_deb'), ENT_QUOTES | ENT_HTML401);
            $new_fin = htmlentities(getpost_variable('new_fin'), ENT_QUOTES | ENT_HTML401);
            $new_demi_jour_fin = htmlentities(getpost_variable('new_demi_jour_fin'), ENT_QUOTES | ENT_HTML401);
            $new_comment = htmlentities(getpost_variable('new_comment'), ENT_QUOTES | ENT_HTML401);
            $new_type = htmlentities(getpost_variable('new_type'), ENT_QUOTES | ENT_HTML401);

            $new_nb_jours = compter($user_login, '', $new_debut,  $new_fin, $new_demi_jour_deb, $new_demi_jour_fin, $new_comment);

            $return .= \hr\Fonctions::new_conges($user_login, "", $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type);
        } else {
            $year_calendrier_saisie_debut   = getpost_variable('year_calendrier_saisie_debut', 0) ;
            $mois_calendrier_saisie_debut   = getpost_variable('mois_calendrier_saisie_debut', 0) ;
            $year_calendrier_saisie_fin     = getpost_variable('year_calendrier_saisie_fin', 0) ;
            $mois_calendrier_saisie_fin     = getpost_variable('mois_calendrier_saisie_fin', 0) ;
            $tri_date                       = getpost_variable('tri_date', "ascendant") ;
            $year_affichage                 = (int) getpost_variable('year_affichage' , date("Y") );

            $return .= \hr\Fonctions::affichage($user_login,  $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date, $onglet);
        }
        return $return;
    }

    public static function affichage_saisie_globale_groupe($tab_type_conges) : string
    {
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        /***********************************************************************/
        /* SAISIE GROUPE pour tous les utilisateurs */

        // on établi la liste complète des groupes pour le mode RH
        $list_group = \hr\Fonctions::get_list_groupes_pour_rh($_SESSION['userlogin']);

        if ($list_group!="") //si la liste n'est pas vide ( serait le cas si n'est responsable d'aucun groupe)
        {
            $return .= '<h2>' . _('resp_ajout_conges_ajout_groupe') . '</h2>';
            $return .= '<form action="' . $PHP_SELF . '?onglet=ajout_conges" method="POST">';
            $return .= '<fieldset class="cal_saisie">';
            $return .= '<div class="table-responsive"><table class="table table-hover table-condensed table-striped">';
            $return .= '<tr>';
            $return .= '<td class="big">' . _('resp_ajout_conges_choix_groupe') . ' : </td>';
            // création du select pour le choix du groupe
            $text_choix_group="<select name=\"choix_groupe\" >";
            $sql_group = "SELECT g_gid, g_groupename FROM conges_groupe WHERE g_gid IN ($list_group) ORDER BY g_groupename "  ;
            $ReqLog_group = \includes\SQL::singleton()->query($sql_group) ;

            while ($resultat_group = $ReqLog_group->fetch_array()) {
                $current_group_id=$resultat_group["g_gid"];
                $current_group_name=$resultat_group["g_groupename"];
                $text_choix_group=$text_choix_group."<option value=\"$current_group_id\" >$current_group_name</option>";
            }
            $text_choix_group=$text_choix_group."</select>" ;

            $return .= '<td colspan="3">' . $text_choix_group . '</td>';
            $return .= '</tr>';
            $return .= '<tr>';
            $return .= '<th colspan="2">' . _('resp_ajout_conges_nb_jours_all_1') . ' ' . _('resp_ajout_conges_nb_jours_all_2') . '</th>';
            $return .= '<th>' ._('resp_ajout_conges_calcul_prop') . '</th>';
            $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
            $return .= '</tr>';
            foreach($tab_type_conges as $id_conges => $libelle) {
                $return .= '<tr>';
                $return .= '<td><strong>' . $libelle . '<strong></td>';
                $return .= '<td><input class="form-control" type="text" name="tab_new_nb_conges_all[' . $id_conges . ']" size="6" maxlength="6" value="0"></td>';
                $return .= '<td>' . _('resp_ajout_conges_oui') . '<input type="checkbox" name="tab_calcul_proportionnel[' . $id_conges . ']" value="TRUE" checked></td>';
                $return .= '<td><input class="form-control" type="text" name="tab_new_comment_all[' . $id_conges . ']" size="30" maxlength="200" value=""></td>';
                $return .= '</tr>';
            }
            $return .= '</table></div>';
            $return .= '<p>' . _('resp_ajout_conges_calcul_prop_arondi') . '! </p>';
            $return .= '<input class="btn" type="submit" value="' . _('form_valid_groupe') . '">';
            $return .= '</fieldset>';
            $return .= '<input type="hidden" name="ajout_groupe" value="TRUE">';
            $return .= '</form>';
        }
        return $return;
    }

    // renvoit un tableau de tableau contenant les informations de tous les users dont $login est HR responsable
    public static function recup_infos_all_users_du_hr($login) : array
    {
        $tab=array();
        $list_groupes_double_validation=get_list_groupes_double_valid();

        $sql1 = "SELECT u_login FROM conges_users ORDER BY u_nom";
        $ReqLog = \includes\SQL::singleton()->query($sql1) ;

        while ($resultat = $ReqLog->fetch_array()) {
            $tab_user=array();
            $sql_login=$resultat["u_login"];
            $tab[$sql_login] = recup_infos_du_user($sql_login, $list_groupes_double_validation);
        }
        return $tab ;
    }

    // recup de la liste de TOUS les users pour le responsable RH
    // renvoit une liste de login entre quotes et séparés par des virgules
    private static function get_list_all_users_du_hr($resp_login) : string
    {
        $list_users="";

        $sql1="SELECT DISTINCT(u_login) FROM conges_users WHERE u_is_active='Y' ORDER BY u_nom  ";
        $ReqLog1 = \includes\SQL::singleton()->query($sql1);

        while ($resultat1 = $ReqLog1->fetch_array())
        {
            $current_login=$resultat1["u_login"];
                if ($list_users=="") {
                    $list_users="'$current_login'";
                } else {
                    $list_users=$list_users.", '$current_login'";
                }
        }
        return $list_users;
    }

    // calcul de la date limite d'utilisation des reliquats (si on utilise une date limite et qu'elle n'est pas encore calculée) et stockage dans la table
    private static function set_nouvelle_date_limite_reliquat()
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        //si on autorise les reliquats
        if ($config->isReliquatsAutorise()) {
            // s'il y a une date limite d'utilisation des reliquats (au format jj-mm)
            if ($config->getDateLimiteReliquats() != 0) {
                // nouvelle date limite au format aaa-mm-jj
                $t=explode("-", $config->getDateLimiteReliquats());
                $new_date_limite = date("Y")."-".$t[1]."-".$t[0];

                //si la date limite n'a pas encore été updatée
                if ($_SESSION['config']['date_limite_reliquats'] < $new_date_limite) {
                    /* Modification de la table conges_appli */
                    $sql_update= 'UPDATE conges_appli SET appli_valeur = \''.$new_date_limite.'\' WHERE appli_variable=\'date_limite_reliquats\';';
                    $ReqLog_update = $db->query($sql_update) ;

                }
            }
        }
    }

    // verifie si tous les users on été basculés de l'exercice précédent vers le suivant.
    // si oui : on incrémente le num_exercice de l'application
    private static function update_appli_num_exercice()
    {
        $db = \includes\SQL::singleton();
        // verif
        $appli_num_exercice = $_SESSION['config']['num_exercice'] ;
        $sql_verif = "SELECT u_login FROM conges_users WHERE u_num_exercice != $appli_num_exercice "  ;
        $ReqLog_verif = $db->query($sql_verif) ;

        if ($ReqLog_verif->num_rows == 0) {
            /* Modification de la table conges_appli */
            $sql_update= "UPDATE conges_appli SET appli_valeur = appli_valeur+1 WHERE appli_variable='num_exercice' ";
            $ReqLog_update = $db->query($sql_update) ;

            // ecriture dans les logs
            $new_appli_num_exercice = $appli_num_exercice+1 ;
            log_action(0, "", "", "fin/debut exercice (appli_num_exercice : $appli_num_exercice -> $new_appli_num_exercice)");
        }
    }

    private static function affiche_calendrier_fermeture_mois($year, $mois, $tab_year) : string
    {
        $config = new \App\Libraries\Configuration(\includes\SQL::singleton());
        $jour_today=date("j");
        $jour_today_name=date("D");
        $return = '';

        $first_jour_mois_timestamp=mktime (0,0,0,$mois,1,$year);
        $mois_name=date_fr("F", $first_jour_mois_timestamp);
        $first_jour_mois_rang=date("w", $first_jour_mois_timestamp);      // jour de la semaine en chiffre (0=dim , 6=sam)
        if ($first_jour_mois_rang==0) {
            $first_jour_mois_rang=7 ;    // jour de la semaine en chiffre (1=lun , 7=dim)
        }

        $return .= '<table>';
        /* affichage  2 premieres lignes */
        $return .= '<thead>';
        $return .= '<tr><th colspan=7 class="titre">' . $mois_name . ' ' . $year . '</th></tr>';
        $return .= '<tr>';
        $return .= '<th class="cal-saisie2">' . _('lundi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('mardi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('mercredi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('jeudi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('vendredi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('samedi_1c') . '</th>';
        $return .= '<th class="cal-saisie2">' . _('dimanche_1c') . '</th>';
        $return .= '</tr>' ;
        $return .= '</thead>';

        /* affichage ligne 1 du mois*/
        $return .= '<tr>';
        // affichage des cellules vides jusqu'au 1 du mois ...
        for($i=1; $i<$first_jour_mois_rang; $i++) {
            if ( (($i==6)&&(!$config->isSamediOuvrable())) || (($i==7)&&(!$config->isDimancheOuvrable())) ) {
                $bgcolor=$_SESSION['config']['week_end_bgcolor'];
            } else {
                $bgcolor=$_SESSION['config']['semaine_bgcolor'];
            }
            $return .= '<td class="month-out cal-saisie2">&nbsp;</td>';
        }
        // affichage des cellules du 1 du mois à la fin de la ligne ...
        for($i=$first_jour_mois_rang; $i<8; $i++) {
            $j=$i-$first_jour_mois_rang+1 ;
            $j_timestamp=mktime (0,0,0,$mois,$j,$year);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td  class="cal-saisie ' . $td_second_class . '">' . $j_day . '</td>';
        }
        $return .= '</tr>';

        /* affichage ligne 2 du mois*/
        $return .= '<tr>';
        for($i=8-$first_jour_mois_rang+1; $i<15-$first_jour_mois_rang+1; $i++) {
            $j_timestamp=mktime (0,0,0,$mois,$i,$year);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td class="cal-saisie ' . $td_second_class . '">' . $j_day . '</td>';
        }
        $return .= '</tr>';

        /* affichage ligne 3 du mois*/
        $return .= '<tr>';
        for($i=15-$first_jour_mois_rang+1; $i<22-$first_jour_mois_rang+1; $i++) {
            $j_timestamp=mktime (0,0,0,$mois,$i,$year);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td class="cal-saisie ' . $td_second_class . '">' . $j_day .  '</td>';
        }
        $return .= '</tr>';

        /* affichage ligne 4 du mois*/
        $return .= '<tr>';
        for($i=22-$first_jour_mois_rang+1; $i<29-$first_jour_mois_rang+1; $i++) {
            $j_timestamp=mktime (0,0,0,$mois,$i,$year);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td class="cal-saisie ' . $td_second_class . '">' . $j_day . '</td>';
        }
        $return .= '</tr>';

        /* affichage ligne 5 du mois (peut etre la derniere ligne) */
        $return .= '<tr>';
        for($i=29-$first_jour_mois_rang+1; $i<36-$first_jour_mois_rang+1 && checkdate($mois, $i, $year); $i++) {
            $j_timestamp=mktime (0,0,0,$mois,$i,$year);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td  class="cal-saisie ' . $td_second_class . '">' . $j_day . '</td>';
        }
        for($i; $i<36-$first_jour_mois_rang+1; $i++) {
            if ((($i==35-$first_jour_mois_rang)&&(!$config->isSamediOuvrable())) || (($i==36-$first_jour_mois_rang)&&(!$config->isDimancheOuvrable()))) {
                $bgcolor=$_SESSION['config']['week_end_bgcolor'];
            } else {
                $bgcolor=$_SESSION['config']['semaine_bgcolor'];
            }
            $return .= '<td class="cal-saisie2 month-out">&nbsp;</td>';
        }
        $return .= '</tr>';

        /* affichage ligne 6 du mois (derniere ligne)*/
        $return .= '<tr>';
        for($i=36-$first_jour_mois_rang+1; checkdate($mois, $i, $year); $i++) {
            $j_timestamp=mktime (0,0,0,$mois,$i,$year);
            $j_date=date("Y-m-d", $j_timestamp);
            $j_day=date("d", $j_timestamp);
            $td_second_class=get_td_class_of_the_day_in_the_week($j_timestamp);

            if (in_array ("$j_date", $tab_year)) {
                $td_second_class="fermeture";
            }

            $return .= '<td  class="cal-saisie ' . $td_second_class . '">' . $j_day . '</td>';
        }
        for($i; $i<43-$first_jour_mois_rang+1; $i++) {
            if ( (($i==42-$first_jour_mois_rang)&&(!$config->isSamediOuvrable())) || (($i==43-$first_jour_mois_rang)&&(!$config->isDimancheOuvrable()))) {
                $bgcolor=$_SESSION['config']['week_end_bgcolor'];
            } else {
                $bgcolor=$_SESSION['config']['semaine_bgcolor'];
            }
            $return .= '<td class="month-out cal-saisie2">&nbsp;</td>';
        }
        $return .= '</tr></table>';
        return $return;
    }

    //calendrier des fermeture
    private static function affiche_calendrier_fermeture($year, $groupe_id = 0) : string
    {
        // on construit le tableau de l'année considérée
        $tab_year=array();
        \hr\Fonctions::get_tableau_jour_fermeture($year, $tab_year,  $groupe_id);
        // navigation
        $onglet = htmlentities(getpost_variable('onglet'), ENT_QUOTES | ENT_HTML401);
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $return = '<div class="btn-group pull-right">';
        $prev_link = "$PHP_SELF?year=". ($year - 1) . "&groupe_id=$groupe_id";
        $return .= '<a href="' . $prev_link . '" class="btn btn-default"><i class="fa fa-chevron-left"></i></a>';
        $currentLink = "$PHP_SELF?year=". date('Y') . "&groupe_id=$groupe_id";
        $return .= '<a href="' . $currentLink . '" class="btn btn-default"><i class="fa fa-home" title="Retourner à l\'année courante"></i></a>';
        $next_link = "$PHP_SELF?year=". ($year + 1) . "&groupe_id=$groupe_id";
        $return .= '<a href="' . $next_link . '" class="btn btn-default"><i class="fa fa-chevron-right"></i></a>';
        $return .= '</div>';
        $return .= '<a href="/hr/jours_fermeture?option=saisie" class="btn btn-success pull-right" style="margin-right:15px">' . _('admin_jours_fermeture_titre') . '</a>';
        $return .= '<h1>Calendrier des fermetures <span class="current-year">' .  $year . '</span></h1>';

        $return .= '<div class="calendar calendar-year">';
        $months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');

        foreach ($months as $month) {
            $return .= '<div class="month">';
            $return .= '<div class="wrapper">';
            $return .= \hr\Fonctions::affiche_calendrier_fermeture_mois($year, $month, $tab_year);
            $return .= '</div>';
            $return .= '</div>';
        }
        $return .= '</div>';
        return $return;
    }

    //insertion des nouvelles dates de fermeture
    private static function insert_year_fermeture($fermeture_id, $tab_j_ferme, $groupe_id) : bool
    {
        $sql_insert="";
        foreach($tab_j_ferme as $jf_date ) {
            $sql_insert="INSERT INTO conges_jours_fermeture (jf_id, jf_gid, jf_date) VALUES ($fermeture_id, $groupe_id, '$jf_date') ;";
            $result_insert = \includes\SQL::singleton()->query($sql_insert);
        }
        return TRUE;
    }

    // supprime une fermeture
    private static function delete_year_fermeture($fermeture_id, $groupe_id) : bool
    {
        $sql_delete="DELETE FROM conges_jours_fermeture WHERE jf_id = '$fermeture_id' AND jf_gid= '$groupe_id' ;";
        $result = \includes\SQL::singleton()->query($sql_delete);
        return TRUE;
    }

    // recup l'id de la derniere fermeture (le max)
    private static function get_last_fermeture_id() : int
    {
        $req_1="SELECT MAX(jf_id) as max FROM conges_jours_fermeture ";
        $res_1 = \includes\SQL::singleton()->query($req_1);
        $row_1 = $res_1->fetch_array();
        if (empty($row_1['max'])) {
            return 0;     // si la table est vide, on renvoit 0
        }
        return $row_1['max'];
    }

    // verifie si la periode donnee chevauche une periode de conges d'un des user du groupe ..
    // retourne TRUE si chevauchement et FALSE sinon !
    private static function verif_periode_chevauche_periode_groupe($date_debut, $date_fin, $num_current_periode='', $tab_periode_calcul, $groupe_id)
    {
        /*****************************/
        // on construit le tableau des users affectés par les fermetures saisies :
        if ($groupe_id==0)  // fermeture pour tous !
            $list_users = get_list_all_users();
        else
            $list_users = get_list_users_du_groupe($groupe_id);

        $tab_users = explode(",", $list_users);

        foreach($tab_users as $current_login) {
            $current_login = trim($current_login);
            // on enleve les quotes qui ont été ajoutées lors de la creation de la liste
            $current_login = trim($current_login, "\'");
            $comment="";
            if (verif_periode_chevauche_periode_user($date_debut, $date_fin, $current_login, $num_current_periode, $tab_periode_calcul, $comment))
                return TRUE;
        }
    }

    private static function commit_annul_fermeture($fermeture_id, $groupe_id) : string
    {
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $db = \includes\SQL::singleton();
        $return = '';

        /*****************************/
        // on construit le tableau des users affectés par les fermetures saisies :
        if ($groupe_id==0) { // fermeture pour tous !
            $list_users = get_list_all_users();
        } else {
            $list_users = get_list_users_du_groupe($groupe_id);
        }

        $tab_users = explode(",", $list_users);

        /***********************************************/
        /** suppression des jours de fermetures   **/
        // on suprimme les dates de cette fermeture dans conges_jours_fermeture
        $result = \hr\Fonctions::delete_year_fermeture($fermeture_id, $groupe_id);


        // on va traiter user par user pour annuler sa periode de conges correspondant et lui re-crediter son solde
        foreach($tab_users as $current_login) {
            $current_login = trim($current_login);
            // on enleve les quotes qui ont été ajoutées lors de la creation de la liste
            $current_login = trim($current_login, "\'");

            // on recupère les infos de la periode ....
            $sql_credit='SELECT p_num, p_nb_jours, p_type FROM conges_periode WHERE p_login="'. $db->quote($current_login).'" AND p_fermeture_id="' . $db->quote($fermeture_id) .'" AND p_etat=\'ok\'';
            $result_credit = $db->query($sql_credit);
            $row_credit = $result_credit->fetch_array();
            $sql_num_periode=$row_credit['p_num'];
            $sql_nb_jours_a_crediter=$row_credit['p_nb_jours'];
            $sql_type_abs=$row_credit['p_type'];


            // on met à jour la table conges_periode .
            $etat = "annul" ;

            $sql1 = 'UPDATE conges_periode SET p_etat = "'. $db->quote($etat).'" WHERE p_num="'. $db->quote($sql_num_periode).'" AND p_etat=\'ok\';';
            $ReqLog = $db->query($sql1);

            if ($ReqLog && \includes\SQL::getVar('affected_rows')) {
                // mise à jour du solde de jours de conges pour l'utilisateur $current_login
                if ($sql_nb_jours_a_crediter != 0) {
                    $sql1 = 'UPDATE conges_solde_user SET su_solde = su_solde + '. $db->quote($sql_nb_jours_a_crediter).' WHERE su_login="'. $db->quote($current_login).'" AND su_abs_id = '. $db->quote($sql_type_abs) ;
                    $ReqLog = $db->query($sql1);
                }
            }
        }

        $return .= '<div class="wrapper">';
        if ($result) {
            $return .= '<br>' . _('form_modif_ok') . '<br><br>';
        } else {
            $return .= '<br>' . _('form_modif_not_ok') . ' !<br><br>';
        }

        // on enregistre cette action dan les logs
        if ($groupe_id==0) { // fermeture pour tous !
            $comment_log = "annulation fermeture $fermeture_id (pour tous) " ;
        } else {
            $comment_log = "annulation fermeture $fermeture_id (pour le groupe $groupe_id)" ;
        }
        log_action(0, "", "", $comment_log);

        $return .= '<form action="/hr/jours_fermeture?option=saisie" method="POST">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_ok') . '">';
        $return .= '</form>';
        $return .= '</div>';
        return $return;
    }

    private static function commit_new_fermeture($new_date_debut, $new_date_fin, $groupe_id, $id_type_conges) : string
    {
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $return = '';

        // on transforme les formats des dates
        $tab_date_debut=explode("/",$new_date_debut);   // date au format d/m/Y
        $date_debut=$tab_date_debut[2]."-".$tab_date_debut[1]."-".$tab_date_debut[0];
        $tab_date_fin=explode("/",$new_date_fin);   // date au format d/m/Y
        $date_fin=$tab_date_fin[2]."-".$tab_date_fin[1]."-".$tab_date_fin[0];

        /*****************************/
        // on construit le tableau des users affectés par les fermetures saisies :
        if ($groupe_id==0) { // fermeture pour tous !
            $list_users = get_list_all_users();
        } else {
            $list_users = get_list_users_du_groupe($groupe_id);
        }

        $tab_users = explode(",", $list_users);
        //******************************
        // !!!!
            // type d'absence à modifier ....
        //    $id_type_conges = 1 ; //"cp" : conges payes

        //calcul de l'ID de de la fermeture (en fait l'ID de la saisie de fermeture)
        $new_fermeture_id = \hr\Fonctions::get_last_fermeture_id() + 1;

        /***********************************************/
        /** enregistrement des jours de fermetures   **/
        $tab_fermeture=array();
        for($current_date=$date_debut; $current_date <= $date_fin; $current_date=jour_suivant($current_date)) {
            $tab_fermeture[] = $current_date;
        }

        // on insere les nouvelles dates saisies dans conges_jours_fermeture
        $result = \hr\Fonctions::insert_year_fermeture($new_fermeture_id, $tab_fermeture, $groupe_id);

        $opt_debut='am';
        $opt_fin='pm';

        /*********************************************************/
        /** insersion des jours de fermetures pour chaque user  **/
        foreach($tab_users as $current_login) {
            $current_login = trim($current_login);
            // on enleve les quotes qui ont été ajoutées lors de la creation de la liste
            $current_login = trim($current_login, "\'");

            if (is_active($current_login)) {
                // on compte le nb de jour à enlever au user (par periode et au total)
                // on ne met à jour la table conges_periode
                $nb_jours = 0;
                $comment="" ;

                $nb_jours = compter($current_login, "", $date_debut, $date_fin, $opt_debut, $opt_fin, $comment);

                // on ne met à jour la table conges_periode .
                $commentaire =  _('divers_fermeture') ;
                $etat = "ok" ;
                $num_periode = insert_dans_periode($current_login, $date_debut, $opt_debut, $date_fin, $opt_fin, $nb_jours, $commentaire, $id_type_conges, $etat, $new_fermeture_id) ;

                // mise à jour du solde de jours de conges pour l'utilisateur $current_login
                if ($nb_jours != 0) {
                    soustrait_solde_et_reliquat_user($current_login, "", $nb_jours, $id_type_conges, $date_debut, $opt_debut, $date_fin, $opt_fin);
                }
            }
        }

        // on recharge les jours fermés dans les variables de session
        init_tab_jours_fermeture($_SESSION['userlogin']);

        $return .= '<div class="wrapper">';

        if ($result) {
            $return .= '<br>' . _('form_modif_ok') . '<br><br>';
        } else {
            $return .= '<br>' . _('form_modif_not_ok') . ' !<br><br>';
        }

        $comment_log = "saisie des jours de fermeture de $date_debut a $date_fin" ;
        log_action(0, "", "", $comment_log);
        $return .= '<form action="' . $PHP_SELF . '" method="POST">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_ok') . '">';
        $return .= '</form>';
        $return .= '</div>';
        return $return;
    }

    private static function confirm_annul_fermeture($fermeture_id, $groupe_id, $fermeture_date_debut, $fermeture_date_fin) : string
    {
        $PHP_SELF = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $return = '';

        $return .= '<div class="wrapper">';
        $return .= '<form action="/hr/jours_fermeture?option=saisie" method="POST">';
        $return .= _('divers_fermeture_du') . '<b>' . $fermeture_date_debut . '</b>' . _('divers_au') . '<b>' . $fermeture_date_fin . '</b>.';
        $return .= '<b>' . _('admin_annul_fermeture_confirm') . '</b>.<br>';
        $return .= '<input type="hidden" name="fermeture_id" value="' . $fermeture_id . '">';
        $return .= '<input type="hidden" name="fermeture_date_debut" value="' . $fermeture_date_debut . '">';
        $return .= '<input type="hidden" name="fermeture_date_fin" value="' . $fermeture_date_fin . '">';
        $return .= '<input type="hidden" name="groupe_id" value="' . $groupe_id . '">';
        $return .= '<input type="hidden" name="choix_action" value="commit_annul_fermeture">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_continuer') . '">';
        $return .= '<a class="btn" href="/hr/jours_fermeture?option=saisie">' . _('form_cancel') . '</a>';
        $return .= '</form>';
        $return .= '</div>';
        return $return;
    }

    // retourne un tableau des periodes de fermeture (pour un groupe donné (gid=0 pour tout le monde))
    private static function get_tableau_periodes_fermeture(&$tab_periodes_fermeture)
    {
        $req_1="SELECT DISTINCT conges_periode.p_date_deb, conges_periode.p_date_fin, conges_periode.p_fermeture_id, conges_jours_fermeture.jf_gid, conges_groupe.g_groupename FROM conges_periode, conges_jours_fermeture LEFT JOIN conges_groupe ON conges_jours_fermeture.jf_gid=conges_groupe.g_gid WHERE conges_periode.p_fermeture_id = conges_jours_fermeture.jf_id AND conges_periode.p_etat='ok' ORDER BY conges_periode.p_date_deb DESC  ";
        $res_1 = \includes\SQL::singleton()->query($req_1);

        $num_select = $res_1->num_rows;
        if ($num_select!=0) {
            while($result_select = $res_1->fetch_array()) {
                $tab_periode=array();
                $tab_periode['date_deb']=$result_select["p_date_deb"];
                $tab_periode['date_fin']=$result_select["p_date_fin"];
                $tab_periode['fermeture_id']=$result_select["p_fermeture_id"];
                $tab_periode['groupe_id']=$result_select["jf_gid"];
                $tab_periode['groupe_name']=$result_select["g_groupename"];
                $tab_periodes_fermeture[]=$tab_periode;
            }
        }
    }

    // Affichage d'un SELECT de formulaire pour choix d'un type d'absence
    private static function affiche_select_conges_id() : string
    {
        $tab_conges=recup_tableau_types_conges();
        $tab_conges_except=recup_tableau_types_conges_exceptionnels();
        $return = '';

        foreach($tab_conges as $id => $libelle) {
            if ($libelle == 1) {
                $return .= '<option value="' . $id . '" selected>' . $libelle . '</option>';
            } else {
                $return .= '<option value="' . $id  . '">' . $libelle . '</option>';
            }
        }
        if (count($tab_conges_except)!=0) {
            foreach($tab_conges_except as $id => $libelle) {
                if ($libelle == 1) {
                    $return .= '<option value="' . $id . '" selected>' . $libelle . '</option>';
                } else {
                    $return .= '<option value="' . $id . '">' . $libelle . '</option>';
                }
            }
        }
        return $return;
    }

    //renvoi un tableau des jours de fermeture
    private static function get_tableau_jour_fermeture($year, &$tab_year,  $groupe_id)
    {
        $sql_select = " SELECT jf_date FROM conges_jours_fermeture WHERE DATE_FORMAT(jf_date, '%Y-%m-%d') LIKE '$year%'  ";
        // on recup les fermeture du groupe + les fermetures de tous !
        if ($groupe_id==0)
            $sql_select = $sql_select."AND jf_gid = 0";
        else
            $sql_select = $sql_select."AND  (jf_gid = $groupe_id OR jf_gid =0 ) ";
        $res_select = \includes\SQL::singleton()->query($sql_select);
        $num_select =$res_select->num_rows;

        if ($num_select!=0) {
            while($result_select = $res_select->fetch_array()) {
                $tab_year[]=$result_select["jf_date"];
            }
        }
    }

    private static function saisie_dates_fermeture($year, $groupe_id, $new_date_debut, $new_date_fin, $code_erreur) : string
    {
        $return = '';

        $tab_date_debut=explode("/",$new_date_debut);   // date au format d/m/Y
        $timestamp_date_debut = mktime(0,0,0, $tab_date_debut[1], $tab_date_debut[0], $tab_date_debut[2]) ;
        $date_debut_yyyy_mm_dd = $tab_date_debut[2]."-".$tab_date_debut[1]."-".$tab_date_debut[0] ;
        $tab_date_fin=explode("/",$new_date_fin);   // date au format d/m/Y
        $timestamp_date_fin = mktime(0,0,0, $tab_date_fin[1], $tab_date_fin[0], $tab_date_fin[2]) ;
        $date_fin_yyyy_mm_dd = $tab_date_fin[2]."-".$tab_date_fin[1]."-".$tab_date_fin[0] ;
        $timestamp_today = mktime(0,0,0, date("m"), date("d"), date("Y")) ;

        // on construit le tableau de l'année considérée
        $tab_year=array();
        \hr\Fonctions::get_tableau_jour_fermeture($year, $tab_year,  $groupe_id);

        $return .= '<form id="form-fermeture" class="form-inline" role="form" action="/hr/jours_fermeture?option=saisie" method="POST">';
        $return .= '<div class="form-group">';
        $return .= '<label for="new_date_debut">' . _('divers_date_debut') . '</label><input type="text" class="form-control date" name="new_date_debut" value="' . $new_date_debut . '">';
        $return .= '</div>';
        $return .= '<div class="form-group">';
        $return .= '<label for="new date_fin">' . _('divers_date_fin') . '</label><input type="text" class="form-control date" name="new_date_fin" value="' . $new_date_fin . '">';
        $return .= '</div>';
        $return .= '<div class="form-group">';
        $return .= '<label for="id_type_conges">' . _('admin_jours_fermeture_affect_type_conges') . '</label>';
        $return .= '<select name="id_type_conges" class="form-control">';
        $return .= \hr\Fonctions::affiche_select_conges_id();
        $return .= '</select>';
        $return .= '</div>';
        $return .= '<hr/>';
        $return .= '<input type="hidden" name="groupe_id" value="' . $groupe_id . '">';
        $return .= '<input type="hidden" name="choix_action" value="commit_new_fermeture">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
        $return .= '</form>';
        return $return;
    }

    private static function saisie_groupe_fermeture() : string
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $return = '<h1>Nouvelle fermeture</h1>';
        $return .= '<a href="' . ROOT_PATH . 'hr/jours_fermeture" class="admin-back"><i class="fa fa-arrow-circle-o-left"></i>Retour calendrier des fermetures</a>';


        $return .= '<div class="row">';
        $return .= '<div class="col-md-6">';
        /********************/
        /* Choix Tous       */
        /********************/

        // AFFICHAGE TABLEAU
        $return .= '<form action="/hr/jours_fermeture?option=saisie" method="POST">';
        $return .= '<input type="hidden" name="groupe_id" value="0">';
        $return .= '<input type="hidden" name="choix_action" value="saisie_dates">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('admin_jours_fermeture_fermeture_pour_tous') . ' !">';
        $return .= '</form>';
        $return .= '</div>';

        if ($config->canFermetureParGroupe()) {
            /********************/
            /* Choix Groupe     */
            /********************/
            // Récuperation des informations :
            $sql_gr = "SELECT g_gid, g_groupename, g_comment FROM conges_groupe ORDER BY g_groupename"  ;

            // AFFICHAGE TABLEAU
            $return .= '<div class="col-md-6">';
            $return .= '<form action="/hr/jours_fermeture?option=saisie" class="form-inline" method="POST">';
            $return .= '<div class="form-group" style="margin-right: 10px;">';
            $ReqLog_gr = $db->query($sql_gr);
            $return .= '<select class="form-control" name="groupe_id">';
            while ($resultat_gr = $ReqLog_gr->fetch_array()) {
                $sql_gid=$resultat_gr["g_gid"] ;
                $sql_group=$resultat_gr["g_groupename"] ;
                $sql_comment=$resultat_gr["g_comment"] ;

                $return .= '<option value="' . $sql_gid . '">' . $sql_group;
            }
            $return .= '</select>';
            $return .= '<input type="hidden" name="choix_action" value="saisie_dates">';
            $return .= '</div>';
            $return .= '<input class="btn btn-success" type="submit" value="' . _('admin_jours_fermeture_fermeture_par_groupe') . '">';
            $return .= '</form>';
            $return .= '</div>';
        }

        /************************************************/
        // HISTORIQUE DES FERMETURES

        $tab_periodes_fermeture = array();
        \hr\Fonctions::get_tableau_periodes_fermeture($tab_periodes_fermeture);
        if (count($tab_periodes_fermeture)!=0) {
            $return .= '<table class="table">';
            $return .= '<thead>';
            $return .= '<tr>';
            $return .= '<th colspan="2">Fermetures</th>';
            $return .= '</tr>';
            $return .= '</thead>';
            foreach($tab_periodes_fermeture as $tab_periode) {
                $date_affiche_1=eng_date_to_fr($tab_periode['date_deb']);
                $date_affiche_2=eng_date_to_fr($tab_periode['date_fin']);
                $fermeture_id =($tab_periode['fermeture_id']);
                $groupe_id =($tab_periode['groupe_id']);
                $groupe_name =($tab_periode['groupe_name']);

                if ($groupe_id==0) {
                    $groupe_name = 'Tous';
                } else {
                    $groupe_name = $groupe_name;
                }

                $return .= '<tr>';
                $return .= '<td>';
                $return .= _('divers_du') . ' <b>'. $date_affiche_1 . '</b> ' . _('divers_au') . ' <b>' . $date_affiche_2 . '</b>  (id ' . $fermeture_id . ')</b> ' . $groupe_name;
                $return .= '</td>';
                $return .= '<td>';
                $return .= '<a href="/hr/jours_fermeture?option=saisie&choix_action=annul_fermeture&fermeture_id=' . $fermeture_id . '&groupe_id=' . $groupe_id . '&fermeture_date_debut=' . $date_affiche_1 . '&fermeture_date_fin=' . $date_affiche_2 . '">' . _('admin_annuler_fermeture') . '</a>';
                $return .= '</td>';
                $return .= '</tr>';
            }
            $return .= '</table>';
        }
        $return .= '</div>';
        return $return;
    }

    /**
     * Encapsule le comportement du module de jours de fermeture
     *
     * @access public
     * @static
     */
    public static function pageJoursFermetureModule() : string
    {
        $return = '';

        /*** initialisation des variables ***/
        /*************************************/
        // recup des parametres reçus :
        // SERVER
        // GET / POST
        $choix_action         = getpost_variable('choix_action');
        $year                 = getpost_variable('year', 0);
        $groupe_id            = htmlentities(getpost_variable('groupe_id'), ENT_QUOTES | ENT_HTML401);
        $id_type_conges       = getpost_variable('id_type_conges');
        $new_date_debut       = getpost_variable('new_date_debut'); // valeur par dédaut = aujourd'hui
        $new_date_fin         = getpost_variable('new_date_fin');   // valeur par dédaut = aujourd'hui
        $fermeture_id         = getpost_variable('fermeture_id', 0);
        $fermeture_date_debut = getpost_variable('fermeture_date_debut');
        $fermeture_date_fin   = getpost_variable('fermeture_date_fin');
        $code_erreur          = getpost_variable('code_erreur', 0);

        // si les dates de début ou de fin ne sont pas passé par get/post alors date du jour.
        if ($new_date_debut=="") {
            if ($year==0) {
                $new_date_debut=date("d/m/Y") ;
            } else {
                $new_date_debut=date("d/m/Y", mktime(0,0,0, date("m"), date("d"), $year) ) ;
            }
        }
        if ($new_date_fin=="") {
            if ($year==0) {
                $new_date_fin=date("d/m/Y") ;
            } else {
                $new_date_fin=date("d/m/Y", mktime(0,0,0, date("m"), date("d"), $year) ) ;
            }
        }

        if ($year ==0) {
            $year= date("Y");
        }

        /*************************************/

        /***********************************/
        /*  VERIF DES DATES RECUES   */
        $tab_date_debut=explode("/",$new_date_debut);   // date au format d/m/Y
        $timestamp_date_debut = mktime(0,0,0, $tab_date_debut[1], $tab_date_debut[0], $tab_date_debut[2]) ;
        $date_debut_yyyy_mm_dd = $tab_date_debut[2]."-".$tab_date_debut[1]."-".$tab_date_debut[0] ;
        $tab_date_fin=explode("/",$new_date_fin);   // date au format d/m/Y
        $timestamp_date_fin = mktime(0,0,0, $tab_date_fin[1], $tab_date_fin[0], $tab_date_fin[2]) ;
        $date_fin_yyyy_mm_dd = $tab_date_fin[2]."-".$tab_date_fin[1]."-".$tab_date_fin[0] ;
        $timestamp_today = mktime(0,0,0, date("m"), date("d"), date("Y")) ;

        /*********************************/
        /*   COMPOSITION DES ONGLETS...  */
        /*********************************/

        $option = htmlentities(getpost_variable('option'), ENT_QUOTES | ENT_HTML401);

        if (!$option) {
            $option = 'calendar';
        }

        //initialisation de l'action par défaut
        if ($choix_action=="") {
            $choix_action="saisie_groupe";
        }

        /***********************************/
        // AFFICHAGE DE LA PAGE
        //header_menu('', 'Libertempo : '._('divers_fermeture'));


        // vérifie si les jours fériés sont saisie pour l'année en cours
        if ( (verif_jours_feries_saisis($date_debut_yyyy_mm_dd)==FALSE) && (verif_jours_feries_saisis($date_fin_yyyy_mm_dd)==FALSE) ) {
                $code_erreur=1 ;  // code erreur : jour feriés non saisis
                $option="calendar";
        }

        //initialisation de l'action demandée : saisie_dates, commit_new_fermeture pour enregistrer une fermeture, annul_fermeture pour confirmer une annulation, commit_annul_fermeture pour annuler une fermeture

        //en cas de confirmation d'une fermeture :
        if ($choix_action == "commit_new_fermeture") {
            // on verifie que $new_date_debut est anterieure a $new_date_fin
            if ($timestamp_date_debut > $timestamp_date_fin) {
                $code_erreur=2 ;  // code erreur : $new_date_debut est posterieure a $new_date_fin
                $choix_action="saisie_dates";
            }
            // on verifie que ce ne sont pas des dates passées
            elseif ($timestamp_date_debut < $timestamp_today) {
                $code_erreur=3 ;  // code erreur : saisie de date passée
                $choix_action="saisie_dates";
            }
            // on ne verifie QUE si date_debut ou date_fin sont !=  d'aujourd'hui
            // (car aujourd'hui est la valeur par dédaut des dates, et on ne peut saisir aujourd'hui puisque c'est fermé !)
            elseif ( ($timestamp_date_debut==$timestamp_today) || ($timestamp_date_fin==$timestamp_today) ) {
                $code_erreur=4 ;  // code erreur : saisie de aujourd'hui
                $choix_action="saisie_dates";
            } else {
                // fabrication et initialisation du tableau des demi-jours de la date_debut à la date_fin
                $tab_periode_calcul = make_tab_demi_jours_periode($date_debut_yyyy_mm_dd, $date_fin_yyyy_mm_dd, "am", "pm");
                // on verifie si la periode saisie ne chevauche pas une periode existante
                if (\hr\Fonctions::verif_periode_chevauche_periode_groupe($date_debut_yyyy_mm_dd, $date_fin_yyyy_mm_dd, '', $tab_periode_calcul, $groupe_id) ) {
                    $code_erreur=5 ;  // code erreur : fermeture chevauche une periode deja saisie
                    $choix_action="saisie_dates";
                }
            }
        }

        if ($option == 'calendar') {
            // les jours fériés de l'annee de la periode saisie ne sont pas enregistrés
            if ($code_erreur==1) {
                $return .= '<div class="alert alert-danger">' . _('admin_jours_fermeture_annee_non_saisie') . '</div>';
            }

                   /************************************************/
            // CALENDRIER DES FERMETURES
            $return .= \hr\Fonctions::affiche_calendrier_fermeture($year);
        } elseif ($choix_action=="saisie_dates") {
            if ($groupe_id=="") {
                $groupe_id=0;
            }

            // $new_date_debut est anterieure a $new_date_fin
            if ($code_erreur==2) {
                $return .= '<div class="alert alert-danger">' . _('admin_jours_fermeture_dates_incompatibles') . '</div>';
            }

            // ce ne sont des dates passées
            if ($code_erreur==3) {
                $return .= '<div class="alert alert-danger">' . _('admin_jours_fermeture_date_passee_error') . '</div>';
            }

            // fermeture le jour même impossible
            if ($code_erreur==4) {
                $return .= '<div class="alert alert-danger">' . _('admin_jours_fermeture_fermeture_aujourd_hui') . '</div>';
            }

            // la periode saisie chevauche une periode existante
            if ($code_erreur==5) {
                $return .= '<div class="alert alert-danger">' . _('admin_jours_fermeture_chevauche_periode') . '</div>';
            }

            $return .= '<div class="wrapper">';
            if ($option == 'saisie') {
                $return .= \hr\Fonctions::saisie_dates_fermeture($year, $groupe_id, $new_date_debut, $new_date_fin, $code_erreur);
            }
        } elseif ($choix_action=="saisie_groupe") {
            $return .= '<div class="wrapper">';
            $return .= \hr\Fonctions::saisie_groupe_fermeture();
            $return .= '</div>';
        } elseif ($choix_action=="commit_new_fermeture") {
            $return .= \hr\Fonctions::commit_new_fermeture($new_date_debut, $new_date_fin, $groupe_id, $id_type_conges);
        } elseif ($choix_action=="annul_fermeture") {
            $return .= \hr\Fonctions::confirm_annul_fermeture($fermeture_id, $groupe_id, $fermeture_date_debut, $fermeture_date_fin);
        } elseif ($choix_action=="commit_annul_fermeture") {
            $return .= \hr\Fonctions::commit_annul_fermeture($fermeture_id, $groupe_id);
        }

        return $return;
    }

    public static function getJoursFeriesFrance(int $iAnnee) : array
    {
        //Initialisation de variables
        $unJour = 3600*24;
        $tbJourFerie = [];
        $timePaques = \easter_date($iAnnee) + 6 * 3600; // évite les changements d'heures

        $tbJourFerie["Jour de l an"] = $iAnnee . "-01-01";
        $tbJourFerie["Paques"] = date('Y-m-d', $timePaques);
        $tbJourFerie["Lundi de Paques"] = $iAnnee . date("-m-d", $timePaques + 1 * $unJour);
        $tbJourFerie["Fete du travail"] = $iAnnee . "-05-01";
        $tbJourFerie["Armistice 39-45"] = $iAnnee . "-05-08";
        $tbJourFerie["Jeudi de l ascension"] = $iAnnee . date("-m-d", easter_date($iAnnee) + 39 * $unJour);
        $tbJourFerie["Fete nationale"] = $iAnnee . "-07-14";
        $tbJourFerie["Assomption"] = $iAnnee . "-08-15";
        $tbJourFerie["Toussaint"] = $iAnnee . "-11-01";
        $tbJourFerie["Armistice 14-18"] = $iAnnee . "-11-11";
        $tbJourFerie["Noel"] = $iAnnee . "-12-25";

        return $tbJourFerie;
    }

    public static function insereFeriesAnnee(array $tab_checkbox_j_chome) : bool
    {
        $db = \includes\SQL::singleton();
        foreach ($tab_checkbox_j_chome as $date) {
            $db->query('INSERT INTO conges_jours_feries SET jf_date="'. $db->quote($date).'";');
        }
        return true;
    }

    public static function supprimeFeriesAnnee(int $year) : bool
    {
        $db = \includes\SQL::singleton();
        $sql_delete='DELETE FROM conges_jours_feries WHERE jf_date LIKE "'. $db->quote($year).'%" ;';
        $db->query($sql_delete);

        return true;
    }
}
