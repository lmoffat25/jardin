# Tests

- Le test d'abord, et son **premier rouge observé** : on note le message réel dans la demande de fusion, surtout s'il diffère de celui que prévoyait le plan.
- Un critère du §10 du CDC est couvert par un test qui porte le groupe `critere-NN` (`->group('critere-07')`) ; `make criteres` en fait le compte.
- Un attendu qui change ce que l'utilisateur voit ou fait n'est pas modifié par l'agent : c'est une question d'expérience utilisateur (`docs/plan/README.md` §3). Un attendu technique modifié se signale règle par règle dans la demande de fusion (RG-49).
- Le moteur (`src/Echeances/Moteur/`) ne dépend ni du framework ni de l'horloge : la date du jour lui est passée. `tests/Architecture/FrontieresTest.php` le vérifie.
- Les tests du moteur déclarent par `mutates(Classe::class)` la classe qu'ils gardent : `make mutation` ne mute que celles-là (C9). Le seuil ne fait que monter (C10).
- Aucune donnée réelle d'un jardin dans les tests ni les jeux de démonstration (RG-28).
- Un test qui lit une sortie se méfie du bruit : un avertissement PHP ou une couleur ANSI suffisent à le fausser.
