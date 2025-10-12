<?php

namespace Openeeer\Minesweeper;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;
    private string $dbPath;

    public function __construct(string $dbPath = null)
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
        $this->createTables();
    }

    private function connect(): void
    {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new \RuntimeException("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    private function createTables(): void
    {
        $gamesTable = "
            CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date_played TEXT NOT NULL,
                player_name TEXT NOT NULL,
                board_size INTEGER NOT NULL,
                mines_count INTEGER NOT NULL,
                mines_positions TEXT NOT NULL,
                game_result TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $movesTable = "
            CREATE TABLE IF NOT EXISTS moves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id INTEGER NOT NULL,
                move_number INTEGER NOT NULL,
                row_coord INTEGER NOT NULL,
                col_coord INTEGER NOT NULL,
                result TEXT NOT NULL,
                FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE
            )
        ";

        try {
            $this->pdo->exec($gamesTable);
            $this->pdo->exec($movesTable);
        } catch (PDOException $e) {
            throw new \RuntimeException("Ошибка создания таблиц: " . $e->getMessage());
        }
    }

    public function saveGame(string $playerName, int $boardSize, int $minesCount, array $minesPositions, string $gameResult): int
    {
        $datePlayed = date('Y-m-d H:i:s');
        $minesPositionsJson = json_encode($minesPositions);

        $stmt = $this->pdo->prepare("
            INSERT INTO games (date_played, player_name, board_size, mines_count, mines_positions, game_result)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([$datePlayed, $playerName, $boardSize, $minesCount, $minesPositionsJson, $gameResult]);

        return (int)$this->pdo->lastInsertId();
    }

    public function saveMove(int $gameId, int $moveNumber, int $row, int $col, string $result): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO moves (game_id, move_number, row_coord, col_coord, result)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$gameId, $moveNumber, $row, $col, $result]);
    }

    public function getAllGames(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, date_played, player_name, board_size, mines_count, game_result
            FROM games
            ORDER BY date_played DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGameById(int $gameId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, date_played, player_name, board_size, mines_count, mines_positions, game_result
            FROM games
            WHERE id = ?
        ");

        $stmt->execute([$gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return null;
        }

        $game['mines_positions'] = json_decode($game['mines_positions'], true);
        return $game;
    }

    public function getMovesByGameId(int $gameId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT move_number, row_coord, col_coord, result
            FROM moves
            WHERE game_id = ?
            ORDER BY move_number ASC
        ");

        $stmt->execute([$gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}
