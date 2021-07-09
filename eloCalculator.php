<?php

// src/Service/EloCalculator.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class EloCalculator
{
    // Variables
    private $delta;                                 // Différence d'ELO pour laquelle un joueur A à 10x plus de chances de gagner qu'un joueur B (De base : 400)
    private $factor;                                // Nombre de points maximum que peut gagner ou perdre un joueur après un match (De base : 32)
    private $winStatus;                             // Situation finale de la partie, 'p1win' | 'p2win' | 'nowin'

    // Builders
    function __construct($delta = 400, $factor = 32, $winStatus = 'nowin') {
        $this->delta = $delta;
        $this->factor = $factor;
        $this->winStatus = $winStatus;
    }

    function __destruct() {

    }
    // Getters & Setters
    public function getDelta() {
        return $this->delta;
    }
    public function setDelta($delta) {
        if (!empty($delta)) {
            $this->delta = $delta;
        } else {
            $this->delta = $this->getDelta();
        }
    }
    public function getFactor() {
        return $this->factor;
    }
    public function setFactor($factor) {
        if (!empty($factor)) {
            $this->factor = $factor;
        } else {
            $this->factor = $this->getFactor();
        }
    }
    public function getWinStatus() {
        return $this->winStatus;
    }
    public function setWinStatus($winStatus) {
        if (empty($winStatus)) {
            $this->winStatus = $winStatus;
        } else {
            $this->winStatus = $this->getWinStatus();
        }
    }

    // Maths - Probabilites
    public function calculatriceProbabiltesPlayerA($eloA,$eloB) {

        return 1 / (1 + pow(10, ($eloB-$eloA)/$this->getDelta()));

    }
    // Maths - Mise à jour des scores Elo
    public function calculatriceElo($eloA = 1000, $eloB  = 1000, $player = "Undefined") {

        $successStatus = true;

        // Calcul des propabilités selon le joueur concerné
        switch ($player) {
            case 'A':
                $probability = $this->calculatriceProbabiltesPlayerA($eloA, $eloB);
                break;
            case 'B':
                $probability = 1.0-($this->calculatriceProbabiltesPlayerA($eloA, $eloB));
                break;
            default:
                echo "Je ne sais pas quel joueur est examiné du côté user.";
                $successStatus = false;
                break;
        }

        // Établissement des ratings
        switch ($this->getWinStatus()) {
            case 'p1win':
                $newEloPlayer = ($player === "A") ? $eloA + $this->getFactor()*(1-$probability) : $eloB + $this->getFactor()*(0-$probability);
                break;
            case 'p2win':
                $newEloPlayer = ($player === "A") ? $eloA + $this->getFactor()*(0-$probability) : $eloB + $this->getFactor()*(1-$probability);
                break;
            case 'nowin':
                $newEloPlayer = ($player === "A") ? $eloA + $this->getFactor()*(0.5-$probability) : $eloB + $this->getFactor()*(0.5-$probability);
                break;
            default:
                echo "Je ne comprends pas l'issue du match.";
                $successStatus = false;
                break;
        }

        // Résultat du nouveau ELO
        return ($successStatus) ? round($newEloPlayer,0) : null;

    }
}
