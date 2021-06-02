<?php
namespace responsable;

/**
 * Regroupement des fonctions liées au responsable
 */
class Fonctions
{
    // on insert l'ajout de conges dans la table periode
    private static function insert_ajout_dans_periode($login, $nb_jours, $id_type_abs, $commentaire)
    {
        $date_today=date("Y-m-d");

        $result=insert_dans_periode($login, $date_today, "am", $date_today, "am", $nb_jours, $commentaire, $id_type_abs, "ajout", 0);
    }

    public static function ajout_global_groupe($choix_groupe, $tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all)
    {
        // $tab_new_nb_conges_all[$id_conges]= nb_jours
        // $tab_calcul_proportionnel[$id_conges]= TRUE / FALSE

        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $db = \includes\SQL::singleton();
        $return = '';

        // recup de la liste des users d'un groupe donné
        $list_users = get_list_users_du_groupe($choix_groupe);

        foreach($tab_new_nb_conges_all as $id_conges => $nb_jours) {
            if ($nb_jours!=0) {
                $comment = $tab_new_comment_all[$id_conges];

                $sql1="SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ($list_users) AND u_is_active='Y' ORDER BY u_login ";
                $ReqLog1 = $db->query($sql1);

                while ($resultat1 = $ReqLog1->fetch_array()) {
                    $current_login  =$resultat1["u_login"];
                    $current_quotite=$resultat1["u_quotite"];

                    if ((!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=true) ) {
                        $nb_conges=$nb_jours;
                    } else {
                        // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2
                        $nb_conges = (round(($nb_jours*($current_quotite/100))*2))/2  ;
                    }
                    $nb_conges_ok = verif_saisie_decimal($nb_conges);
                    if ($nb_conges_ok) {
                        // 1 : on update conges_solde_user
                        $req_update = "UPDATE conges_solde_user SET su_solde = su_solde+$nb_conges
                            WHERE  su_login = '$current_login' AND su_abs_id = $id_conges   ";
                        $db->query($req_update);

                        // 2 : on insert l'ajout de conges dans la table periode
                        // recup du nom du groupe
                        $groupename= get_group_name_from_id($choix_groupe);
                        $commentaire =  _('resp_ajout_conges_comment_periode_groupe') ." $groupename";

                        // ajout conges
                        \responsable\Fonctions::insert_ajout_dans_periode($current_login, $nb_conges, $id_conges, $commentaire);
                    }

                }

                $group_name = get_group_name_from_id($choix_groupe);
                // 3 : Enregistrement du commentaire relatif à l'ajout de jours de congés
                if ((!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=true) ) {
                    $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
                } else {
                    $comment_log = "ajout conges pour groupe $group_name ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
                }
                log_action(0, "ajout", "groupe", $comment_log);
            }
        }
        $return .= ' ' . _('form_modif_ok') . '<br><br>';
        redirect(ROOT_PATH .'responsable/resp_index.php');
        return $return;
    }

    public static function ajout_global($tab_new_nb_conges_all, $tab_calcul_proportionnel, $tab_new_comment_all)
    {
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $db = \includes\SQL::singleton();
        $return = '';

        // $tab_new_nb_conges_all[$id_conges]= nb_jours
        // $tab_calcul_proportionnel[$id_conges]= TRUE / FALSE

        // recup de la liste de TOUS les users dont $resp_login est responsable
        // (prend en compte le resp direct, les groupes, le resp virtuel, etc ...)
        // renvoit une liste de login entre quotes et séparés par des virgules
        $list_users_du_resp = get_list_all_users_du_resp($_SESSION['userlogin']);

        foreach($tab_new_nb_conges_all as $id_conges => $nb_jours) {
            if ($nb_jours!=0) {
                $comment = $tab_new_comment_all[$id_conges];

                $sql1="SELECT u_login, u_quotite FROM conges_users WHERE u_login IN ($list_users_du_resp) AND u_is_active='Y' ORDER BY u_login ";
                $ReqLog1 = $db->query($sql1);

                while($resultat1 = $ReqLog1->fetch_array()) {
                    $current_login  =$resultat1["u_login"];
                    $current_quotite=$resultat1["u_quotite"];

                    if ((!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=true) ) {
                        $nb_conges=$nb_jours;
                    } else {
                        // pour arrondir au 1/2 le + proche on  fait x 2, on arrondit, puis on divise par 2
                        $nb_conges = (round(($nb_jours*($current_quotite/100))*2))/2  ;
                    }

                    $nb_conges_ok = verif_saisie_decimal($nb_conges);
                    if ($nb_conges_ok) {
                        // 1 : update de la table conges_solde_user
                        $req_update = "UPDATE conges_solde_user SET su_solde = su_solde+$nb_conges
                            WHERE  su_login = '$current_login' AND su_abs_id = $id_conges   ";
                        $db->query($req_update);

                        // 2 : on insert l'ajout de conges GLOBAL (pour tous les users) dans la table periode
                        $commentaire =  _('resp_ajout_conges_comment_periode_all');
                        // ajout conges
                        \responsable\Fonctions::insert_ajout_dans_periode($current_login, $nb_conges, $id_conges, $commentaire);
                    }
                }
                // 3 : Enregistrement du commentaire relatif à l'ajout de jours de congés
                if ((!isset($tab_calcul_proportionnel[$id_conges])) || ($tab_calcul_proportionnel[$id_conges]!=true) ) {
                    $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : No)";
                } else {
                    $comment_log = "ajout conges global ($nb_jours jour(s)) ($comment) (calcul proportionnel : Yes)";
                }
                log_action(0, "ajout", "tous", $comment_log);
            }
        }

