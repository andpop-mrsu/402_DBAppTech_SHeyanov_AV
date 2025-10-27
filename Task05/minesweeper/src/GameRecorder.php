<?php

namespace Openeeer\Minesweeper;

class GameRecorder
{
    private DatabaseORM $database;
    private ?int $currentGameId = null;
    private int $moveNumber = 0;
    private array $minesPositions = [];
    private array $pendingMoves = [];
    private string $playerName = '';
    private int $boardSize = 0;
    private int $minesCount = 0;

    public function __construct(DatabaseORM $database)
    {
        $this->database = $database;
    }

    public function startGame(string $playerName, int $boardSize, int $minesCount, array $minesPositions): void
    {
        $this->minesPositions = $minesPositions;
        $this->moveNumber = 0;
        $this->currentGameId = null;
        $this->pendingMoves = [];
        $this->playerName = $playerName;
        $this->boardSize = $boardSize;
        $this->minesCount = $minesCount;
    }

    public function recordMove(int $row, int $col, string $result): void
    {
        $this->moveNumber++;
        $this->pendingMoves[] = [
            'move_number' => $this->moveNumber,
            'row' => $row,
            'col' => $col,
            'result' => $result
        ];
    }

    public function finishGame(string $playerName, int $boardSize, int $minesCount, string $gameResult): void
    {
        if ($this->currentGameId !== null) {
            return; // Игра уже завершена
        }

        // Сохраняем игру
        $this->currentGameId = $this->database->saveGame(
            $playerName,
            $boardSize,
            $minesCount,
            $this->minesPositions,
            $gameResult
        );

        // Сохраняем все накопленные ходы
        foreach ($this->pendingMoves as $move) {
            $this->database->saveMove(
                $this->currentGameId,
                $move['move_number'],
                $move['row'],
                $move['col'],
                $move['result']
            );
        }
    }

    public function getCurrentGameId(): ?int
    {
        return $this->currentGameId;
    }

    public function reset(): void
    {
        $this->currentGameId = null;
        $this->moveNumber = 0;
        $this->minesPositions = [];
        $this->pendingMoves = [];
        $this->playerName = '';
        $this->boardSize = 0;
        $this->minesCount = 0;
    }
}
