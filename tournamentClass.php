<?php

// src/Service/bracketCalculator.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

use App\Service\matchBuilder;
use function Composer\Autoload\includeFile;

class bracketCalculator {

    private $tournamentCode;        // String de caractères d'identification du tournoi
    private $listPlayers;           // Array de tous les joueurs inscrits (Liste d'objets)
    private $bracketWinner;         // Array du tournoi généré (Winner bracket)
    private $bracketLoser;          // Array du tournoi généré (Loser  bracket)
    private $bracketFinal;          // Array du tournoi généré (Final  bracket)
    private $seedingRange;          // Nombre de personnes par panier (Arrondi au supérieur) | Exemple pour 8 joueurs & 3 paniers -> (1,2,3) / (4,5,6) / (7,8).
    private $nbMatchs;              // Nombre de matchs enregistrés depuis le début

    function __construct($tournamentCode = "XXXXXXXX", $listPlayers = array(), $bracketWinner = array(), $bracketLoser = array(), $bracketFinal = array(), $seedingRange = 0, $nbMatchs = 1) {
        $this->tournamentCode   = $tournamentCode;
        $this->listPlayers      = $listPlayers;
        $this->bracketWinner    = $bracketWinner;
        $this->bracketLoser     = $bracketLoser;
        $this->bracketFinal     = $bracketFinal;
        $this->seedingRange     = $seedingRange;
        $this->nbMatchs         = $nbMatchs;
    }
    function __destruct() {

    }
    // Getters & Setters
    public function getTournamentCode(): string
    {
        return $this->tournamentCode;
    }
    public function setTournamentCode($tournamentCode): void
    {
        $this->tournamentCode = $tournamentCode;
    }
    public function getListPlayers(): array{
        return $this->listPlayers;
    }
    public function setListPlayers(array $listPlayers): void {
        if (!empty($nbPlayers)) {
            $this->listPlayers = $listPlayers;
        } else {
            $this->listPlayers = $this->getListPlayers();
        }
    }
    public function getBracketWinner(): array
    {
        return $this->bracketWinner;
    }
    public function setBracketWinner(array $bracketWinner): void
    {
        $this->bracketWinner = $bracketWinner;
    }
    public function getBracketLoser(): array
    {
        return $this->bracketLoser;
    }
    public function setBracketLoser(array $bracketLoser): void
    {
        $this->bracketLoser = $bracketLoser;
    }
    public function getBracketFinal(): array
    {
        return $this->bracketFinal;
    }
    public function setBracketFinal(array $bracketFinal): void
    {
        $this->bracketFinal = $bracketFinal;
    }
    public function getSeedingRange(): int{
        return $this->seedingRange;
    }
    public function setSeedingRange(int $seedingRange): void {
        if (!empty($seedingRange)) {
            $this->seedingRange = $seedingRange;
        } else {
            $this->seedingRange = $this->getSeedingRange();
        }
    }
    public function getNbMatchs(): int
    {
        return $this->nbMatchs;
    }
    public function setNbMatchs(int $nbMatchs): void
    {
        $this->nbMatchs = $nbMatchs;
    }

    public function addPlayerInList($user): void
    {
        if (!empty($user)) {
            $listUsers = $this->getListPlayers();
            $listUsers[] = $user;
            $this->listPlayers = $listUsers;
        }
    }

    public function startTournament($styleTournament): void
    {
        $this->orderPlayersByElo();
        if ($this->getSeedingRange() > 0) {
            $this->shufflePlayersPaniers();
        }
        // Test de la demande | Single, Double Elimination
        switch ($styleTournament) {
            case 'single':
            case 'double':
                $this->buildTournamentTree($styleTournament);
                break;
            default:
        }
    }

    public function insertNewVersus($tree = "X"): string
    {
        $nbMatch = dechex($this->getNbMatchs());

        switch ($tree) {
            case "W":
            case "L":
                $code = $tree."_".$nbMatch;
                break;
            default:
                $code = "X"."_".$nbMatch;
        }

        $this->setNbMatchs($this->getNbMatchs()+1);
        return $code;

    }

