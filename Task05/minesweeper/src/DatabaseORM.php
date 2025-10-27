<?php

namespace Openeeer\Minesweeper;

use RedBeanPHP\R;
use RedBeanPHP\RedException\SQL;

class DatabaseORM
{
    private string $dbPath;

    public function __construct(?string $dbPath = null)
    {
        // База будет храниться в %APPDATA%\Minesweeper\minesweeper.db
        if ($dbPath === null) {
            $appData = getenv('APPDATA') ?: __DIR__;
            $dbDir = $appData . DIRECTORY_SEPARATOR . 'Minesweeper';
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            $dbPath = $dbDir . DIRECTORY_SEPARATOR . 'minesweeper.db';
        }

        $this->dbPath = $dbPath;
        $this->connect();
        $this->setupDatabase();
    }

    private function connect(): void
    {
        try {
            R::setup('sqlite:' . $this->dbPath);
            R::useFeatureSet('novice/latest');
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    private function setupDatabase(): void
    {
        try {
            // RedBeanPHP автоматически создаст таблицы при первом использовании
            // Проверяем подключение, выполнив простой запрос
            R::getDatabaseAdapter()->getDatabase();
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка настройки базы данных: " . $e->getMessage());
        }
    }

    public function saveGame(
        string $playerName,
        int $boardSize,
        int $minesCount,
        array $minesPositions,
        string $gameResult
    ): int {
        try {
            $game = R::dispense('game');
            $game->datePlayed = date('Y-m-d H:i:s');
            $game->playerName = $playerName;
            $game->boardSize = $boardSize;
            $game->minesCount = $minesCount;
            $game->minesPositions = json_encode($minesPositions);
            $game->gameResult = $gameResult;
            $game->createdAt = date('Y-m-d H:i:s');

            $id = R::store($game);
            return (int)$id;
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка сохранения игры: " . $e->getMessage());
        }
    }

    public function saveMove(int $gameId, int $moveNumber, int $row, int $col, string $result): void
    {
        try {
            $move = R::dispense('move');
            $move->gameId = $gameId;
            $move->moveNumber = $moveNumber;
            $move->rowCoord = $row;
            $move->colCoord = $col;
            $move->result = $result;

            R::store($move);
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка сохранения хода: " . $e->getMessage());
        }
    }

    public function getAllGames(): array
    {
        try {
            $games = R::findAll('game', 'ORDER BY date_played DESC');

            $result = [];
            foreach ($games as $game) {
                $result[] = [
                    'id' => $game->id,
                    'date_played' => $game->datePlayed,
                    'player_name' => $game->playerName,
                    'board_size' => $game->boardSize,
                    'mines_count' => $game->minesCount,
                    'game_result' => $game->gameResult
                ];
            }

            return $result;
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка получения списка игр: " . $e->getMessage());
        }
    }

    public function getGameById(int $gameId): ?array
    {
        try {
            $game = R::load('game', $gameId);

            if (!$game->id) {
                return null;
            }

            return [
                'id' => $game->id,
                'date_played' => $game->datePlayed,
                'player_name' => $game->playerName,
                'board_size' => $game->boardSize,
                'mines_count' => $game->minesCount,
                'mines_positions' => json_decode($game->minesPositions, true),
                'game_result' => $game->gameResult
            ];
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка получения игры: " . $e->getMessage());
        }
    }

    public function getMovesByGameId(int $gameId): array
    {
        try {
            $moves = R::find('move', 'game_id = ? ORDER BY move_number ASC', [$gameId]);

            $result = [];
            foreach ($moves as $move) {
                $result[] = [
                    'move_number' => $move->moveNumber,
                    'row_coord' => $move->rowCoord,
                    'col_coord' => $move->colCoord,
                    'result' => $move->result
                ];
            }

            return $result;
        } catch (SQL $e) {
            throw new \RuntimeException("Ошибка получения ходов: " . $e->getMessage());
        }
    }

    public function getGameWithMoves(int $gameId): ?array
    {
        $game = $this->getGameById($gameId);
        if (!$game) {
            return null;
        }

        $game['moves'] = $this->getMovesByGameId($gameId);
        return $game;
    }

    public function close(): void
    {
        R::close();
    }
}
