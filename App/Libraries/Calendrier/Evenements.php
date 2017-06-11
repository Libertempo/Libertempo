<?php
namespace App\Libraries\Calendrier;

/**
 * Construction des événements du calendrier.
 *
 * @since 1.9
 * @author Prytoegrian <prytoegrian@protonmail.com>
 * @see Tests\Units\App\Libraries\Calendrier\Evenements
 */
class Evenements
{
    public function __construct(
        \App\Libraries\InjectableCreator $injectableCreator)
    {
        $this->injectableCreator = $injectableCreator;
    }

    private $injectableCreator;

    private $employesATrouver;

    private $evenements;

    /**
     * Recupère la liste ordonnée des événements des employés
     *
     * @param \DateTimeInterface $dateDebut
     * @param \DateTimeInterface $dateFin
     * @param array $employesATrouver Liste d'utilisateurs dont on veut voir les événements
     * @param bool $canVoirEnTransit Si l'utilisateur a la possiblité de voir les événements non encore validés
     */
    public function fetchEvenements(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin, array $employesATrouver, $canVoirEnTransit)
    {
        $canVoirEnTransit = (bool) $canVoirEnTransit;
        $this->employesATrouver = $employesATrouver;
        $this->fetchWeekends($dateDebut, $dateFin);
        $this->fetchFeries($dateDebut, $dateFin);
        $this->fetchFermeture($dateDebut, $dateFin);
        $this->fetchConges($dateDebut, $dateFin, $canVoirEnTransit);
        $this->fetchHeuresAdditionnelles($dateDebut, $dateFin);
        $this->fetchHeuresRepos($dateDebut, $dateFin);
    }

    /**
     * Recupère la liste ordonnée des weekend des employés
     */
    private function fetchWeekends(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin)
    {
        $weekend = $this->injectableCreator->get(Evenements\Weekend::class);
        $weekendsListe = $weekend->getListe($dateDebut, $dateFin);
        foreach ($weekendsListe as $date) {
            foreach ($this->employesATrouver as $employe) {
                $this->setEvenementDate($employe, $date, 'weekend', 'Week-end');
            }
        }
    }

    /**
     * Recupère la liste ordonnée des jours fériés des employés
     */
    private function fetchFeries(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin)
    {
        $feries = $this->injectableCreator->get(Evenements\Ferie::class);
        $feriesListe = $feries->getListe($dateDebut, $dateFin);
        foreach ($feriesListe as $date) {
            foreach ($this->employesATrouver as $employe) {
                $this->setEvenementDate($employe, $date, 'ferie', 'Jour férié');
            }
        }
    }

    private function fetchFermeture(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin)
    {
        $fermeture = $this->injectableCreator->get(Evenements\Fermeture::class);
        $fermetureListe = $fermeture->getListe($dateDebut, $dateFin, []);
        foreach ($fermetureListe as $date) {
            foreach ($this->employesATrouver as $employe) {
                $this->setEvenementDate($employe, $date, 'fermeture', 'Fermeture');
            }
        }
    }

    private function fetchConges(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin, $canVoirEnTransit)
    {
        $conge = $this->injectableCreator->get(Evenements\Conge::class);
        $congesListe = $conge->getListe($dateDebut, $dateFin, $this->employesATrouver, $canVoirEnTransit);
        foreach ($congesListe as $jour => $evenementsJour) {
            foreach ($evenementsJour as $evenement) {
                $suffixe = '*' !== $evenement['demiJournee']
                ? '_' . $evenement['demiJournee']
                : '_all';
                $this->setEvenementDate($evenement['employe'], $jour, 'conge' . $suffixe . ' conge_' . $evenement['statut'], 'Congé');
            }
        }
    }

    private function fetchHeuresAdditionnelles(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin)
    {
        $heure = $this->injectableCreator->get(Evenements\Heure\Additionnelle::class);
        $heureListe = $heure->getListe($dateDebut, $dateFin, $this->employesATrouver, false);
        foreach ($heureListe as $jour => $evenementsJour) {
            foreach ($evenementsJour as $evenement) {
                $this->setEvenementDate($evenement['employe'], $jour, 'heure_additionnelle_' . $evenement['statut'], 'Heure additionnelle');
            }
        }
    }

    private function fetchHeuresRepos(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin)
    {
        $heure = $this->injectableCreator->get(Evenements\Heure\Repos::class);
        $heureListe = $heure->getListe($dateDebut, $dateFin, $this->employesATrouver, false);
        foreach ($heureListe as $jour => $evenementsJour) {
            foreach ($evenementsJour as $evenement) {
                $this->setEvenementDate($evenement['employe'], $jour, 'heure_repos_' . $evenement['statut'], 'Heure de repos');
            }
        }
    }

    private function setEvenementDate($idEmploye, $date, $idEvenement, $labelEvenement)
    {
        if ($this->isEvenementWeekend($idEvenement)) {
            unset($this->evenements[$idEmploye]['dates'][$date]['evenements']);
        }
        if (!$this->isDayWeekend($idEmploye, $date)) {
            $this->evenements[$idEmploye]['dates'][$date]['evenements'][] = $idEvenement;
            $this->evenements[$idEmploye]['dates'][$date]['title'][] = $labelEvenement;
        }
    }

    private function isDayWeekend($idEmploye, $date)
    {
        if (!isset($this->evenements[$idEmploye]) || !isset($this->evenements[$idEmploye]['dates'][$date])) {
            return false;
        }

        return in_array('weekend', $this->evenements[$idEmploye]['dates'][$date]['evenements'], true);
    }

    private function isEvenementWeekend($nomEvenement)
    {
        return 'weekend' === $nomEvenement;
    }

    public function getEmployes()
    {
        return $this->employesATrouver;
    }

    /**
     * @TODO: utile ?
     */
    public function getEmploye($idEmploye)
    {
        $this->verificationExistenceEmploye($idEmploye);
    }

    public function getEvenementsDate($idEmploye, $date)
    {
        $this->verificationExistenceEmploye($idEmploye);
        if (!isset($this->evenements[$idEmploye]['dates'][$date])) {
            return [];
        }

        return $this->evenements[$idEmploye]['dates'][$date]['evenements'];
    }

    public function getTitleDate($idEmploye, $date)
    {
        $this->verificationExistenceEmploye($idEmploye);
        if (!isset($this->evenements[$idEmploye]['dates'][$date])) {
            return [];
        }

        return $this->evenements[$idEmploye]['dates'][$date]['title'];

    }

    private function verificationExistenceEmploye($idEmploye)
    {
        if (!isset($this->evenements[$idEmploye])) {
            throw new \DomainException('Employé inconnu');
        }
    }

    /**
     * @TODO : à but de test, à supprimer quand c'est terminé
     */
    public function getEvenements()
    {
        return $this->evenements;
    }
}
