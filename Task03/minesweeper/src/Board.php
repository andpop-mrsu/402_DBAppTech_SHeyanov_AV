<?php

namespace Openeeer\Minesweeper;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Board
{
    private int $rows;
    private int $cols;
    private int $mines;
    private array $cells = [];
    private ConsoleOutput $output;

    public function __construct(int $rows, int $cols, int $mines, ConsoleOutput $output)
    {
        $this->rows = $rows;
        $this->cols = $cols;
        $this->mines = $mines;
        $this->output = $output;

        $this->output->getFormatter()->setStyle('mine', new OutputFormatterStyle('white', 'red', ['bold']));
        $this->output->getFormatter()->setStyle('exploded', new OutputFormatterStyle('black', 'yellow', ['bold'])); // взорванная мина
        $this->output->getFormatter()->setStyle('flag', new OutputFormatterStyle('yellow', null, ['bold']));
        $this->output->getFormatter()->setStyle('number1', new OutputFormatterStyle('green', null));
        $this->output->getFormatter()->setStyle('number2', new OutputFormatterStyle('blue', null));
        $this->output->getFormatter()->setStyle('number3', new OutputFormatterStyle('red', null));
        $this->output->getFormatter()->setStyle('number4', new OutputFormatterStyle('magenta', null));
        $this->output->getFormatter()->setStyle('number5', new OutputFormatterStyle('cyan', null));

        for ($i = 0; $i < $rows; $i++) {
            $this->cells[$i] = [];
            for ($j = 0; $j < $cols; $j++) {
                $this->cells[$i][$j] = new Cell();
            }
        }

        $this->placeMines();
        $this->calculateAdjacentMines();
    }

    private function placeMines(): void
    {
        $placed = 0;
        while ($placed < $this->mines) {
            $r = rand(0, $this->rows - 1);
            $c = rand(0, $this->cols - 1);
            if (!$this->cells[$r][$c]->isMine) {
                $this->cells[$r][$c]->isMine = true;
                $placed++;
            }
        }
    }

    private function calculateAdjacentMines(): void
    {
        for ($i = 0; $i < $this->rows; $i++) {
            for ($j = 0; $j < $this->cols; $j++) {
                if ($this->cells[$i][$j]->isMine) continue;
                $count = 0;
                for ($x = $i - 1; $x <= $i + 1; $x++) {
                    for ($y = $j - 1; $y <= $j + 1; $y++) {
                        if ($x >= 0 && $x < $this->rows && $y >= 0 && $y < $this->cols) {
                            if ($this->cells[$x][$y]->isMine) $count++;
                        }
                    }
                }
                $this->cells[$i][$j]->adjacentMines = $count;
            }
        }
    }

    public function openCell(int $row, int $col): bool
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) return true;
        $cell = $this->cells[$row][$col];
        if ($cell->isOpen || $cell->isFlagged) return true;

        $cell->isOpen = true;

        if ($cell->isMine) return false;

        if ($cell->adjacentMines === 0) {
            for ($x = $row - 1; $x <= $row + 1; $x++) {
                for ($y = $col - 1; $y <= $col + 1; $y++) {
                    if ($x === $row && $y === $col) continue;
                    $this->openCell($x, $y);
                }
            }
        }

        return true;
    }

    public function toggleFlag(int $row, int $col): void
    {
        if ($row < 0 || $row >= $this->rows || $col < 0 || $col >= $this->cols) return;
        $cell = $this->cells[$row][$col];
        if (!$cell->isOpen) $cell->isFlagged = !$cell->isFlagged;
    }

    public function checkWin(): bool
    {
        for ($i = 0; $i < $this->rows; $i++) {
            for ($j = 0; $j < $this->cols; $j++) {
                $cell = $this->cells[$i][$j];
                if (!$cell->isMine && !$cell->isOpen) return false;
            }
        }
        return true;
    }


    public function render(bool $revealMines = false, ?array $exploded = null): void
    {
        $leftWidth = strlen((string)($this->rows - 1)) + 1; 

        $this->output->writeln(str_repeat(" ", $leftWidth) . implode(" ", range(0, $this->cols - 1)));

        $this->output->writeln(str_repeat(" ", $leftWidth - 1) . "┌" . str_repeat("─", $this->cols * 2) . "┐");

        for ($r = 0; $r < $this->rows; $r++) {
            $line = str_pad($r, $leftWidth - 1, " ", STR_PAD_LEFT) . "│";
            for ($c = 0; $c < $this->cols; $c++) {
                $cell = $this->cells[$r][$c];

                if ($exploded && $r === $exploded['row'] && $c === $exploded['col']) {
                    $line .= "<exploded>*</exploded> ";
                } elseif ($cell->isOpen) {
                    if ($cell->adjacentMines === 0) {
                        $line .= "  ";
                    } else {
                        $style = "number" . min($cell->adjacentMines, 5);
                        $line .= "<$style>{$cell->adjacentMines}</$style> ";
                    }
                } elseif ($cell->isFlagged) {
                    $line .= "<flag>F</flag> ";
                } elseif ($revealMines && $cell->isMine) {
                    $line .= "<mine>*</mine> ";
                } else {
                    $line .= "# ";
                }
            }
            $line .= "│";
            $this->output->writeln($line);
        }

        $this->output->writeln(str_repeat(" ", $leftWidth - 1) . "└" . str_repeat("─", $this->cols * 2) . "┘");
    }
}
