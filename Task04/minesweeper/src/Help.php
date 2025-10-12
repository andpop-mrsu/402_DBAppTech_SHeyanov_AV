<?php

namespace Openeeer\Minesweeper;

use Symfony\Component\Console\Output\ConsoleOutput;

class Help
{
    private ConsoleOutput $output;

    public function __construct(ConsoleOutput $output)
    {
        $this->output = $output;
    }

    public function show(): void
    {
        $this->output->writeln("\e[36mДобро пожаловать в Сапёр!\e[0m\n");
        $this->output->writeln("Правила игры:");
        $this->output->writeln("1. Игровое поле квадратное. Игрок выбирает размер и количество мин.");
        $this->output->writeln("2. Цель: открыть все клетки без мин.");
        $this->output->writeln("3. Команды для хода:");
        $this->output->writeln("   o row col  - открыть ячейку в строке row и столбце col");
        $this->output->writeln("   f row col  - поставить или снять флаг на ячейке");
        $this->output->writeln("4. Если открыть мину, игра заканчивается поражением.");
        $this->output->writeln("5. Если открыть все безопасные клетки — победа.");
        $this->output->writeln("\nРежимы игры:");
        $this->output->writeln("1. Новая игра - начать новую партию");
        $this->output->writeln("2. Список партий - просмотр всех сохраненных игр");
        $this->output->writeln("3. Повтор партии - воспроизведение сохраненной игры");
        $this->output->writeln("\nПример команд:");
        $this->output->writeln("   o 1 2    # открыть ячейку в строке 1, столбце 2");
        $this->output->writeln("   f 0 0    # поставить или снять флаг на ячейке 0,0");
        $this->output->writeln("\nВсе партии автоматически сохраняются в базу данных SQLite.");
        $this->output->writeln("Удачи и приятной игры!\n");
    }
}