    public function compareElo($a, $b): int
    {
        if ($a->getElo() == $b->getElo()) {
            return 0;
        }
        return ($a->getElo() > $b->getElo()) ? -1 : 1;
    }

    public function orderPlayersByElo(): void
    {
        // Tri par score Elo, si égalité -> joueur inscrit en premier.
        $allPlayers = $this->getListPlayers();
        usort($allPlayers, array($this, 'compareElo'));
        $this->listPlayers = $allPlayers;
    }

    public function setupPlayers(): void
    {
        // Récupérer tous les joueurs
        $allPlayers = $this->getListPlayers();
        // Structure du premier tour
        $startRound = $this->getBracketWinner()[0];
        // Attribution des places des joueurs en fonction du classement ELO pré-établit
        foreach ($startRound as $inputRound => $gameObject) {
            if (isset($allPlayers[$gameObject->getUserA()-1])) {
                $gameObject->setUserA($allPlayers[$gameObject->getUserA()-1]);
            }
            if (isset($allPlayers[$gameObject->getUserB()-1])) {
                $gameObject->setUserB($allPlayers[$gameObject->getUserB()-1]);
            }
        }
    }

    public function shufflePlayersPaniers()
    {
        $newList = array();
        // Mélange selon un nombre de paniers pour booster les joueurs bas ELO
        $allPlayers = $this->getListPlayers();
        $allPlayers = array_chunk($allPlayers, $this->getSeedingRange(),false);
        foreach ($allPlayers as $power => $players) {
            shuffle($players);
            foreach ($players as $oldCle => $dataUser) {
                $newList[] = $dataUser;
            }
        }
        $this->listPlayers = $newList;
    }

