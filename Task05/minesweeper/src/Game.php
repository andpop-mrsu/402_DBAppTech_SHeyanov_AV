<?php

namespace Openeeer\Minesweeper;

use Symfony\Component\Console\Output\ConsoleOutput;

class Game
{
    public function run(): void
    {
        $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, true);

        // Проверка аргументов командной строки для --help при запуске
        global $argv;
        if (isset($argv[1]) && $argv[1] === '--help') {
            $help = new Help($output);
            $help->show();
            exit(0);
        }

        $gameManager = new GameManager();
        $gameManager->run();
    }
}
