<?php
namespace App\ProtoControllers\HautResponsable;


/**
 * ProtoContrôleur d'utilisateur, en attendant la migration vers le MVC REST
 *
 * @since  1.13
 * @author Prytoegrian <prytoegrian@protonmail.com>
 * @author Wouldsmina <wouldsmina@tuxfamily.org>
 */
class ClotureExercice
{
    public static function traitementClotureEmploye($employes, $typeConges, &$error, \includes\SQL $sql, \App\Libraries\Configuration $config)
    {
        $return = true;
        $exerciceGlobal = $_SESSION['config']['num_exercice'];
        $comment =  _('resp_cloture_exercice_commentaire') . " " . $exerciceGlobal;
        $sql->getPdoObj()->begin_transaction();

        foreach ($employes as $employe => $infosEmploye) {
            if ("Y" !== $infosEmploye['u_is_active']) {
                continue;
            }
            if ($infosEmploye['u_num_exercice'] < $exerciceGlobal) {
                $return = static::updateSoldesEmploye($employe, $typeConges, $comment, $sql, $config);
                if ($return) {
                    static::setNumExeEmploye($employe, $exerciceGlobal, $sql);
                }
            }
        }

        if (!$return) {
            $sql->getPdoObj()->rollback();
            $error = _('Une erreur inattendue s\'est produite durant le traitement de <b>' . $employe . "</b>. Cloture d'exercice annulée.");
            return $return;
        }
        $sql->getPdoObj()->commit();

        return $return;
    }

    private static function updateSoldesEmploye($employe, $typeConges, $comment, \includes\SQL $sql, \App\Libraries\Configuration $config)
    {
        $return = true;
        $soldesEmploye = \App\ProtoControllers\Utilisateur::getSoldesEmploye($sql, $config, $employe);
        foreach ($typeConges as $idType => $libelle) {
            $soldeRestant = $soldesEmploye[$idType]['su_solde'];
            $soldeFutur = $soldesEmploye[$idType]['su_nb_an'];
            // Si le solde est négatif, on le déduit du futur solde
            if (0 > $soldeRestant) {
                $soldeFutur = $soldeFutur + $soldeRestant;
            } elseif ($config->isReliquatsAutorise()) {
                if (!static::setReliquatEmploye($employe, $idType, $soldeRestant, $sql, $config)) {
                    $return = false;
                    break;
                }
            }
            if (!static::setSoldeEmploye($employe, $idType, $soldeFutur, $sql)) {
                $return = false;
                break;
            }
            $today = date("Y-m-d");
            insert_dans_periode($employe, $today, "am", $today, "am", $soldeFutur, $comment, $idType, "ajout", 0);
        }

        return $return;
    }

    private static function setSoldeEmploye($employe, $idType, $soldeFutur, \includes\SQL $sql)
    {
        $req = 'UPDATE conges_solde_user 
                  SET su_solde = ' . $soldeFutur .
                ' WHERE su_login = "' . $sql->quote($employe) .
                '" AND su_abs_id = ' . intval($idType) . ';';
        $sql->query($req);

        return true;
    }

    private static function setReliquatEmploye($employe, $idType, $soldeRestant, \includes\SQL $sql, \App\Libraries\Configuration $config)
    {
        $reliquatMax = $config->getReliquatsMax();
        if (0 !== $reliquatMax && $soldeRestant > $reliquatMax) {
            $soldeRestant = $reliquatMax;
        }
        $req = 'UPDATE conges_solde_user
                  SET su_reliquat = ' . $soldeRestant .
                ' WHERE su_login="' . $sql->quote($employe) .
                '" AND su_abs_id = ' . intval($idType) . ';';
        $sql->query($req);

        return true;
    }

    private static function setNumExeEmploye($employe, $numExercice, \includes\SQL $sql) {
        $req = 'UPDATE conges_users
                SET u_num_exercice = ' . $numExercice .
                ' WHERE u_login="' . $sql->quote($employe) . '";';
        $sql->query($req);

        return 0 < $sql->affected_rows;
    }

    public static function updateNumExerciceGlobal(\includes\SQL $sql)
    {
        $req = "UPDATE conges_appli
                SET appli_valeur = appli_valeur+1
                WHERE appli_variable='num_exercice';";
        $sql->query($req);

        $exeSuivant = $_SESSION['config']['num_exercice'] + 1;
        log_action(0, "", "", "fin/debut exercice (appli_num_exercice : " . $_SESSION['config']['num_exercice'] . " -> " . $exeSuivant . ")");

        return true;
    }

    public static function updateDateLimiteReliquats($annee, &$error, \includes\SQL $sql, \App\Libraries\Configuration $config)
    {
        $LimiteReliquats = $config->getDateLimiteReliquats();
        if (0 === $LimiteReliquats) {
            return true;
        }

        if (!preg_match('/^([0-9]{1,2})\-([0-9]{1,2})$/', $LimiteReliquats)) {
            $error = "Erreur de configuration du jour et mois de limite des reliquats.";
            return false;
        }

        $JourMois = explode("-", $LimiteReliquats);
        $dateLimite = $annee . "-" . $JourMois[1] . "-" . $JourMois[0];
        $req = 'UPDATE conges_appli
                       SET appli_valeur = \'' . $dateLimite . '\' 
                       WHERE appli_variable=\'date_limite_reliquats\';';
        $sql->query($req);

        return 0 < $sql->affected_rows;
    }

    public static function setJoursFeriesFrance($annee = null)
    {
        if (!isset($annee)) {
            $annee = date('Y') + 1;
        }
        $joursFeriesFrance = \hr\Fonctions::getJoursFeriesFrance($annee);
        \hr\Fonctions::supprimeFeriesAnnee($annee);
        if (!\hr\Fonctions::insereFeriesAnnee($joursFeriesFrance)) {
            return false;
        }

        log_action(0, "", "", "insertion automatique des jours chomés pour " . $annee);
        init_tab_jours_feries();

        return true;
    }
}