    public function buildTournamentTree($styleTournament)
    {

        // Récupérer tous les joueurs
        $allPlayers = $this->getListPlayers();
        // NB joueurs
        $nbPlayers = count($allPlayers);
        $logPower2Players = log($nbPlayers,2);
        // NB Rounds (Single Elimination Structure)
        $nbRoundsSingle = ceil($logPower2Players);
        // NB Rounds (Double Elimination Structure) - Info : On génère plus de rounds que nécessaire car les opérations mathématiques trouvées défaillent à de larges quantités de joueurs
        $nbRoundsDouble = $nbRoundsSingle*2;

        // Vérification que le nombre de joueurs est supérieur à 3 (Min en double élimination)
        if ($nbPlayers >= 3) {

            // Création du Winner Bracket (On le génère séparément sous la forme d'un arbre binaire pour le retourner ensuite)
            $games = $structure = array( array(1,2) );                                      // Point d'arrivée (2 joueurs) du tournoi, on va remonter l'arbre

            for ( $round=1; $round < $nbRoundsSingle; $round++)                             // Pour chaque round
            {
                $roundMatches = array();                                                    // - On nettoie le tableau de génération
                $sum = pow(2, $round + 1) + 1;                               // - On compte le nombre de matchs prévus durant ce tour (2^$round+1)+1
                foreach($games as $match)
                {                                                                               // Pour chaque match
                    $home = $this->ajouterUnExempt($match[0], $nbPlayers);                      // - On vérifie si le joueur A doit être exempté
                    $away = $this->ajouterUnExempt($sum - $match[0], $nbPlayers);         // - On ajoute le joueur mirror du seeding de A
                    $roundMatches[] = array($home, $away);                                      // - On compile le résultat
                    $home = $this->ajouterUnExempt($sum - $match[1], $nbPlayers);         // - On vérifie si le joueur B doit être exempté
                    $away = $this->ajouterUnExempt($match[1], $nbPlayers);                      // - On ajoute le joueur mirror du seeding de B
                    $roundMatches[] = array($home, $away);                                      // - On compile le résultat
                }
                $structure[] = $roundMatches;                                                   // On enregistre la structure du round actuel ($structure contient tous les rounds)
                $games = $roundMatches;                                                         // On reboucle pour le nombre de rounds prédéfinis
            }
            $structure = array_reverse($structure);                                             // On renverse la structure pour qu'on ait le format d'un tournoi (Quarts, Demi, Finale etc...)

            // Mise en place des matchs dans le winner bracket (Récupération de l'arbre et remplacement par nos matchs)
            $winnerBracket = $parent1 = $parent2 = $parentsA = $parentsB = array();
            $currentTournamentBuilderRound = 1;

            foreach ($structure as $generation => $encounters) {                                                                                                                // Pour chaque round qui a été généré
                $firstParent = 1;                                                                                                                                               // - Reset de la répartition des parents de matchs
                ($parent1 !== array() ) ? $parentsA = $parent1 : array();                                                                                                       // - Test si des parents de matchs 1 existent
                ($parent2 !== array() ) ? $parentsB = $parent2 : array();                                                                                                       // - Test si des parents de matchs 2 existent
                $parent1 = $parent2 = $buildStructure = array();                                                                                                                // - Nettoyage des données du round précedent
                if ($encounters !== end($structure)) {                                                                                                                   // - * Test si on est sur le round final
                    foreach ($encounters as $players => $seed) {                                                                                                                // - Pour chaque match du round
                        if ($currentTournamentBuilderRound === 1) {                                                                                                             // - - * Si c'est le premier round (pas de parents)
                            if ($seed[0] === null || $seed[1] === null) {                                                                                                       // - - - * Est-ce qu'un joueur est exempté ?
                                $skipPlayer = ($seed[0] !== null) ? $seed[0] : $seed[1];                                                                                        // - - - - On récupère le joueur exempté
                                $newMatchUp = new matchBuilder($seed[0],$seed[1],$this->insertNewVersus('W'),$currentTournamentBuilderRound, $allPlayers[$skipPlayer-1]);  // - - - - On crée un objet "Match" avec le joueur exempté comme gagnant
                            } else {                                                                                                                                            // - - - * Si un joueur n'est pas exempté
                                $newMatchUp = new matchBuilder($seed[0],$seed[1],$this->insertNewVersus('W'),$currentTournamentBuilderRound);                              // - - - - On crée un objet "Match"
                            }
                        } else {                                                                                                                                         // - - * Pour tous les rounds suivants (avec des parents)
                            if ($parentsA !== array() || $parentsB !== array()) {                                                                                        // - - - Sécurité pour ne pas générer de matchs avec des parents bugés
                                $newMatchUp = new matchBuilder(null,null,$this->insertNewVersus('W'),$currentTournamentBuilderRound,null,null,$parentsA[0],$parentsB[0]); // - - - On crée un match avec les parents des matchs précédents
                                array_shift($parentsA);                                                                                                           // - - - On retire le parent A enregistré
                                array_shift($parentsB);                                                                                                           // - - - On retire le parent B enregistré
                            }
                        }
                        if ($firstParent === 1) {                                                                                                                           // - - - * On reparti les matchs pour enregistrer les parents
                            $parent1[] = $newMatchUp->getKey();                                                                                                             // - - - -
                            $firstParent++;                                                                                                                                 // - - - - Un match part en parent 1
                        } else {                                                                                                                                            // - - - - Un match part en parent 2
                            $parent2[] = $newMatchUp->getKey();                                                                                                             // - - - -
                            $firstParent--;                                                                                                                                 // - - - - Ils sont enregistrés sur deux listes actualisées chaque round
                        }
                        $buildStructure[] = $newMatchUp;
                    }
                } else {                                                                                                                                                  // - * Si c'est le round final
                    $buildStructure[] = new matchBuilder(null,null,$this->insertNewVersus('W'),$currentTournamentBuilderRound,null,null,$parentsA[0],$parentsB[0]);
                }
                $winnerBracket[] = $buildStructure;                                                                                                                        // - On sauvegarde tous les matchs enregistrés ce round
                $currentTournamentBuilderRound++;                                                                                                                          // - On incrément pour calculer le round suivant
            }

            // Vérification des matchs exemptés et transfert vers les matchs suivants si il en existe
            foreach ($winnerBracket[0] as $id => $battle) {
                // Si il y a une exemption de match
                if ($battle->getUserA() === null || $battle->getUserB() === null) {
                    // On insert dans le match enfant suivant le gagnant
                    $battle->setEndedAt(time());
                    $newPositionRound2 = intval(floor($id/2));
                    $childBattle = $winnerBracket[1][$newPositionRound2];
                    // On vérifie bien que le parent correspond
                    if ( $childBattle->getParent1() === $battle->getKey() || $childBattle->getParent2() === $battle->getKey()) {
                        if ($childBattle->getUserA() === null) {
                            $childBattle->setUserA($battle->getWinPlayer());
                        } elseif ($childBattle->getUserB() === null) {
                            $childBattle->setUserB($battle->getWinPlayer());
                        }
                    }
                }
            }

            // Validation du Winner Bracket et Génération des joueurs dans le tableau
            $this->setBracketWinner($winnerBracket);
            $this->setupPlayers();

            // Test de la demande | Single, Double Elimination
            if ($styleTournament === 'single') { $this->generateFinal($styleTournament,$nbRoundsSingle); return 'done'; }

            // Génération du loser bracket selon les paramètres du winner bracket
            $loserBracket = array();

            // Construction des rounds au travers de la compilation des matchs
            for ( $round=1; $round <= $nbRoundsDouble; $round++)
            {
                // Pour chaque round, combiner les différents match en un round de loser bracket
                $loserBracket[] = $this->combineGamesForLoserBracket($round, $winnerBracket, $loserBracket);
            }

            // Nettoyage des tours inutiles du loser bracket
            foreach ($loserBracket as $round => $battles) {
                if ($battles === array()) {
                    unset($loserBracket[$round]);
                }
            }
            $loserBracket = array_values($loserBracket);

            // Mise en place du round final
            $this->setBracketLoser($loserBracket);
            $this->generateFinal($styleTournament,$nbRoundsDouble);
            return 'done';

        } else {

            return "Nope";

        }

    }

