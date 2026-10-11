<?php
namespace App;

class Game
{
    public function play(int $bet): string
    {
        if ($bet <= 0) {
            throw new \InvalidArgumentException('Ставка має бути більшою за нуль.');
        }

        return "Гра почалася зі ставкою $bet";
    }
}