        $return .= ' ' . _('form_modif_ok') . '<br><br>';
        /* APPEL D'UNE AUTRE PAGE au bout d'une tempo de 2secondes */
        redirect(ROOT_PATH .'responsable/resp_index.php');
        return $return;
    }

    public static function ajout_conges($tab_champ_saisie, $tab_commentaire_saisie)
    {
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        foreach ($tab_champ_saisie as $user_name => $tab_conges)   // tab_champ_saisie[$current_login][$id_conges]=valeur du nb de jours ajouté saisi
        {
            foreach ($tab_conges as $id_conges => $user_nb_jours_ajout) {
                $user_nb_jours_ajout_float =(float) $user_nb_jours_ajout ;
                $valid=verif_saisie_decimal($user_nb_jours_ajout_float);   //verif la bonne saisie du nombre décimal
                if ($valid) {
                    if ($user_nb_jours_ajout_float!=0) {
                        /* Modification de la table conges_users */
                        $sql1 = "UPDATE conges_solde_user SET su_solde = su_solde+$user_nb_jours_ajout_float WHERE su_login='$user_name' AND su_abs_id = $id_conges " ;
                        /* On valide l'UPDATE dans la table ! */
                        \includes\SQL::singleton()->query($sql1);

                        /*            // Enregistrement du commentaire relatif à l'ajout de jours de congés
                                    $comment = $tab_commentaire_saisie[$user_name];
                                    $sql1 = "INSERT INTO conges_historique_ajout (ha_login, ha_date, ha_abs_id, ha_nb_jours, ha_commentaire)
                                    VALUES ('$user_name', NOW(), $id_conges, $user_nb_jours_ajout_float , '$comment')";
                                    $ReqLog1 = SQL::query($sql1) ;
                         */
                        // on insert l'ajout de conges dans la table periode
                        $commentaire =  _('resp_ajout_conges_comment_periode_user');
                        \responsable\Fonctions::insert_ajout_dans_periode($user_name, $user_nb_jours_ajout_float, $id_conges, $commentaire);
                    }
                }
            }
        }
        $return .= ' '. _('form_modif_ok') . '<br><br>';
        redirect(ROOT_PATH .'responsable/resp_index.php');
        return $return;
    }

    private static function new_conges($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type_id)
    {
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        //conversion des dates
        $new_debut = convert_date($new_debut);
        $new_fin = convert_date($new_fin);

        // verif validité des valeurs saisies
        $valid = verif_saisie_new_demande($new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $user_login);

        if ($valid) {
            $return .= $user_login . '---' . $new_debut . '_' . $new_demi_jour_deb . '---' . $new_fin . '_' .  $new_demi_jour_fin . '---' . $new_nb_jours . '---' . $new_comment . '---' . $new_type_id . '<br>';

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
            if (isset($tab_tout_type_abs[$new_type_id]['type']) && $tab_tout_type_abs[$new_type_id]['type']=="conges") {
                $user_nb_jours_pris_float=(float) $new_nb_jours ;
                soustrait_solde_et_reliquat_user($user_login, "", $user_nb_jours_pris_float, $new_type_id, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin);
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
        $return .= '<input class="btn" type="submit" value="' . _('form_retour') . '">';
        $return .= '</form>';
        return $return;
    }

    private static function traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus)
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

            if ($reponse == "ACCEPTE") // acceptation definitive d'un conges
            {
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat="ok", p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';
                $ReqLog1 = $db->query($sql1);

                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int, "ok", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

                    /* UPDATE table "conges_solde_user" (jours restants) */
                    // on retranche les jours seulement pour des conges pris (pas pour les absences)
                    // donc seulement si le type de l'absence qu'on accepte est un "conges"
                    if (($tab_tout_type_abs[$value_type_abs_id]['type']=="conges")||($tab_tout_type_abs[$value_type_abs_id]['type']=="conges_exceptionnels")) {
                        soustrait_solde_et_reliquat_user($user_login, $numero_int, $user_nb_jours_pris_float, $value_type_abs_id, $date_deb, $demi_jour_deb, $date_fin, $demi_jour_fin);
                    }

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailValidationUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "accept_conges");
                    }
                }
            } elseif ($reponse == "VALID") {
                // première validation dans le cas d'une double validation
                /* UPDATE table "conges_periode" */
                $sql1 = 'UPDATE conges_periode SET p_etat="valid", p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND p_etat=\'demande\';';
                $ReqLog1 = $db->query($sql1);

                if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int, "valid", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

                    //envoi d'un mail d'alerte au user (si demandé dans config de php_conges)
                    if ($config->isSendMailValidationUtilisateur()) {
                        alerte_mail($_SESSION['userlogin'], $user_login, $numero_int, "valid_conges");
                    }
                }
            }
            elseif ($reponse == "REFUSE") {
                // recup di motif de refus
                $motif_refus = addslashes($tab_text_refus[$numero_int]);
                $sql3 = 'UPDATE conges_periode SET p_etat="refus", p_motif_refus=\''.$motif_refus.'\', p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND ( p_etat=\'valid\' OR p_etat=\'demande\' );';
                $ReqLog3 = $db->query($sql3);

                if ($ReqLog3 && \includes\SQL::getVar('affected_rows')) {
                    // Log de l'action
                    log_action($numero_int, "refus", $user_login, "traite demande $numero ($user_login) ($user_nb_jours_pris jours) : $date_deb");

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

    private static function annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul)
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // recup dans un tableau de tableau les infos des types de conges et absences
        $tab_tout_type_abs = recup_tableau_tout_types_abs();

        while($elem_tableau = each($tab_checkbox_annule)) {
            $champs = explode("--", $elem_tableau['value']);
            $user_login=$champs[0];
            $user_nb_jours_pris_float=$champs[1];
            $VerifDec=verif_saisie_decimal($user_nb_jours_pris_float);
            $numero=$elem_tableau['key'];
            $numero_int=(int) $numero;
            $user_type_abs_id=$champs[2];

            $motif_annul=addslashes($tab_text_annul[$numero_int]);

            /* UPDATE table "conges_periode" */
            $sql1 = 'UPDATE conges_periode SET p_etat="annul", p_motif_refus="'. $db->quote($motif_annul).'", p_date_traitement=NOW() WHERE p_num="'. $db->quote($numero_int).'" AND p_etat=\'ok\';';
            $ReqLog1 = $db->query($sql1);

            if ($ReqLog1 && \includes\SQL::getVar('affected_rows')) {
                // Log de l'action
                log_action($numero_int, "annul", $user_login, "annulation conges $numero ($user_login) ($user_nb_jours_pris_float jours)");

                /* UPDATE table "conges_solde_user" (jours restants) */
                // on re-crédite les jours seulement pour des conges pris (pas pour les absences)
                // donc seulement si le type de l'absence qu'on annule est un "conges"
                if (in_array($tab_tout_type_abs[$user_type_abs_id]['type'], ["conges","conges_exceptionnels"])) {
                    $sql2 = 'UPDATE conges_solde_user SET su_solde = su_solde+"'. $db->quote($user_nb_jours_pris_float).'" WHERE su_login="'. $db->quote($user_login).'" AND su_abs_id="'. $db->quote($user_type_abs_id).'";';
                    $ReqLog2 = $db->query($sql2);
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
    private static function affiche_etat_conges_user_for_resp($user_login, $year_affichage, $tri_date, $onglet)
    {
        $db = \includes\SQL::singleton();
        $config = new \App\Libraries\Configuration($db);
        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        // affichage de l'année et des boutons de défilement
        $year_affichage_prec = $year_affichage-1 ;
        $year_affichage_suiv = $year_affichage+1 ;
        $return .= '<div class="calendar-nav">';
        $return .= '<ul>';
        $return .= '<li><a class="action previous" href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&year_affichage=' . $year_affichage_prec . '"><i class="fa fa-chevron-left"></i></a></li>';
        $return .= '<li class="current-year">' . $year_affichage . '</li>';
        $return .= '<li><a class="action next" href="' . $PHP_SELF . '?onglet=traite_user&user_login=' . $user_login . '&year_affichage=' . $year_affichage_suiv . '"><i class="fa fa-chevron-right"></i></a></li>';
        $return .= '</ul>';
        $return .= '</div>';

        $return .= '<h2>' . _('resp_traite_user_etat_conges') . ' ' . $year_affichage . '</h2>';

        // Récupération des informations de speriodes de conges/absences
        $sql3 = "SELECT p_login, p_date_deb, p_demi_jour_deb, p_date_fin, p_demi_jour_fin, p_nb_jours, p_commentaire, p_type, p_etat, p_motif_refus, p_date_demande, p_date_traitement, p_num FROM conges_periode " .
            "WHERE p_login = '$user_login' " .
            "AND p_etat !='demande' " .
            "AND p_etat !='valid' " .
            "AND (p_date_deb LIKE '$year_affichage%' OR p_date_fin LIKE '$year_affichage%') ";
        if ($tri_date=="descendant") {
            $sql3=$sql3." ORDER BY p_date_deb DESC ";
        } else {
            $sql3=$sql3." ORDER BY p_date_deb ASC ";
        }

        $ReqLog3 = $db->query($sql3);

        $count3=$ReqLog3->num_rows;
        if ($count3==0) {
            $return .= '<b>' . _('resp_traite_user_aucun_conges') . '</b><br><br>';
        } else {
            // recup dans un tableau de tableau les infos des types de conges et absences
            $tab_types_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user" method="POST">';
            $return .= '<table class="table table-hover table-responsive table-condensed table-striped">';
            $return .= '<thead>';
            $return .= '<tr align="center">';
            $return .= '<th>';
            $return .= _('divers_debut_maj_1');
            $return .= '</th>';
            $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
            $return .= '<th>' . _('divers_nb_jours_pris_maj_1') . '</th>';
            $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
            $return .= '<th>' . _('divers_type_maj_1') . '</th>';
            $return .= '<th>' . _('divers_etat_maj_1') . '</th>';
            $return .= '<th>' . _('resp_traite_user_annul') . '</th>';
            $return .= '<th>' . _('resp_traite_user_motif_annul') . '</th>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<th>' . _('divers_date_traitement') . '</th>';
            }
            $return .= '</tr>';
            $return .= '</thead>';
            $return .= '<tbody>';
            $tab_checkbox=array();
            $i = true;
            while ($resultat3 = $ReqLog3->fetch_array()) {
                $sql_login=$resultat3["p_login"] ;
                $sql_date_deb=$resultat3["p_date_deb"];
                $sql_demi_jour_deb=$resultat3["p_demi_jour_deb"] ;
                $demi_j_deb = ($sql_demi_jour_deb == "am") ? 'matin' : 'après-midi';
                $sql_date_fin=$resultat3["p_date_fin"];
                $sql_demi_jour_fin=$resultat3["p_demi_jour_fin"] ;
                $demi_j_fin = ($sql_demi_jour_fin == "am") ? 'matin' : 'après-midi';
                $sql_nb_jours=affiche_decimal($resultat3["p_nb_jours"]);
                $sql_commentaire=$resultat3["p_commentaire"] ;
                $sql_type=$resultat3["p_type"] ;
                $sql_etat=$resultat3["p_etat"] ;
                $sql_motif_refus=$resultat3["p_motif_refus"] ;
                $sql_p_date_demande = $resultat3["p_date_demande"];
                $sql_p_date_traitement = $resultat3["p_date_traitement"];
                $sql_num=$resultat3["p_num"] ;

                if (($sql_etat=="annul") || ($sql_etat=="refus") || ($sql_etat=="ajout")) {
                    $casecocher1="";
                    if ($sql_etat=="refus") {
                        if ($sql_motif_refus=="") {
                            $sql_motif_refus =  _('divers_inconnu');
                        }
                        //$text_annul="<i>motif du refus : $sql_motif_refus</i>";
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    } elseif ($sql_etat=="annul") {
                        if ($sql_motif_refus=="")
                            $sql_motif_refus =  _('divers_inconnu')  ;
                        //$text_annul="<i>motif de l'annulation : $sql_motif_refus</i>";
                        $text_annul="<i>". _('resp_traite_user_motif') ." : $sql_motif_refus</i>";
                    } elseif ($sql_etat=="ajout") {
                        $text_annul="&nbsp;";
                    }
                } else {
                    $casecocher1=sprintf("<input type=\"checkbox\" name=\"tab_checkbox_annule[$sql_num]\" value=\"$sql_login--$sql_nb_jours--$sql_type--ANNULE\">");
                    $text_annul="<input type=\"text\" name=\"tab_text_annul[$sql_num]\" size=\"20\" max=\"100\">";
                }

                $return .= '<tr class="' . ($i ? 'i' : 'p') . '">';
                $return .= '<td>' . \App\Helpers\Formatter::dateIso2Fr($sql_date_deb) .' <span class="demi">' . schars($demi_j_deb) . '</span></td>';
                $return .= '<td>' . \App\Helpers\Formatter::dateIso2Fr($sql_date_fin) .' <span class="demi">' . schars($demi_j_deb) . '</span></td>';
                $return .= '<td>' . $sql_nb_jours . '</td>';
                $return .= '<td>' . $sql_commentaire . '</td>';
                $return .= '<td>' . $tab_types_abs[$sql_type]['libelle'] . '</td>';
                $return .= '<td>';
                if ($sql_etat=="refus") {
                    $return .= _('divers_refuse');
                } elseif ($sql_etat=="annul") {
                    $return .= _('divers_annule');
                } else {
                    $return .= $sql_etat;
                }
                $return .= '</td>';
                $return .= '<td>' . $casecocher1 . '</td>';
                $return .= '<td>' . $text_annul . '</td>';
                if ($config->canAfficheDateTraitement()) {
                    if (empty($sql_p_date_traitement)) {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_p_date_demande . '<br>' . _('divers_traitement') . ' : pas traité</td>';
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
            $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
            $return .= '</form>';
        }
        return $return;
    }

    //affiche l'état des demande en attente de 2ieme validation du user (avec le formulaire pour le responsable)
    private static function affiche_etat_demande_2_valid_user_for_resp($user_login)
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
            //echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"80%\">\n";
            $return .= '<table cellpadding="2" class="table table-hover table-responsive table-condensed table-striped">';
            $return .= '<thead>';
            $return .= '<tr align="center">';
            $return .= '<th>' . _('divers_debut_maj_1') . '</th>';
            $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
            $return .= '<th>' . _('divers_nb_jours_pris_maj_1') . '</th>';
            $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
            $return .= '<th>' . _('divers_type_maj_1') . '</th>';
            $return .= '<th>' . _('divers_accepter_maj_1') . '</th>';
            $return .= '<th>' . _('divers_refuser_maj_1') . '</th>';
            $return .= '<th>' . _('resp_traite_user_motif_refus') . '</th>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<th>' . _('divers_date_traitement') . '</th>';
            }
            $return .= '</tr>';
            $return .= '</thead>';
            $return .= '<tbody>';

            $i = true;
            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array()) {
                $sql_date_deb = $resultat2["p_date_deb"];
                $sql_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]);
                $sql_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                if ($sql_demi_jour_deb=="am") {
                    $demi_j_deb =  _('divers_am_short');
                } else {
                    $demi_j_deb =  _('divers_pm_short');
                }
                $sql_date_fin = $resultat2["p_date_fin"];
                $sql_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]);
                $sql_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                if ($sql_demi_jour_fin=="am") {
                    $demi_j_fin =  _('divers_am_short');
                } else {
                    $demi_j_fin =  _('divers_pm_short');
                }
                $sql_nb_jours=affiche_decimal($resultat2["p_nb_jours"]);
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
                    if (empty($sql_date_traitement)) {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : pas traité</td>';
                    } else {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_date_traitement . '</td>';
                    }
                }
                $return .= '</tr>';
                $i = !$i;
            }
            $return .= '</tbody></table>';

            $return .= '<input type="hidden" name="user_login" value="' . $user_login . '">';
            $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
            $return .= '<a class="btn" href="' . $PHP_SELF . '">' . _('form_cancel') . '</a>';
            $return .= ' </form>';
        }
        return $return;
    }

    //affiche l'état des demandes du user (avec le formulaire pour le responsable)
    private static function affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp)
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
            $return .= '<p><strong>' . _('resp_traite_user_aucune_demande') . '</strong></p>';
        } else {
            // recup dans un tableau des types de conges
            $tab_type_all_abs = recup_tableau_tout_types_abs();

            // AFFICHAGE TABLEAU
            $return .= '<form action="' . $PHP_SELF . '?onglet=traite_user" method="POST">';
            //echo "<table cellpadding=\"2\" class=\"table table-hover table-responsive table-condensed table-striped\" width=\"80%\">\n";
            $return .= '<table cellpadding="2" class="table table-hover table-responsive table-condensed table-striped">';
            $return .= '<tr align="center">';
            $return .= '<td>' . _('divers_debut_maj_1') . '</td>';
            $return .= '<td>' . _('divers_fin_maj_1') . '</td>';
            $return .= '<td>' . _('divers_nb_jours_pris_maj_1') . '</td>';
            $return .= '<td>' . _('divers_comment_maj_1') . '</td>';
            $return .= '<td>' . _('divers_type_maj_1') . '</td>';
            $return .= '<td>' . _('divers_accepter_maj_1') . '</td>';
            $return .= '<td>' . _('divers_refuser_maj_1') . '</td>';
            $return .= '<td>' . _('resp_traite_user_motif_refus') . '</td>';
            if ($config->canAfficheDateTraitement()) {
                $return .= '<td>' . _('divers_date_traitement') . '</td>';
            } else {
                $return .= '<td></td>';
            }
            $return .= '</tr>';

            $tab_checkbox=array();
            while ($resultat2 = $ReqLog2->fetch_array()) {
                $sql_date_deb = $resultat2["p_date_deb"];
                $sql_date_deb_fr = eng_date_to_fr($resultat2["p_date_deb"]);
                $sql_demi_jour_deb=$resultat2["p_demi_jour_deb"] ;
                if ($sql_demi_jour_deb=="am") {
                    $demi_j_deb =  _('divers_am_short');
                } else {
                    $demi_j_deb =  _('divers_pm_short');
                }
                $sql_date_fin = $resultat2["p_date_fin"];
                $sql_date_fin_fr = eng_date_to_fr($resultat2["p_date_fin"]);
                $sql_demi_jour_fin=$resultat2["p_demi_jour_fin"] ;
                if ($sql_demi_jour_fin=="am") {
                    $demi_j_fin =  _('divers_am_short');
                } else {
                    $demi_j_fin =  _('divers_pm_short');
                }
                $sql_nb_jours=affiche_decimal($resultat2["p_nb_jours"]);
                $sql_commentaire=$resultat2["p_commentaire"] ;
                $sql_type=$resultat2["p_type"] ;
                $sql_date_demande = $resultat2["p_date_demande"];
                $sql_date_traitement = $resultat2["p_date_traitement"];
                $sql_num=$resultat2["p_num"] ;

                // on construit la chaine qui servira de valeur à passer dans les boutons-radio
                $chaine_bouton_radio = "$user_login--$sql_nb_jours--$sql_type--$sql_date_deb--$sql_demi_jour_deb--$sql_date_fin--$sql_demi_jour_fin";

                // si le user fait l'objet d'une double validation on a pas le meme resultat sur le bouton !
                if ($tab_user['double_valid'] == "Y") {
                    /*******************************/
                    /* verif si le resp est grand_responsable pour ce user*/
                    if (in_array($_SESSION['userlogin'], $tab_grd_resp)) { // si user_login est dans le tableau des grand responsable
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                    } else {
                        $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--VALID\">";
                    }
                } else {
                    $boutonradio1="<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--ACCEPTE\">";
                }

                $boutonradio2 = "<input type=\"radio\" name=\"tab_radio_traite_demande[$sql_num]\" value=\"$chaine_bouton_radio--REFUSE\">";

                $text_refus  = "<input type=\"text\" name=\"tab_text_refus[$sql_num]\" size=\"20\" max=\"100\">";

                $return .= '<tr align="center">';
                $return .= '<td>' . $sql_date_deb_fr . '_' . $demi_j_deb . '</td>';
                $return .= '<td>' . $sql_date_fin_fr . '_' . $demi_j_fin . '</td>';
                $return .= '<td>' . $sql_nb_jours . '</td>';
                $return .= '<td>' . $sql_commentaire . '</td>';
                $return .= '<td>' . $tab_type_all_abs[$sql_type]['libelle'] . '</td>';
                $return .= '<td>' . $boutonradio1 . '</td>';
                $return .= '<td>' . $boutonradio2 . '</td>';
                $return .= '<td>' . $text_refus . '</td>';
                $return .= '<td>' . $sql_date_demande . '</td>';

                if ($config->canAfficheDateTraitement()) {
                    if (empty($sql_date_traitement)) {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : pas traité</td>';
                    } else {
                        $return .= '<td class="histo-left">' . _('divers_demande') . ' : ' . $sql_date_demande . '<br>' . _('divers_traitement') . ' : ' . $sql_date_traitement . '</td>';
                    }
                }

                $return .= '</tr>';
            }
            $return .= '</table>';

            $return .= '<input type="hidden" name="user_login" value="' . $user_login . '">';
            $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
            $return .= '<a class="btn" href="' . $PHP_SELF . '">' . _('form_cancel') . '</a>';
            $return .= '</form>';
        }
        return $return;
    }

    private static function affichage($user_login, $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date)
    {
        $config = new \App\Libraries\Configuration(\includes\SQL::singleton());

        $PHP_SELF = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
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

        $list_all_users_du_resp=get_list_all_users_du_resp($_SESSION['userlogin']);

        // recup des grd resp du user
        $tab_grd_resp=array();
        get_tab_grd_resp_du_user($user_login, $tab_grd_resp);

        /********************/
        /* Titre */
        /********************/
        $return .= '<h1>' . $tab_user['prenom'] . ' ' . $tab_user['nom'] . '</h1>';


        /********************/
        /* Bilan des Conges */
        /********************/
        // AFFICHAGE TABLEAU
        // affichage du tableau récapitulatif des solde de congés d'un user
        $return .= affiche_tableau_bilan_conges_user($user_login);
        $return .= '<hr/>';

        /*************************/
        /* SAISIE NOUVEAU CONGES */
        /*************************/
        // dans le cas ou les users ne peuvent pas saisir de demande, le responsable saisi les congès :
        if (!$config->canUserSaisieDemande() || $config->canResponsableSaisieMission() ) {
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

            if (!empty($_SESSION["tab_j_fermeture"]) && is_array($_SESSION["tab_j_fermeture"])) {
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

            //affiche le formulaire de saisie d'une nouvelle demande de conges ou d'un  nouveau conges
            $onglet = "traite_user";
            $return .= saisie_nouveau_conges2($user_login, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $onglet);

            $return .= '<hr/>';
        }

        /*********************/
        /* Etat des Demandes */
        /*********************/
        if ($config->canUserSaisieDemande()) {
            //verif si le user est bien un user du resp (et pas seulement du grand resp)
            if (strstr($list_all_users_du_resp, "'$user_login'")!=false) {
                $return .= '<h2>' . _('resp_traite_user_etat_demandes') . '</h2>';

                //affiche l'état des demandes du user (avec le formulaire pour le responsable)
                $return .= \responsable\Fonctions::affiche_etat_demande_user_for_resp($user_login, $tab_user, $tab_grd_resp);

                $return .= '<hr/>';
            }
        }

        /*********************/
        /* Etat des Demandes en attente de 2ieme validation */
        /*********************/
        /*******************************/
        /* verif si le resp est grand_responsable pour ce user*/

        if (in_array($_SESSION['userlogin'], $tab_grd_resp)) // si resp_login est dans le tableau
        {
            $return .= '<h2>' . _('resp_traite_user_etat_demandes_2_valid') . '</h2>';

            //affiche l'état des demande en attente de 2ieme valid du user (avec le formulaire pour le responsable)
            $return .= \responsable\Fonctions::affiche_etat_demande_2_valid_user_for_resp($user_login);

            $return .= '<hr/>';
        }

        /*******************/
        /* Etat des Conges */
        /*******************/
        //affiche l'état des conges du user (avec le formulaire pour le responsable)
        $onglet = "traite_user";
        $return .= \responsable\Fonctions::affiche_etat_conges_user_for_resp($user_login,  $year_affichage, $tri_date, $onglet);
        return $return;
    }

    /**
     * Encapsule le comportement du module de gestion des congés des utilisateurs
     *
     * @return string
     * @static
     */
    public static function traiteUserModule()
    {
        $config = new \App\Libraries\Configuration(\includes\SQL::singleton());
        $entities = function ($element) {
            return htmlentities($element, ENT_QUOTES | ENT_HTML401);
        };
        $userLogin = $entities(getpost_variable('user_login'));

        if (!(\App\ProtoControllers\Responsable::isRespDeUtilisateur($_SESSION['userlogin'], $userLogin)
                || \App\ProtoControllers\Responsable::isGrandRespDeUtilisateur($_SESSION['userlogin'])))  {
            redirect(ROOT_PATH . 'deconnexion');
            exit;
        }

        $return = self::traiterUserConge($userLogin);
        if ($config->isHeuresAutorise()) {
            $return .= '<hr  />';

            $return .= self::traiteUserHeureAdditionnelle($userLogin);
            $return .= '<hr />';
            $return .= self::traiteUserHeureRepos($userLogin);
        }
        return $return;
    }

    /**
     * Traite les congés de l'utilisateur
     *
     * @param string $user_login
     *
     * @return string
     */
    private static function traiterUserConge($user_login)
    {
        $entities = function ($element) {
            return htmlentities($element, ENT_QUOTES | ENT_HTML401);
        };
        $year_affichage = (int) getpost_variable('year_affichage', date("Y"));

        $year_calendrier_saisie_debut = $entities(getpost_variable('year_calendrier_saisie_debut', 0));
        $mois_calendrier_saisie_debut = $entities(getpost_variable('mois_calendrier_saisie_debut', 0));
        $year_calendrier_saisie_fin = getpost_variable('year_calendrier_saisie_fin', 0);
        $mois_calendrier_saisie_fin = $entities(getpost_variable('mois_calendrier_saisie_fin', 0));
        $tri_date = $entities(getpost_variable('tri_date', "ascendant"));
        $tab_checkbox_annule = array_map($entities, getpost_variable('tab_checkbox_annule', []));
        $tab_radio_traite_demande = array_map($entities, getpost_variable('tab_radio_traite_demande', []));
        $tab_text_refus = array_map($entities, getpost_variable('tab_text_refus', []));
        $tab_text_annul = array_map($entities, (array) getpost_variable('tab_text_annul', []));
        $new_demande_conges = getpost_variable('new_demande_conges', 0);
        $new_debut = $entities(getpost_variable('new_debut'));
        $new_demi_jour_deb = $entities(getpost_variable('new_demi_jour_deb'));
        $new_fin = $entities(getpost_variable('new_fin'));
        $new_demi_jour_fin = $entities(getpost_variable('new_demi_jour_fin'));
        $new_comment = $entities(getpost_variable('new_comment'));
        $new_type = $entities(getpost_variable('new_type'));

        $return = '';

        $new_nb_jours = compter($user_login, '', $new_debut,  $new_fin, $new_demi_jour_deb, $new_demi_jour_fin, $new_comment);

        /************************************/

        // si une annulation de conges a été selectionée :
        if (!empty($tab_checkbox_annule)) {
            $return .= \responsable\Fonctions::annule_conges($user_login, $tab_checkbox_annule, $tab_text_annul);
        }
        // si le traitement des demandes a été selectionée :
        elseif (!empty($tab_radio_traite_demande)) {
            $return .= \responsable\Fonctions::traite_demandes($user_login, $tab_radio_traite_demande, $tab_text_refus);
        }
        // si un nouveau conges ou absence a été saisi pour un user :
        elseif ($new_demande_conges==1) {
            $return .= \responsable\Fonctions::new_conges($user_login, $new_debut, $new_demi_jour_deb, $new_fin, $new_demi_jour_fin, $new_nb_jours, $new_comment, $new_type);
        } else {
            $return .= \responsable\Fonctions::affichage($user_login,  $year_affichage, $year_calendrier_saisie_debut, $mois_calendrier_saisie_debut, $year_calendrier_saisie_fin, $mois_calendrier_saisie_fin, $tri_date);
        }

        return $return;
    }

    /**
     * Traite les heures additionnelles de l'utilisateur
     *
     * @param string $userLogin
     *
     * @return string
     */
    private static function traiteUserHeureAdditionnelle($userLogin)
    {
        $year = (int) getpost_variable('year_affichage_heure_additionnelle', date("Y"));
        $entities = function ($element) {
            return htmlentities($element, ENT_QUOTES | ENT_HTML401);
        };
        $commentairesAnnulation = array_map($entities, getpost_variable('commentaireAnnulationAdditionnelle', []));
        $annulations = getpost_variable('annulationAdditionnelle', []);
        $idsAnnules = array_map(
            function ($idHeure) {
                return (int) $idHeure;
            }, array_keys($annulations)
        );

        if (!empty($annulations)) {
            $aAnnuler = [];
            foreach ($idsAnnules as $idAnnule) {
                $commentaire = isset($commentairesAnnulation[$idAnnule])
                    ? $commentairesAnnulation[$idAnnule]
                    : '';
                $aAnnuler[] = [
                    'id' => $idAnnule,
                    'commentaire' => $commentaire,
                ];
            }
            self::annulerHeureAdditionnelle($userLogin, $aAnnuler);
        }

        return self::getFormulaireAnnulationHeureAdditionnelle($userLogin, $year);
    }

    private static function annulerHeureAdditionnelle($userLogin, array $annulations)
    {
        $sql = \includes\SQL::singleton();
        $pdo = $sql->getPdoObj();
        $pdo->begin_transaction();
        $transactionACommiter = true;

        foreach ($annulations as $annulation) {
            $id = (int) $annulation['id'];
            $reqAnnulation = 'UPDATE heure_additionnelle
                SET statut = ' . \App\Models\AHeure::STATUT_ANNUL . ', comment_refus = "' . $sql->quote($annulation['commentaire']) . '"
                WHERE id_heure = ' . $id;

            $reqSoustraction = 'UPDATE conges_users
                SET u_heure_solde = u_heure_solde -
                    (SELECT duree FROM heure_additionnelle WHERE id_heure = ' . $id . ')
                WHERE u_login = "' . $userLogin . '"';
            $transactionACommiter = $sql->query($reqAnnulation)
                && $sql->query($reqSoustraction)
                && $transactionACommiter;
        }

        if ($transactionACommiter) {
            $pdo->commit();
            return;
        }
        $pdo->rollback();
        return;
    }

    /**
     * Formulaire d'affichage des heures additionelles à manipuler
     *
     * @param string $userLogin
     * @param int $year
     *
     * @return string
     */
    private static function getFormulaireAnnulationHeureAdditionnelle($userLogin, $year)
    {
        $yearPrec = $year - 1;
        $yearSucc = $year + 1;
        $db = \includes\SQL::singleton();
        $url = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        $return .= '<div class="calendar-nav">';
        $return .= '<ul>';
        $return .= '<li><a class="action previous" href="' . $url . '?onglet=traite_user&user_login=' . $userLogin . '&year_affichage_heure_additionnelle=' . $yearPrec . '"><i class="fa fa-chevron-left"></i></a></li>';
        $return .= '<li class="current-year">' . $year . '</li>';
        $return .= '<li><a class="action next" href="' . $url . '?onglet=traite_user&user_login=' . $userLogin . '&year_affichage_heure_additionnelle=' . $yearSucc . '"><i class="fa fa-chevron-right"></i></a></li>';
        $return .= '</ul>';
        $return .= '</div>';
        $return .= '<h2>' . _('resp_traite_user_etat_heures_additionnelles') . ' ' . $year . '</h2>';

        /* Récupération des heures éligibles */
        $additionnelles = new \App\ProtoControllers\Employe\Heure\Additionnelle();
        $debutTime = mktime(0, 0, 0, 1, 1, $year);
        $finTime = mktime(0, 0, 0, 1, 1, $yearSucc);
        $params = [
            'login' => $userLogin,
            'timestampDebut' => $debutTime,
            'timestampFin' => $finTime,
            'statut' => [\App\Models\AHeure::STATUT_VALIDATION_FINALE, \App\Models\AHeure::STATUT_ANNUL],
        ];
        $heuresIds = $additionnelles->getListeId($params);

        $return .= '<form action="" method="POST">';
        $return .= '<table class="table table-hover table-responsive table-condensed table-striped">';
        $return .= '<thead>';
        $return .= '<tr align="center">';
        $return .= '<th>' . _('jour') . '</th>';
        $return .= '<th>' . _('divers_debut_maj_1') . '</th>';
        $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
        $return .= '<th>' . _('duree') . '</th>';
        $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
        $return .= '<th>' . _('divers_etat_maj_1') . '</th>';
        $return .= '<th>' . _('resp_traite_user_annul') . '</th>';
        $return .= '<th>' . _('resp_traite_user_motif_annul') . '</th>';

        $return .= '</tr>';
        $return .= '</thead>';
        $return .= '<tbody>';
        if (empty($heuresIds)) {
            $return .= '<td colspan=9><center><b>' . _('resp_traite_user_aucune_heure_additionnelle') . '</b></center></td>';
        } else {
            $heures = $additionnelles->getListeSQL($heuresIds);
            $positionElement = 1;
            foreach ($heures as $heure) {
                $idHeure = (int) $heure['id_heure'];
                $jour   = date('d/m/Y', $heure['debut']);
                $debut  = date('H\:i', $heure['debut']);
                $fin    = date('H\:i', $heure['fin']);
                $duree  = \App\Helpers\Formatter::timestamp2Duree($heure['duree']);
                $statut = \App\Models\AHeure::statusText($heure['statut']);
                $comment = $db->quote($heure['comment']);
                $commentaireRefus = $db->quote($heure['comment_refus']);
                if (empty($commentaireRefus)) {
                    $commentaireRefus = _('divers_inconnu');
                }

                switch ($heure['statut']) {
                    case \App\Models\AHeure::STATUT_ANNUL:
                    case \App\Models\AHeure::STATUT_REFUS:
                        $annulation = '';
                        $commentaireAnnulation = '<i>' . _('resp_traite_user_motif') . ' : ' . $commentaireRefus . '</i>';
                        break;
                    default:
                        $annulation = '<input type="checkbox" name="annulationAdditionnelle[' . $idHeure . ']" />';
                        $commentaireAnnulation = '<input type="text" name="commentaireAnnulationAdditionnelle[' . $idHeure . ']" size="20" max="100"/>';
                        break;
                }
                $return .= '<tr class="' . (($positionElement % 2 == 0) ? 'i' : 'p') . '">';
                $return .= '<td>' . $jour . '</td>';
                $return .= '<td>' . $debut . '</td>';
                $return .= '<td>' . $fin . '</td>';
                $return .= '<td>' . $duree . '</td>';
                $return .= '<td>' . $comment . '</td>';
                $return .= '<td>' . $statut . '</td>';
                $return .= '<td>' . $annulation . '</td>';
                $return .= '<td>' . $commentaireAnnulation . '</td>';
                $return .= '</tr>';
                ++$positionElement;
            }
        }
        $return .= '</tbody>';
        $return .= '</table>';
        $return .= '<input type="hidden" name="userLogin" value="' . $userLogin . '">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
        $return .= '</form>';

        return $return;
    }

    /**
     * Traite les heures de repos de l'utilisateur
     *
     * @param string $userLogin
     *
     * @return string
     */
    private static function traiteUserHeureRepos($userLogin)
    {
        $year = (int) getpost_variable('year_affichage_heure_repos', date("Y"));
        $entities = function ($element) {
            return htmlentities($element, ENT_QUOTES | ENT_HTML401);
        };
        $commentairesAnnulation = array_map($entities, getpost_variable('commentaireAnnulationRepos', []));
        $annulations = getpost_variable('annulationRepos', []);
        $idsAnnules = array_map(
            function ($idHeure) {
                return (int) $idHeure;
            }, array_keys($annulations)
        );

        if (!empty($annulations)) {
            $aAnnuler = [];
            foreach ($idsAnnules as $idAnnule) {
                $commentaire = isset($commentairesAnnulation[$idAnnule])
                    ? $commentairesAnnulation[$idAnnule]
                    : '';
                $aAnnuler[] = [
                    'id' => $idAnnule,
                    'commentaire' => $commentaire,
                ];
            }
            self::annulerHeureRepos($userLogin, $aAnnuler);
        }

        return self::getFormulaireAnnulationHeureRepos($userLogin, $year);
    }

    private static function annulerHeureRepos($userLogin, array $annulations)
    {
        $sql = \includes\SQL::singleton();
        $pdo = $sql->getPdoObj();
        $pdo->begin_transaction();
        $transactionACommiter = true;

        foreach ($annulations as $annulation) {
            $id = (int) $annulation['id'];
            $reqAnnulation = 'UPDATE heure_repos
                SET statut = ' . \App\Models\AHeure::STATUT_ANNUL . ', comment_refus = "' . $sql->quote($annulation['commentaire']) . '"
                WHERE id_heure = ' . $id;

            $reqAjout = 'UPDATE conges_users
                SET u_heure_solde = u_heure_solde +
                    (SELECT duree FROM heure_repos WHERE id_heure = ' . $id . ')
                WHERE u_login = "' . $userLogin . '"';
            $transactionACommiter = $sql->query($reqAnnulation)
                && $sql->query($reqAjout)
                && $transactionACommiter;
        }

        if ($transactionACommiter) {
            $pdo->commit();
            return;
        }
        $pdo->rollback();
        return;
    }

    /**
     * Formulaire d'affichage des heures de repos à manipuler
     *
     * @param string $userLogin
     * @param int $year
     *
     * @return string
     */
    private static function getFormulaireAnnulationHeureRepos($userLogin, $year)
    {
        $yearPrec = $year - 1;
        $yearSucc = $year + 1;
        $db = \includes\SQL::singleton();
        $url = filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
        $return = '';

        $return .= '<div class="calendar-nav">';
        $return .= '<ul>';
        $return .= '<li><a class="action previous" href="' . $url . '?onglet=traite_user&user_login=' . $userLogin . '&year_affichage_heure_repos=' . $yearPrec . '"><i class="fa fa-chevron-left"></i></a></li>';
        $return .= '<li class="current-year">' . $year . '</li>';
        $return .= '<li><a class="action next" href="' . $url . '?onglet=traite_user&user_login=' . $userLogin . '&year_affichage_heure_repos=' . $yearSucc . '"><i class="fa fa-chevron-right"></i></a></li>';
        $return .= '</ul>';
        $return .= '</div>';
        $return .= '<h2>' . _('resp_traite_user_etat_heures_repos') . ' ' . $year . '</h2>';

        /* Récupération des heures éligibles */
        $repos = new \App\ProtoControllers\Employe\Heure\Repos();
        $debutTime = mktime(0, 0, 0, 1, 1, $year);
        $finTime = mktime(0, 0, 0, 1, 1, $yearSucc);
        $params = [
            'login' => $userLogin,
            'timestampDebut' => $debutTime,
            'timestampFin' => $finTime,
            'statut' => [\App\Models\AHeure::STATUT_VALIDATION_FINALE, \App\Models\AHeure::STATUT_ANNUL],
        ];
        $heuresIds = $repos->getListeId($params);

        $return .= '<form action="" method="POST">';
        $return .= '<table class="table table-hover table-responsive table-condensed table-striped">';
        $return .= '<thead>';
        $return .= '<tr align="center">';
        $return .= '<th>' . _('jour') . '</th>';
        $return .= '<th>' . _('divers_debut_maj_1') . '</th>';
        $return .= '<th>' . _('divers_fin_maj_1') . '</th>';
        $return .= '<th>' . _('duree') . '</th>';
        $return .= '<th>' . _('divers_comment_maj_1') . '</th>';
        $return .= '<th>' . _('divers_etat_maj_1') . '</th>';
        $return .= '<th>' . _('resp_traite_user_annul') . '</th>';
        $return .= '<th>' . _('resp_traite_user_motif_annul') . '</th>';
        $return .= '</tr>';
        $return .= '</thead>';
        $return .= '<tbody>';
        if (empty($heuresIds)) {
            $return .= '<td colspan=9><center><b>' . _('resp_traite_user_aucune_heure_repos') . '</b></center></td>';
        } else {
            $heures = $repos->getListeSQL($heuresIds);
            $positionElement = 1;
            foreach ($heures as $heure) {
                $idHeure = (int) $heure['id_heure'];
                $jour   = date('d/m/Y', $heure['debut']);
                $debut  = date('H\:i', $heure['debut']);
                $fin    = date('H\:i', $heure['fin']);
                $duree  = \App\Helpers\Formatter::timestamp2Duree($heure['duree']);
                $statut = \App\Models\AHeure::statusText($heure['statut']);
                $comment = $db->quote($heure['comment']);
                $commentaireRefus = $db->quote($heure['comment_refus']);
                if (empty($commentaireRefus)) {
                    $commentaireRefus = _('divers_inconnu');
                }

                switch ($heure['statut']) {
                    case \App\Models\AHeure::STATUT_ANNUL:
                    case \App\Models\AHeure::STATUT_REFUS:
                        $annulation = '';
                        $commentaireAnnulation = '<i>' . _('resp_traite_user_motif') . ' : ' . $commentaireRefus . '</i>';
                        break;
                    default:
                        $annulation = '<input type="checkbox" name="annulationRepos[' . $idHeure . ']" />';
                        $commentaireAnnulation = '<input type="text" name="commentaireAnnulationRepos[' . $idHeure . ']" size="20" max="100"/>';
                        break;
                }
                $return .= '<tr class="' . (($positionElement % 2 == 0) ? 'i' : 'p') . '">';
                $return .= '<td>' . $jour . '</td>';
                $return .= '<td>' . $debut . '</td>';
                $return .= '<td>' . $fin . '</td>';
                $return .= '<td>' . $duree . '</td>';
                $return .= '<td>' . $comment . '</td>';
                $return .= '<td>' . $statut . '</td>';
                $return .= '<td>' . $annulation . '</td>';
                $return .= '<td>' . $commentaireAnnulation . '</td>';
                $return .= '</tr>';
                ++$positionElement;
            }
        }

        $return .= '</tbody>';
        $return .= '</table>';
        $return .= '<input type="hidden" name="userLogin" value="' . $userLogin . '">';
        $return .= '<input class="btn btn-success" type="submit" value="' . _('form_submit') . '">';
        $return .= '</form>';

        return $return;
    }
}
