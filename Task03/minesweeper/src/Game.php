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

        $output->writeln("Добро пожаловать в Сапёр!");
        $output->writeln("Во время игры введите 'h', чтобы посмотреть правила.\n");

        // Ввод размера поля (квадратное, ≥2)
        do {
            $size = (int)readline("Введите размер игрового поля (не меньше 2): ");
            if ($size < 2) {
                $output->writeln("\e[33mРазмер должен быть не меньше 2!\e[0m");
            }
        } while ($size < 2);

        // Ввод количества мин (≥1 и < количества клеток)
        do {
            $mines = (int)readline("Введите количество мин (от 1 до " . ($size * $size - 1) . "): ");
            if ($mines < 1 || $mines >= $size * $size) {
                $output->writeln("\e[33mКоличество мин должно быть от 1 до " . ($size * $size - 1) . "!\e[0m");
            }
        } while ($mines < 1 || $mines >= $size * $size);

        // Создаём игровое поле
        $board = new Board($size, $size, $mines, $output);

        while (true) {
            $board->render();

            $input = readline("Введите действие (o row col - открыть, f row col - флаг, h - помощь): ");
            $input = trim($input);

            // Вызов help во время игры
            if ($input === 'h') {
                $help = new Help($output);
                $help->show();
                continue; // вернуться к циклу игры
            }

            $parts = preg_split('/\s+/', $input);

            if (count($parts) !== 3) {
                $output->writeln("Неверный формат. Используйте: o row col или f row col");
                continue;
            }

            [$action, $r, $c] = $parts;
            $r = (int)$r;
            $c = (int)$c;

            // Проверка выхода за пределы поля
            if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                $output->writeln("\e[33mКоординаты вне игрового поля! Введите значения от 0 до " . ($size - 1) . ".\e[0m");
                continue;
            }

            if ($action === "o") {
                $safe = $board->openCell($r, $c);
                if (!$safe) {
                    // Взорванная мина
                    $board->render(true, ['row' => $r, 'col' => $c]);
                    $output->writeln("\e[41m\e[97mВы наступили на мину! Игра окончена.\e[0m");
                    break;
                }

                if ($board->checkWin()) {
                    $board->render(true);
                    $output->writeln("\e[32mПоздравляем! Вы открыли все безопасные клетки!\e[0m");
                    break;
                }
            } elseif ($action === "f") {
                $board->toggleFlag($r, $c);
            } else {
                $output->writeln("Неверное действие. Используйте o или f.");
            }
        }
    }
}
