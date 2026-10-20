<?php

// V60 : une haie n'est jamais taillée en nidification, dans aucun climat
// (critère 4, RG-44). Chaque jour du 16/03 au 15/08, pour les cinq profils,
// la haie témoin (16/08 → 15/03, butoirs 16/08 et 15/03) n'est ni ouverte, ni
// optimale, ni urgente : qu'elle n'ait jamais été taillée, ou l'ait été au
// dernier jour permis.

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Etat;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Fenetre::class, Moteur::class);

uses()->group('critere-04');

it('ne propose jamais la haie du 16/03 au 15/08', function (int $annee, int $decalage) {
    $tailleeLe = Calendrier::date($annee, 3, 15);
    $historiques = [
        'jamais taillée' => Historique::vide(),
        'taillée le 15/03' => Historique::depuis([['plante_id' => 'plante-haie', 'type_id' => 1, 'fait_le' => $tailleeLe]]),
    ];
    $proposees = [];
    foreach ($historiques as $nom => $historique) {
        for ($jour = Calendrier::date($annee, 3, 16); $jour <= Calendrier::date($annee, 8, 15); $jour = $jour->modify('+1 day')) {
            foreach (Moteur::calculer([JeuTemoin::haie()], $historique, Decalage::jours($decalage), $jour) as $e) {
                /** @var Echeance $e */
                if (in_array($e->etat, [Etat::Ouverte, Etat::Optimale, Etat::Urgente], true)) {
                    $proposees[] = "{$nom}, le {$jour->format('d/m/Y')} : {$e->etat->value}";
                }
            }
        }
    }

    expect($proposees)->toBe([]);
})->with([2027, 2028])->with(JeuTemoin::PROFILS);
