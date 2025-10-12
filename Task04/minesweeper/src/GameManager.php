<?php

namespace Openeeer\Minesweeper;

use Symfony\Component\Console\Output\ConsoleOutput;

class GameManager
{
    private Database $database;
    private GameRecorder $recorder;
    private GameReplay $replay;
    private ConsoleOutput $output;

    public function __construct()
    {
        $this->database = new Database();
        $this->recorder = new GameRecorder($this->database);
        $this->output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, true);
        $this->replay = new GameReplay($this->database, $this->output);
    }

    public function run(): void
    {
        $this->output->writeln("Добро пожаловать в Сапёр!");
        $this->output->writeln("Во время игры введите 'h', чтобы посмотреть правила.\n");

        while (true) {
            $this->showMainMenu();
            $choice = readline("Выберите режим (1-3): ");

            switch ($choice) {
                case '1':
                    $this->startNewGame();
                    break;
                case '2':
                    $this->showGamesList();
                    break;
                case '3':
                    $this->replayGame();
                    break;
                default:
                    $this->output->writeln("\e[33mНеверный выбор. Попробуйте снова.\e[0m");
            }
        }
    }

    private function showMainMenu(): void
    {
        $this->output->writeln("\e[36m=== ГЛАВНОЕ МЕНЮ ===\e[0m");
        $this->output->writeln("1. Новая игра");
        $this->output->writeln("2. Список сохраненных партий");
        $this->output->writeln("3. Повтор партии");
        $this->output->writeln("");
    }

    private function startNewGame(): void
    {
        $this->output->writeln("\e[36m=== НОВАЯ ИГРА ===\e[0m");

        // Ввод имени игрока
        $playerName = readline("Введите ваше имя: ");
        if (empty(trim($playerName))) {
            $playerName = "Игрок";
        }

        // Ввод размера поля
        do {
            $size = (int)readline("Введите размер игрового поля (не меньше 2): ");
            if ($size < 2) {
                $this->output->writeln("\e[33mРазмер должен быть не меньше 2!\e[0m");
            }
        } while ($size < 2);

        // Ввод количества мин
        do {
            $mines = (int)readline("Введите количество мин (от 1 до " . ($size * $size - 1) . "): ");
            if ($mines < 1 || $mines >= $size * $size) {
                $this->output->writeln("\e[33mКоличество мин должно быть от 1 до " . ($size * $size - 1) . "!\e[0m");
            }
        } while ($mines < 1 || $mines >= $size * $size);

        // Создаем игровое поле
        $board = new Board($size, $size, $mines, $this->output);

        // Начинаем запись игры
        $this->recorder->startGame($playerName, $size, $mines, $board->getMinesPositions());

        $this->playGame($board, $playerName, $size, $mines);
    }

    private function playGame(Board $board, string $playerName, int $size, int $mines): void
    {
        while (true) {
            $board->render();

            $input = readline("Введите действие (o row col - открыть, f row col - флаг, h - помощь): ");
            $input = trim($input);

            // Вызов help во время игры
            if ($input === 'h') {
                $help = new Help($this->output);
                $help->show();
                continue;
            }

            $parts = preg_split('/\s+/', $input);

            if (count($parts) !== 3) {
                $this->output->writeln("Неверный формат. Используйте: o row col или f row col");
                continue;
            }

            [$action, $r, $c] = $parts;
            $r = (int)$r;
            $c = (int)$c;

            // Проверка выхода за пределы поля
            if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                $this->output->writeln("\e[33mКоординаты вне игрового поля! Введите значения от 0 до " . ($size - 1) . ".\e[0m");
                continue;
            }

            if ($action === "o") {
                $safe = $board->openCell($r, $c);
                
                // Записываем ход
                if ($safe) {
                    if ($board->checkWin()) {
                        $this->recorder->recordMove($r, $c, "выиграл");
                        $board->render(true);
                        $this->output->writeln("\e[32mПоздравляем! Вы открыли все безопасные клетки!\e[0m");
                        $this->recorder->finishGame($playerName, $size, $mines, "Победа");
                        break;
                    } else {
                        $this->recorder->recordMove($r, $c, "мины нет");
                    }
                } else {
                    // Взорванная мина
                    $this->recorder->recordMove($r, $c, "взорвался");
                    $board->render(true, ['row' => $r, 'col' => $c]);
                    $this->output->writeln("\e[41m\e[97mВы наступили на мину! Игра окончена.\e[0m");
                    $this->recorder->finishGame($playerName, $size, $mines, "Поражение");
                    break;
                }
            } elseif ($action === "f") {
                $board->toggleFlag($r, $c);
            } else {
                $this->output->writeln("Неверное действие. Используйте o или f.");
            }
        }

        $this->recorder->reset();
    }

    private function showGamesList(): void
{
    $this->output->writeln("\e[36m=== СПИСОК СОХРАНЕННЫХ ПАРТИЙ ===\e[0m");

    $games = $this->database->getAllGames();

    if (empty($games)) {
        $this->output->writeln("Сохраненных партий не найдено.\n");
        return;
    }

    // Заголовки
    $headers = ['ID', 'Игрок', 'Дата', 'Размер', 'Мины', 'Результат'];

    // Подготавливаем данные таблицы
    $rows = [];
    foreach ($games as $game) {
        $rows[] = [
            (string)$game['id'],
            $game['player_name'],
            date('d.m.Y H:i', strtotime($game['date_played'])),
            $game['board_size'] . 'x' . $game['board_size'],
            (string)$game['mines_count'],
            $game['game_result'],
        ];
    }

    // Вычисляем максимальную ширину каждой колонки
    $widths = [];
    foreach ($headers as $i => $header) {
        $widths[$i] = mb_strlen($header);
        foreach ($rows as $row) {
            $widths[$i] = max($widths[$i], mb_strlen($row[$i]));
        }
        $widths[$i] += 2; // немного отступа для визуала
    }

    // Функция для вывода строки с учётом UTF-8
    $formatRow = function (array $columns) use ($widths): string {
        $out = '';
        foreach ($columns as $i => $col) {
            $pad = $widths[$i] - mb_strlen($col);
            $out .= $col . str_repeat(' ', $pad);
        }
        return $out;
    };

    // Печать таблицы
    $this->output->writeln($formatRow($headers));
    $this->output->writeln(str_repeat('-', array_sum($widths)));

    foreach ($rows as $row) {
        $this->output->writeln($formatRow($row));
    }

    $this->output->writeln("");
}

    
    private function replayGame(): void
    {
        $this->output->writeln("\e[36m=== ПОВТОР ПАРТИИ ===\e[0m");

        $gameId = (int)readline("Введите ID партии для воспроизведения: ");

        if ($gameId <= 0) {
            $this->output->writeln("\e[33mНеверный ID партии!\e[0m");
            return;
        }

        $this->replay->replayGame($gameId);
    }
}
