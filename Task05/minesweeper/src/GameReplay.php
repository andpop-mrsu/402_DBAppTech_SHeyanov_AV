<?php

namespace Openeeer\Minesweeper;

use Symfony\Component\Console\Output\ConsoleOutput;

class GameReplay
{
    private DatabaseORM $database;
    private ConsoleOutput $output;

    public function __construct(DatabaseORM $database, ConsoleOutput $output)
    {
        $this->database = $database;
        $this->output = $output;
    }

    public function replayGame(int $gameId): void
    {
        $gameData = $this->database->getGameWithMoves($gameId);

        if (!$gameData) {
            $this->output->writeln("\e[31mИгра с ID {$gameId} не найдена!\e[0m");
            return;
        }

        $this->output->writeln("\e[36m=== Воспроизведение игры #{$gameId} ===\e[0m");
        $this->output->writeln("Игрок: {$gameData['player_name']}");
        $this->output->writeln("Дата: {$gameData['date_played']}");
        $this->output->writeln("Размер поля: {$gameData['board_size']}x{$gameData['board_size']}");
        $this->output->writeln("Количество мин: {$gameData['mines_count']}");
        $this->output->writeln("Результат: {$gameData['game_result']}");
        $this->output->writeln("");

        if (empty($gameData['moves'])) {
            $this->output->writeln("\e[33mВ этой игре не было записано ходов.\e[0m");
            return;
        }

        // Создаем доску с теми же минами
        $board = new Board(
            $gameData['board_size'],
            $gameData['board_size'],
            $gameData['mines_count'],
            $this->output
        );

        // Устанавливаем мины в те же позиции
        $board->setMinesPositions($gameData['mines_positions']);

        $this->output->writeln("Нажмите Enter для начала воспроизведения...");
        readline();

        // Показываем начальное состояние поля
        $this->output->writeln("\e[33mНачальное состояние поля:\e[0m");
        $board->render();

        // Воспроизводим ходы
        foreach ($gameData['moves'] as $move) {
            $moveInfo = "Ход {$move['move_number']}: "
                . "({$move['row_coord']}, {$move['col_coord']}) - {$move['result']}";
            $this->output->writeln("\n\e[33m{$moveInfo}\e[0m");

            if ($move['result'] === 'взорвался') {
                $board->openCell($move['row_coord'], $move['col_coord']);
                $board->render(true, ['row' => $move['row_coord'], 'col' => $move['col_coord']]);
                $this->output->writeln("\e[41m\e[97mВзорвался на мине!\e[0m");
                break;
            } elseif ($move['result'] === 'выиграл') {
                $board->openCell($move['row_coord'], $move['col_coord']);
                $board->render(true);
                $this->output->writeln("\e[32mПобеда!\e[0m");
                break;
            } else {
                $board->openCell($move['row_coord'], $move['col_coord']);
                $board->render();
            }

            $this->output->writeln("Нажмите Enter для следующего хода...");
            readline();
        }

        $this->output->writeln("\n\e[36m=== Воспроизведение завершено ===\e[0m");
    }
}