    public function checkAllParentsGame($match = array(), $location = 'winner'): bool
    {
        $countPlayers = 0;
        if ($match !== array()) {
            switch ($location) {
                case 'winner':
                    $listGames = $this->getBracketWinner();
                    break;
                case 'loser':
                    $listGames = $this->getBracketLoser();
                    break;
                default:
                    $listGames = array();
            }
            foreach ($listGames as $key => $round) {
                foreach ($round as $id => $battle) {
                    if ($match->getParent1() === $battle->getKey() || $match->getParent2() === $battle->getKey()) {
                        ($battle->getUserA() !== null) ? $countPlayers++ : null;
                        ($battle->getUserB() !== null) ? $countPlayers++ : null;
                    }
                }
            }
        }
        if ($countPlayers === 4) { return true; } else { return false; }
    }

    public function getParentGameUser($match = array(), $location = 'winner', $parent = '1'): string
    {
        if ($match !== array()) {
            switch ($location) {
                case 'winner':
                    $listGames = $this->getBracketWinner();
                    break;
                case 'loser':
                    $listGames = $this->getBracketLoser();
                    break;
                default:
                    $listGames = array();
            }
            foreach ($listGames as $key => $round) {
                foreach ($round as $id => $battle) {
                    switch ($parent) {
                        case '1':
                            if ($match->getParent1() === $battle->getKey()) {
                                $user = $battle->getUserA();
                            }
                            break;
                        case '2':
                            if ($match->getParent2() === $battle->getKey()) {
                                $user = $battle->getUserB();
                            }
                            break;
                        default:
                            if ($match->getParent1() === $battle->getKey() || $match->getParent2() === $battle->getKey()) {
                                $user = $battle->getKey();
                            }
                    }
                }
            }
        }
        if (isset($user)) { return $user; } else { return ''; }
    }

