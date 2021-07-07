<?php
defined('_PHP_CONGES') or die('Restricted access');
$conge = new \App\ProtoControllers\Employe\Conge();
$config = new \App\Libraries\Configuration(\includes\SQL::singleton());

/**
 * Y-a-t-il une recherche dans l'avion ?
 *
 * @param array $post
 *
 * @return bool
 */
function isSearch(array $post)
{
    return !empty($post['search']);
}

$champsRecherche = [];
$champsSql = [];
if (!empty($_POST) && isSearch($_POST)) {
    $champsRecherche = $_POST['search'];
    $champsSql = $conge->transformChampsRecherche($_POST);
}

// on initialise le tableau global des jours fériés s'il ne l'est pas déjà :
init_tab_jours_feries();

$canAskConge = $config->canUserSaisieDemande() || $config->canUserSaisieMission();
$titre = _('user_liste_conge_titre');
$titreTB = ('Récapitulatif');
$params = $champsSql + [
    'p_login' => $_SESSION['userlogin'],
]; // champs par défaut écrasés par posté

$listId = $conge->getListeId($params);
if (empty($listId)) {
    $listeConges = [];
} else {
    $i = true;
    $listeConges = $conge->getListeSQL($listId);
    $interdictionModification = $config->canUserModifieDemande();
    $affichageDateTraitement = $config->canAfficheDateTraitement();
    $dataConges = new \stdClass;
    $i = 0;
    foreach ($listeConges as $conges) {
        ++$i;
        $data = new \stdClass;
        $dataConges->$i = $data;
        $data->dateDebut = \App\Helpers\Formatter::dateIso2Fr($conges["p_date_deb"]);
        $data->periodeDebut = schars($conges["p_demi_jour_deb"] == "am" ? 'matin' : 'après-midi');
        $data->dateFin = \App\Helpers\Formatter::dateIso2Fr($conges["p_date_fin"]);
        $data->periodeFin = schars($conges["p_demi_jour_fin"] === "am" ? 'matin' : 'après-midi');
        $data->libelle = schars($conges["ta_libelle"]);
        $data->nbJours = affiche_decimal($conges["p_nb_jours"]);
        $data->statut = \App\Models\Conge::statusText($conges["p_etat"]);

        /** Dates demande / traitement */
        $dateDemande = '';
        $dateReponse = '';

        if ($affichageDateTraitement) {
            if (!empty($conges["p_date_demande"])) {
                list($date, $heure) = explode(' ', $conges["p_date_demande"]);
                $dateDemande = '(' . \App\Helpers\Formatter::dateIso2Fr($date) . ' ' . $heure . ') ';
            }
            if (null != $conges["p_date_traitement"]) {
                list($date, $heure) = explode(' ', $conges["p_date_traitement"]);
                $dateReponse = '(' . \App\Helpers\Formatter::dateIso2Fr($date) . ' ' . $heure . ') ';
            }
        }

        /** Messages complémentaires */
        if (!empty($conges["p_commentaire"])) {
            $messageDemande = '> Demande ' . $dateDemande . ":\n" . $conges["p_commentaire"];
        } else {
            $messageDemande = '';
        }
        if (!empty($conges["p_motif_refus"])) {
            $messageReponse = '> Réponse ' . $dateReponse . ":\n" . $conges["p_motif_refus"];
        } else {
            $messageReponse = '';
        }
        $data->messageDemande = $messageDemande;
        $data->messageReponse = $messageReponse;
        $data->isModifiable = $conges["p_etat"] == \App\Models\Conge::STATUT_DEMANDE && !$interdictionModification;
        $data->numConge = $conges['p_num'];
        $data->isSupprimable = $conges["p_etat"] == \App\Models\Conge::STATUT_DEMANDE;
    }
}

require_once VIEW_PATH . 'Employe/Conges/Liste.php';
