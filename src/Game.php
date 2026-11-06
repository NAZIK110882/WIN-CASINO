<?php
namespace App;

class Game
{
    public function play(int|float $bet): string
    {
        // Preserve the prototype's integer bet behavior without relying on
        // PHP's deprecated implicit float-to-int parameter coercion.
        $bet = (int) $bet;

        if ($bet <= 0) {
            throw new \InvalidArgumentException('Ставка має бути більшою за нуль.');
        }

        return "Гра почалася зі ставкою $bet";
    }
}
