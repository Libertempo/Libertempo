<?php
define('ROOT_PATH', '');
require_once ROOT_PATH . 'define.php';

include_once ROOT_PATH .'fonctions_conges.php';
include_once INCLUDE_PATH .'fonction.php';
header_menu('', 'Libertempo : '._('calendrier_titre'));

$calendar = new \CalendR\Calendar();

// recuperer les donnes et les injecter dans la vue ! @Timn
//require_once VIEW_PATH . 'Calendrier.php';

echo (new \App\ProtoControllers\Calendrier())->get();

bottom();
