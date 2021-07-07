<?php declare(strict_types = 1);
/*
 * $canAskConge
 * $titre
 * $champsRecherche
 * $dataConges
 */
?>
<?php if ($canAskConge) : ?>
<a href="<?= ROOT_PATH ?>utilisateur/user_index.php?onglet=nouvelle_absence" style="float:right" class="btn btn-success"><?= _('divers_nouvelle_absence') ?></a>
<?php endif; ?>
<h1><?= $titreTB ?></h1>
<?php require_once VIEW_PATH . 'Employe/TableauBord.php'; ?>

<div class="liste-conge wrapper" id="main-content">
    <h1><?= $titre ?></h1>
    <form method="post" action="" class="form-inline search" role="form">
        <div class="form-group">
            <label class="control-label col-md-4" for="statut">Statut&nbsp;:</label>
            <div class="col-md-8">
                <select class="form-control" name="search[p_etat]" id="statut">
                    <option value="all"><?= _('tous') ?></option>
                    <?php foreach (\App\Models\Conge::getOptionsStatuts() as $key => $value) : ?>
                        <?php
                        $selected = (isset($champsRecherche['p_etat']) && $key == $champsRecherche['p_etat'])
                            ? 'selected="selected"'
                            : '';
                        ?>
                        <option value="<?= $key ?>" <?= $selected ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group ">
            <label class="control-label col-md-4" for="type">Type&nbsp;:</label>
            <div class="col-md-8">
                <select class="form-control" name="search[type]" id="type">
                    <option value="all"><?= _('tous') ?></option>
                    <?php foreach (\utilisateur\Fonctions::getOptionsTypeConges() as $key => $value) : ?>
                        <?php
                        $selected = (isset($champsRecherche['type']) && $key == $champsRecherche['type'])
                            ? 'selected="selected"'
                            : '';
                        ?>
                        <option value="<?= $key ?>" <?= $selected ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-md-4" for="annee">Année&nbsp;:</label>
            <div class="col-md-8">
                <select class="form-control" name="search[annee]" id="sel1">
                    <?php foreach (\utilisateur\Fonctions::getOptionsAnnees() as $key => $value) : ?>
                        <?php
                        $selected = (isset($champsRecherche['annee']) && $key == $champsRecherche['annee'])
                            ? 'selected="selected"'
                            : '';
                        ?>
                        <option value="<?= $key ?>" <?= $selected ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="input-group">
                <button type="submit" class="btn btn-default"><i class="fa fa-search" aria-hidden="true"></i></button>&nbsp;<a href="<?= ROOT_PATH ?>utilisateur/user_index.php?onglet=liste_conge" type="reset" class="btn btn-default">Reset</a>
            </div>
        </div>
    </form>
    <table class="table table-hover table-responsive table-condensed table-striped">
        <thead>
            <tr>
                <th><?= _('divers_debut_maj_1') ?></th>
                <th><?= _('divers_fin_maj_1') ?></th>
                <th><?= _('divers_type_maj_1') ?></th>
                <th><?= _('divers_nb_jours_pris_maj_1') ?></th>
                <th>Statut</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($listeConges)) : ?>
            <tr><td colspan="8"><center><?= _('aucun_resultat') ?></center></td></tr>
        <?php else : ?>
            <?php foreach ($dataConges as $conges) : ?>
            <tr>
                <td><?= $conges->dateDebut ?> <span class="demi"><?= $conges->periodeDebut ?></span></td>
                <td class="histo"><?= $conges->dateFin ?> <span class="demi"><?= $conges->periodeFin ?></span></td>
                <td class="histo"><?= $conges->libelle ?></td>
                <td class="histo"><?= $conges->nbJours ?></td>
                <td class="histo"><?= $conges->statut ?></td>
                <td class="histo"><i class="fa fa-comments" aria-hidden="true" title="<?= $conges->messageDemande ?><?= $conges->messageReponse ?>"></i></td>
                <td>
                <?php if ($conges->isModifiable) : ?>
                    <a href="user_index.php?p_num=<?= $conges->numConge ?>&amp;onglet=modif_demande"><i class="fa fa-pencil"></i></a>
                <?php else : ?>
                    <i class="fa fa-pencil disabled"></i>
                <?php endif; ?>
                <?php if ($conges->isSupprimable) : ?>
                    <a href="user_index.php?p_num=<?= $conges->numConge ?>&amp;onglet=suppr_demande"><i class="fa fa-times-circle"></i></a>
                <?php else : ?>
                    <i class="fa fa-times-circle disabled"></i>
                <?php endif; ?>
                </td>
            </tr>
            <?php endforeach ?>
        <?php endif ?>
        </tbody>
    </table>
</div>