    public function combineGamesForLoserBracket($currentRound, $winnerBracket, $currentLoserBracket): array
    {

        // Initialisation du calcul pour le round
        $loserBracketRoundBuilder = $placeholderGamesBracketRoundBuilder = array();
        $countNbPlayers = count($this->getListPlayers());

        // On garde en mémoire les 3 derniers matchs du winner bracket si jamais on a atteint le bout du tournoi
        $fallback = $flagRemonteDansLeTemps = 0;
        while ( isset($winnerBracket[$currentRound-$fallback]) === false )  {
            $fallback++;
        }
        ($fallback > 0) ? $fallback-- : null;

        ( isset($winnerBracket[($currentRound-$fallback-2)]))       ? $lastTxWinner         = $winnerBracket[($currentRound-$fallback-2)]       : $lastTxWinner     = array();
        ( isset($winnerBracket[($currentRound-$fallback-1)]))       ? $currentTxWinner      = $winnerBracket[($currentRound-$fallback-1)]       : $currentTxWinner  = array();
        ( isset($winnerBracket[($currentRound-$fallback)]))         ? $nextTxWinner         = $winnerBracket[($currentRound-$fallback)]         : $nextTxWinner     = array();
        $lastTxLoser = end($currentLoserBracket);

        // On commence à compiler les objets
        switch ($currentRound) {
            case "1":
                // Règles particulières pour le round 1 (Exemptions)
                if ($this->testIfCountPlayersIsClean($countNbPlayers))
                {
                    // Si c'est une puissance de deux, on fait le traitement sur les matchs du tour en cours
                    foreach ($currentTxWinner as $key => $match) {
                        // On ne fait donc le traitement que sur la moitié des matchs (mais on test bien tout les matchs)
                        if ($key % 2 === 0) {
                            // On vérifie si il y a une exemption
                            if ( ($match->getUserA() !== null && $match->getUserB() !== null) && ($currentTxWinner[$key+1]->getUserA() !== null && $currentTxWinner[$key+1]->getUserB() !== null)) {
                                $loserBracketRoundBuilder[] = new matchBuilder(null,null,$this->insertNewVersus('L'),$currentRound,null,null,$match->getKey(),$currentTxWinner[$key+1]->getKey());
                            }
                        }
                    }
                }
                else
                {
                    // Sinon, on fait le traitement sur les enfants des matchs du tour en cours
                    foreach ($nextTxWinner as $key => $match) {
                        if ($this->testIfParentIsPlayed($match)) {
                            $loserBracketRoundBuilder[] = new matchBuilder(null,null,$this->insertNewVersus('L'),$currentRound,null,null,$match->getParent1(),$match->getParent2());
                        }
                    }
                }
                break;
            default:
                // Calculs des matchs selon les règles pairs et impaires
                if ($currentRound % 2 === 0)
                {   // Round pair (Matchs Winners)
                    // Système calcul classique
                    if ( $this->testIfCountPlayersIsClean($countNbPlayers) || $currentRound !== 2) {
                        if ( (count($currentTxWinner) === 1) && (count($lastTxLoser) === 1) ) {
                            $loserBracketRoundBuilder = $this->combineArraysOfGames($currentRound,$currentTxWinner,$lastTxLoser,true,true);
                        } else {
                            $loserBracketRoundBuilder = $this->combineArraysOfGames($currentRound,$this->getLatestWinnerRoundWithSameValueAsLoser($lastTxLoser),$lastTxLoser,true,true);
                        }
                    } else {
                        // Intégration des exemptions dans le round 2 du loser bracket
                        $saveStateCurrentTxWinner = $currentTxWinner;
                        // Pour chaque match de ce tour
                        foreach (array_reverse($currentTxWinner) as $key => $match) {
                            // Si ses parents n'ont pas étés joués
                            if ( !$this->testIfParentIsPlayed($match) ) {
                                // On compile le parent non joué avec le 1er match du round actuel
                                $placeholderGamesBracketRoundBuilder[] = new matchBuilder(null,null,$this->insertNewVersus('L'),$currentRound,null,null,$this->getGameExemptedFromChild($match)->getKey(),$saveStateCurrentTxWinner[0]->getKey());
                                array_splice($saveStateCurrentTxWinner,0,1);
                            }
                        }
                        // On combien ensuire les matchs du Loser Round avec le Current Round
                        $placeholderCombinedGames = $this->combineArraysOfGames($currentRound,$saveStateCurrentTxWinner,$lastTxLoser,false,false);
                        $loserBracketRoundBuilder = array_merge($placeholderGamesBracketRoundBuilder,$placeholderCombinedGames);
                    }
                }
                else
                {   // Round impair (Matchs Losers)
                    $loserBracketRoundBuilder = $this->combineGamesFromAnArray($currentRound,$lastTxLoser,true);
                }
        }

        return $loserBracketRoundBuilder;
    }

