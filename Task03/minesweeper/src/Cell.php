<?php

namespace Openeeer\Minesweeper;

class Cell
{
    public bool $isMine = false;
    public bool $isOpen = false;
    public bool $isFlagged = false;
    public int $adjacentMines = 0;
}
