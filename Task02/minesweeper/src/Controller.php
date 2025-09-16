<?php
namespace openeeer\Minesweeper;

use openeeer\Minesweeper\View;

class Controller
{
    public static function startGame()
    {
        View::startScreen();
    }
}