    public function combineArraysOfGames($round = 0, $arrayA = array(), $arrayB = array(), $requestReverseA = false, $requestReverseB = false): array
    {
        // Initialisation des valeurs
        $returnCompiledGames = array();
        ($requestReverseA) ? $arrayA = array_reverse($arrayA) : null;
        ($requestReverseB) ? $arrayB = array_reverse($arrayB) : null;

        // Si les tableaux ont la même quantité de valeurs
        if ( count($arrayA) === count($arrayB) ) {
            foreach ($arrayA as $nb => $match) {
                $returnCompiledGames[] = new matchBuilder(null,null,$this->insertNewVersus('L'),$round,null,null,$match->getKey(),$arrayB[$nb]->getKey());
            }
        }

        return $returnCompiledGames;
    }

    public function combineGamesFromAnArray($round = 0, $libraryGames = array(), $requestFlip = false): array
    {
        // Initialisation des valeurs
        $returnCompiledGames = array();
        ($requestFlip) ? $libraryGames = array_reverse($libraryGames) : null;

        // Si on a un nombre pair de matchs
        if ( count($libraryGames) % 2 === 0 ) {
            foreach ($libraryGames as $nb => $match) {
                if ($nb % 2 === 0) {
                    $returnCompiledGames[] = new matchBuilder(null,null,$this->insertNewVersus('L'),$round,null,null,$match->getKey(),$libraryGames[$nb+1]->getKey());
                }
            }
        }

        return $returnCompiledGames;
    }

    public function getLatestWinnerRoundWithSameValueAsLoser($loserRound): array
    {
        // Initialisation des valeurs
        $nbGamesLoserRound = count($loserRound);
        $libraryGames = array_reverse($this->getBracketWinner());

        foreach ($libraryGames as $round => $games) {
            if (count($games) === $nbGamesLoserRound) {
                return $games;
            }
        }
        return array();
    }

    public function generateFinal($typeTournament,$nbRounds): bool
    {
        $libraryWinner = $this->getBracketWinner();
        $finalGameWinner = end($libraryWinner)[0];
        $libraryLoser = array_reverse($this->getBracketLoser());

        // Est-ce que le dernier tour a été utilisé pour compiler un match avec un winner bracket ? Oui alors on le prends en compte, sinon on ignore et prends celui d'avant
        foreach ($libraryLoser as $round => $pile) {
            $flagW = $flagL = false;
            // Récupération des tags de matchs
            if ( strpos($pile[0]->getParent1(), "W") !== false ) {
                $flagW = !$flagW;
            }
            if ( strpos($pile[0]->getParent1(), "L") !== false ) {
                $flagL = !$flagL;
            }
            if ( strpos($pile[0]->getParent2(), "W") !== false ) {
                $flagW = !$flagW;
            }
            if ( strpos($pile[0]->getParent2(), "L") !== false ) {
                $flagL = !$flagL;
            }

            if ( $flagW && $flagL && ($pile !== array()) ) {
                $finalGameLoser = $pile[0];
                break;
            }
        }

        switch ($typeTournament) {
            // Sélection du type de match final
            case 'single':
                $this->setBracketFinal($finalGameWinner);
                break;
            case 'double':
                $finalGame1 = new matchBuilder(null,null,$this->insertNewVersus('W'),$finalGameLoser->getRound()+1,null,null,$finalGameWinner->getKey(),$finalGameLoser->getKey());
                $finalGame2 = new matchBuilder(null,null,$this->insertNewVersus('W'),$finalGameLoser->getRound()+2,null,null,$finalGameWinner->getKey(),$finalGameLoser->getKey());
                $this->setBracketFinal(array($finalGame1,$finalGame2));
                break;
            default:
                return false;
        }
        return true;
    }

    public function testIfParentIsPlayed($match): bool
    {
        // Initialisation de variables
        $count = 0;
        $round0WinnerBracket = $this->getBracketWinner()[0];
        $parentTestA = $match->getParent1();
        $parentTestB = $match->getParent2();

        // Pour chaque match du round 0
        foreach ($round0WinnerBracket as $key => $winnerGame) {
            // On verifie si c'est bien un parent de notre match
            if ($winnerGame->getKey() === $parentTestA || $winnerGame->getKey() === $parentTestB) {
                // On vérifie si le match a bien pu être joué
                if ( ($winnerGame->getUserA() !== null) && ($winnerGame->getUserB() !== null)) {
                    // On incrément le count
                    $count++;
                }
            }
        }

        // Si le count correspond à 2, le match enfant a bien ses matchs parents jouables.
        ($count === 2) ? $flag = true : $flag = false;
        return $flag;
    }

    public function testIfCountPlayersIsClean($nbPlayers = 0): bool
    {
        // On vérifie juste qu'on a bien un entier supérieur à 0
        if ($nbPlayers < 0) {
            return 0;
        }
        // On divise par deux le nombre tant qu'on a pas atteint 1
        while ($nbPlayers !== 1)
        {
            // Si le reste est différent de 0, le nombre n'est pas une puissance de 2. Ce n'est donc pas un tournoi "clean"
            if ($nbPlayers % 2 !== 0) {
                return 0;
            }
            $nbPlayers = $nbPlayers / 2;
        }
        // Si le nombre après while est 1, le nombre est une puissance de 2. C'est donc un tournoi "clean"
        return 1;
    }

    public function getReverseFirstExemptedGameFromRound($library): object
    {
        // Initalisation des variables
        $gameLibraryParents = $library;

        // Pour chaque match
        foreach ($gameLibraryParents as $key => $parentGame) {
            // On vérifie si le match est un match exemptable
            if ( (($parentGame->getUserA() === null) || ($parentGame->getUserB() === null)) && !(($parentGame->getUserA() === null) && ($parentGame->getUserB() === null)) ) {
                // On récupère le match parent commun
                $keyGame = explode("_",$parentGame->getKey());
                $numGame = intval(end($keyGame));
                if ($numGame % 2 === 0) {
                    return $gameLibraryParents[$key+1];
                } else {
                    return $gameLibraryParents[$key-1];
                }
            }
        }
        // Si rien n'est trouvé, on renvoit le premier match de la librarie
        return $gameLibraryParents[0];
    }

    public function getGameExemptedFromChild($match): object
    {
        // Initalisation des variables
        $gameLibraryParents = $this->getBracketWinner()[0];

        foreach ($gameLibraryParents as $key => $libraryGame) {
            if ( $match->getParent1() === $libraryGame->getKey() ) {
                if ($libraryGame->getUserA() === null || $libraryGame->getUserB() === null) {
                    return $gameLibraryParents[$key+1];
                }
            }
            if ( $match->getParent2() === $libraryGame->getKey() ) {
                if ($libraryGame->getUserA() === null || $libraryGame->getUserB() === null) {
                    return $gameLibraryParents[$key-1];
                }
            }
        }
        return $gameLibraryParents[0];
    }

    /*
    public function getAllPlayedGamesFromRound0(): array
    {
        // Initalisation des variables
        $gameLibrary = $this->getBracketWinner()[0];
        $returnTable = array();

        // Pour chaque match
        foreach ($gameLibrary as $key => $r0Game) {
            if ($key % 2 === 0) {
                $sibling = 1;
            } else {
                $sibling = -1;
            }
            // On vérifie si le match est un match jouable ET qu'il n'est pas compilable avec un match parent
            if ( (($r0Game->getUserA() !== null) && ($r0Game->getUserB() !== null)) ) {
                if ( isset($gameLibrary[$key+$sibling]) && (($gameLibrary[$key+$sibling]->getUserA() === null) || ($gameLibrary[$key+$sibling]->getUserB() === null)) ) {
                    // On l'insert dans la table retour
                    $returnTable[] = $r0Game;
                } else {
                    $returnTable[] = array();
                }
            } else {
                $returnTable[] = array();
            }
        }

        return $returnTable;
    }
    */

    public function ajouterUnExempt($seed, $participantsCount)
    {
        return ($seed <= $participantsCount) ?  $seed : null;
    }

}
